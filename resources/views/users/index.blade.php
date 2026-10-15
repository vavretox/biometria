@extends('layouts.app')
@section('title', 'Usuarios del sistema')
@section('heading', 'Usuarios del sistema')
@section('content')
<p class="mb-5 text-sm text-slate-500">Administra las cuentas que pueden acceder a BioControl.</p>
<div class="mb-5 flex flex-wrap justify-between gap-3">
    <form class="flex flex-wrap gap-2" method="GET" action="{{ route('users.index') }}">
        <input aria-label="Buscar por nombre o correo" class="rounded-xl border px-4 py-3" name="search" value="{{ request('search') }}" placeholder="Nombre o correo electrónico">
        <button class="rounded-xl bg-slate-800 px-5 py-3 text-white">Buscar</button>
        <a class="px-4 py-3 text-slate-600" href="{{ route('users.index') }}">Limpiar</a>
    </form>
    <a class="rounded-xl bg-emerald-600 px-5 py-3 font-bold text-white" href="{{ route('users.create') }}">+ Nuevo usuario</a>
</div>
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
<table class="w-full text-left text-sm">
    <thead class="bg-slate-50 text-slate-500"><tr><th class="p-4">Nombre</th><th class="p-4">Correo electrónico</th><th class="p-4">Rol</th><th class="p-4">Estado</th><th class="p-4">Último acceso</th><th class="p-4">Acciones</th></tr></thead>
    <tbody>
    @forelse($users as $user)
        <tr class="border-t">
            <td class="p-4 font-bold">{{ $user->name }}</td><td class="p-4">{{ $user->email }}</td>
            <td class="p-4">{{ $user->is_admin ? 'Administrador' : 'Técnico' }}</td>
            <td class="p-4"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $user->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $user->is_active ? 'Activo' : 'Inactivo' }}</span></td>
            <td class="p-4">{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Nunca' }}</td>
            <td class="p-4"><a class="font-bold text-emerald-600" href="{{ route('users.edit', $user) }}">Editar</a></td>
        </tr>
    @empty
        <tr><td class="p-10 text-center text-slate-500" colspan="6">No se encontraron usuarios.</td></tr>
    @endforelse
    </tbody>
</table>
<div class="p-4">{{ $users->links() }}</div>
</div>
@endsection
