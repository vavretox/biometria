<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkUnitRequest;
use App\Models\Office;
use App\Models\WorkUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfficeController extends Controller
{
    public function index(): View
    {
        return view('offices.index', [
            'workUnits' => WorkUnit::withCount('employees')->orderBy('name')->get(),
        ]);
    }

    public function updateWorkUnit(StoreWorkUnitRequest $request, WorkUnit $workUnit): RedirectResponse
    {
        $workUnit->update($request->validated());

        return redirect()->route('office-units.index')->with('success', 'Unidad actualizada correctamente.');
    }

    public function destroyWorkUnit(Request $request, WorkUnit $workUnit): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        if ($workUnit->employees()->withTrashed()->exists()) {
            return back()->withErrors(['work_unit' => 'No se puede eliminar una unidad con empleados asignados. Reasígnelos primero.']);
        }

        $workUnit->delete();

        return redirect()->route('office-units.index')->with('success', 'Unidad eliminada correctamente.');
    }

    public function storeWorkUnit(StoreWorkUnitRequest $request): RedirectResponse
    {
        $office = Office::firstOrCreate(
            ['code' => 'GENERAL'],
            ['name' => 'Unidades de oficina', 'is_active' => true],
        );
        WorkUnit::create([...$request->validated(), 'office_id' => $office->id, 'is_active' => true]);

        return back()->with('success', 'Unidad de trabajo creada correctamente.');
    }
}
