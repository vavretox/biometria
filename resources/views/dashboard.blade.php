@extends('layouts.app')
@section('title', 'Panel principal')
@section('heading', 'Resumen de hoy')
@section('content')
<div class='grid gap-6'>
    <section class='flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-slate-900 p-6 text-white'>
        <div><p class='text-xs font-semibold uppercase tracking-widest text-emerald-300'>Control de asistencia</p><h2 class='mt-2 text-2xl font-bold'>Tu jornada, de un vistazo</h2><p class='mt-2 text-sm text-slate-300'>{{ today()->format('d/m/Y') }} · Datos actualizados a las {{ now()->format('H:i') }}</p></div>
        <div class='flex flex-wrap gap-2'><button type='button' onclick='window.location.reload()' class='rounded-xl border border-slate-600 px-4 py-3 text-sm font-semibold hover:bg-slate-800'>Actualizar</button><a href='{{ route('attendance.index', ['from' => today()->toDateString(), 'to' => today()->toDateString()]) }}' class='rounded-xl bg-emerald-500 px-4 py-3 text-sm font-semibold text-slate-950 hover:bg-emerald-400'>Consultar asistencias →</a></div>
    </section>
    <div class='grid gap-4 sm:grid-cols-2 xl:grid-cols-4'>
        @foreach([['Empleados activos', $employeeCount, 'Personal registrado', 'bg-blue-500'], ['Con marcación hoy', $presentCount, $presencePercentage.'% del personal activo', 'bg-emerald-500'], ['Sin marcación hoy', $withoutMarksCount, 'Pendientes de revisión', 'bg-amber-500'], ['Llegadas tarde', $lateCount, 'Empleados con retraso registrado', 'bg-rose-500']] as [$label, $value, $caption, $color])
        <article class='rounded-2xl border border-slate-200 bg-white p-5 shadow-sm'><div class='flex items-center justify-between gap-3'><p class='text-sm font-medium text-slate-500'>{{ $label }}</p><span class='size-2.5 rounded-full {{ $color }}' aria-hidden='true'></span></div><p class='mt-4 text-4xl font-bold tracking-tight text-slate-900'>{{ $value }}</p><p class='mt-2 text-xs text-slate-500'>{{ $caption }}</p></article>
        @endforeach
    </div>
    <div class='grid min-w-0 gap-6 xl:grid-cols-[22rem_1fr]'>
        <section class='rounded-2xl border border-slate-200 bg-white p-6 shadow-sm'>
            <h2 class='font-bold'>Marcaciones de hoy</h2><p class='mt-1 text-xs text-slate-500'>Distribución del personal activo</p>
            <div class='relative mx-auto my-6 size-48'>
                <svg viewBox='0 0 120 120' class='size-full -rotate-90' role='img' aria-label='{{ $presentCount }} empleados con marcación y {{ $withoutMarksCount }} sin marcación'><circle cx='60' cy='60' r='48' fill='none' stroke='#f1f5f9' stroke-width='13'/><circle cx='60' cy='60' r='48' fill='none' stroke='#10b981' stroke-width='13' pathLength='100' stroke-dasharray='{{ $presencePercentage }} 100'/></svg>
                <div class='absolute inset-0 grid content-center justify-items-center'><strong class='text-4xl tracking-tight'>{{ $presencePercentage }}%</strong><span class='mt-1 text-xs text-slate-500'>con marcación</span></div>
            </div>
            <div class='grid gap-3 text-sm'><div class='flex items-center justify-between'><span class='flex items-center gap-2'><span class='size-2 rounded-full bg-emerald-500'></span>Con marcación</span><strong>{{ $presentCount }}</strong></div><div class='flex items-center justify-between'><span class='flex items-center gap-2'><span class='size-2 rounded-full bg-slate-200'></span>Sin marcación</span><strong>{{ $withoutMarksCount }}</strong></div></div>
            <p class='mt-5 border-t border-slate-100 pt-4 text-xs leading-relaxed text-slate-500'>Sin marcación no implica ausencia: revisa horarios, permisos y feriados. Se incluyen las marcaciones automáticas.</p>
        </section>
        <section class='min-w-0 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm'>
            <div class='flex flex-wrap items-start justify-between gap-3'><div><h2 class='font-bold'>Incidencias de asistencia</h2><p class='mt-1 text-xs text-slate-500'>Últimos 14 días · Empleados activos por día</p></div><div class='flex gap-2'>@foreach(['late' => ['Retrasos', 'bg-rose-500'], 'early' => ['Salidas tempranas', 'bg-blue-500']] as $key => [$label, $color])<button type='button' data-chart-toggle='{{ $key }}' aria-pressed='true' class='flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium'><span class='size-2 rounded-full {{ $color }}'></span>{{ $label }}</button>@endforeach</div></div>
            @php($chartMaximum = max(1, $history->max('late'), $history->max('early')))
            <div class='mt-6 overflow-x-auto'>
                <svg viewBox='0 0 720 260' class='w-full min-w-96' role='img' aria-label='Historial de retrasos y salidas tempranas de los últimos 14 días'>
                    @foreach([0, 0.5, 1] as $fraction)
                    <line x1='38' x2='700' y1='{{ 210 - $fraction * 175 }}' y2='{{ 210 - $fraction * 175 }}' stroke='#e2e8f0' stroke-dasharray='4 5'/><text x='28' y='{{ 214 - $fraction * 175 }}' text-anchor='end' fill='#64748b' font-size='11'>{{ round($fraction * $chartMaximum, 1) }}</text>
                    @endforeach
                    @foreach(['late' => '#f43f5e', 'early' => '#3b82f6'] as $key => $color)
                    <g data-chart-series='{{ $key }}'>
                        <polyline fill='none' stroke='{{ $color }}' stroke-width='3' stroke-linejoin='round' points='{{ $history->map(fn ($day, $index) => (38 + $index * 50).",".(210 - $day[$key] / $chartMaximum * 175))->join(" ") }}'/>
                        @foreach($history as $index => $day)<circle cx='{{ 38 + $index * 50 }}' cy='{{ 210 - $day[$key] / $chartMaximum * 175 }}' r='4' fill='white' stroke='{{ $color }}' stroke-width='2'><title>{{ $day['date'] }}: {{ $day[$key] }} {{ $key === 'late' ? 'con retrasos' : 'con salidas tempranas' }}</title></circle>@endforeach
                    </g>
                    @endforeach
                    @foreach($history as $index => $day)@if($index % 2 === 0 || $loop->last)<text x='{{ 38 + $index * 50 }}' y='240' text-anchor='middle' fill='#64748b' font-size='11'>{{ $day['date'] }}</text>@endif @endforeach
                </svg>
            </div>
            <p class='mt-4 text-xs text-slate-500'>Pulsa la leyenda para mostrar u ocultar una serie. Coloca el cursor sobre un punto para ver el dato.</p>
        </section>
    </div>
    <div class='grid min-w-0 gap-6 xl:grid-cols-2'>
        <section class='min-w-0 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm'><div class='flex justify-between gap-3'><div><h2 class='font-bold'>Actividad por hora</h2><p class='mt-1 text-xs text-slate-500'>Marcaciones registradas hoy</p></div><span class='text-sm font-bold text-emerald-700'>{{ $markCount }} registros</span></div>
            @php($hourMaximum = max(1, $hourlyMarks->max()))
            <div class='mt-6 overflow-x-auto'><svg viewBox='0 0 600 180' class='w-full min-w-80' role='img' aria-label='Número de marcaciones de hoy por hora'><line x1='12' x2='588' y1='145' y2='145' stroke='#e2e8f0'/>@foreach($hourlyMarks as $hour => $count)<rect x='{{ 14 + $hour * 24 }}' y='{{ 145 - $count / $hourMaximum * 120 }}' width='15' height='{{ $count / $hourMaximum * 120 }}' rx='3' fill='#10b981'><title>{{ sprintf('%02d:00', $hour) }}: {{ $count }} marcaciones</title></rect>@if($hour % 4 === 0)<text x='{{ 20 + $hour * 24 }}' y='170' text-anchor='middle' fill='#64748b' font-size='11'>{{ sprintf('%02d:00', $hour) }}</text>@endif @endforeach</svg></div>
            <div class='mt-5 flex items-center justify-between border-t border-slate-100 pt-4 text-xs text-slate-500'><span>{{ $deviceCount }} biométricos activos</span><a href='{{ route('biometric.devices.index') }}' class='font-semibold text-blue-700'>Ver dispositivos →</a></div>
        </section>
        <section class='rounded-2xl border border-slate-200 bg-white p-6 shadow-sm'><div class='mb-5 flex items-center justify-between gap-3'><h2 class='font-bold'>Últimas marcaciones de hoy</h2><span class='text-xs text-slate-500'>8 más recientes</span></div><div class='grid divide-y divide-slate-100'>@forelse($recentAttendances as $attendance)<a href='{{ route('attendance.index', ['employee_id' => $attendance->employee_id, 'from' => $attendance->checked_at->toDateString(), 'to' => $attendance->checked_at->toDateString()]) }}' class='flex items-center justify-between gap-3 rounded-lg py-3 hover:bg-slate-50'><div class='min-w-0'><p class='truncate text-sm font-semibold'>{{ $attendance->employee->full_name }}</p><p class='mt-1 text-xs text-slate-500'>{{ ['in' => 'Entrada', 'out' => 'Salida', 'break_in' => 'Inicio pausa', 'break_out' => 'Fin pausa'][$attendance->type] ?? $attendance->type }} · {{ $attendance->device?->device_name ?? ($attendance->source === 'automatic' ? 'Automática' : 'Manual') }}</p></div><div class='text-right'><p class='text-sm font-bold tabular-nums'>{{ $attendance->checked_at->format('H:i') }}</p><span class='text-xs {{ $attendance->status === 'late' ? "text-rose-600" : "text-slate-500" }}'>{{ ['late' => 'Retraso', 'early' => 'Salida temprana', 'normal' => 'Normal'][$attendance->status] ?? $attendance->status }}</span></div></a>@empty<div class='rounded-xl bg-slate-50 px-4 py-10 text-center'><p class='text-sm font-semibold text-slate-700'>Todavía no hay marcaciones hoy</p><p class='mt-2 text-xs text-slate-500'>Los registros aparecerán después de sincronizar los equipos.</p></div>@endforelse</div></section>
    </div>
</div>
<script>
document.querySelectorAll('[data-chart-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const visible = button.getAttribute('aria-pressed') !== 'true';
        button.setAttribute('aria-pressed', String(visible));
        button.classList.toggle('opacity-50', !visible);
        document.querySelector(`[data-chart-series="${button.dataset.chartToggle}"]`).style.display = visible ? '' : 'none';
    });
});
</script>
@endsection
