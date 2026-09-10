@extends('layouts.app')
@section('title', 'Reportes')
@section('heading', 'Reporte de asistencia')
@section('content')
<div class='grid gap-6'>
    <section class='rounded-2xl bg-white p-6 shadow-sm'>
        <div class='mb-5'>
            <h2 class='text-lg font-bold'>Filtros del reporte</h2>
            <p class='text-sm text-slate-500'>Los resultados respetan la unidad, el empleado, el tipo de horario y la vigencia histórica dentro del período.</p>
        </div>
        <form method='GET' class='grid gap-4 md:grid-cols-2 xl:grid-cols-6'>
            <label class='text-sm font-medium'>Desde<input type='date' name='from' value='{{ $from->toDateString() }}' class='mt-1 w-full rounded-xl border-slate-300'></label>
            <label class='text-sm font-medium'>Hasta<input type='date' name='to' value='{{ $to->toDateString() }}' class='mt-1 w-full rounded-xl border-slate-300'></label>
            <label class='text-sm font-medium'>Unidad<select name='work_unit_id' class='mt-1 w-full rounded-xl border-slate-300'><option value=''>Todas las unidades</option>@foreach($workUnits as $unit)<option value='{{ $unit->id }}' @selected(request('work_unit_id') == $unit->id)>{{ $unit->name }}</option>@endforeach</select></label>
            <label class='text-sm font-medium'>Empleado<select name='employee_id' class='mt-1 w-full rounded-xl border-slate-300'><option value=''>Todos los empleados</option>@foreach($employeeOptions as $employee)<option value='{{ $employee->id }}' @selected(request('employee_id') == $employee->id)>{{ $employee->last_name }} {{ $employee->maternal_last_name }} {{ $employee->first_name }}</option>@endforeach</select></label>
            <label class='text-sm font-medium'>Tipo de horario<select name='schedule_type' class='mt-1 w-full rounded-xl border-slate-300'><option value=''>Todos los horarios</option><option value='continuous' @selected($scheduleType === 'continuous')>Continuo</option><option value='split' @selected($scheduleType === 'split')>Discontinuo</option></select></label>
            <div class='grid self-end gap-2'>
                <button class='rounded-xl bg-slate-900 px-6 py-3 font-semibold text-white'>Generar reporte</button>
                <button formaction='{{ route('reports.export') }}' class='rounded-xl bg-emerald-600 px-6 py-3 font-semibold text-white'>Exportar Excel por unidad</button>
            </div>
        </form>
    </section>

    <section class='grid gap-4 md:grid-cols-3'>
        <div class='rounded-2xl bg-sky-50 p-5'><p class='text-sm text-sky-700'>Período evaluado</p><p class='mt-1 text-xl font-bold'>{{ $from->format('d/m/Y') }} — {{ $to->format('d/m/Y') }}</p></div>
        <div class='rounded-2xl bg-emerald-50 p-5'><p class='text-sm text-emerald-700'>Empleados evaluados</p><p class='mt-1 text-3xl font-bold'>{{ $employees->count() }}</p></div>
        <div class='rounded-2xl bg-violet-50 p-5'><p class='text-sm text-violet-700'>Marcaciones filtradas</p><p class='mt-1 text-3xl font-bold'>{{ $totalMarks }}</p></div>
    </section>

    @if($leaveTypes->isNotEmpty())
        <section class='rounded-2xl bg-white p-6 shadow-sm'>
            <div class='mb-4'><h2 class='text-lg font-bold'>Códigos de permisos</h2><p class='text-sm text-slate-500'>Estos colores y abreviaturas se aplican también en la incidencia diaria del Excel.</p></div>
            <div class='flex flex-wrap gap-2'>
                @foreach($leaveTypes as $leaveType)
                    <span class='rounded-full px-3 py-1.5 text-xs font-bold text-white' style='background-color: {{ $leaveType->color }}'>{{ $leaveType->abbreviation }} · {{ $leaveType->name }}</span>
                @endforeach
            </div>
        </section>
    @endif

    <section class='rounded-2xl bg-white p-6 shadow-sm'>
        <div class='mb-4 flex flex-wrap items-center justify-between gap-3'>
            <div><h2 class='text-lg font-bold'>Feriados dentro del período</h2><p class='text-sm text-slate-500'>Se muestran también los feriados recurrentes correspondientes al año consultado.</p></div>
            <span class='rounded-full bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700'>{{ $holidays->count() }} feriados</span>
        </div>
        <div class='grid gap-3 md:grid-cols-2 xl:grid-cols-4'>
            @forelse($holidays as $occurrence)
                <div class='flex items-center gap-3 rounded-xl border border-rose-100 bg-rose-50/50 p-4'>
                    <div class='rounded-lg bg-white px-3 py-2 text-center text-rose-700'><b class='block text-lg'>{{ $occurrence['date']->format('d') }}</b><span class='text-xs uppercase'>{{ $occurrence['date']->translatedFormat('M') }}</span></div>
                    <div><p class='font-semibold'>{{ $occurrence['holiday']->name }}</p><p class='text-xs text-slate-500'>{{ ucfirst($occurrence['holiday']->scope) }}{{ $occurrence['holiday']->is_recurring ? ' · Recurrente' : '' }}</p></div>
                </div>
            @empty
                <p class='text-sm text-slate-500'>No hay feriados registrados dentro del período seleccionado.</p>
            @endforelse
        </div>
    </section>

    <section class='rounded-2xl bg-white p-6 shadow-sm'>
        <div class='mb-5'><h2 class='text-lg font-bold'>Resumen por empleado</h2><p class='text-sm text-slate-500'>Los totales de marcaciones y atrasos respetan el tipo de horario seleccionado.</p></div>
        <div class='overflow-x-auto'>
            <table class='w-full text-left text-sm'>
                <thead class='bg-slate-50 text-xs uppercase text-slate-500'><tr><th class='p-3'>Empleado</th><th class='p-3'>Unidad</th><th class='p-3 text-center'>Marcaciones</th><th class='p-3 text-center'>Atrasos</th><th class='p-3 text-center'>Min. acumulados</th><th class='p-3 text-center'>Permisos aprobados</th></tr></thead>
                <tbody class='divide-y'>
                    @forelse($employees as $employee)
                        <tr><td class='p-3'><p class='font-semibold'>{{ $employee->last_name }} {{ $employee->maternal_last_name }} {{ $employee->first_name }}</p><p class='text-xs text-slate-500'>Ítem {{ $employee->item_number ?: 'Sin ítem' }}</p></td><td class='p-3'>{{ $employee->workUnit?->name ?? 'Sin asignar' }}</td><td class='p-3 text-center font-semibold'>{{ $employee->attendance_count }}</td><td class='p-3 text-center'><span class='rounded-full bg-amber-50 px-3 py-1 text-amber-700'>{{ $employee->late_count }}</span></td><td class='p-3 text-center font-semibold'>{{ $employee->late_minutes_total }}</td><td class='p-3'><div class='flex flex-wrap justify-center gap-1'>@forelse($employee->leaveRequests->pluck('leaveType')->filter()->unique('id') as $leaveType)<span class='rounded-full px-2 py-1 text-[11px] font-bold text-white' style='background-color: {{ $leaveType->color }}' title='{{ $leaveType->name }}'>{{ $leaveType->abbreviation }}</span>@empty<span class='text-slate-400'>0</span>@endforelse</div></td></tr>
                    @empty
                        <tr><td colspan='6' class='p-10 text-center text-slate-500'>No existen empleados para los filtros elegidos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class='rounded-2xl bg-white p-6 shadow-sm'>
        <div class='mb-5'><h2 class='text-lg font-bold'>Horarios aplicados dentro del período</h2><p class='text-sm text-slate-500'>Cada fila representa la vigencia real de un horario. Si hubo un cambio entre fechas, aparecerán ambos tramos por separado.</p></div>
        <div class='overflow-x-auto'>
            <table class='w-full text-left text-sm'>
                <thead class='bg-slate-50 text-xs uppercase text-slate-500'><tr><th class='p-3'>Empleado</th><th class='p-3'>Horario</th><th class='p-3'>Tipo</th><th class='p-3'>Horas</th><th class='p-3'>Desde</th><th class='p-3'>Hasta</th></tr></thead>
                <tbody class='divide-y'>
                    @forelse($scheduleHistory as $period)
                        <tr><td class='p-3 font-semibold'>{{ $period['employee']->last_name }} {{ $period['employee']->first_name }}</td><td class='p-3'>{{ $period['schedule']->name }}</td><td class='p-3'><span class='rounded-full px-3 py-1 text-xs font-bold {{ $period['type'] === 'Continuo' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700' }}'>{{ $period['type'] }}</span></td><td class='p-3 font-medium'>{{ $period['hours'] }}</td><td class='p-3'>{{ $period['starts_on']->format('d/m/Y') }}</td><td class='p-3'>{{ $period['ends_on']->format('d/m/Y') }}</td></tr>
                    @empty
                        <tr><td colspan='6' class='p-10 text-center text-slate-500'>No hay horarios asignados dentro del período y los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
