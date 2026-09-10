@extends('layouts.app')
@section('title', 'Iniciar sesión')
@section('content')
<main class='grid min-h-screen place-items-center bg-slate-950 p-6'><div class='w-full max-w-md rounded-3xl bg-white p-8 shadow-2xl'>
<div class='mb-8 text-center'><div class='mx-auto mb-4 grid size-16 place-items-center rounded-2xl bg-emerald-500 text-3xl text-white'>◉</div><h1 class='text-2xl font-bold'>Sistema de asistencia</h1><p class='mt-2 text-sm text-slate-500'>Acceso administrativo</p></div>
<form method='POST' action='{{ route('login.store') }}' class='grid gap-5'>@csrf
<label class='grid gap-2 text-sm font-semibold'>Correo<input class='rounded-xl border border-slate-300 px-4 py-3' name='email' type='email' value='{{ old('email') }}' required autofocus></label>
<label class='grid gap-2 text-sm font-semibold'>Contraseña<input class='rounded-xl border border-slate-300 px-4 py-3' name='password' type='password' required></label>
<label class='flex gap-2 text-sm'><input name='remember' type='checkbox' value='1'> Recordarme</label>
<button class='rounded-xl bg-emerald-500 px-5 py-3 font-bold text-white'>Iniciar sesión</button>
</form></div></main>
@endsection
