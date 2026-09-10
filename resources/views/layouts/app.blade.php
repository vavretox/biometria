<!doctype html><html lang='es'><head>
<meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'>
<meta name='csrf-token' content='{{ csrf_token() }}'><title>@yield('title', 'BioControl')</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head><body class='min-h-screen bg-slate-100 text-slate-900'>
@auth
<div class='min-h-screen lg:grid lg:grid-cols-[17rem_1fr]'>
<aside class='bg-slate-950 px-5 py-6 text-slate-300'>
<a href='{{ route('dashboard') }}' class='mb-8 flex items-center gap-3 text-xl font-bold text-white'><span class='grid size-11 place-items-center rounded-2xl bg-emerald-500'>◉</span>BioControl</a>
<nav class='grid gap-2 text-sm font-medium'>
<a href='{{ route('dashboard') }}' class='rounded-xl px-4 py-3 hover:bg-slate-800'>Panel principal</a>
<a href='{{ route('employees.index') }}' class='rounded-xl px-4 py-3 hover:bg-slate-800'>Empleados</a>
<a href='{{ route('office-units.index') }}' class='rounded-xl px-4 py-3 hover:bg-slate-800'>Unidades de oficina</a>
<a href='{{ route('attendance.index') }}' class='rounded-xl px-4 py-3 hover:bg-slate-800'>Asistencias</a>
<a href='{{ route('schedules.index') }}' class='rounded-xl px-4 py-3 hover:bg-slate-800'>Horarios</a>
<a href='{{ route('biometric.devices.index') }}' class='rounded-xl px-4 py-3 hover:bg-slate-800'>Dispositivos biométricos</a>
<a href='{{ route('leaves.index') }}' class='rounded-xl px-4 py-3 hover:bg-slate-800'>Permisos y feriados</a>
<a href='{{ route('reports.index') }}' class='rounded-xl px-4 py-3 hover:bg-slate-800'>Reportes</a>
</nav></aside>
<main class='min-w-0 p-5 lg:p-9'>
<header class='mb-7 flex items-center justify-between'><h1 class='text-2xl font-bold'>@yield('heading', 'Sistema de asistencia')</h1><span class='rounded-full bg-emerald-100 px-4 py-2 text-xs font-bold text-emerald-700'>Sistema activo</span></header>
@if(session('success'))<div class='mb-5 rounded-xl bg-emerald-50 p-4 text-emerald-800'>{{ session('success') }}</div>@endif
@if($errors->any())<div class='mb-5 rounded-xl bg-rose-50 p-4 text-rose-800'><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')</main></div>
@else @yield('content') @endauth
</body></html>
