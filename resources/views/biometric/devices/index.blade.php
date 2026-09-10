@extends('layouts.app')
@section('title', 'Dispositivos biométricos')
@section('heading', 'Dispositivos biométricos')
@section('content')
<div class='grid gap-6'>
    <section class='grid gap-4 md:grid-cols-3'>
        <article class='rounded-2xl bg-white p-5 shadow-sm'><p class='text-sm text-slate-500'>Dispositivos registrados</p><p class='mt-2 text-3xl font-bold'>{{ $devices->total() }}</p></article>
        <article class='rounded-2xl bg-white p-5 shadow-sm'><p class='text-sm text-slate-500'>Conectados en esta página</p><p class='mt-2 text-3xl font-bold text-emerald-600'>{{ $devices->getCollection()->where('connection_status', 'connected')->count() }}</p></article>
        <article class='rounded-2xl bg-white p-5 shadow-sm'><p class='text-sm text-slate-500'>Sincronización automática</p><p class='mt-2 text-3xl font-bold text-blue-600'>{{ $devices->getCollection()->where('auto_sync', true)->count() }}</p></article>
    </section>

    <section class='rounded-2xl bg-white p-6 shadow-sm'>
        <div class='flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between'>
            <div><h2 class='text-lg font-bold'>Terminales configuradas</h2><p class='mt-1 text-sm text-slate-500'>Conecta, consulta y sincroniza los relojes biométricos de la institución.</p></div>
            <div class='flex flex-wrap gap-2'>
                <button type='button' onclick='togglePanel(&#39;deviceForm&#39;)' class='rounded-xl bg-emerald-500 px-5 py-3 text-sm font-bold text-white'>+ Agregar dispositivo</button>
                <button type='button' onclick='togglePanel(&#39;bulkForm&#39;)' class='rounded-xl bg-slate-800 px-5 py-3 text-sm font-bold text-white'>Importación masiva</button>
            </div>
        </div>
    </section>

    <section id='deviceForm' class='hidden rounded-2xl bg-white p-6 shadow-sm'>
        <div class='mb-6'><h2 class='text-lg font-bold'>Registrar dispositivo</h2><p class='text-sm text-slate-500'>Los campos de red son obligatorios. Las credenciales solo aplican a equipos con API.</p></div>
        <form action='{{ route('biometric.devices.store') }}' method='POST' class='grid gap-5'>@csrf<input type='hidden' name='connection_type' value='TCP/IP'>
            <div class='grid gap-5 md:grid-cols-2 xl:grid-cols-3'>
                <label class='grid gap-2 text-sm font-semibold'>Nombre *<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='device_name' value='{{ old('device_name') }}' required></label>
                <label class='grid gap-2 text-sm font-semibold'>Dirección IP *<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='device_ip' value='{{ old('device_ip') }}' placeholder='192.168.1.100' required></label>
                <label class='grid gap-2 text-sm font-semibold'>Puerto *<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='device_port' type='number' value='{{ old('device_port', 4370) }}' required><span class='text-xs font-normal text-slate-500'>ZKTeco utiliza 4370 por defecto.</span></label>
                <label class='grid gap-2 text-sm font-semibold'>Marca *<select class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='manufacturer' required>@foreach(['ZKSoftware','Suprema','HID','Anviz','FingerTec','Generic','Other'] as $manufacturer)<option value='{{ $manufacturer }}' @selected(old('manufacturer') === $manufacturer)>{{ $manufacturer }}</option>@endforeach</select></label>
                <label class='grid gap-2 text-sm font-semibold'>Modelo<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='device_model' value='{{ old('device_model') }}'></label>
                <label class='grid gap-2 text-sm font-semibold'>Protocolo *<select id='protocolSelector' class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='protocol' required><option value='ZKProtocol' @selected(old('protocol', 'ZKProtocol') === 'ZKProtocol')>ZK directo por red (puerto 4370)</option><option value='HTTPAPI' @selected(old('protocol') === 'HTTPAPI')>API HTTP</option></select></label>
                <label class='grid gap-2 text-sm font-semibold'>Ubicación<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='location' value='{{ old('location') }}' placeholder='Oficina central'></label>
                <label class='grid gap-2 text-sm font-semibold'>Zona horaria<select class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='timezone'><option value='America/La_Paz'>Bolivia (UTC-4)</option><option value='America/Lima'>Perú (UTC-5)</option><option value='America/Santiago'>Chile</option></select></label>
                <label class='grid gap-2 text-sm font-semibold'>Timeout (segundos)<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='connection_timeout' type='number' value='{{ old('connection_timeout', 30) }}'></label>
                <label class='grid gap-2 text-sm font-semibold'>Sincronizar cada (minutos)<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='sync_interval' type='number' value='{{ old('sync_interval', 60) }}'></label>
                <div id='apiCredentials' class='hidden contents'><label class='grid gap-2 text-sm font-semibold'>API Key<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='api_key'></label><label class='grid gap-2 text-sm font-semibold'>API Secret<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='api_secret' type='password'></label></div>
            </div>
            <label class='flex items-center gap-2 text-sm font-semibold'><input name='auto_sync' type='checkbox' value='1' @checked(old('auto_sync', true))> Sincronización automática</label>
            <div class='flex gap-3'><button class='rounded-xl bg-emerald-500 px-6 py-3 font-bold text-white'>Registrar dispositivo</button><button type='button' onclick='togglePanel(&#39;deviceForm&#39;)' class='rounded-xl bg-slate-200 px-6 py-3 font-bold'>Cancelar</button></div>
        </form>
    </section>

    <section id='bulkForm' class='hidden rounded-2xl bg-white p-6 shadow-sm'>
        <h2 class='text-lg font-bold'>Importación masiva</h2><p class='mb-4 mt-1 text-sm text-slate-500'>Pega un arreglo JSON con device_name, device_ip, manufacturer y device_model.</p>
        <textarea id='bulkData' class='min-h-56 w-full rounded-xl border border-slate-300 p-4 font-mono text-sm' placeholder='Pega aquí el arreglo JSON'></textarea>
        <button type='button' onclick='bulkImport()' class='mt-4 rounded-xl bg-slate-800 px-6 py-3 font-bold text-white'>Procesar importación</button>
    </section>

    <section class='grid gap-5 xl:grid-cols-2'>
        @forelse($devices as $device)
        <article class='rounded-2xl bg-white p-6 shadow-sm'>
            <div class='flex items-start justify-between gap-4'><div><h3 class='text-lg font-bold'>{{ $device->device_name }}</h3><p class='mt-1 text-sm text-slate-500'>{{ $device->manufacturer }} · {{ $device->device_model ?: 'Modelo no indicado' }}</p></div><span class='rounded-full px-3 py-1 text-xs font-bold {{ $device->isConnected() ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}'>{{ $device->status_text }}</span></div>
            <dl class='mt-6 grid grid-cols-2 gap-4 text-sm'><div><dt class='text-slate-500'>Dirección de red</dt><dd class='font-semibold'>{{ $device->device_ip }}:{{ $device->device_port }}</dd></div><div><dt class='text-slate-500'>Ubicación</dt><dd class='font-semibold'>{{ $device->location ?: 'Sin definir' }}</dd></div><div><dt class='text-slate-500'>Protocolo</dt><dd class='font-semibold'>{{ $device->protocol }}</dd></div><div><dt class='text-slate-500'>Huellas almacenadas</dt><dd id='fingerprint-count-{{ $device->id }}' class='font-semibold'>{{ $device->biometric_templates_count }}</dd></div><div class='col-span-2'><dt class='text-slate-500'>Última sincronización</dt><dd class='font-semibold'>{{ $device->last_sync?->format('d/m/Y H:i') ?: 'Nunca' }}</dd></div></dl>
            <div class='mt-6 grid gap-3'>
                <section class='rounded-xl border border-violet-100 bg-violet-50 p-4'>
                    <p class='text-xs font-bold uppercase tracking-wide text-violet-700'>Biométrico → sistema</p>
                    <p class='mt-1 text-xs text-slate-600'>Trae los usuarios, sus plantillas de huella y las marcaciones registradas en el reloj.</p>
                    <div class='mt-3 grid gap-2 sm:grid-cols-2'>
                        <button onclick='deviceAction({{ $device->id }}, &#39;pull-biometrics&#39;)' class='rounded-xl bg-violet-600 px-4 py-3 text-sm font-bold text-white'>Descargar usuarios y huellas</button>
                        <button onclick='deviceAction({{ $device->id }}, &#39;sync-attendance&#39;)' class='rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white'>Importar marcaciones</button>
                    </div>
                </section>
                <section class='rounded-xl border border-blue-100 bg-blue-50 p-4'>
                    <p class='text-xs font-bold uppercase tracking-wide text-blue-700'>Sistema → biométrico</p>
                    <p class='mt-1 text-xs text-slate-600'>Envía los empleados. Si usan código de acceso, también envía ese código como contraseña del reloj.</p>
                    <div class='mt-3 grid gap-2 sm:grid-cols-2'>
                        <button onclick='deviceAction({{ $device->id }}, &#39;sync-employees&#39;)' class='rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white'>Enviar empleados y códigos</button>
                        <button onclick='deviceAction({{ $device->id }}, &#39;sync-employees?include_fingerprints=1&#39;)' @disabled($device->biometric_templates_count === 0) class='rounded-xl px-4 py-3 text-sm font-bold {{ $device->biometric_templates_count > 0 ? 'bg-indigo-600 text-white' : 'cursor-not-allowed bg-slate-200 text-slate-400' }}'>Enviar huellas guardadas</button>
                    </div>
                </section>
                <div id='sync-result-{{ $device->id }}' class='hidden rounded-xl border px-4 py-3 text-sm' role='status'></div>
                <div class='grid gap-2 sm:grid-cols-2'><button onclick='deviceAction({{ $device->id }}, &#39;test&#39;)' class='rounded-xl bg-white px-4 py-3 text-sm font-bold ring-1 ring-slate-200'>Probar conexión</button><button onclick='deviceInfo({{ $device->id }})' class='rounded-xl bg-white px-4 py-3 text-sm font-bold text-violet-700 ring-1 ring-slate-200'>Información técnica</button></div>
            </div>
        </article>
        @empty
        <article class='col-span-full rounded-2xl border-2 border-dashed border-slate-300 bg-white p-12 text-center'><p class='text-lg font-bold'>No hay dispositivos registrados</p><p class='mt-2 text-sm text-slate-500'>Agrega el primer reloj biométrico para comenzar a sincronizar marcaciones.</p></article>
        @endforelse
    </section>
    <div>{{ $devices->links() }}</div>
</div>

<script>
const csrfToken = document.querySelector('meta[name=csrf-token]').content;
const protocolSelector = document.getElementById('protocolSelector');
const apiCredentials = document.getElementById('apiCredentials');
function updateProtocolFields() { apiCredentials?.classList.toggle('hidden', protocolSelector?.value !== 'HTTPAPI'); }
protocolSelector?.addEventListener('change', updateProtocolFields);
updateProtocolFields();
function togglePanel(id) { document.getElementById(id).classList.toggle('hidden'); }
async function request(url, options = {}) {
    const response = await fetch(url, { ...options, headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', ...(options.headers || {}) } });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'No se pudo completar la operación.');
    return data;
}
async function deviceAction(id, action) {
    const result = document.getElementById('sync-result-' + id);
    result.className = 'rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800';
    result.textContent = 'Procesando operación con el biométrico…';
    try {
        const data = await request('/biometric/devices/' + id + '/' + action, { method: 'POST' });
        result.className = 'rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800';
        result.textContent = data.message;
        if (data.data?.stored_fingerprints !== undefined) document.getElementById('fingerprint-count-' + id).textContent = data.data.stored_fingerprints;
        if (action === 'test' && data.success) setTimeout(() => location.reload(), 800);
    } catch (error) {
        result.className = 'rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800';
        result.textContent = error.message;
    }
}
async function deviceInfo(id) {
    try { const data = await request('/biometric/devices/' + id + '/info'); alert(Object.entries(data.data).map(([key,value]) => key + ': ' + value).join('\n')); } catch (error) { alert(error.message); }
}
async function bulkImport() {
    try { const devices = JSON.parse(document.getElementById('bulkData').value); const data = await request('/biometric/devices/bulk-import', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ devices }) }); alert(data.message); location.reload(); } catch (error) { alert(error.message); }
}
</script>
@endsection
