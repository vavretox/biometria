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
    public function index(): View
    {
        $today = today()->toDateString();

        return view('schedules.index', [
            'schedules' => WorkSchedule::with('days')
                ->withCount(['assignments as employees_count' => fn ($query) => $query
                    ->whereDate('starts_on', '<=', $today)
                    ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))])
                ->orderBy('name')->get(),
            'employees' => Employee::where('is_active', true)->orderBy('last_name')->get(),
            'assignments' => WorkScheduleAssignment::with([
                'employee:id,first_name,last_name,maternal_last_name,item_number,office_id',
                'employee.office:id,name',
                'workSchedule:id,name',
            ])->latest('starts_on')->latest('id')->paginate(20),
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
            'period_type' => ['required', Rule::in(['day', 'week', 'month', 'year', 'indefinite', 'custom'])],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on', 'required_if:period_type,custom'],
        ]);

        $startsOn = CarbonImmutable::parse($data['starts_on'])->startOfDay();
        $endsOn = match ($data['period_type']) {
            'day' => $startsOn,
            'week' => $startsOn->addDays(6),
            'month' => $startsOn->addMonth()->subDay(),
            'year' => $startsOn->addYear()->subDay(),
            'custom' => CarbonImmutable::parse($data['ends_on'])->startOfDay(),
            default => null,
        };
        $schedule = WorkSchedule::findOrFail($data['work_schedule_id']);
        $employees = Employee::query()->where('is_active', true)
            ->when(
                $data['assignment_scope'] === 'selected',
                fn ($query) => $query->whereIn('id', $data['employee_ids']),
            )->get();

        foreach ($employees as $employee) {
            $assignmentService->assign($employee, $schedule, $startsOn, $endsOn, $request->user()->id);
        }

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
