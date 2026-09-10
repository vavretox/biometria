@extends('layouts.app')
@section('title', 'Panel principal')
@section('heading', 'Resumen de hoy')
@section('content')
<div class='grid gap-5 sm:grid-cols-2 xl:grid-cols-4'>
@foreach([['Empleados activos',$employeeCount],['Presentes hoy',$presentCount],['Llegadas tarde',$lateCount],['Dispositivos',$deviceCount]] as [$label,$value])
<article class='rounded-2xl bg-white p-6 shadow-sm'><span class='mb-4 block size-3 rounded-full bg-emerald-500'></span><p class='text-sm text-slate-500'>{{ $label }}</p><p class='mt-1 text-4xl font-bold'>{{ $value }}</p></article>
@endforeach</div>
<section class='mt-7 rounded-2xl bg-white p-6 shadow-sm'><h2 class='mb-5 font-bold'>Marcaciones recientes</h2>
<div class='grid gap-3'>@forelse($recentAttendances as $attendance)
<div class='flex justify-between border-b border-slate-100 pb-3'><span class='font-semibold'>{{ $attendance->employee->full_name }}</span><span class='text-slate-500'>{{ $attendance->checked_at->format('d/m/Y H:i') }}</span></div>
@empty<p class='py-8 text-center text-slate-500'>Todavía no hay marcaciones.</p>@endforelse</div></section>
@endsection
