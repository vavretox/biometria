<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LeaveTypeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $validated = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:leave_types,name']]);
        $baseCode = Str::slug($validated['name'], '_');
        $code = $baseCode;
        $suffix = 2;

        while (LeaveType::where('code', $code)->exists()) {
            $code = $baseCode.'_'.$suffix;
            $suffix++;
        }

        LeaveType::create([
            'name' => $validated['name'],
            'code' => $code,
            'abbreviation' => LeaveType::nextAvailableAbbreviation($validated['name']),
            'color' => LeaveType::nextColor(),
            'is_active' => true,
        ]);

        return back()->with('success', 'Tipo de permiso creado correctamente.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $leaveType->update($request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('leave_types')->ignore($leaveType)],
            'is_active' => ['required', 'boolean'],
        ]));

        return back()->with('success', 'Tipo de permiso actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, LeaveType $leaveType): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        if ($leaveType->leaveRequests()->exists()) {
            return back()->withErrors(['leave_type' => 'No se puede eliminar un tipo de permiso que ya está en uso.']);
        }

        $leaveType->delete();

        return back()->with('success', 'Tipo de permiso eliminado correctamente.');
    }
}
