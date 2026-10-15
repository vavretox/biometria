@extends('layouts.app')
@section('title', 'Dispositivos biométricos')
@section('heading', 'Dispositivos biométricos')
@section('content')
<div class='grid gap-6'>
    <section class='grid gap-4 md:grid-cols-3'>
        <article class='rounded-2xl bg-white p-5 shadow-sm'><p class='text-sm text-slate-500'>Dispositivos registrados</p><p class='mt-2 text-3xl font-bold'>{{ $devices->total() }}</p></article>
        <article id='verified-count' class='rounded-2xl bg-white p-5 shadow-sm'><p class='text-sm text-slate-500'>Conexión verificada en esta página</p><p class='mt-2 text-3xl font-bold text-emerald-600'>{{ $devices->getCollection()->filter(fn ($device) => $device->isConnected())->count() }}</p></article>
        <article class='rounded-2xl bg-white p-5 shadow-sm'><p class='text-sm text-slate-500'>Sincronización automática</p><p class='mt-2 text-3xl font-bold text-blue-600'>{{ $devices->getCollection()->where('auto_sync', true)->count() }}</p></article>
    </section>

    <section id='connection-alerts' class='rounded-2xl bg-white p-6 shadow-sm' aria-live='polite'>
        <div class='flex flex-wrap items-center justify-between gap-3'>
            <div><h2 class='text-lg font-bold'>Supervisión de conexión</h2><p class='mt-1 text-sm text-slate-500'>Verificación automática cada 30 minutos. Cada prueba de conexión sincroniza también la hora de Bolivia (UTC−4). Los avisos se actualizan cada minuto mientras este módulo esté abierto.</p></div>
            <a href='{{ route('biometric.devices.connection-history') }}' class='rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-blue-700'>Historial de conexión</a>
        </div>
        <p id='monitor-refresh-error' class='mt-3 hidden text-sm text-red-700' role='alert'>No se pudo actualizar la supervisión. Los estados mostrados pueden estar desactualizados.</p>
        <div class='mt-4 grid gap-3'>
            @forelse($connectionAlerts as $alertDevice)
                <div class='rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800' role='alert'>
                    <p class='font-bold'>{{ $alertDevice->device_name }} · {{ $alertDevice->status_text }}</p>
                    <p class='mt-1'>{{ $alertDevice->connection_error ?: ($alertDevice->isConnectionCheckOverdue() ? 'No hay una verificación reciente. Compruebe que el monitor automático esté funcionando.' : 'No se ha confirmado la conexión con este biométrico.') }}</p>
                    <p class='mt-1 text-xs'>Última verificación: {{ $alertDevice->last_connection_test?->format('d/m/Y H:i:s') ?: 'Nunca' }}</p>
                    <a href='{{ route('biometric.devices.connection-history', ['device_id' => $alertDevice->id]) }}' class='mt-2 inline-flex font-semibold underline'>Ver historial del dispositivo</a>
                </div>
            @empty
                <p class='rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800'>Sin alertas de conexión en los biométricos activos.</p>
            @endforelse
        </div>
    </section>

    <section class='rounded-2xl bg-white p-6 shadow-sm'>
        <div class='flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between'>
            <div><h2 class='text-lg font-bold'>Terminales configuradas</h2><p class='mt-1 text-sm text-slate-500'>Conecta, consulta y sincroniza los relojes biométricos de la institución.</p></div>
            <div class='flex flex-wrap gap-2'>
                <button type='button' onclick='togglePanel(&#39;deviceForm&#39;)' class='rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white'>+ Agregar dispositivo</button>
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
            <div class='flex gap-3'><button class='rounded-xl bg-emerald-600 px-6 py-3 font-bold text-white'>Registrar dispositivo</button><button type='button' onclick='togglePanel(&#39;deviceForm&#39;)' class='rounded-xl bg-slate-200 px-6 py-3 font-bold'>Cancelar</button></div>
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
            <a href='{{ route('biometric.devices.edit', $device) }}' class='mb-4 inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50' aria-label='Editar {{ $device->device_name }}'>Editar</a>
            <div class='flex items-start justify-between gap-4'><div><h3 class='text-lg font-bold'>{{ $device->device_name }}</h3><p class='mt-1 text-sm text-slate-500'>{{ $device->manufacturer }} · {{ $device->device_model ?: 'Modelo no indicado' }}</p></div><span id='device-status-{{ $device->id }}' class='rounded-full px-3 py-1 text-xs font-bold {{ $device->isConnected() ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}'>{{ $device->status_text }}</span></div>
            <div id='last-check-{{ $device->id }}' class='mt-4 text-sm text-slate-500'>Última verificación: {{ $device->last_connection_test?->format('d/m/Y H:i:s') ?: 'Nunca' }}@if($device->connection_error)<p class='mt-1 text-red-700'>{{ $device->connection_error }}</p>@endif</div>
            <a href='{{ route('biometric.devices.connection-history', ['device_id' => $device->id]) }}' class='mt-2 inline-flex text-sm font-semibold text-blue-700 underline'>Ver historial de conexión</a>
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
                @if(auth()->user()?->is_admin)
                <section class='rounded-xl border border-blue-100 bg-blue-50 p-4'>
                    <p class='text-xs font-bold uppercase tracking-wide text-blue-700'>Sistema → biométrico</p>
                    <p class='mt-1 text-xs text-slate-600'>Selecciona empleados por nombre o unidad. Puedes volver a enviarlos para actualizar sus datos y códigos.</p>
                    <div class='mt-3 grid gap-2 sm:grid-cols-2'>
                        <button onclick='openEmployeeSelection({{ $device->id }}, false)' class='rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white'>Enviar empleados y códigos</button>
                        <button onclick='openEmployeeSelection({{ $device->id }}, true)' @disabled($device->biometric_templates_count === 0) class='rounded-xl px-4 py-3 text-sm font-bold {{ $device->biometric_templates_count > 0 ? 'bg-indigo-600 text-white' : 'cursor-not-allowed bg-slate-200 text-slate-400' }}'>Enviar huellas guardadas</button>
                    </div>
                </section>
                @endif
                @if(auth()->user()?->is_admin && $device->protocol === 'ZKProtocol')
                <button type='button' onclick='openDeviceUsers({{ $device->id }})' class='rounded-xl border border-rose-200 bg-white px-4 py-3 text-sm font-semibold text-rose-700 hover:bg-rose-50'>Gestionar usuarios del biométrico</button>
                @endif
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

@if(auth()->user()?->is_admin)
<dialog id='employee-selection' aria-labelledby='employee-selection-title' class='m-auto w-[calc(100%_-_2rem)] max-w-2xl rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-slate-950/50'>
    <form id='employee-selection-form' class='grid gap-4 p-5 sm:p-6' onsubmit='sendSelectedEmployees(event)'>
        <div class='flex items-start justify-between gap-4'><div><h2 id='employee-selection-title' class='text-lg font-bold'>Enviar o actualizar empleados</h2><p class='mt-1 text-sm text-slate-500'>Selecciona a quién enviar. Los demás empleados del reloj se conservan.</p></div><button type='button' onclick='closeEmployeeSelection()' class='rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100'>Cerrar</button></div>
        <div class='grid gap-3 sm:grid-cols-2'>
            <label class='grid gap-1 text-sm font-medium'>Buscar por nombre o código<input id='sync-employee-search' type='search' oninput='filterSyncEmployees()' class='min-h-11 min-w-0 rounded-xl border border-slate-300 px-3 py-2' placeholder='Nombre del empleado'></label>
            <label class='grid gap-1 text-sm font-medium'>Unidad<select id='sync-employee-unit' onchange='filterSyncEmployees()' class='min-h-11 min-w-0 rounded-xl border border-slate-300 px-3 py-2'><option value=''>Todas las unidades</option></select></label>
        </div>
        <div class='flex flex-wrap items-center justify-between gap-2 text-xs'><span id='sync-employee-count' class='font-semibold text-slate-600' aria-live='polite'>0 seleccionados</span><div class='flex gap-2'><button type='button' onclick='selectFilteredEmployees()' class='rounded-lg border border-blue-200 px-3 py-2 font-semibold text-blue-700'>Seleccionar resultados</button><button type='button' onclick='clearSelectedEmployees()' class='rounded-lg px-3 py-2 font-semibold text-slate-600'>Limpiar selección</button></div></div>
        <div id='sync-employee-list' class='max-h-80 overflow-y-auto rounded-xl border border-slate-200'></div>
        <p id='sync-employee-result' class='text-sm text-slate-600' role='status'></p>
        <div class='flex justify-end gap-3 border-t border-slate-200 pt-4'><button type='button' onclick='closeEmployeeSelection()' class='rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold'>Cancelar</button><button id='sync-employee-submit' type='submit' disabled class='rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500'>Enviar seleccionados</button></div>
    </form>
</dialog>
@endif
@if(auth()->user()?->is_admin)
<dialog id='device-users-dialog' aria-labelledby='device-users-title' class='m-auto w-[calc(100%_-_2rem)] max-w-2xl rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-slate-950/50'>
    <div class='grid gap-4 p-5 sm:p-6'>
        <div class='flex items-start justify-between gap-3'><div><h2 id='device-users-title' class='text-lg font-bold'>Usuarios del biométrico</h2><p class='mt-1 text-sm text-slate-500'>Elimina del reloj los usuarios que ya no deben marcar.</p></div><button type='button' onclick='closeDeviceUsers()' class='rounded-lg px-3 py-2 text-sm font-semibold text-slate-600'>Cerrar</button></div>
        <p class='rounded-xl bg-amber-50 p-3 text-sm text-amber-900'>La eliminación afecta al usuario y sus huellas en este reloj. Conserva los empleados y las asistencias del sistema. Los administradores del reloj están protegidos.</p>
        <label class='grid gap-1 text-sm font-medium'>Buscar por nombre o código<input id='device-user-search' type='search' oninput='renderDeviceUsers()' class='min-h-11 rounded-xl border border-slate-300 px-3 py-2'></label>
        <div class='flex items-center justify-between gap-3'><span id='device-user-count' class='text-sm font-semibold text-slate-600' aria-live='polite'>0 seleccionados</span><button id='device-user-refresh' type='button' onclick='loadDeviceUsers()' class='rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-blue-700'>Actualizar lista</button></div>
        <div id='device-user-list' class='max-h-80 overflow-y-auto rounded-xl border border-slate-200'></div>
        <p id='device-user-result' class='text-sm text-slate-600' role='status'></p>
        <div class='flex justify-end gap-3 border-t border-slate-200 pt-4'><button type='button' onclick='closeDeviceUsers()' class='rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold'>Cancelar</button><button id='device-user-delete' type='button' onclick='deleteSelectedDeviceUsers()' disabled class='rounded-xl bg-rose-600 px-4 py-3 text-sm font-semibold text-white disabled:bg-slate-200 disabled:text-slate-500'>Eliminar seleccionados</button></div>
    </div>
</dialog>
@endif
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
    if (!response.ok || data.success === false) throw new Error(data.message || 'No se pudo completar la operación.');
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
        await refreshConnectionMonitoring();
    } catch (error) {
        result.className = 'rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800';
        result.textContent = error.message;
        await refreshConnectionMonitoring();
    }
}
let syncEmployeeDevice = null;
let syncEmployeeFingerprints = false;
let syncEmployees = [];
let filteredSyncEmployees = [];
const selectedSyncEmployees = new Set();
let syncEmployeeBusy = false;
let syncEmployeeLoadVersion = 0;
async function openEmployeeSelection(deviceId, fingerprints) {
    const dialog = document.getElementById('employee-selection');
    if (!dialog || syncEmployeeBusy) return;
    const loadVersion = ++syncEmployeeLoadVersion;
    syncEmployeeDevice = deviceId;
    syncEmployeeFingerprints = fingerprints;
    syncEmployees = [];
    selectedSyncEmployees.clear();
    document.getElementById('sync-employee-search').value = '';
    document.getElementById('sync-employee-unit').replaceChildren(new Option('Todas las unidades', ''));
    document.getElementById('sync-employee-result').textContent = 'Cargando empleados…';
    filterSyncEmployees();
    document.getElementById('employee-selection-title').textContent = fingerprints ? 'Enviar huellas de empleados seleccionados' : 'Enviar o actualizar empleados';
    dialog.showModal();
    try {
        const data = await request(@json(route('biometric.employees-for-sync')));
        if (!dialog.open || loadVersion !== syncEmployeeLoadVersion) return;
        syncEmployees = data.employees;
        const units = new Map(syncEmployees.map(employee => [String(employee.unit_id ?? 'none'), employee.unit_name]));
        for (const [id, name] of units) document.getElementById('sync-employee-unit').add(new Option(name, id));
        document.getElementById('sync-employee-result').textContent = 'Puedes reenviar empleados existentes o seleccionar los recién registrados.';
        filterSyncEmployees();
    } catch (error) { if (loadVersion === syncEmployeeLoadVersion) document.getElementById('sync-employee-result').textContent = error.message; }
}
function normalizeEmployeeSearch(value) { return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); }
function filterSyncEmployees() {
    const words = normalizeEmployeeSearch(document.getElementById('sync-employee-search').value).trim().split(/\s+/).filter(Boolean);
    const unit = document.getElementById('sync-employee-unit').value;
    filteredSyncEmployees = syncEmployees.filter(employee => (!unit || String(employee.unit_id ?? 'none') === unit) && words.every(word => normalizeEmployeeSearch(employee.name + ' ' + employee.code).includes(word)));
    const list = document.getElementById('sync-employee-list');
    list.replaceChildren();
    for (const employee of filteredSyncEmployees) {
        const label = document.createElement('label');
        label.className = 'flex cursor-pointer items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0 hover:bg-blue-50';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox'; checkbox.checked = selectedSyncEmployees.has(employee.id);
        checkbox.className = 'size-4 accent-blue-600';
        checkbox.addEventListener('change', () => { checkbox.checked ? selectedSyncEmployees.add(employee.id) : selectedSyncEmployees.delete(employee.id); updateSyncEmployeeCount(); });
        const details = document.createElement('span');
        const name = document.createElement('span'); name.className = 'block text-sm font-semibold'; name.textContent = employee.name;
        const unitName = document.createElement('span'); unitName.className = 'block text-xs text-slate-500'; unitName.textContent = employee.code + ' · ' + employee.unit_name;
        details.append(name, unitName); label.append(checkbox, details); list.append(label);
    }
    if (!filteredSyncEmployees.length) { const empty = document.createElement('p'); empty.className = 'p-6 text-center text-sm text-slate-500'; empty.textContent = 'No hay empleados para estos filtros.'; list.append(empty); }
    updateSyncEmployeeCount();
}
function updateSyncEmployeeCount() {
    document.getElementById('sync-employee-count').textContent = selectedSyncEmployees.size + ' seleccionados · ' + filteredSyncEmployees.length + ' encontrados';
    document.getElementById('sync-employee-submit').disabled = syncEmployeeBusy || !selectedSyncEmployees.size;
    document.getElementById('sync-employee-search').disabled = syncEmployeeBusy;
    document.getElementById('sync-employee-unit').disabled = syncEmployeeBusy;
    document.querySelectorAll('#sync-employee-list input').forEach(input => input.disabled = syncEmployeeBusy);
}
function selectFilteredEmployees() { if (syncEmployeeBusy) return; for (const employee of filteredSyncEmployees) selectedSyncEmployees.add(employee.id); filterSyncEmployees(); }
function clearSelectedEmployees() { if (syncEmployeeBusy) return; selectedSyncEmployees.clear(); filterSyncEmployees(); }
function closeEmployeeSelection() { if (!syncEmployeeBusy) document.getElementById('employee-selection').close(); }
document.getElementById('employee-selection')?.addEventListener('cancel', event => { if (syncEmployeeBusy) event.preventDefault(); });
async function sendSelectedEmployees(event) {
    event.preventDefault();
    if (syncEmployeeBusy || !selectedSyncEmployees.size) return;
    syncEmployeeBusy = true; updateSyncEmployeeCount();
    const result = document.getElementById('sync-employee-result'); result.textContent = 'Enviando empleados seleccionados…';
    try {
        const data = await request('/biometric/devices/' + syncEmployeeDevice + '/sync-employees', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({employee_ids: [...selectedSyncEmployees], include_fingerprints: syncEmployeeFingerprints})});
        result.textContent = data.message;
        const deviceResult = document.getElementById('sync-result-' + syncEmployeeDevice);
        if (deviceResult) { deviceResult.classList.remove('hidden'); deviceResult.textContent = data.message; }
        await refreshConnectionMonitoring();
    } catch (error) { result.textContent = error.message; }
    finally { syncEmployeeBusy = false; updateSyncEmployeeCount(); }
}
let managedDeviceId = null;
let managedDeviceUsers = [];
let deviceUsersBusy = false;
const selectedDeviceUsers = new Set();
function closeDeviceUsers() { if (!deviceUsersBusy) document.getElementById('device-users-dialog').close(); }
document.getElementById('device-users-dialog')?.addEventListener('cancel', event => { if (deviceUsersBusy) event.preventDefault(); });
async function openDeviceUsers(deviceId) {
    if (deviceUsersBusy) return;
    managedDeviceId = deviceId;
    document.getElementById('device-user-search').value = '';
    document.getElementById('device-users-dialog').showModal();
    await loadDeviceUsers();
}
function updateDeviceUserControls() {
    document.getElementById('device-user-delete').disabled = deviceUsersBusy || !selectedDeviceUsers.size;
    document.getElementById('device-user-refresh').disabled = deviceUsersBusy;
    document.getElementById('device-user-count').textContent = selectedDeviceUsers.size + ' seleccionados';
    document.querySelectorAll('#device-user-list input').forEach(input => input.disabled = deviceUsersBusy || input.dataset.protected === '1');
}
async function loadDeviceUsers() {
    if (deviceUsersBusy) return;
    deviceUsersBusy = true;
    selectedDeviceUsers.clear(); managedDeviceUsers = []; renderDeviceUsers();
    const result = document.getElementById('device-user-result'); result.textContent = 'Consultando usuarios del reloj…';
    try {
        const data = await request('/biometric/devices/' + managedDeviceId + '/users');
        managedDeviceUsers = data.users;
        result.textContent = managedDeviceUsers.length + ' usuarios encontrados.';
    } catch (error) { result.textContent = error.message; }
    finally { deviceUsersBusy = false; renderDeviceUsers(); }
}
function renderDeviceUsers() {
    const words = normalizeEmployeeSearch(document.getElementById('device-user-search').value).trim().split(/\s+/).filter(Boolean);
    const list = document.getElementById('device-user-list'); list.replaceChildren();
    for (const user of managedDeviceUsers.filter(user => words.every(word => normalizeEmployeeSearch(user.name + ' ' + user.userid).includes(word)))) {
        const label = document.createElement('label'); label.className = 'flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0';
        const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.checked = selectedDeviceUsers.has(user.uid); checkbox.dataset.protected = user.role !== 0 || user.uid < 1 ? '1' : '0'; checkbox.className = 'size-4 accent-rose-600';
        checkbox.addEventListener('change', () => { checkbox.checked ? selectedDeviceUsers.add(user.uid) : selectedDeviceUsers.delete(user.uid); updateDeviceUserControls(); });
        const details = document.createElement('span');
        const name = document.createElement('span'); name.className = 'block text-sm font-semibold'; name.textContent = user.name || 'Sin nombre';
        const code = document.createElement('span'); code.className = 'block text-xs text-slate-500'; code.textContent = 'Código: ' + user.userid + ' · UID: ' + user.uid + (user.role !== 0 ? ' · Administrador / protegido' : '');
        details.append(name, code); label.append(checkbox, details); list.append(label);
    }
    if (!list.children.length) { const empty = document.createElement('p'); empty.className = 'p-6 text-center text-sm text-slate-500'; empty.textContent = 'No hay usuarios para mostrar.'; list.append(empty); }
    updateDeviceUserControls();
}
async function deleteSelectedDeviceUsers() {
    if (deviceUsersBusy || !selectedDeviceUsers.size) return;
    if (selectedDeviceUsers.size > 100) { document.getElementById('device-user-result').textContent = 'Seleccione hasta 100 usuarios por operación.'; return; }
    const names = managedDeviceUsers.filter(user => selectedDeviceUsers.has(user.uid)).map(user => (user.name || 'Sin nombre') + ' [' + user.userid + ']');
    if (!confirm('¿Eliminar del biométrico estos ' + names.length + ' usuarios y sus huellas?\n\n' + names.join('\n') + '\n\nLos empleados y las asistencias del sistema se conservarán.')) return;
    deviceUsersBusy = true; updateDeviceUserControls();
    const result = document.getElementById('device-user-result'); result.textContent = 'Eliminando usuarios seleccionados…';
    try {
        const data = await request('/biometric/devices/' + managedDeviceId + '/users', {method: 'DELETE', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({user_ids: [...selectedDeviceUsers], confirmed: true})});
        managedDeviceUsers = managedDeviceUsers.filter(user => !data.deleted.includes(user.uid));
        selectedDeviceUsers.clear(); result.textContent = data.message;
    } catch (error) { result.textContent = error.message + ' Actualice la lista antes de volver a intentar.'; }
    finally { deviceUsersBusy = false; renderDeviceUsers(); }
}
let monitoringRefreshPending = false;
async function refreshConnectionMonitoring() {
    if (monitoringRefreshPending) return;
    monitoringRefreshPending = true;
    try {
        const response = await fetch(window.location.href, { headers: { 'Accept': 'text/html' }, cache: 'no-store' });
        if (!response.ok || response.redirected) throw new Error('No se pudo actualizar');
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        if (!page.getElementById('connection-alerts')) throw new Error('Respuesta inválida');
        document.querySelectorAll('#connection-alerts, #verified-count, [id^="device-status-"], [id^="last-check-"]').forEach(current => {
            const updated = page.getElementById(current.id);
            if (updated) current.replaceWith(updated);
        });
    } catch (error) {
        document.getElementById('monitor-refresh-error')?.classList.remove('hidden');
    } finally {
        monitoringRefreshPending = false;
    }
}
setInterval(() => { if (!document.hidden) refreshConnectionMonitoring(); }, 60000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshConnectionMonitoring(); });
async function deviceInfo(id) {
    try { const data = await request('/biometric/devices/' + id + '/info'); alert(Object.entries(data.data).map(([key,value]) => key + ': ' + value).join('\n')); } catch (error) { alert(error.message); }
}
async function bulkImport() {
    try { const devices = JSON.parse(document.getElementById('bulkData').value); const data = await request('/biometric/devices/bulk-import', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ devices }) }); alert(data.message); location.reload(); } catch (error) { alert(error.message); }
}
</script>
@endsection
