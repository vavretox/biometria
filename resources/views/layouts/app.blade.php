<!doctype html><html lang='es'><head>
<meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'>
<meta name='csrf-token' content='{{ csrf_token() }}'><title>@yield('title', 'Control Biométrico')</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head><body class='min-h-screen bg-slate-100 text-slate-900'>
@auth
<div class='app-shell min-h-screen lg:grid lg:grid-cols-[17rem_1fr]'>
<button type='button' id='mobile-menu-toggle' aria-controls='app-sidebar' aria-expanded='false' class='mobile-menu-toggle lg:hidden'><svg aria-hidden='true' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><path d='M4 6h16M4 12h16M4 18h16'/></svg><span>Menú</span></button>
<aside id='app-sidebar' class='app-sidebar bg-slate-950 px-5 py-6 text-slate-300'>
<a href='{{ route('dashboard') }}' class='mb-8 flex items-start gap-3 text-white'>
<span class='grid size-11 shrink-0 place-items-center rounded-2xl bg-emerald-500 text-white'>
<svg aria-hidden='true' class='size-7' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'>
<path d='M4.5 7a9 9 0 0 1 15 0M2.5 12a9.5 9.5 0 0 1 .4-2.7M21.5 12a9.5 9.5 0 0 0-.4-2.7M6 16v-4a6 6 0 0 1 12 0v2c0 3 1 4 2 5M4 18a16 16 0 0 0 2-7M8 20a16 16 0 0 0 1-8 3 3 0 0 1 6 0v2c0 3 .6 5 2 7M12 12v3c0 3-.5 5-1.5 7M14 20l.5 2'/>
</svg>
</span>
<span class='min-w-0'><span class='block text-lg font-bold leading-tight'>Control Biométrico</span><span class='mt-2 block text-[11px] font-medium leading-relaxed text-slate-300'>Dirección Departamental de Recursos Humanos</span></span>
</a>
<p class='mb-4 px-4 text-[11px] font-bold uppercase tracking-widest text-slate-400'>Espacio de trabajo</p>
<nav aria-label='Navegación principal' class='app-navigation grid gap-2 text-sm font-medium'>
<a href='{{ route('dashboard') }}' @if(request()->routeIs('dashboard')) aria-current='page' @endif class='app-nav-link rounded-xl px-4 py-3'><svg aria-hidden='true' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'><path d='M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z'/></svg><span>Panel principal</span></a>
<a href='{{ route('employees.index') }}' @if(request()->routeIs('employees.*')) aria-current='page' @endif class='app-nav-link rounded-xl px-4 py-3'><svg aria-hidden='true' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'><path d='M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M16 3a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-3.87M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0'/></svg><span>Empleados</span></a>
<a href='{{ route('office-units.index') }}' @if(request()->routeIs('office-units.*')) aria-current='page' @endif class='app-nav-link rounded-xl px-4 py-3'><svg aria-hidden='true' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'><path d='M4 21V3h12v18M16 9h4v12M8 7h4M8 11h4M8 15h4M2 21h20'/></svg><span>Unidades de oficina</span></a>
<a href='{{ route('attendance.index') }}' @if(request()->routeIs('attendance.*')) aria-current='page' @endif class='app-nav-link rounded-xl px-4 py-3'><svg aria-hidden='true' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'><path d='M8 3v4M16 3v4M3 11h18M5 5h14a2 2 0 0 1 2 2v13H3V7a2 2 0 0 1 2-2M8 16l3 3 5-5'/></svg><span>Asistencias</span></a>
<a href='{{ route('schedules.index') }}' @if(request()->routeIs('schedules.*')) aria-current='page' @endif class='app-nav-link rounded-xl px-4 py-3'><svg aria-hidden='true' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'><path d='M12 8v4l3 2M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0'/></svg><span>Horarios</span></a>
<a href='{{ route('biometric.devices.index') }}' @if(request()->routeIs('biometric.*')) aria-current='page' @endif class='app-nav-link rounded-xl px-4 py-3'><svg aria-hidden='true' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'><path d='M4 4h16v13H4zM8 21h8M12 17v4M8 8h8M8 12h3'/></svg><span>Dispositivos biométricos</span></a>
<a href='{{ route('leaves.index') }}' @if(request()->routeIs('leaves.*')) aria-current='page' @endif class='app-nav-link rounded-xl px-4 py-3'><svg aria-hidden='true' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'><path d='M8 3h8v4H8zM8 5H5v16h14V5h-3M8 12h8M8 16h5'/></svg><span>Permisos y feriados</span></a>
<a href='{{ route('reports.index') }}' @if(request()->routeIs('reports.*')) aria-current='page' @endif class='app-nav-link rounded-xl px-4 py-3'><svg aria-hidden='true' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'><path d='M5 3h10l4 4v14H5zM14 3v5h5M8 17v-3M12 17v-6M16 17v-4'/></svg><span>Reportes</span></a>
@can('manage-users')
<a href='{{ route('users.index') }}' @if(request()->routeIs('users.*')) aria-current='page' @endif class='app-nav-link rounded-xl px-4 py-3'><svg aria-hidden='true' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'><path d='M12 3l8 4v5c0 5-8 9-8 9s-8-4-8-9V7zM9 12l2 2 4-4'/></svg><span>Usuarios del sistema</span></a>
@endcan
</nav><div class='mt-8 border-t border-slate-800 px-4 pt-5'><p class='text-xs font-semibold text-white'>{{ auth()->user()->name }}</p><p class='mt-1 text-xs text-slate-400'>Control de asistencia</p></div></aside>
<main class='app-main min-w-0 p-5 lg:p-9'>
<header class='app-header mb-7 flex flex-wrap items-center justify-between gap-4'><div><p class='mb-1 text-xs font-semibold uppercase tracking-widest text-slate-500'>Control Biométrico / @yield('title', 'Inicio')</p><h1 class='text-2xl font-bold tracking-tight'>@yield('heading', 'Sistema de asistencia')</h1></div><span class='system-status rounded-full bg-emerald-100 px-4 py-2 text-xs font-bold text-emerald-700'><span aria-hidden='true' class='size-2 rounded-full bg-emerald-500'></span>Sistema activo</span></header>
@if(session('success'))<div role='status' class='app-notice mb-5 rounded-xl bg-emerald-50 p-4 text-emerald-800'>{{ session('success') }}</div>@endif
@if($errors->any())<div role='alert' class='app-notice mb-5 rounded-xl bg-rose-50 p-4 text-rose-800'><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')</main></div>
@else @yield('content') @endauth
</body></html>
