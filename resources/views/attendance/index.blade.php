@extends('layouts.app')
@section('title', 'Asistencias')
@section('heading', 'Control de asistencias')
@section('content')
<div class='grid gap-6'>
<section class='rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6'>
    <div class='flex flex-wrap items-center justify-between gap-3'>
        <div><h2 class='text-lg font-bold'>Consultar asistencias</h2><p class='mt-1 text-sm text-slate-500'>Busca un empleado, revisa sus días y gestiona marcaciones o permisos desde el detalle.</p></div>
        @if(auth()->user()?->is_admin)<button type='button' onclick="startAttendanceEntry()" class='inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600'>+ Marcación o permiso</button>@endif
    </div>
    <div class='mt-5 flex flex-wrap items-center gap-2 text-sm'>
        <span class='mr-1 font-medium text-slate-500'>Ir a:</span>
        <a href='{{ route('attendance.index', [...request()->only('search', 'work_unit_id', 'device_id', 'mark_filter', 'per_page'), 'from' => today()->toDateString(), 'to' => today()->toDateString()]) }}' class='rounded-lg border border-slate-200 px-3 py-2 font-medium text-slate-700 hover:border-emerald-300 hover:bg-emerald-50'>Hoy</a>
        <a href='{{ route('attendance.index', [...request()->only('search', 'work_unit_id', 'device_id', 'mark_filter', 'per_page'), 'from' => today()->subDay()->toDateString(), 'to' => today()->subDay()->toDateString()]) }}' class='rounded-lg border border-slate-200 px-3 py-2 font-medium text-slate-700 hover:border-emerald-300 hover:bg-emerald-50'>Ayer</a>
        <a href='{{ route('attendance.index', [...request()->only('search', 'work_unit_id', 'device_id', 'mark_filter', 'per_page'), 'from' => today()->startOfWeek()->toDateString(), 'to' => today()->toDateString()]) }}' class='rounded-lg border border-slate-200 px-3 py-2 font-medium text-slate-700 hover:border-emerald-300 hover:bg-emerald-50'>Esta semana</a>
    </div>
    <form method='GET' action='{{ route('attendance.index') }}' class='mt-5 grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 xl:grid-cols-4'>
        <label class='grid gap-1 text-sm font-medium sm:col-span-2'>Buscar empleado<input type='search' name='search' value='{{ request('search') }}' placeholder='Nombre, apellido, código, documento o ID biométrico' class='min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'></label>
        <label class='grid gap-1 text-sm font-medium'>Desde<input type='date' name='from' value='{{ $searchRequested ? $from->toDateString() : '' }}' class='min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'></label>
        <label class='grid gap-1 text-sm font-medium'>Hasta<input type='date' name='to' value='{{ $searchRequested ? $to->toDateString() : '' }}' class='min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'></label>
        @if(request('employee_id'))<input type='hidden' name='employee_id' value='{{ request('employee_id') }}'>@endif
        <label class='grid gap-1 text-sm font-medium'>Unidad<select name='work_unit_id' class='min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'><option value=''>Todas las unidades</option>@foreach($workUnits as $unit)<option value='{{ $unit->id }}' @selected(request('work_unit_id') == $unit->id)>{{ $unit->name }}</option>@endforeach</select></label>
        <label class='grid gap-1 text-sm font-medium'>Biométrico<select name='device_id' class='min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'><option value=''>Todos los biométricos</option>@foreach($filterDevices as $filterDevice)<option value='{{ $filterDevice->id }}' @selected(request('device_id') == $filterDevice->id)>{{ $filterDevice->device_name }}</option>@endforeach</select></label>
        <label class='grid gap-1 text-sm font-medium'>Marcaciones<select name='mark_filter' class='min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'><option value=''>Todos</option><option value='with_marks' @selected(request('mark_filter') === 'with_marks')>Con marcaciones</option><option value='without_marks' @selected(request('mark_filter') === 'without_marks')>Sin marcaciones</option><option value='with_leave' @selected(request('mark_filter') === 'with_leave')>Con permiso aprobado o pendiente</option></select></label>
        <label class='grid gap-1 text-sm font-medium'>Por página<select name='per_page' class='min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'>@foreach([15, 25, 50] as $pageSize)<option value='{{ $pageSize }}' @selected(request('per_page', 25) == $pageSize)>{{ $pageSize }} empleados</option>@endforeach</select></label>
        <div class='flex items-end gap-2 sm:col-span-2 xl:col-span-4'><button class='min-h-11 rounded-xl bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-700'>Filtrar</button><a href='{{ route('attendance.index') }}' class='inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-100'>Limpiar</a></div>
    </form>
</section>
@if($searchRequested)
<section class='overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm'>
    <div class='flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-5 sm:px-6'>
        <div><h2 class='font-bold text-slate-900'>Listado de empleados <span class='ml-2 rounded-lg bg-slate-100 px-2.5 py-1 text-sm text-slate-600'>{{ $dailySummaries->total() }}</span></h2><p class='mt-1 text-sm text-slate-500'>Abre un empleado para consultar sus marcaciones y permisos.</p></div>
        <p class='rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600'>{{ $from->format('d/m/Y') }} — {{ $to->format('d/m/Y') }}</p>
    </div>
    <div class='divide-y divide-slate-100'>
        @forelse($dailySummaries as $summary)
        <details class='group' @if($dailySummaries->total() === 1) open @endif>
            <summary class='grid cursor-pointer list-none gap-3 px-5 py-5 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-emerald-600 group-open:bg-slate-50 sm:grid-cols-[1fr_auto_auto] sm:items-center sm:px-6'>
                <div><p class='text-sm font-bold'>{{ $summary['employee']->last_name }} {{ $summary['employee']->first_name }} {{ $summary['employee']->maternal_last_name }}</p><p class='mt-1 text-xs text-slate-500'>{{ $summary['employee']->employee_code }} · {{ $summary['employee']->workUnit?->name ?: 'Unidad pendiente' }}</p></div>
                <div class='flex flex-wrap gap-2 text-xs'><span class='rounded-full px-3 py-1 font-semibold {{ ($summary['marked_count'] || $summary['covered_count']) ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}'>{{ $summary['marked_count'] ? $summary['marked_count'].' marcas' : ($summary['covered_count'] ? 'Cubierto por permiso' : 'Sin marcaciones') }}</span>@if($summary['leave_count'])<span class='rounded-full bg-blue-50 px-3 py-1 font-semibold text-blue-700'>Con permiso</span>@endif</div>
                <span class='text-xs font-semibold text-blue-700'>Ver detalle <span class='inline-block group-open:rotate-180' aria-hidden='true'>⌄</span></span>
            </summary>
            <div class='grid gap-4 border-t border-slate-200 bg-slate-50 p-3 sm:p-6'>
                @if(auth()->user()?->is_admin && ! $summary['employee']->identity_document)<p class='text-xs text-amber-800'>Datos personales pendientes. <a href='{{ route('employees.edit', $summary['employee']) }}' class='font-semibold underline'>Completar ficha del empleado</a></p>@endif
                @foreach($summary['days'] as $day)
                <section class='overflow-hidden rounded-xl border border-slate-200 bg-white'>
                    <div class='border-b border-slate-200 bg-slate-50/70 px-4 py-4 sm:px-5'>
                    <div class='flex flex-wrap items-center justify-between gap-2'>
                        <div class='grid gap-1'><h3 class='text-base font-bold text-slate-900'>{{ $day['date']->format('d/m/Y') }}</h3><p class='text-xs text-slate-500'>Horario: {{ $day['schedule']?->name ?? 'Sin horario asignado' }}</p></div>
                        <div class='flex flex-wrap items-center gap-3'>
                            @if($day['expectedCount'] && $day['completedCount'] >= $day['expectedCount'])<span class='rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-semibold text-emerald-800'>Completo {{ $day['completedCount'] }}/{{ $day['expectedCount'] }}</span>
                            @elseif($day['expectedCount'])<span class='rounded-full bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-800'>{{ $day['completedCount'] ? 'Incompleto' : 'Sin marcaciones' }} {{ $day['completedCount'] }}/{{ $day['expectedCount'] }}</span>@else<span class='text-xs text-slate-500'>{{ $day['markedCount'] }} marcas</span>@endif
                            @if(auth()->user()?->is_admin)<button type='button' data-employee='{{ $summary['employee']->id }}' data-date='{{ $day['date']->toDateString() }}' onclick='fillLeave(this.dataset.employee, this.dataset.date)' class='min-h-10 rounded-lg border border-blue-200 bg-white px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50'>+ Permiso</button>@endif
                        </div>
                    </div>
                    </div>
                    @if($day['leaves']->isNotEmpty())
                    <div class='grid gap-3 border-b border-slate-200 p-4 sm:p-5'>
                        <h4 class='text-xs font-bold uppercase tracking-wide text-slate-500'>Permisos del día</h4>
                        @foreach($day['leaves'] as $leave)
                        <div class='rounded-xl border border-blue-100 bg-blue-50/60 p-4'>
                            <div class='flex flex-wrap items-start justify-between gap-3'>
                                <div class='grid gap-2'>
                                    <p class='text-sm font-semibold text-slate-900'>{{ $leave->leaveType?->name ?? $leave->type }}</p>
                                    <p class='text-xs text-slate-600'>{{ ['all_day' => 'Día completo', 'half_day' => 'Medio día', 'hours' => 'Por horas'][$leave->duration_type] ?? 'Día completo' }}@if($leave->starts_at) · {{ mb_substr($leave->starts_at, 0, 5) }}–{{ mb_substr($leave->ends_at, 0, 5) }}@endif</p>
                                </div>
                                <span class='rounded-full px-3 py-1.5 text-xs font-semibold {{ $leave->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}'>{{ $leave->status === 'approved' ? 'Aprobado' : 'Pendiente de aprobación' }}</span>
                            </div>
                            @if(auth()->user()?->is_admin)
                            <div class='mt-4 flex flex-wrap items-center gap-2 border-t border-blue-100 pt-3 text-xs'>
                                <a href='{{ route('attendance.index', [...request()->except('edit_attendance', 'edit_leave', 'panel', 'selected_employee', 'selected_date', 'selected_time', 'selected_type'), 'edit_leave' => $leave->id, 'panel' => 'leave']) }}' class='inline-flex min-h-10 items-center rounded-lg border border-blue-200 bg-white px-3 py-2 font-semibold text-blue-700 hover:bg-blue-100'>Editar permiso</a>
                                <form method='POST' action='{{ route('leaves.destroy', $leave) }}' onsubmit='return confirm("¿Eliminar este permiso completo, incluidas todas sus fechas?")'>@csrf @method('DELETE')<button class='min-h-10 rounded-lg px-3 py-2 font-semibold text-red-600 hover:bg-red-50'>Eliminar permiso</button></form>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @endif
                    <h3 class='sr-only'>Marcaciones del día</h3>
                    <div class='px-4 pb-2 sm:px-5'>
                        <div class='hidden grid-cols-[1fr_1fr_1.5fr_1fr] gap-4 border-b border-slate-200 py-3 text-xs font-semibold text-slate-500 lg:grid'>
                            <span>Movimiento</span><span>Hora esperada</span><span>Registro</span><span class='text-right'>@if(auth()->user()?->is_admin)Acciones @endif</span>
                        </div>
                        <div class='divide-y divide-slate-100'>
                        @foreach($day['slots'] as $slot)
                        <div class='grid gap-3 py-4 text-sm sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1.5fr_1fr] lg:items-center lg:gap-4'>
                            <p class='font-semibold text-slate-800'>{{ $slot['label'] }}</p>
                            <p class='text-slate-600'><span class='mr-1 text-xs text-slate-400 lg:hidden'>Esperado:</span>{{ $slot['expected_time'] ?? 'No definido' }}</p>
                            <div class='grid gap-1'>
                                @if($slot['mark'])
                                <p class='font-bold text-emerald-700'>{{ $slot['mark']->checked_at->format('H:i') }}</p>
                                <p class='text-xs text-slate-500'>{{ ['biometric' => 'Biometría', 'access_code' => 'Código', 'automatic' => 'Automática', 'manual' => 'Manual'][$slot['mark']->source] ?? ucfirst($slot['mark']->source) }}@if($slot['difference'] !== null) · {{ $slot['difference'] > 0 ? $slot['difference'].' min después' : ($slot['difference'] < 0 ? abs($slot['difference']).' min antes' : 'A tiempo') }}@endif</p>
                                @elseif($slot['leave'])
                                <span class='w-fit rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800'>Cubierto por permiso</span>
                                @else
                                <span class='w-fit rounded-md bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800'>Sin marca</span>
                                @endif
                                @if($slot['leave'])<p class='text-xs text-blue-700'>Permiso: {{ $slot['leave']->leaveType?->name ?? $slot['leave']->type }}</p>@endif
                            </div>
                            @if(auth()->user()?->is_admin)<div class='flex flex-wrap items-center gap-2 lg:justify-end'>
                                @if($slot['mark'])<a href='{{ route('attendance.index', [...request()->except('edit_attendance', 'edit_leave', 'panel'), 'edit_attendance' => $slot['mark']->id]) }}#attendance-form' class='inline-flex min-h-10 items-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50'>Editar</a><form method='POST' action='{{ route('attendance.destroy', $slot['mark']) }}' onsubmit='return confirm("¿Eliminar esta marcación?")'>@csrf @method('DELETE')<button class='min-h-10 rounded-lg px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50'>Eliminar</button></form>
                                @elseif($slot['leave'])<span class='text-xs font-medium text-blue-700'>Justificado</span>
                                @else<button type='button' data-employee='{{ $summary['employee']->id }}' data-date='{{ $day['date']->toDateString() }}' data-time='{{ $slot['expected_time'] ?? '08:00' }}' data-type='{{ $slot['type'] }}' onclick='fillManual(this.dataset.employee, this.dataset.date, this.dataset.time, this.dataset.type)' class='min-h-10 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800 hover:bg-emerald-100'>+ Añadir marcación</button>@endif
                            </div>@endif
                            @if($slot['mark']?->observation)<p class='rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600 sm:col-span-2 lg:col-span-4'>Observación: {{ $slot['mark']->observation }}</p>@endif
                        </div>
                        @endforeach
                        </div>
                    </div>
                </section>
                @endforeach
            </div>
        </details>
        @empty<div class='grid justify-items-center gap-3 px-5 py-12 text-center'><p class='font-semibold text-slate-800'>No hay empleados para estos filtros.</p><p class='max-w-md text-sm text-slate-500'>Prueba con otro nombre, unidad o período para encontrar al empleado.</p><a href='{{ route('attendance.index') }}' class='rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50'>Limpiar filtros</a></div>
        @endforelse
    </div>
    <div class='border-t border-slate-100 p-5'>{{ $dailySummaries->links() }}</div>
</section>
@endif
@if(auth()->user()?->is_admin)
<dialog id='attendance-form' aria-labelledby='attendance-drawer-title' class='fixed inset-y-0 left-auto right-0 m-0 h-dvh max-h-none w-full max-w-lg border-0 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/40'>
<div class='flex h-full flex-col'>
    <header class='flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 px-5 py-4'>
        <h2 id='attendance-drawer-title' class='text-lg font-bold'>Marcación o permiso</h2>
        <button type='button' onclick='closeAttendancePanel()' class='rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100' aria-label='Cerrar formulario'>Cerrar ×</button>
    </header>
    <div class='min-h-0 flex-1 overflow-y-auto p-5'>
@php
    $leavePanelActive = ! $editableAttendance && ($editableLeave || request('panel') === 'leave' || old('form_context') === 'leave');
    $contextEmployeeId = $editableAttendance?->employee_id ?? $editableLeave?->employee_id ?? (old('employee_context') ? old('employee_id') : null) ?? request('selected_employee') ?? request('leave_employee');
    $contextEmployee = $employees->firstWhere('id', $contextEmployeeId);
@endphp
<div class='mb-5 grid grid-cols-2 rounded-xl bg-slate-100 p-1 text-sm font-semibold'>
<button id='mark-panel-tab' type='button' onclick="showAttendancePanel('mark')" class='rounded-lg px-3 py-2 {{ $leavePanelActive ? 'text-slate-500' : 'bg-white text-slate-900 shadow-sm' }}'>Marcación</button>
<button id='leave-panel-tab' type='button' onclick="showAttendancePanel('leave')" class='rounded-lg px-3 py-2 {{ $leavePanelActive ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500' }}'>Permiso</button>
</div>
<div id='mark-panel' @class(['hidden' => $leavePanelActive])>
<h2 class='text-lg font-bold'>{{ $editableAttendance ? 'Editar marcación' : 'Marcación manual' }}</h2>
<p class='mb-5 text-sm text-slate-500'>{{ $editableAttendance ? 'Corrige la información seleccionada.' : 'Añade marcas o correcciones autorizadas.' }}</p>
<form method='POST' action='{{ $editableAttendance ? route('attendance.update', $editableAttendance) : route('attendance.store') }}' class='grid gap-4'>@csrf @if($editableAttendance) @method('PUT') @endif
<div id='manual-employee-context' @class(['rounded-xl border border-slate-200 bg-slate-50 p-4', 'hidden' => ! $contextEmployee])>
    <p class='text-xs font-medium text-slate-500'>Empleado</p>
    <p id='manual-employee-name' class='mt-1 text-sm font-semibold text-slate-900'>{{ $contextEmployee?->last_name }} {{ $contextEmployee?->first_name }}</p>
    <p class='mt-1 text-xs text-slate-500'>Este registro corresponde únicamente a este empleado.</p>
</div>
<input id='manual-employee-fixed' type='hidden' name='employee_id' value='{{ $contextEmployee?->id }}' @disabled(! $contextEmployee)>
<input id='manual-employee-context-flag' type='hidden' name='employee_context' value='{{ $contextEmployee ? 1 : 0 }}'>
<label id='manual-employee-picker' @class(['text-sm font-medium', 'hidden' => $contextEmployee])>Empleado<select id='manual-employee' name='employee_id' @required(! $contextEmployee) @disabled($contextEmployee) class='mt-1 min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'><option value=''>Seleccione</option>@foreach($employees as $employee)<option value='{{ $employee->id }}' @selected(($editableAttendance?->employee_id ?? old('employee_id', request('selected_employee'))) == $employee->id)>{{ $employee->last_name }} {{ $employee->first_name }}</option>@endforeach</select></label>
<label class='text-sm font-medium'>Fecha y hora<input id='manual-checked-at' type='datetime-local' name='checked_at' value='{{ $editableAttendance?->checked_at->format('Y-m-d\TH:i') ?? old('checked_at', now()->format('Y-m-d\TH:i')) }}' required class='mt-1 min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'></label>
<label class='text-sm font-medium'>Movimiento<select id='manual-type' name='type' class='mt-1 min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'><option value='in' @selected($editableAttendance?->type === 'in')>Entrada</option><option value='out' @selected($editableAttendance?->type === 'out')>Salida</option><option value='break_in' @selected($editableAttendance?->type === 'break_in')>Inicio pausa</option><option value='break_out' @selected($editableAttendance?->type === 'break_out')>Fin pausa</option></select></label>
<label class='text-sm font-medium'>Estado<select name='status' class='mt-1 min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'><option value='normal' @selected(($editableAttendance?->status ?? 'normal') === 'normal')>Normal</option><option value='late' @selected($editableAttendance?->status === 'late')>Atraso</option><option value='early' @selected($editableAttendance?->status === 'early')>Salida anticipada</option></select></label>
<label class='text-sm font-medium'>Minutos de atraso<input type='number' name='late_minutes' value='{{ $editableAttendance?->late_minutes ?? 0 }}' min='0' max='1440' required class='mt-1 min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'></label>
<label class='text-sm font-medium'>Observación<textarea name='observation' rows='3' maxlength='1000' required placeholder='Indique el motivo de la marcación o corrección' class='mt-1 w-full resize-y rounded-xl border border-slate-300 px-3 py-2 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'>{{ old('observation', $editableAttendance?->observation) }}</textarea><span class='mt-1 block text-xs font-normal text-slate-500'>Obligatoria para registrar o modificar una marcación.</span></label>
<div class='flex gap-2'>@if($editableAttendance)<a href='{{ route('attendance.index', request()->except('edit_attendance')) }}' class='rounded-xl bg-slate-100 px-4 py-3 font-semibold'>Cancelar</a>@endif<button class='grow rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white'>{{ $editableAttendance ? 'Guardar cambios' : 'Registrar marcación' }}</button></div>
</form>
</div>
<div id='leave-panel' @class(['hidden' => ! $leavePanelActive])>
<h2 class='text-lg font-bold'>{{ $editableLeave ? 'Editar permiso' : 'Registrar permiso' }}</h2>
<p class='mb-5 text-sm text-slate-500'>Asigna un permiso a un empleado.</p>
<form method='POST' action='{{ $editableLeave ? route('leaves.update', ['leaveRequest' => $editableLeave, ...request()->except('edit_leave', 'panel', 'edit_attendance', 'selected_employee', 'selected_date', 'selected_time', 'selected_type')]) : route('leaves.store') }}' class='grid gap-4'>@csrf @if($editableLeave) @method('PUT') @endif
<input type='hidden' name='form_context' value='leave'>
<div id='leave-employee-context' @class(['rounded-xl border border-slate-200 bg-slate-50 p-4', 'hidden' => ! $contextEmployee])>
    <p class='text-xs font-medium text-slate-500'>Empleado</p>
    <p id='leave-employee-name' class='mt-1 text-sm font-semibold text-slate-900'>{{ $contextEmployee?->last_name }} {{ $contextEmployee?->first_name }}</p>
    <p class='mt-1 text-xs text-slate-500'>Este registro corresponde únicamente a este empleado.</p>
</div>
<input id='leave-employee-fixed' type='hidden' name='employee_id' value='{{ $contextEmployee?->id }}' @disabled(! $contextEmployee)>
<input id='leave-employee-context-flag' type='hidden' name='employee_context' value='{{ $contextEmployee ? 1 : 0 }}'>
<label id='leave-employee-picker' @class(['text-sm font-medium', 'hidden' => $contextEmployee])>Empleado<select id='leave-employee' name='employee_id' @required(! $contextEmployee) @disabled($contextEmployee) class='mt-1 min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'><option value=''>Seleccione</option>@foreach($employees as $employee)<option value='{{ $employee->id }}' @selected(old('employee_id', $editableLeave?->employee_id) == $employee->id)>{{ $employee->last_name }} {{ $employee->first_name }}</option>@endforeach</select></label>
<label class='text-sm font-medium'>Tipo de permiso<select name='leave_type_id' required class='mt-1 min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'><option value=''>Seleccione</option>@foreach($leaveTypes as $leaveType)<option value='{{ $leaveType->id }}' @selected(old('leave_type_id', $editableLeave?->leave_type_id) == $leaveType->id)>{{ $leaveType->name }}</option>@endforeach</select></label>
<label class='text-sm font-medium'>Duración<select id='attendance-leave-duration' name='duration_type' required class='mt-1 min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'><option value='all_day' @selected(old('duration_type', $editableLeave?->duration_type ?? 'all_day') === 'all_day')>Día completo</option><option value='half_day' @selected(old('duration_type', $editableLeave?->duration_type) === 'half_day')>Medio día</option><option value='hours' @selected(old('duration_type', $editableLeave?->duration_type) === 'hours')>Por horas</option></select></label>
<div class='grid grid-cols-2 gap-3'><label class='text-sm font-medium'>Desde<input id='leave-starts-on' type='date' name='starts_on' value='{{ old('starts_on', $editableLeave?->starts_on?->toDateString()) }}' required class='mt-1 min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'></label><label class='text-sm font-medium'>Hasta<input id='leave-ends-on' type='date' name='ends_on' value='{{ old('ends_on', $editableLeave?->ends_on?->toDateString()) }}' required class='mt-1 min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'></label></div>
<label id='attendance-half-day-field' class='text-sm font-medium'>Jornada<select name='half_day_period' class='mt-1 min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'><option value='morning' @selected(old('half_day_period', $editableLeave?->half_day_period) === 'morning')>Mañana</option><option value='afternoon' @selected(old('half_day_period', $editableLeave?->half_day_period) === 'afternoon')>Tarde</option></select></label>
<div id='attendance-time-fields' class='grid grid-cols-2 gap-3'><label class='text-sm font-medium'>Hora inicial<input type='time' name='starts_at' value='{{ old('starts_at', $editableLeave?->starts_at ? mb_substr($editableLeave->starts_at, 0, 5) : null) }}' class='mt-1 min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'></label><label class='text-sm font-medium'>Hora final<input type='time' name='ends_at' value='{{ old('ends_at', $editableLeave?->ends_at ? mb_substr($editableLeave->ends_at, 0, 5) : null) }}' class='mt-1 min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'></label></div>
<label class='text-sm font-medium'>Motivo u observaciones<textarea name='reason' rows='3' maxlength='1000' class='mt-1 w-full resize-y rounded-xl border border-slate-300 px-3 py-2 focus:border-emerald-500 focus:outline-2 focus:outline-emerald-500/20'>{{ old('reason', $editableLeave?->reason) }}</textarea></label>
<div class='flex gap-2'>@if($editableLeave)<a href='{{ route('attendance.index', request()->except('edit_leave', 'panel')) }}' class='rounded-xl bg-slate-100 px-4 py-3 font-semibold'>Cancelar</a>@endif<button class='grow rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white'>{{ $editableLeave ? 'Guardar cambios' : 'Registrar permiso' }}</button></div>
</form>
</div>
</div>
</div>
</dialog>
@endif
</div>
@php
    $newAttendanceEntryUrl = route('attendance.index', [...request()->except('edit_leave', 'edit_attendance', 'panel', 'selected_employee', 'selected_date', 'selected_time', 'selected_type', 'leave_employee', 'leave_date'), 'panel' => 'mark']);
@endphp
<script>
function startAttendanceEntry() {
    if (@json((bool) ($editableLeave || $editableAttendance))) {
        window.location.assign(@json($newAttendanceEntryUrl));
        return;
    }
    setAttendanceEmployeeContext();
    showAttendancePanel('mark');
}
function setAttendanceEmployeeContext(employeeId = null) {
    for (const prefix of ['manual', 'leave']) {
        const select = document.getElementById(prefix + '-employee');
        if (!select) continue;
        const isFixed = employeeId !== null;
        if (isFixed) select.value = employeeId;
        select.disabled = isFixed;
        select.required = !isFixed;
        document.getElementById(prefix + '-employee-picker').classList.toggle('hidden', isFixed);
        document.getElementById(prefix + '-employee-context').classList.toggle('hidden', !isFixed);
        document.getElementById(prefix + '-employee-name').textContent = isFixed ? select.selectedOptions[0]?.textContent.trim() ?? '' : '';
        const fixedEmployee = document.getElementById(prefix + '-employee-fixed');
        fixedEmployee.value = isFixed ? employeeId : '';
        fixedEmployee.disabled = !isFixed;
        document.getElementById(prefix + '-employee-context-flag').value = isFixed ? '1' : '0';
    }
}
function fillManual(employeeId, date, time, type = 'in') {
    if (@json((bool) ($editableAttendance || $editableLeave))) {
        const url = new URL(window.location.href);
        url.searchParams.delete('edit_attendance');
        url.searchParams.delete('edit_leave');
        url.searchParams.set('panel', 'mark');
        url.searchParams.set('selected_employee', employeeId);
        url.searchParams.set('selected_date', date);
        url.searchParams.set('selected_time', time);
        url.searchParams.set('selected_type', type);
        url.hash = 'attendance-form';
        window.location.assign(url);
        return;
    }
    setAttendanceEmployeeContext(employeeId);
    showAttendancePanel('mark');
    document.getElementById('manual-checked-at').value = date + 'T' + time;
    document.getElementById('manual-type').value = type;
}

function fillLeave(employeeId, date) {
    if (@json((bool) ($editableLeave || $editableAttendance))) {
        const url = new URL(window.location.href);
        url.searchParams.delete('edit_leave');
        url.searchParams.delete('edit_attendance');
        url.searchParams.set('panel', 'leave');
        url.searchParams.set('leave_employee', employeeId);
        url.searchParams.set('leave_date', date);
        window.location.assign(url);
        return;
    }
    setAttendanceEmployeeContext(employeeId);
    showAttendancePanel('leave');
    document.getElementById('leave-starts-on').value = date;
    document.getElementById('leave-ends-on').value = date;
}

function showAttendancePanel(panel) {
    const drawer = document.getElementById('attendance-form');
    if (!drawer) return;
    const showsLeave = panel === 'leave';
    document.getElementById('mark-panel').classList.toggle('hidden', showsLeave);
    document.getElementById('leave-panel').classList.toggle('hidden', ! showsLeave);
    document.getElementById('mark-panel-tab').className = 'rounded-lg px-3 py-2 ' + (showsLeave ? 'text-slate-500' : 'bg-white text-slate-900 shadow-sm');
    document.getElementById('leave-panel-tab').className = 'rounded-lg px-3 py-2 ' + (showsLeave ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500');
    if (!drawer.open) {
        attendancePageOverflow = document.body.style.overflow;
        drawer.showModal();
        document.body.style.overflow = 'hidden';
    }
}

let attendancePageOverflow = '';
const attendanceDrawer = document.getElementById('attendance-form');
function closeAttendancePanel() { attendanceDrawer?.close(); }
attendanceDrawer?.addEventListener('close', () => { document.body.style.overflow = attendancePageOverflow; });
attendanceDrawer?.addEventListener('click', (event) => {
    if (event.target !== attendanceDrawer) return;
    const bounds = attendanceDrawer.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) closeAttendancePanel();
});

const attendanceLeaveDuration = document.getElementById('attendance-leave-duration');

function updateAttendanceLeaveDuration() {
    const usesHours = attendanceLeaveDuration.value === 'hours';
    const usesHalfDay = attendanceLeaveDuration.value === 'half_day';
    const timeFields = document.getElementById('attendance-time-fields');
    const halfDayField = document.getElementById('attendance-half-day-field');
    timeFields.classList.toggle('hidden', ! usesHours);
    timeFields.querySelectorAll('input').forEach((input) => input.required = usesHours);
    halfDayField.classList.toggle('hidden', ! usesHalfDay);
    halfDayField.querySelector('select').required = usesHalfDay;
}

if (attendanceLeaveDuration) {
    attendanceLeaveDuration.addEventListener('change', updateAttendanceLeaveDuration);
    updateAttendanceLeaveDuration();
}
if (@json((bool) ($editableAttendance || $editableLeave || request('panel') || $errors->any())) && attendanceDrawer) {
    showAttendancePanel(@json(! $editableAttendance && ($editableLeave || request('panel') === 'leave' || old('form_context') === 'leave') ? 'leave' : 'mark'));
}
if (@json((bool) (request('selected_employee') && ! $errors->any())) && document.getElementById('manual-employee')) {
    fillManual(@json(request('selected_employee')), @json(request('selected_date')), @json(request('selected_time')), @json(request('selected_type', 'in')));
}
if (@json((bool) (request('leave_employee') && ! $errors->any())) && document.getElementById('leave-employee') && !@json((bool) $editableLeave)) {
    fillLeave(@json(request('leave_employee')), @json(request('leave_date')));
}
</script>
@endsection
