@extends('layouts.app')
@section('title', 'Editar dispositivo')
@section('heading', 'Editar dispositivo')
@section('content')
<form action='{{ route('biometric.devices.update', $device) }}' method='POST' class='grid gap-6'>
    @csrf
    @method('PUT')
    <h2 class='break-words text-lg font-bold'>{{ $device->device_name }}</h2>
    <div class='grid gap-5 md:grid-cols-2 xl:grid-cols-3'>
        @foreach(['device_name' => ['Nombre', 'text'], 'device_ip' => ['Dirección IP', 'text'], 'device_port' => ['Puerto', 'number'], 'manufacturer' => ['Marca', 'text'], 'device_model' => ['Modelo', 'text'], 'location' => ['Ubicación', 'text'], 'connection_timeout' => ['Timeout (segundos)', 'number'], 'sync_interval' => ['Sincronizar cada (minutos)', 'number']] as $field => [$label, $type])
        <label class='grid min-w-0 gap-2 text-sm font-semibold'>{{ $label }}
            <input name='{{ $field }}' type='{{ $type }}' value='{{ old($field, $device->$field) }}' class='w-full min-w-0 rounded-lg border border-slate-300 bg-white px-4 py-3 font-normal' @required(!in_array($field, ['device_model', 'location'])) @if($field === 'device_port') min='1' max='65535' @elseif($field === 'connection_timeout') min='5' max='300' @elseif($field === 'sync_interval') min='5' max='1440' @endif>
        </label>
        @endforeach
        <label class='grid min-w-0 gap-2 text-sm font-semibold'>Zona horaria
            <select name='timezone' class='w-full min-w-0 rounded-lg border border-slate-300 bg-white px-4 py-3 font-normal' required>
                @foreach(array_unique([$device->timezone, 'America/La_Paz', 'America/Lima', 'America/Santiago']) as $timezone)
                <option value='{{ $timezone }}' @selected(old('timezone', $device->timezone) === $timezone)>{{ $timezone }}</option>
                @endforeach
            </select>
        </label>
        <label class='grid min-w-0 gap-2 text-sm font-semibold'>Protocolo
            <select id='editProtocol' name='protocol' class='w-full min-w-0 rounded-lg border border-slate-300 bg-white px-4 py-3 font-normal' required>
                @foreach(['ZKProtocol' => 'ZK directo por red', 'HTTPAPI' => 'API HTTP', 'Serial' => 'Serial', 'WebSocket' => 'WebSocket'] as $value => $label)
                <option value='{{ $value }}' @selected(old('protocol', $device->protocol) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </div>
    <div id='editCredentials' class='grid gap-5 md:grid-cols-2'>
        @foreach(['api_key' => 'API Key', 'api_secret' => 'API Secret'] as $field => $label)
        <label class='grid min-w-0 gap-2 text-sm font-semibold'>{{ $label }}
            <input type='password' name='{{ $field }}' autocomplete='new-password' placeholder='Sin cambios' class='w-full min-w-0 rounded-lg border border-slate-300 bg-white px-4 py-3 font-normal'>
        </label>
        @endforeach
    </div>
    <div class='flex flex-wrap gap-5'>
        @foreach(['auto_sync' => 'Sincronización automática', 'is_active' => 'Dispositivo activo'] as $field => $label)
        <input type='hidden' name='{{ $field }}' value='0'>
        <label class='flex items-center gap-2 text-sm font-semibold'><input type='checkbox' name='{{ $field }}' value='1' @checked(old($field, $device->$field))> {{ $label }}</label>
        @endforeach
    </div>
    <div class='flex flex-wrap gap-3'>
        <button type='submit' class='rounded-lg bg-emerald-600 px-6 py-3 font-bold text-white hover:bg-emerald-700'>Guardar cambios</button>
        <a href='{{ route('biometric.devices.index') }}' class='rounded-lg bg-white px-6 py-3 font-bold ring-1 ring-slate-300'>Cancelar</a>
    </div>
</form>
<script>
const editProtocol = document.getElementById('editProtocol');
function updateEditCredentials() {
    const credentials = document.getElementById('editCredentials');
    credentials.classList.toggle('hidden', editProtocol.value !== 'HTTPAPI');
    credentials.querySelectorAll('input').forEach(input => input.disabled = editProtocol.value !== 'HTTPAPI');
}
editProtocol.addEventListener('change', updateEditCredentials);
updateEditCredentials();
</script>
@endsection
