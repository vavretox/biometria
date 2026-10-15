@extends('layouts.app')
@section('title', 'Historial de conexión')
@section('heading', 'Historial de conexión de biométricos')
@section('content')
<div class='grid gap-6'>
    <section class='rounded-2xl bg-white p-6 shadow-sm'>
        <div class='flex flex-wrap items-center justify-between gap-3'>
            <div><h2 class='text-lg font-bold'>Historial de conexión</h2><p class='mt-1 text-sm text-slate-500'>Verificaciones automáticas cada 30 minutos, pruebas manuales y conexiones durante las operaciones.</p></div>
            <a href='{{ route('biometric.devices.index') }}' class='rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold'>Volver a dispositivos</a>
        </div>
        <form method='GET' action='{{ route('biometric.devices.connection-history') }}' class='mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5'>
            <label class='grid gap-2 text-sm font-semibold'>Dispositivo<select name='device_id' class='rounded-xl border border-slate-300 px-3 py-2 font-normal'><option value=''>Todos</option>@foreach($devices as $device)<option value='{{ $device->id }}' @selected(($filters['device_id'] ?? '') == $device->id)>{{ $device->device_name }}</option>@endforeach</select></label>
            <label class='grid gap-2 text-sm font-semibold'>Resultado<select name='status' class='rounded-xl border border-slate-300 px-3 py-2 font-normal'><option value=''>Todos</option><option value='success' @selected(($filters['status'] ?? '') === 'success')>Conexión disponible</option><option value='failed' @selected(($filters['status'] ?? '') === 'failed')>Error de conexión</option></select></label>
            <label class='grid gap-2 text-sm font-semibold'>Desde<input type='date' name='start_date' value='{{ $filters['start_date'] ?? '' }}' class='rounded-xl border border-slate-300 px-3 py-2 font-normal'></label>
            <label class='grid gap-2 text-sm font-semibold'>Hasta<input type='date' name='end_date' value='{{ $filters['end_date'] ?? '' }}' class='rounded-xl border border-slate-300 px-3 py-2 font-normal'></label>
            <div class='flex items-end gap-2'><button class='rounded-xl bg-blue-600 px-4 py-2 font-semibold text-white'>Filtrar</button><a href='{{ route('biometric.devices.connection-history') }}' class='rounded-xl border border-slate-300 px-4 py-2'>Limpiar</a></div>
        </form>
    </section>
    <section class='overflow-x-auto rounded-2xl bg-white shadow-sm'>
        <table class='w-full text-left text-sm'>
            <thead class='bg-slate-50 text-slate-600'><tr><th class='p-4'>Fecha y hora</th><th class='p-4'>Dispositivo</th><th class='p-4'>Origen</th><th class='p-4'>Resultado</th><th class='p-4'>Tiempo</th><th class='p-4'>Error</th></tr></thead>
            <tbody class='divide-y divide-slate-100'>
                @forelse($logs as $log)
                <tr>
                    <td class='whitespace-nowrap p-4'>{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                    <td class='p-4'><p class='font-semibold'>{{ $log->device?->device_name ?: 'Dispositivo eliminado' }}</p><p class='text-xs text-slate-500'>{{ $log->device?->device_ip }}:{{ $log->device?->device_port }}</p></td>
                    <td class='p-4'>{{ ['automatic' => 'Automática', 'manual' => 'Manual', 'operation' => 'Operación'][$log->request_data['source'] ?? 'operation'] ?? 'Operación' }}</td>
                    <td class='p-4'><span class='inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $log->status === 'success' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}'>{{ $log->status === 'success' ? 'Conexión disponible' : 'Error de conexión' }}</span></td>
                    <td class='whitespace-nowrap p-4'>{{ $log->response_time_ms !== null ? $log->response_time_ms.' ms' : '—' }}</td>
                    <td class='max-w-md break-words p-4 text-red-700'>{{ $log->error_message ?: '—' }}</td>
                </tr>
                @empty
                <tr><td colspan='6' class='p-8 text-center text-slate-500'>No hay registros de conexión para estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <div>{{ $logs->links() }}</div>
</div>
@endsection
