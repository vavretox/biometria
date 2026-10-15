<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Biometric\BiometricDevice;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\WorkUnit;
use App\Services\AttendanceSummaryService;
use App\Services\AutomaticAttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(private readonly AutomaticAttendanceService $automaticAttendanceService) {}

    public function index(Request $request, AttendanceSummaryService $summaryService): View
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'work_unit_id' => ['nullable', 'integer', 'exists:work_units,id'],
            'device_id' => ['nullable', 'integer', 'exists:biometric_devices,id'],
            'mark_filter' => ['nullable', Rule::in(['with_marks', 'without_marks', 'with_leave'])],
            'per_page' => ['nullable', Rule::in([15, 25, 50])],
            'selected_employee' => ['nullable', 'integer', 'exists:employees,id'],
            'selected_date' => ['nullable', 'required_with:selected_employee', 'date_format:Y-m-d'],
            'selected_time' => ['nullable', 'required_with:selected_employee', 'date_format:H:i'],
            'selected_type' => ['nullable', Rule::in(['in', 'out', 'break_in', 'break_out'])],
        ]);

        $searchRequested = $request->query() !== [];

        $from = $request->date('from')?->startOfDay() ?? today()->startOfDay();
        $to = $request->date('to')?->endOfDay() ?? $from->copy()->endOfDay();

        if ($to->isBefore($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        if ($from->diffInDays($to->copy()->startOfDay()) > 30) {
            throw ValidationException::withMessages(['to' => 'Seleccione un período de hasta 31 días.']);
        }

        if ($searchRequested && today()->betweenIncluded($from, $to)) {
            $this->automaticAttendanceService->generate(today());
        }

        $marksInPeriod = fn ($query) => $query->whereBetween('checked_at', [$from, $to])
            ->when($request->integer('device_id'), fn ($query, $deviceId) => $query->where('device_id', $deviceId));
        $leavesInPeriod = fn ($query) => $query->whereIn('status', ['pending', 'approved'])
            ->whereDate('starts_on', '<=', $to->toDateString())->whereDate('ends_on', '>=', $from->toDateString());
        $employees = $searchRequested ? Employee::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhereHas('attendances', $marksInPeriod))
            ->when($request->filled('search'), function ($query) use ($request): void {
                foreach (preg_split('/\s+/u', trim($request->string('search')->toString()), -1, PREG_SPLIT_NO_EMPTY) as $word) {
                    $query->where(function ($query) use ($word): void {
                        foreach (['first_name', 'last_name', 'maternal_last_name', 'employee_code', 'identity_document', 'biometric_id'] as $index => $column) {
                            if ($index === 0) {
                                $query->where($column, 'like', '%'.$word.'%');
                            } else {
                                $query->orWhere($column, 'like', '%'.$word.'%');
                            }
                        }
                    });
                }
            })
            ->when($request->integer('employee_id'), fn ($query, $id) => $query->whereKey($id))
            ->when($request->integer('work_unit_id'), fn ($query, $id) => $query->where('work_unit_id', $id))
            ->when($request->integer('device_id'), fn ($query, $deviceId) => $query->where(fn ($query) => $query
                ->whereHas('attendances', fn ($query) => $query->where('device_id', $deviceId))
                ->orWhereHas('biometricTemplates', fn ($query) => $query->where('device_id', $deviceId))))
            ->when($request->input('mark_filter') === 'with_marks', fn ($query) => $query->whereHas('attendances', $marksInPeriod))
            ->when($request->input('mark_filter') === 'without_marks', fn ($query) => $query->whereDoesntHave('attendances', $marksInPeriod))
            ->when($request->input('mark_filter') === 'with_leave', fn ($query) => $query->whereHas('leaveRequests', $leavesInPeriod))
            ->with([
                'workUnit:id,name', 'workSchedule.days',
                'scheduleAssignments' => fn ($query) => $query->whereDate('starts_on', '<=', $to->toDateString())
                    ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from->toDateString()))
                    ->with('workSchedule.days'),
                'attendances' => $marksInPeriod,
                'leaveRequests' => fn ($query) => $leavesInPeriod($query)->with('leaveType:id,name'),
            ])
            ->orderBy('last_name')->orderBy('first_name')->orderBy('id')
            ->paginate($request->integer('per_page', 25))->withQueryString()
            : new LengthAwarePaginator([], 0, 25);
        $dailySummaries = $employees->through(fn (Employee $employee): array => $summaryService->summarize($employee, $from, $to));
        $editableAttendance = $request->user()?->is_admin && $request->integer('edit_attendance')
            ? Attendance::findOrFail($request->integer('edit_attendance'))
            : null;

        $editableLeave = $request->user()?->is_admin && $request->integer('edit_leave')
            ? LeaveRequest::findOrFail($request->integer('edit_leave'))
            : null;

        return view('attendance.index', [
            'searchRequested' => $searchRequested,
            'from' => $from,
            'to' => $to,
            'employees' => Employee::whereIn('id', $dailySummaries->getCollection()->pluck('employee.id')
                ->push($editableLeave?->employee_id, $editableAttendance?->employee_id, $request->integer('selected_employee'), old('employee_id'))->filter()->unique())
                ->orderBy('last_name')->get(['id', 'first_name', 'last_name']),
            'workUnits' => WorkUnit::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filterDevices' => BiometricDevice::orderBy('device_name')->get(['id', 'device_name']),
            'devices' => BiometricDevice::active()->orderBy('device_name')->get(['id', 'device_name']),
            'dailySummaries' => $dailySummaries,
            'editableAttendance' => $editableAttendance,
            'editableLeave' => $editableLeave,
            'leaveTypes' => LeaveType::where(fn ($query) => $query->where('is_active', true)->when($editableLeave?->leave_type_id, fn ($query, $id) => $query->orWhere('id', $id)))->orderBy('name')->get(),
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
