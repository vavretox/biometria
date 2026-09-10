@extends('layouts.app')
@section('title', 'Asistencias')
@section('heading', 'Control de asistencias')
@section('content')
<div class='grid gap-6 xl:grid-cols-[1fr_22rem]'>
<section class='flex flex-col rounded-2xl bg-white p-6 shadow-sm'>
<style>#daily-summary{order:2}#daily-summary+div{order:3}</style>
<div id='daily-summary' class='overflow-x-auto'><table class='w-full text-left text-sm'><thead class='bg-slate-50 text-xs uppercase text-slate-500'><tr><th class='p-3'>Fecha</th><th class='p-3'>Empleado</th><th class='p-3'>Horario</th><th class='min-w-96 p-3'>Marcaciones del día</th><th class='p-3'>Cumplimiento</th></tr></thead><tbody class='divide-y'>
@forelse($dailySummaries as $summary)
@php($markedSlots = $summary['slots']->whereNotNull('mark')->count())
@php($expectedSlots = $summary['slots']->whereNotNull('expected_time')->count())
<tr class='align-top'>
<td class='p-3 font-semibold'>{{ $summary['date']->format('d/m/Y') }}<small class='block font-normal text-slate-500'>{{ ucfirst($summary['date']->locale('es')->dayName) }}</small></td>
<td class='p-3 font-semibold'>{{ $summary['employee']->last_name }} {{ $summary['employee']->first_name }}</td>
<td class='p-3'><span class='rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700'>{{ $summary['schedule']?->name ?? 'Sin horario asignado' }}</span></td>
<td class='p-3'><div class='grid gap-2 sm:grid-cols-2'>
@foreach($summary['slots'] as $slot)
<div class='rounded-xl border p-3 {{ $slot['mark'] ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}'>
<div class='flex justify-between gap-2'><div><p class='text-xs font-bold uppercase {{ $slot['mark'] ? 'text-emerald-700' : 'text-amber-700' }}'>{{ $slot['label'] }}</p><p class='text-xs text-slate-500'>Esperado: {{ $slot['expected_time'] ?? 'No definido' }}</p></div>
@if($slot['mark'])<span class='font-bold'>{{ $slot['mark']->checked_at->format('H:i') }}</span>@else<span class='text-xs font-bold text-amber-700'>Falta</span>@endif</div>
@if($slot['mark'])
<p class='mt-1 text-xs text-slate-500'>{{ ['biometric' => 'Biometría', 'access_code' => 'Código', 'automatic' => 'Automática', 'manual' => 'Manual'][$slot['mark']->source] ?? ucfirst($slot['mark']->source) }}
@if($slot['difference'] !== null) · {{ $slot['difference'] > 0 ? $slot['difference'].' min después' : ($slot['difference'] < 0 ? abs($slot['difference']).' min antes' : 'A tiempo') }}@endif</p>
@if($slot['mark']->observation)<p class='mt-1 line-clamp-2 text-xs text-slate-600' title='{{ $slot['mark']->observation }}'><span class='font-semibold'>Observación:</span> {{ $slot['mark']->observation }}</p>@endif
@if(auth()->user()?->is_admin)<a href='?edit_attendance={{ $slot['mark']->id }}#attendance-form' class='mt-2 inline-block text-xs font-bold text-blue-700'>Editar</a><form method='POST' action='{{ route('attendance.destroy', $slot['mark']) }}' class='ml-2 inline'>@csrf<input type='hidden' name='_method' value='DELETE'><button class='text-xs font-bold text-red-600'>Eliminar</button></form>@endif
@else
@if(auth()->user()?->is_admin)
<button type='button' data-employee='{{ $summary['employee']->id }}' data-date='{{ $summary['date']->toDateString() }}' data-time='{{ $slot['expected_time'] ?? '08:00' }}' onclick='fillManual(this.dataset.employee, this.dataset.date, this.dataset.time)' class='mt-2 text-xs font-bold text-emerald-700'>+ Añadir</button>
@endif
@endif
</div>
@endforeach
</div></td>
<td class='p-3'>
@if($expectedSlots > 0 && $markedSlots >= $expectedSlots)<span class='rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700'>Completo {{ $markedSlots }}/{{ $expectedSlots }}</span>
@elseif($expectedSlots > 0)<span class='rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-700'>Incompleto {{ $markedSlots }}/{{ $expectedSlots }}</span>
@else<span class='rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600'>{{ $markedSlots }} marcas</span>@endif
</td></tr>
@empty<tr><td colspan='5' class='p-10 text-center text-slate-500'>No existen marcaciones para el período seleccionado.</td></tr>
@endforelse
</tbody></table></div><div class='mt-5'>{{ $dailySummaries->links() }}</div>
<div class='mb-5 flex flex-wrap items-end justify-between gap-4'><div><h2 class='text-lg font-bold'>Marcaciones por período</h2><p class='text-sm text-slate-500'>Entradas, salidas y pausas registradas.</p></div>
<form method='GET' class='flex flex-wrap items-end gap-3'>
<label class='text-sm font-medium'>Desde<input type='date' name='from' value='{{ $from->toDateString() }}' class='mt-1 block rounded-xl border-slate-300'></label>
<label class='text-sm font-medium'>Hasta<input type='date' name='to' value='{{ $to->toDateString() }}' class='mt-1 block rounded-xl border-slate-300'></label>
<label class='text-sm font-medium'>Empleado<select name='employee_id' class='mt-1 block rounded-xl border-slate-300'><option value=''>Todos los empleados</option>@foreach($employees as $employee)<option value='{{ $employee->id }}' @selected(request('employee_id') == $employee->id)>{{ $employee->last_name }} {{ $employee->first_name }}</option>@endforeach</select></label>
<button class='rounded-xl bg-slate-900 px-5 py-2.5 text-white'>Filtrar</button>
</form></div>
</section>
<aside id='attendance-form' class='h-fit rounded-2xl bg-white p-6 shadow-sm xl:sticky xl:top-6'>
@php($leavePanelActive = ! $editableAttendance && (request('panel') === 'leave' || old('form_context') === 'leave'))
<div class='mb-5 grid grid-cols-2 rounded-xl bg-slate-100 p-1 text-sm font-semibold'>
<button id='mark-panel-tab' type='button' onclick=showAttendancePanel('mark') class='rounded-lg px-3 py-2 {{ $leavePanelActive ? 'text-slate-500' : 'bg-white text-slate-900 shadow-sm' }}'>Marcación</button>
<button id='leave-panel-tab' type='button' onclick=showAttendancePanel('leave') class='rounded-lg px-3 py-2 {{ $leavePanelActive ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500' }}'>Permiso</button>
</div>
<div id='mark-panel' @class(['hidden' => $leavePanelActive])>
<h2 class='text-lg font-bold'>{{ $editableAttendance ? 'Editar marcación' : 'Marcación manual' }}</h2>
<p class='mb-5 text-sm text-slate-500'>{{ $editableAttendance ? 'Corrige la información seleccionada.' : 'Añade marcas o correcciones autorizadas.' }}</p>
<form method='POST' action='{{ $editableAttendance ? route('attendance.update', $editableAttendance) : route('attendance.store') }}' class='grid gap-4'>@csrf @if($editableAttendance) @method('PUT') @endif
<label class='text-sm font-medium'>Empleado<select id='manual-employee' name='employee_id' required @disabled($editableAttendance) class='mt-1 w-full rounded-xl border-slate-300'><option value=''>Seleccione</option>@foreach($employees as $employee)<option value='{{ $employee->id }}' @selected(($editableAttendance?->employee_id ?? old('employee_id')) == $employee->id)>{{ $employee->last_name }} {{ $employee->first_name }}</option>@endforeach</select></label>
<label class='text-sm font-medium'>Fecha y hora<input id='manual-checked-at' type='datetime-local' name='checked_at' value='{{ $editableAttendance?->checked_at->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i') }}' required class='mt-1 w-full rounded-xl border-slate-300'></label>
<label class='text-sm font-medium'>Movimiento<select id='manual-type' name='type' class='mt-1 w-full rounded-xl border-slate-300'><option value='in' @selected($editableAttendance?->type === 'in')>Entrada</option><option value='out' @selected($editableAttendance?->type === 'out')>Salida</option><option value='break_in' @selected($editableAttendance?->type === 'break_in')>Inicio pausa</option><option value='break_out' @selected($editableAttendance?->type === 'break_out')>Fin pausa</option></select></label>
<label class='text-sm font-medium'>Estado<select name='status' class='mt-1 w-full rounded-xl border-slate-300'><option value='normal' @selected(($editableAttendance?->status ?? 'normal') === 'normal')>Normal</option><option value='late' @selected($editableAttendance?->status === 'late')>Atraso</option><option value='early' @selected($editableAttendance?->status === 'early')>Salida anticipada</option></select></label>
<label class='text-sm font-medium'>Minutos de atraso<input type='number' name='late_minutes' value='{{ $editableAttendance?->late_minutes ?? 0 }}' min='0' max='1440' required class='mt-1 w-full rounded-xl border-slate-300'></label>
<label class='text-sm font-medium'>Observación<textarea name='observation' rows='3' maxlength='1000' required placeholder='Indique el motivo de la marcación o corrección' class='mt-1 w-full resize-y rounded-xl border-slate-300'>{{ old('observation', $editableAttendance?->observation) }}</textarea><span class='mt-1 block text-xs font-normal text-slate-500'>Obligatoria para registrar o modificar una marcación.</span></label>
<div class='flex gap-2'>@if($editableAttendance)<a href='{{ route('attendance.index', request()->except('edit_attendance')) }}' class='rounded-xl bg-slate-100 px-4 py-3 font-semibold'>Cancelar</a>@endif<button class='grow rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white'>{{ $editableAttendance ? 'Guardar cambios' : 'Registrar marcación' }}</button></div>
</form>
</div>
<div id='leave-panel' @class(['hidden' => ! $leavePanelActive])>
<h2 class='text-lg font-bold'>Registrar permiso</h2>
<p class='mb-5 text-sm text-slate-500'>Asigna un permiso a un empleado.</p>
<form method='POST' action='{{ route('leaves.store') }}' class='grid gap-4'>@csrf
<input type='hidden' name='form_context' value='leave'>
<label class='text-sm font-medium'>Empleado<select name='employee_id' required class='mt-1 w-full rounded-xl border-slate-300'><option value=''>Seleccione</option>@foreach($employees as $employee)<option value='{{ $employee->id }}' @selected(old('form_context') === 'leave' && old('employee_id') == $employee->id)>{{ $employee->last_name }} {{ $employee->first_name }}</option>@endforeach</select></label>
<label class='text-sm font-medium'>Tipo de permiso<select name='leave_type_id' required class='mt-1 w-full rounded-xl border-slate-300'><option value=''>Seleccione</option>@foreach($leaveTypes as $leaveType)<option value='{{ $leaveType->id }}' @selected(old('leave_type_id') == $leaveType->id)>{{ $leaveType->name }}</option>@endforeach</select></label>
<label class='text-sm font-medium'>Duración<select id='attendance-leave-duration' name='duration_type' required class='mt-1 w-full rounded-xl border-slate-300'><option value='all_day' @selected(old('duration_type', 'all_day') === 'all_day')>Día completo</option><option value='half_day' @selected(old('duration_type') === 'half_day')>Medio día</option><option value='hours' @selected(old('duration_type') === 'hours')>Por horas</option></select></label>
<div class='grid grid-cols-2 gap-3'><label class='text-sm font-medium'>Desde<input type='date' name='starts_on' value='{{ old('starts_on') }}' required class='mt-1 w-full rounded-xl border-slate-300'></label><label class='text-sm font-medium'>Hasta<input type='date' name='ends_on' value='{{ old('ends_on') }}' required class='mt-1 w-full rounded-xl border-slate-300'></label></div>
<label id='attendance-half-day-field' class='text-sm font-medium'>Jornada<select name='half_day_period' class='mt-1 w-full rounded-xl border-slate-300'><option value='morning' @selected(old('half_day_period') === 'morning')>Mañana</option><option value='afternoon' @selected(old('half_day_period') === 'afternoon')>Tarde</option></select></label>
<div id='attendance-time-fields' class='grid grid-cols-2 gap-3'><label class='text-sm font-medium'>Hora inicial<input type='time' name='starts_at' value='{{ old('starts_at') }}' class='mt-1 w-full rounded-xl border-slate-300'></label><label class='text-sm font-medium'>Hora final<input type='time' name='ends_at' value='{{ old('ends_at') }}' class='mt-1 w-full rounded-xl border-slate-300'></label></div>
<label class='text-sm font-medium'>Motivo u observaciones<textarea name='reason' rows='3' maxlength='1000' class='mt-1 w-full resize-y rounded-xl border-slate-300'>{{ old('reason') }}</textarea></label>
<button class='rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white'>Registrar permiso</button>
</form>
</div>
</aside>
</div>
<script>
function fillManual(employeeId, date, time) {
    showAttendancePanel('mark');
    document.getElementById('manual-employee').value = employeeId;
    document.getElementById('manual-checked-at').value = date + 'T' + time;
    document.getElementById('attendance-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function showAttendancePanel(panel) {
    const showsLeave = panel === 'leave';
    document.getElementById('mark-panel').classList.toggle('hidden', showsLeave);
    document.getElementById('leave-panel').classList.toggle('hidden', ! showsLeave);
    document.getElementById('mark-panel-tab').className = 'rounded-lg px-3 py-2 ' + (showsLeave ? 'text-slate-500' : 'bg-white text-slate-900 shadow-sm');
    document.getElementById('leave-panel-tab').className = 'rounded-lg px-3 py-2 ' + (showsLeave ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500');
}

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

attendanceLeaveDuration.addEventListener('change', updateAttendanceLeaveDuration);
updateAttendanceLeaveDuration();
</script>
@endsection
