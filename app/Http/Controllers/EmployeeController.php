<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\WorkUnit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'work_unit_id' => ['nullable', 'integer', 'exists:work_units,id'],
            'is_active' => ['nullable', 'boolean'],
            'attendance_method' => ['nullable', Rule::in(['biometric', 'access_code', 'automatic'])],
        ]);

        return view('employees.index', [
            'workUnits' => $this->workUnits(),
            'employees' => Employee::with('workUnit:id,name')
                ->withCount('biometricTemplates')
                ->when(request('search'), function ($query, $search): void {
                    $query->where(function ($query) use ($search): void {
                        $term = '%'.$search.'%';
                        $query->where('employee_code', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('maternal_last_name', 'like', $term)
                            ->orWhere('biometric_id', 'like', $term)
                            ->orWhere('identity_document', 'like', $term);
                    });
                })
                ->when($request->filled('work_unit_id'), fn ($query) => $query->where('work_unit_id', $request->integer('work_unit_id')))
                ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
                ->when($request->filled('attendance_method'), fn ($query) => $query->where('attendance_method', $request->input('attendance_method')))
                ->orderBy('last_name')->orderBy('first_name')->paginate(15)->withQueryString(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('employees.create', ['workUnits' => $this->workUnits(), 'nextBiometricId' => $this->nextBiometricId()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['office_id'] = WorkUnit::findOrFail($data['work_unit_id'])->office_id;
        $data['access_code'] = $data['attendance_method'] === 'access_code' ? $data['access_code'] : null;
        Cache::lock('employees-biometric-id', 30)->block(10, function () use ($data): void {
            Employee::create([...$data, 'biometric_id' => $this->nextBiometricId()]);
        });

        return redirect()->route('employees.index')->with('success', 'Empleado registrado correctamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee): RedirectResponse
    {
        return redirect()->route('employees.edit', $employee);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employee $employee): View
    {
        return view('employees.edit', ['employee' => $employee, 'workUnits' => $this->workUnits()]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $data = $request->validated();
        $data['office_id'] = WorkUnit::findOrFail($data['work_unit_id'])->office_id;
        if ($data['attendance_method'] !== 'access_code') {
            $data['access_code'] = null;
        } elseif (blank($data['access_code'])) {
            unset($data['access_code']);
        }
        $employee->update($data);

        return redirect()->route('employees.index')->with('success', 'Empleado actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        abort_unless(request()->user()?->is_admin, 403);
        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Empleado desactivado correctamente.');
    }

    private function nextBiometricId(): int
    {
        return (int) Employee::withTrashed()->max('biometric_id') + 1;
    }

    private function workUnits(): Collection
    {
        return WorkUnit::where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
