@csrf
@isset($user) @method('PUT') @endisset
<div class="grid gap-5 md:grid-cols-2">
    <label class="grid gap-2 text-sm font-medium">Nombre
        <input class="rounded-xl border px-4 py-3" name="name" value="{{ old('name', $user->name ?? '') }}" required maxlength="255" autocomplete="name">
    </label>
    <label class="grid gap-2 text-sm font-medium">Correo electrónico
        <input class="rounded-xl border px-4 py-3" type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required maxlength="255" autocomplete="email">
    </label>
    <label class="grid gap-2 text-sm font-medium">Contraseña
        <input class="rounded-xl border px-4 py-3" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" @required(!isset($user))>
        <span class="text-xs text-slate-500">Mínimo 8 caracteres. @isset($user) Déjala vacía para conservar la contraseña actual. @endisset</span>
    </label>
    <label class="grid gap-2 text-sm font-medium">Confirmar contraseña
        <input class="rounded-xl border px-4 py-3" type="password" name="password_confirmation" autocomplete="new-password" @required(!isset($user))>
    </label>
    <label class="grid gap-2 text-sm font-medium">Rol
        <select class="rounded-xl border px-4 py-3" name="is_admin"><option value="0" @selected(!old('is_admin', $user->is_admin ?? false))>Técnico</option><option value="1" @selected(old('is_admin', $user->is_admin ?? false))>Administrador</option></select>
    </label>
    <label class="grid gap-2 text-sm font-medium">Estado
        <select class="rounded-xl border px-4 py-3" name="is_active"><option value="1" @selected(old('is_active', $user->is_active ?? true))>Activo</option><option value="0" @selected(!old('is_active', $user->is_active ?? true))>Inactivo</option></select>
    </label>
</div>
<p class="mt-5 text-sm text-slate-500">El rol Técnico puede consultar todos los módulos excepto Usuarios del sistema. Los administradores pueden gestionar usuarios. Las cuentas inactivas no pueden acceder al sistema.</p>
@isset($user)
    @if(auth()->user()->is($user))<p class="mt-3 text-sm text-slate-500">Tu propia cuenta debe permanecer activa y conservar el rol de administrador.</p>@endif
@endisset
<div class="mt-6 flex gap-3"><button class="rounded-xl bg-emerald-600 px-5 py-3 font-bold text-white">Guardar usuario</button><a class="rounded-xl border px-5 py-3" href="{{ route('users.index') }}">Cancelar</a></div>
