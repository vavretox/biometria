<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function index(): View
    {
        return view('leaves.index', [
            'leaveRequests' => LeaveRequest::with([
                'employee:id,first_name,last_name,maternal_last_name',
                'leaveType:id,name',
            ])->latest()->paginate(20),
            'leaveTypes' => LeaveType::orderBy('name')->get(),
            'holidays' => Holiday::orderBy('date')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        LeaveRequest::create([
            ...$this->validatedLeave($request),
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Permiso registrado y aprobado.');
    }

    public function update(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $validated = $this->validatedLeave($request, $leaveRequest);

        if ($leaveRequest->status === 'pending') {
            $validated = [...$validated, 'status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()];
        }

        $leaveRequest->update($validated);

        return redirect()->route('attendance.index', $request->query())->with('success', 'Permiso actualizado correctamente.');
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $leaveRequest->delete();

        return back()->with('success', 'Permiso eliminado correctamente.');
    }

    /** @return array<string, mixed> */
    private function validatedLeave(Request $request, ?LeaveRequest $leaveRequest = null): array
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where(fn ($query) => $query->where('is_active', true)->when($leaveRequest?->leave_type_id, fn ($query, $id) => $query->orWhere('id', $id)))],
            'starts_on' => ['required', 'date'],
            'ends_on' => [
                'required',
                'date',
                'after_or_equal:starts_on',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($request->input('duration_type') === 'half_day' && $value !== $request->input('starts_on')) {
                        $fail('El permiso de medio día debe iniciar y finalizar en la misma fecha.');
                    }
                },
            ],
            'duration_type' => ['required', 'in:all_day,half_day,hours'],
            'half_day_period' => ['nullable', 'required_if:duration_type,half_day', 'in:morning,afternoon'],
            'starts_at' => ['nullable', 'required_if:duration_type,hours', 'date_format:H:i'],
            'ends_at' => [
                'nullable',
                'required_if:duration_type,hours',
                'date_format:H:i',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($request->string('duration_type')->toString() === 'hours'
                        && $request->input('starts_on') === $request->input('ends_on')
                        && $value <= $request->input('starts_at')) {
                        $fail('La hora final debe ser posterior a la hora inicial.');
                    }
                },
            ],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $usesHours = $validated['duration_type'] === 'hours';

        if (! $usesHours) {
            $validated['starts_at'] = null;
            $validated['ends_at'] = null;
        }

        if ($validated['duration_type'] !== 'half_day') {
            $validated['half_day_period'] = null;
        }

        $validated['type'] = LeaveType::findOrFail($validated['leave_type_id'])->code;

        return $validated;
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $leaveRequest->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);

        return back()->with('success', 'Permiso aprobado.');
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        Holiday::create($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'date' => ['required', 'date', 'unique:holidays,date'],
            'scope' => ['required', 'in:national,departmental,institutional'],
            'is_recurring' => ['nullable', 'boolean'],
        ]));

        return back()->with('success', 'Feriado registrado.');
    }
}
