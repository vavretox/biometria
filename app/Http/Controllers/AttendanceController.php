<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\AutomaticAttendanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(private readonly AutomaticAttendanceService $automaticAttendanceService) {}

    public function index(Request $request): View
    {
        $from = $request->date('from')?->startOfDay() ?? today()->startOfDay();
        $to = $request->date('to')?->endOfDay() ?? $from->copy()->endOfDay();

        if ($to->isBefore($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        if (today()->betweenIncluded($from, $to)) {
            $this->automaticAttendanceService->generate(today());
        }

        $attendances = Attendance::with([
            'employee:id,first_name,last_name,maternal_last_name,work_schedule_id',
            'employee.workSchedule.days',
            'employee.scheduleAssignments' => fn ($query) => $query
                ->whereDate('starts_on', '<=', $to->toDateString())
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from->toDateString()))
                ->with('workSchedule.days'),
        ])
            ->whereBetween('checked_at', [$from, $to])
            ->when($request->integer('employee_id'), fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->oldest('checked_at')
            ->get();
        $dailySummaries = $this->paginateDailySummaries($this->dailySummaries($attendances), $request);
        $editableAttendance = $request->user()?->is_admin && $request->integer('edit_attendance')
            ? Attendance::findOrFail($request->integer('edit_attendance'))
            : null;

        return view('attendance.index', [
            'from' => $from,
            'to' => $to,
            'employees' => Employee::where('is_active', true)->orderBy('last_name')->get(),
            'dailySummaries' => $dailySummaries,
            'editableAttendance' => $editableAttendance,
            'leaveTypes' => LeaveType::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'checked_at' => ['required', 'date'],
            'type' => ['required', 'in:in,out,break_in,break_out'],
            'status' => ['required', 'in:normal,late,early'],
            'late_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'observation' => ['required', 'string', 'max:1000'],
        ]);

        Attendance::create([...$data, 'late_minutes' => $request->integer('late_minutes'), 'source' => 'manual']);

        return back()->with('success', 'Marcación manual registrada.');
    }

    public function update(Request $request, Attendance $attendance): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $attendance->update($this->validatedAttendance($request));

        return back()->with('success', 'Marcación actualizada correctamente.');
    }

    public function destroy(Request $request, Attendance $attendance): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $attendance->delete();

        return back()->with('success', 'Marcación eliminada correctamente.');
    }

    /** @param Collection<int, Attendance> $attendances */
    private function dailySummaries(Collection $attendances): Collection
    {
        return $attendances
            ->groupBy(fn (Attendance $attendance): string => $attendance->employee_id.'|'.$attendance->checked_at->toDateString())
            ->map(function (Collection $marks): array {
                $marks = $marks->sortBy('checked_at')->values();
                $firstMark = $marks->first();
                $employee = $firstMark->employee;
                $date = $firstMark->checked_at->copy()->startOfDay();
                $schedule = $employee->scheduleForDate($date) ?? $employee->workSchedule;
                $scheduleDay = $schedule?->days->firstWhere('day_of_week', $date->isoWeekday());
                $expectedTimes = $scheduleDay && $scheduleDay->is_working_day
                    ? collect([$scheduleDay->starts_at, $scheduleDay->ends_at, $scheduleDay->second_starts_at, $scheduleDay->second_ends_at])->filter()->values()
                    : collect();
                $labels = $expectedTimes->count() === 4
                    ? ['Entrada', 'Salida mediodía', 'Retorno', 'Salida']
                    : ['Entrada', 'Salida'];
                $slotCount = max($expectedTimes->count(), $marks->count());
                $slots = collect(range(0, max(0, $slotCount - 1)))->map(function (int $index) use ($date, $expectedTimes, $labels, $marks): array {
                    $mark = $marks->get($index);
                    $expectedTime = $expectedTimes->get($index);
                    $difference = null;

                    if ($mark && $expectedTime) {
                        $scheduledAt = Carbon::parse($date->toDateString().' '.$expectedTime, $date->timezone);
                        $difference = (int) $scheduledAt->diffInMinutes($mark->checked_at, false);
                    }

                    return [
                        'label' => $labels[$index] ?? 'Marcación adicional',
                        'expected_time' => $expectedTime ? mb_substr($expectedTime, 0, 5) : null,
                        'mark' => $mark,
                        'difference' => $difference,
                    ];
                });

                return compact('employee', 'date', 'schedule', 'slots');
            })
            ->sortByDesc(fn (array $summary): string => $summary['date']->format('Y-m-d').'|'.str_pad((string) $summary['employee']->id, 10, '0', STR_PAD_LEFT))
            ->values();
    }

    private function paginateDailySummaries(Collection $summaries, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 20;

        return new LengthAwarePaginator(
            $summaries->forPage($page, $perPage)->values(),
            $summaries->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    /** @return array{checked_at: string, type: string, status: string, late_minutes: int, observation: string} */
    private function validatedAttendance(Request $request): array
    {
        return $request->validate([
            'checked_at' => ['required', 'date'],
            'type' => ['required', 'in:in,out,break_in,break_out'],
            'status' => ['required', 'in:normal,late,early'],
            'late_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'observation' => ['required', 'string', 'max:1000'],
        ]);
    }
}
