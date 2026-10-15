@extends('layouts.app')
@section('title', 'Permisos y feriados')
@section('heading', 'Permisos y feriados')
@section('content')
<div class='grid gap-6 xl:grid-cols-[1fr_23rem]'>
<section class='grid gap-6'>
<div class='rounded-2xl bg-white p-6 shadow-sm'>
<div class='mb-5'>
<h2 class='text-lg font-bold'>Solicitudes registradas</h2>
<p class='text-sm text-slate-500'>Vacaciones, bajas médicas, asuntos personales y comisiones.</p>
</div>
<div class='overflow-x-auto'>
<table class='w-full text-left text-sm'>
<thead class='bg-slate-50 text-xs uppercase text-slate-500'>
<tr>
<th class='p-3'>Empleado</th>
<th class='p-3'>Tipo</th>
<th class='p-3'>Periodo</th>
<th class='p-3'>Estado</th>
<th class='p-3'>
</th>
</tr>
</thead>
<tbody class='divide-y'>@forelse($leaveRequests as $leave)<tr>
<td class='p-3 font-medium'>{{ $leave->employee->last_name }} {{ $leave->employee->first_name }}</td>
<td class='p-3'>{{ $leave->leaveType?->name ?? $leave->type }}</td>
<td class='p-3'>
<span class='block'>{{ $leave->starts_on->format('d/m/Y') }} — {{ $leave->ends_on->format('d/m/Y') }}</span>
<span class='text-xs font-medium text-slate-500'>
@if($leave->duration_type === 'hours'){{ mb_substr($leave->starts_at, 0, 5) }} — {{ mb_substr($leave->ends_at, 0, 5) }}
@elseif($leave->duration_type === 'half_day')Medio día · {{ $leave->half_day_period === 'morning' ? 'Mañana' : 'Tarde' }}
@else Día completo
@endif
</span>
</td>
<td class='p-3'>
<span class='rounded-full px-3 py-1 text-xs {{ $leave->status === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}'>{{ $leave->status === 'approved' ? 'Aprobado' : 'Pendiente' }}</span>
</td>
<td class='p-3 text-right'>@if($leave->status === 'pending')<form method='POST' action='{{ route('leaves.approve', $leave) }}'>@csrf @method('PUT')<button class='font-semibold text-emerald-700'>Aprobar</button>
</form>@endif</td>
</tr>@empty<tr>
<td colspan='5' class='p-10 text-center text-slate-500'>No hay permisos registrados.</td>
</tr>@endforelse</tbody>
</table>
</div>
<div class='mt-5'>{{ $leaveRequests->links() }}</div>
</div>
@if(auth()->user()->is_admin)
<div class='rounded-2xl bg-white p-6 shadow-sm'>
<h2 class='text-lg font-bold'>Tipos de permiso</h2>
<p class='mb-4 text-sm text-slate-500'>Pulsa Editar para cambiar el nombre o el estado de un tipo de permiso.</p>
<div class='max-h-[30rem] overflow-y-auto rounded-xl border border-slate-200'>
<div class='grid divide-y divide-slate-200 lg:grid-cols-2 lg:divide-y-0'>
@foreach($leaveTypes as $leaveType)
@php
    $isEditingLeaveType = (string) old('editing_leave_type') === (string) $leaveType->id;
@endphp
<div class='flex min-w-0 items-center gap-2 border-slate-200 px-3 py-2 lg:border-b lg:odd:border-r'>
<form method='POST' action='{{ route('leave-types.update', $leaveType) }}' class='flex min-w-0 flex-1 flex-wrap items-center gap-2'>@csrf @method('PUT')
<input type='hidden' name='editing_leave_type' value='{{ $leaveType->id }}'>
<span class='w-9 shrink-0 rounded-md px-1.5 py-1 text-center text-[11px] font-bold text-white' style='background-color: {{ $leaveType->color }}' title='Color y abreviatura asignados por el sistema'>{{ $leaveType->abbreviation }}</span>
<input name='name' value='{{ $isEditingLeaveType ? old('name', $leaveType->name) : $leaveType->name }}' required maxlength='100' @readonly(! $isEditingLeaveType) aria-label='Nombre del tipo de permiso' class='min-w-24 flex-1 rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm read-only:border-transparent read-only:bg-transparent focus:outline-2 focus:outline-blue-500'>
<select name='is_active' @disabled(! $isEditingLeaveType) aria-label='Estado del tipo de permiso' class='min-h-10 w-24 rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs disabled:border-transparent disabled:bg-transparent disabled:text-slate-600'><option value='1' @selected($isEditingLeaveType ? old('is_active', $leaveType->is_active) : $leaveType->is_active)>Activo</option><option value='0' @selected($isEditingLeaveType ? ! old('is_active', $leaveType->is_active) : ! $leaveType->is_active)>Inactivo</option></select>
<button type='button' data-leave-type-edit onclick='editLeaveType(this.form)' aria-label='Editar {{ $leaveType->name }}' @class(['min-h-10 rounded-lg border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50', 'hidden' => $isEditingLeaveType])>Editar</button>
<button type='submit' data-leave-type-save @class(['min-h-10 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700', 'hidden' => ! $isEditingLeaveType])>Guardar</button>
<button type='button' data-leave-type-cancel onclick='cancelLeaveTypeEdit(this.form)' @class(['min-h-10 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100', 'hidden' => ! $isEditingLeaveType])>Cancelar</button>
</form>
<form method='POST' action='{{ route('leave-types.destroy', $leaveType) }}'>@csrf @method('DELETE')<button class='px-1 py-1.5 text-xs font-semibold text-rose-600' title='Eliminar {{ $leaveType->name }}'>Eliminar</button></form>
</div>
@endforeach
</div>
</div>
<form method='POST' action='{{ route('leave-types.store') }}' class='mt-3 flex gap-2'>@csrf
<input name='name' value='{{ old('name') }}' required maxlength='100' placeholder='Nuevo tipo de permiso' class='min-w-0 flex-1 rounded-xl border-slate-300 py-2 text-sm'>
<button class='rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white'>Agregar</button></form>
</div>
@endif
<div class='rounded-2xl bg-white p-6 shadow-sm'>
<h2 class='mb-4 text-lg font-bold'>Calendario de feriados</h2>
<div class='grid gap-3 md:grid-cols-2'>@forelse($holidays as $holiday)<div class='flex items-center gap-4 rounded-xl border border-slate-200 p-4'>
<div class='rounded-xl bg-rose-50 px-3 py-2 text-center text-rose-700'>
<b class='block text-xl'>{{ $holiday->date->format('d') }}</b>
<span class='text-xs uppercase'>{{ $holiday->date->translatedFormat('M') }}</span>
</div>
<div>
<p class='font-semibold'>{{ $holiday->name }}</p>
<p class='text-xs text-slate-500'>{{ ucfirst($holiday->scope) }}{{ $holiday->is_recurring ? ' · Recurrente' : '' }}</p>
</div>
</div>@empty<p class='text-sm text-slate-500'>No hay feriados configurados.</p>@endforelse</div>
</div>
</section>
<aside class='grid content-start gap-6'>
<div class='rounded-2xl bg-white p-6 shadow-sm'>
<h2 class='mb-4 text-lg font-bold'>Agregar feriado</h2>
<form method='POST' action='{{ route('holidays.store') }}' class='grid gap-4'>@csrf<input name='name' required placeholder='Nombre del feriado' class='rounded-xl border-slate-300'>
<input type='date' name='date' required class='rounded-xl border-slate-300'>
<select name='scope' class='rounded-xl border-slate-300'>
<option value='national'>Nacional</option>
<option value='departmental'>Departamental</option>
<option value='institutional'>Institucional</option>
</select>
<label class='flex gap-2 text-sm'>
<input type='checkbox' name='is_recurring' value='1' class='rounded border-slate-300'> Repetir cada año</label>
<button class='rounded-xl bg-slate-900 px-5 py-3 text-white'>Guardar feriado</button>
</form>
</div>
</aside>
</div>
<script>
function setLeaveTypeEditing(form, isEditing) {
    form.elements.name.readOnly = !isEditing;
    form.elements.is_active.disabled = !isEditing;
    form.querySelector('[data-leave-type-edit]').classList.toggle('hidden', isEditing);
    form.querySelector('[data-leave-type-save]').classList.toggle('hidden', !isEditing);
    form.querySelector('[data-leave-type-cancel]').classList.toggle('hidden', !isEditing);
}
function editLeaveType(form) {
    setLeaveTypeEditing(form, true);
    form.elements.name.focus();
    form.elements.name.select();
}
function cancelLeaveTypeEdit(form) {
    form.reset();
    setLeaveTypeEditing(form, false);
}
</script>
@endsection
