<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleAssignment;
use App\Services\WorkScheduleAssignmentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'schedule_id' => ['nullable', 'integer', 'exists:work_schedules,id'],
        ]);
        $selectedEmployeeId = $filters['employee_id'] ?? null;
        $today = today()->toDateString();

        return view('schedules.index', [
            'schedules' => WorkSchedule::with('days')
                ->withCount(['assignments as employees_count' => fn ($query) => $query
                    ->whereDate('starts_on', '<=', $today)
                    ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))])
                ->orderBy('name')->get(),
            'selectedScheduleId' => $filters['schedule_id'] ?? null,
            'employees' => Employee::where('is_active', true)->orderBy('last_name')->get(),
            'selectedEmployeeId' => $selectedEmployeeId,
            'assignmentEmployees' => Employee::whereHas('scheduleAssignments')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'maternal_last_name', 'item_number']),
            'assignments' => $selectedEmployeeId ? WorkScheduleAssignment::with([
                'employee:id,first_name,last_name,maternal_last_name,item_number,office_id',
                'employee.office:id,name',
                'workSchedule:id,name',
            ])->where('employee_id', $selectedEmployeeId)->latest('starts_on')->latest('id')->paginate(20)->withQueryString() : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:work_schedules,name'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'second_starts_at' => ['nullable', 'date_format:H:i', 'after:ends_at', 'required_with:second_ends_at'],
            'second_ends_at' => ['nullable', 'date_format:H:i', 'after:second_starts_at', 'required_with:second_starts_at'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
        ]);

        DB::transaction(function () use ($data): void {
            $schedule = WorkSchedule::create(['name' => $data['name'], 'is_active' => true]);
            foreach (range(1, 5) as $day) {
                $schedule->days()->create([
                    'day_of_week' => $day, 'starts_at' => $data['starts_at'],
                    'ends_at' => $data['ends_at'],
                    'second_starts_at' => $data['second_starts_at'] ?? null,
                    'second_ends_at' => $data['second_ends_at'] ?? null,
                    'grace_minutes' => $data['grace_minutes'],
                    'is_working_day' => true,
                ]);
            }
        });

        return back()->with('success', 'Horario semanal creado.');
    }

    public function assign(Request $request, WorkScheduleAssignmentService $assignmentService): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $data = $request->validate([
            'assignment_scope' => ['required', Rule::in(['selected', 'all'])],
            'employee_ids' => ['required_unless:assignment_scope,all', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
            'work_schedule_id' => ['required', 'exists:work_schedules,id'],
            'weekdays' => [Rule::excludeIf($request->input('starts_on') === $request->input('ends_on')), 'required_with:weekdays_present', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['integer', 'distinct', 'between:1,7'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ], [
            'ends_on.required' => 'Seleccione la fecha final del horario.',
            'ends_on.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha de inicio.',
            'weekdays.required_with' => 'Seleccione al menos un día para aplicar el horario.',
            'weekdays.min' => 'Seleccione al menos un día para aplicar el horario.',
        ]);

        $startsOn = CarbonImmutable::parse($data['starts_on'])->startOfDay();
        $endsOn = CarbonImmutable::parse($data['ends_on'])->startOfDay();
        $weekdays = $startsOn->isSameDay($endsOn)
            ? [$startsOn->dayOfWeekIso]
            : array_map('intval', $data['weekdays'] ?? range(1, 7));
        $schedule = WorkSchedule::findOrFail($data['work_schedule_id']);
        $employees = Employee::query()->where('is_active', true)
            ->when(
                $data['assignment_scope'] === 'selected',
                fn ($query) => $query->whereIn('id', $data['employee_ids']),
            )->get();

        DB::transaction(function () use ($employees, $assignmentService, $schedule, $startsOn, $endsOn, $request, $weekdays): void {
            foreach ($employees->sortBy('id') as $employee) {
                $assignmentService->assign($employee, $schedule, $startsOn, $endsOn, $request->user()->id, $weekdays);
            }
        });

        return back()->with('success', 'Horario asignado a '.$employees->count().' empleado(s) para el periodo indicado.');
    }

    public function update(Request $request, WorkSchedule $workSchedule): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('work_schedules', 'name')->ignore($workSchedule)],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'second_starts_at' => ['nullable', 'date_format:H:i', 'after:ends_at', 'required_with:second_ends_at'],
            'second_ends_at' => ['nullable', 'date_format:H:i', 'after:second_starts_at', 'required_with:second_starts_at'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
        ]);

        DB::transaction(function () use ($data, $workSchedule): void {
            $workSchedule->update(['name' => $data['name']]);
            foreach (range(1, 5) as $day) {
                $workSchedule->days()->updateOrCreate(
                    ['day_of_week' => $day],
                    [
                        'starts_at' => $data['starts_at'],
                        'ends_at' => $data['ends_at'],
                        'second_starts_at' => $data['second_starts_at'] ?? null,
                        'second_ends_at' => $data['second_ends_at'] ?? null,
                        'grace_minutes' => $data['grace_minutes'],
                        'is_working_day' => true,
                    ],
                );
            }
        });

        return back()->with('success', 'Horario actualizado correctamente.');
    }

    public function destroy(Request $request, WorkSchedule $workSchedule): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        if ($workSchedule->assignments()->exists() || $workSchedule->employees()->exists()) {
            return back()->withErrors([
                'schedule' => 'No se puede eliminar el horario porque tiene empleados asignados. Reasígnelos primero.',
            ]);
        }

        $workSchedule->delete();

        return back()->with('success', 'Horario eliminado correctamente.');
    }
}
