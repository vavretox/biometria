<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkUnitRequest;
use App\Models\Office;
use App\Models\WorkUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OfficeController extends Controller
{
    public function index(): View
    {
        return view('offices.index', [
            'workUnits' => WorkUnit::withCount('employees')->orderBy('name')->get(),
        ]);
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
