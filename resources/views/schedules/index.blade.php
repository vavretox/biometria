@extends('layouts.app')
@section('title', 'Horarios')
@section('heading', 'Horarios de trabajo')
@section('content')
<div class='grid gap-6 xl:grid-cols-[1fr_25rem]'>
<section>
<div class='mb-5'>
<h2 class='text-lg font-bold'>Jornadas configuradas</h2>
<p class='text-sm text-slate-500'>Cada jornada puede ser continua o tener tramos de mañana y tarde.</p>
</div>
<div class='grid gap-4 md:grid-cols-2'>@forelse($schedules as $schedule)@php($day = $schedule->days->first())<article class='rounded-2xl bg-white p-5 shadow-sm'>
<div class='flex items-start justify-between gap-3'>
<div>
<h3 class='font-bold'>{{ $schedule->name }}</h3>
<span class='mt-1 inline-block rounded-full bg-emerald-100 px-3 py-1 text-xs text-emerald-700'>{{ $schedule->employees_count }} empleados</span>
</div>@if($schedule->employees_count === 0)<form method='POST' action='{{ route('schedules.destroy', $schedule) }}' onsubmit='return confirm(&#39;¿Eliminar este horario definitivamente?&#39;)'>@csrf @method('DELETE')<button class='rounded-lg bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100'>Eliminar</button>
</form>@else<span class='rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-400'>En uso</span>@endif</div>
<div class='mt-4 grid gap-2'>
<p class='text-xl font-bold'>Mañana: {{ $day ? substr($day->starts_at, 0, 5).' - '.substr($day->ends_at, 0, 5) : 'Sin detalle' }}</p>@if($day?->second_starts_at)<p class='text-xl font-bold'>Tarde: {{ substr($day->second_starts_at, 0, 5) }} - {{ substr($day->second_ends_at, 0, 5) }}</p>@endif</div>
<p class='mt-2 text-sm text-slate-500'>Tolerancia diaria acumulada: {{ $day?->grace_minutes ?? 0 }} minutos</p>
<details class='mt-4 border-t border-slate-200 pt-4'>
<summary class='cursor-pointer text-sm font-bold text-blue-700'>Editar horario</summary>
<form method='POST' action='{{ route('schedules.update', $schedule) }}' class='mt-4 grid gap-3'>@csrf @method('PUT')<label class='text-xs font-semibold'>Nombre<input name='name' value='{{ $schedule->name }}' required class='mt-1 w-full rounded-lg border-slate-300 text-sm'>
</label>
<div class='grid grid-cols-2 gap-2'>
<label class='text-xs font-semibold'>Entrada mañana<input type='time' name='starts_at' value='{{ $day?->starts_at ? substr($day->starts_at, 0, 5) : '08:00' }}' required class='mt-1 w-full rounded-lg border-slate-300 text-sm'>
</label>
<label class='text-xs font-semibold'>Salida mañana<input type='time' name='ends_at' value='{{ $day?->ends_at ? substr($day->ends_at, 0, 5) : '16:00' }}' required class='mt-1 w-full rounded-lg border-slate-300 text-sm'>
</label>
<div class='col-span-2'>
<label class='flex cursor-pointer items-center gap-2 rounded-lg bg-slate-50 p-3 text-xs font-bold'>
<input type='checkbox' data-afternoon-toggle data-target='afternoon-edit-{{ $schedule->id }}' class='rounded border-slate-300 text-emerald-600 focus:ring-emerald-500' @checked($day?->second_starts_at)>
Habilitar turno tarde
</label>
</div>
<div id='afternoon-edit-{{ $schedule->id }}' class='col-span-2 grid grid-cols-2 gap-2 transition-opacity'>
<label class='text-xs font-semibold'>Entrada tarde<input type='time' name='second_starts_at' value='{{ $day?->second_starts_at ? substr($day->second_starts_at, 0, 5) : '15:00' }}' class='mt-1 w-full rounded-lg border-slate-300 text-sm disabled:cursor-not-allowed disabled:bg-slate-100' @disabled(! $day?->second_starts_at)>
</label>
<label class='text-xs font-semibold'>Salida tarde<input type='time' name='second_ends_at' value='{{ $day?->second_ends_at ? substr($day->second_ends_at, 0, 5) : '19:00' }}' class='mt-1 w-full rounded-lg border-slate-300 text-sm disabled:cursor-not-allowed disabled:bg-slate-100' @disabled(! $day?->second_starts_at)>
</label>
</div>
</div>
<label class='text-xs font-semibold'>Tolerancia acumulada<input type='number' name='grace_minutes' value='{{ $day?->grace_minutes ?? 0 }}' min='0' max='120' required class='mt-1 w-full rounded-lg border-slate-300 text-sm'>
</label>
<button class='rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white'>Guardar cambios</button>
</form>
</details>
</article>@empty<div class='rounded-2xl bg-white p-10 text-center text-slate-500'>Aún no hay horarios configurados.</div>@endforelse</div>
<div class='mt-6 rounded-2xl bg-white p-6 shadow-sm'>
<div class='mb-5'>
<h2 class='text-lg font-bold'>Asignar horario a empleados</h2>
<p class='text-sm text-slate-500'>Seleccione empleados, horario y vigencia. Una nueva asignación reemplaza solamente las fechas indicadas y conserva el historial.</p>
</div>
<form method='POST' action='{{ route('schedules.assign') }}' class='grid gap-5'>@csrf<div class='grid gap-3 sm:grid-cols-2'>
<label class='flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-4'>
<input type='radio' name='assignment_scope' value='selected' checked class='mt-1 border-slate-300 text-emerald-600'>
<span>
<b class='block'>Uno o algunos</b>
<small class='text-slate-500'>Seleccione uno o más empleados.</small>
</span>
</label>
<label class='flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-4'>
<input type='radio' name='assignment_scope' value='all' class='mt-1 border-slate-300 text-emerald-600'>
<span>
<b class='block'>Todos los empleados</b>
<small class='text-slate-500'>Se asignará a todo el personal activo.</small>
</span>
</label>
</div>
<div class='grid gap-4 md:grid-cols-2'>
<label class='text-sm font-medium'>Empleados<select name='employee_ids[]' multiple size='8' class='mt-1 w-full rounded-xl border-slate-300'>@foreach($employees as $employee)<option value='{{ $employee->id }}'>{{ $employee->last_name }} {{ $employee->maternal_last_name }} {{ $employee->first_name }} · Ítem {{ $employee->item_number }}</option>@endforeach</select>
<small class='mt-1 block text-slate-500'>Use Ctrl para elegir varios empleados.</small>
</label>
<label class='text-sm font-medium'>Horario<select name='work_schedule_id' required class='mt-1 w-full rounded-xl border-slate-300'>
<option value=''>Seleccione horario</option>@foreach($schedules as $schedule)<option value='{{ $schedule->id }}'>{{ $schedule->name }}</option>@endforeach</select>
</label>
<label class='text-sm font-medium'>Duración<select id='period-type' name='period_type' required class='mt-1 w-full rounded-xl border-slate-300'>
<option value='indefinite'>Indefinido</option>
<option value='day'>Un día</option>
<option value='week'>Una semana</option>
<option value='month'>Un mes</option>
<option value='year'>Un año</option>
<option value='custom'>Rango personalizado</option>
</select>
</label>
<label class='text-sm font-medium'>Fecha de inicio<input id='assignment-starts-on' type='date' name='starts_on' value='{{ today()->toDateString() }}' required class='mt-1 w-full rounded-xl border-slate-300'>
</label>
<label id='assignment-end-wrapper' class='hidden text-sm font-medium'>Fecha final<input id='assignment-ends-on' type='date' name='ends_on' class='mt-1 w-full rounded-xl border-slate-300'>
<small class='mt-1 block text-slate-500'>Incluye el día final.</small>
</label>
</div>
<div>
<button class='rounded-xl bg-slate-900 px-6 py-3 font-semibold text-white'>Aplicar horario</button>
</div>
</form>
</div>
<div class='mt-6 rounded-2xl bg-white p-6 shadow-sm'>
<div class='flex flex-wrap items-end justify-between gap-3'>
<div>
<h2 class='text-lg font-bold'>Empleados con horario asignado</h2>
<p class='text-sm text-slate-500'>Consulta el horario actual, futuro e histórico de cada empleado.</p>
</div>
<span class='rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700'>{{ $assignments->total() }} asignaciones</span>
</div>
<div class='mt-5 overflow-x-auto'>
<table class='min-w-full divide-y divide-slate-200 text-sm'>
<thead class='bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500'>
<tr><th class='px-4 py-3'>Empleado</th><th class='px-4 py-3'>Oficina</th><th class='px-4 py-3'>Horario</th><th class='px-4 py-3'>Vigencia</th><th class='px-4 py-3'>Estado</th></tr>
</thead>
<tbody class='divide-y divide-slate-100'>
@foreach($assignments as $assignment)
@php($isFuture = $assignment->starts_on->isFuture())
@php($isExpired = $assignment->ends_on?->isPast() ?? false)
<tr class='hover:bg-slate-50'>
<td class='px-4 py-3'><span class='block font-semibold text-slate-900'>{{ $assignment->employee->last_name }} {{ $assignment->employee->maternal_last_name }} {{ $assignment->employee->first_name }}</span><span class='text-xs text-slate-500'>Ítem {{ $assignment->employee->item_number ?: 'Sin ítem' }}</span></td>
<td class='px-4 py-3 text-slate-600'>{{ $assignment->employee->office?->name ?? 'Sin oficina' }}</td>
<td class='px-4 py-3 font-semibold text-slate-900'>{{ $assignment->workSchedule->name }}</td>
<td class='px-4 py-3 text-slate-600'><span class='block'>Desde {{ $assignment->starts_on->format('d/m/Y') }}</span><span class='text-xs'>{{ $assignment->ends_on ? 'Hasta '.$assignment->ends_on->format('d/m/Y') : 'Sin fecha final' }}</span></td>
<td class='px-4 py-3'>
@if($isFuture)<span class='rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700'>Programado</span>
@elseif($isExpired)<span class='rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600'>Finalizado</span>
@else<span class='rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700'>Vigente</span>@endif
</td>
</tr>
@endforeach
@if($assignments->isEmpty())
<tr><td colspan='5' class='px-4 py-10 text-center text-slate-500'>Aún no existen asignaciones de horarios.</td></tr>
@endif
</tbody>
</table>
</div>
<div class='mt-4'>{{ $assignments->links() }}</div>
</div>
</section>
<aside class='rounded-2xl bg-white p-6 shadow-sm'>
<h2 class='text-lg font-bold'>Nuevo horario</h2>
<p class='mb-5 text-sm text-slate-500'>Configure uno o dos tramos. La tolerancia se comparte entre todas las entradas del día.</p>
<form method='POST' action='{{ route('schedules.store') }}' class='grid gap-4'>@csrf<label class='text-sm font-medium'>Nombre<input name='name' required placeholder='Horario discontinuo' class='mt-1 w-full rounded-xl border-slate-300'>
</label>
<fieldset class='rounded-xl border border-slate-200 p-4'>
<legend class='px-2 text-sm font-bold'>Turno mañana</legend>
<div class='grid grid-cols-2 gap-3'>
<label class='text-sm font-medium'>Entrada<input type='time' name='starts_at' value='08:00' required class='mt-1 w-full rounded-xl border-slate-300'>
</label>
<label class='text-sm font-medium'>Salida<input type='time' name='ends_at' value='12:00' required class='mt-1 w-full rounded-xl border-slate-300'>
</label>
</div>
</fieldset>
<label class='flex cursor-pointer items-center gap-3 rounded-xl bg-slate-50 p-3 text-sm font-bold'>
<input type='checkbox' data-afternoon-toggle data-target='afternoon-new' class='rounded border-slate-300 text-emerald-600 focus:ring-emerald-500'>
Habilitar turno tarde
</label>
<fieldset id='afternoon-new' class='rounded-xl border border-slate-200 p-4 opacity-50 transition-opacity'>
<legend class='px-2 text-sm font-bold'>Turno tarde (opcional)</legend>
<div class='grid grid-cols-2 gap-3'>
<label class='text-sm font-medium'>Entrada<input type='time' name='second_starts_at' value='15:00' disabled class='mt-1 w-full rounded-xl border-slate-300 disabled:cursor-not-allowed disabled:bg-slate-100'>
</label>
<label class='text-sm font-medium'>Salida<input type='time' name='second_ends_at' value='19:00' disabled class='mt-1 w-full rounded-xl border-slate-300 disabled:cursor-not-allowed disabled:bg-slate-100'>
</label>
</div>
</fieldset>
<label class='text-sm font-medium'>Tolerancia diaria acumulada<input type='number' name='grace_minutes' value='5' min='0' max='120' class='mt-1 w-full rounded-xl border-slate-300'>
<small class='mt-1 block text-slate-500'>Ejemplo: 3 minutos en la mañana + 4 en la tarde = 7 acumulados.</small>
</label>
<button class='rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white'>Crear horario</button>
</form>
</aside>
</div>
<script>
document.querySelectorAll('[data-afternoon-toggle]').forEach((toggle) => {
    const fields = document.getElementById(toggle.dataset.target);

    const synchronizeAfternoonFields = () => {
        fields.classList.toggle('opacity-50', !toggle.checked);
        fields.querySelectorAll('input').forEach((input) => {
            input.disabled = !toggle.checked;
        });
    };

    toggle.addEventListener('change', synchronizeAfternoonFields);
    synchronizeAfternoonFields();
});

const periodType = document.getElementById('period-type');
const endWrapper = document.getElementById('assignment-end-wrapper');
const endInput = document.getElementById('assignment-ends-on');

const synchronizePeriodFields = () => {
    const isCustom = periodType.value === 'custom';
    endWrapper.classList.toggle('hidden', !isCustom);
    endInput.disabled = !isCustom;
    endInput.required = isCustom;
};

periodType.addEventListener('change', synchronizePeriodFields);
synchronizePeriodFields();
</script>
@endsection
