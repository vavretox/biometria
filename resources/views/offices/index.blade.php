@extends('layouts.app')
@section('title', 'Unidades de oficina')
@section('heading', 'Unidades de oficina')
@section('content')
<div class='grid gap-6 xl:grid-cols-[1fr_22rem]'>
<section class='rounded-2xl bg-white shadow-sm'>
<div class='border-b border-slate-100 p-6'><h2 class='text-lg font-bold'>Unidades registradas</h2><p class='mt-1 text-sm text-slate-500'>Una unidad puede representar cualquier área, dirección, jefatura o dependencia, sin importar su ubicación física.</p></div>
<div class='grid gap-4 p-6 md:grid-cols-2'>
@forelse($workUnits as $unit)
<article class='rounded-xl border border-slate-200 p-5'>
<div class='flex items-start justify-between gap-4'><div><p class='font-bold'>{{ $unit->name }}</p><p class='mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500'>{{ $unit->code }}</p></div><span class='rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700'>{{ $unit->employees_count }} empleados</span></div>
@if($unit->description)<p class='mt-4 text-sm text-slate-600'>{{ $unit->description }}</p>@endif
</article>
@empty<div class='col-span-full rounded-xl border-2 border-dashed border-slate-300 p-10 text-center'><p class='font-bold'>No hay unidades registradas</p><p class='mt-2 text-sm text-slate-500'>Crea la primera unidad para poder asignar empleados.</p></div>@endforelse
</div></section>
<aside>
<form method='POST' action='{{ route('work-units.store') }}' class='grid gap-4 rounded-2xl bg-white p-6 shadow-sm'>@csrf
<div><h2 class='font-bold'>Nueva unidad de oficina</h2><p class='mt-1 text-sm text-slate-500'>Registra una unidad independiente.</p></div>
<label class='grid gap-2 text-sm font-semibold'>Nombre *<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='name' value='{{ old('name') }}' required></label>
<label class='grid gap-2 text-sm font-semibold'>Código *<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='code' value='{{ old('code') }}' required></label>
<label class='grid gap-2 text-sm font-semibold'>Descripción<textarea class='min-h-28 rounded-xl border border-slate-300 px-4 py-3 font-normal' name='description'>{{ old('description') }}</textarea></label>
<button class='rounded-xl bg-emerald-500 px-5 py-3 font-bold text-white'>Crear unidad</button>
</form></aside></div>
@endsection
