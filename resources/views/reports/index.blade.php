@extends('layouts.app')
@section('title', 'Reportes')
@section('heading', 'Reporte de asistencia')
@section('content')
<div class='grid gap-6'>
    <section class='rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6'>
        <div class='mb-5 flex flex-wrap items-start justify-between gap-3'>
            <div><h2 class='text-lg font-bold'>Prepara tu reporte</h2><p class='mt-1 max-w-3xl text-sm text-slate-500'>Después de revisar y corregir las asistencias, elige el período y los empleados. Elige una hoja por empleado o todos los empleados en una sola hoja para la vista previa y el Excel.</p></div>
            <span class='rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700'>Marcaciones, permisos y feriados</span>
        </div>
        <form id='report-filters' method='GET' action='{{ route('reports.index') }}' class='grid gap-5'>
            <input type='hidden' name='custom_fields' value='1'>
            <div class='grid gap-4 sm:grid-cols-2'>
                <label class='grid gap-1 text-sm font-medium'>Tipo de reporte<select name='report_type' onchange='this.form.requestSubmit()' class='rounded-xl border border-slate-300'>
                    <option value='attendance' @selected($reportType === 'attendance')>Asistencia</option>
                    <option value='payroll' @selected($reportType === 'payroll')>Planillas · Resumen</option>
                    <option value='audit' @selected($reportType === 'audit')>Auditoría · Pendiente de definición</option>
                </select></label>
                <label class='grid gap-1 text-sm font-medium'>Distribución de hojas<select name='excel_layout' onchange='this.form.requestSubmit()' class='rounded-xl border border-slate-300'>
                    <option value='per_employee' @selected($excelLayout === 'per_employee')>Una hoja por empleado</option>
                    <option value='single_sheet' @selected($excelLayout === 'single_sheet')>Todos los empleados en una hoja</option>
                </select></label>
            </div>
            @if($reportType === 'audit')
                <div class='rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900' role='status'><h3 class='font-bold'>Reporte de {{ $reportType === 'payroll' ? 'Planillas' : 'Auditoría' }}</h3><p class='mt-2'>El contenido de este reporte está pendiente de definición. La generación y descarga se habilitarán cuando se establezcan sus datos y reglas.</p></div>
            @endif

            @if($reportType === 'payroll')
            <fieldset class='grid gap-4 rounded-xl border border-slate-200 p-4 sm:grid-cols-2'>
                <legend class='px-2 text-sm font-bold'>Encabezado de la planilla</legend>
                @foreach(['payroll_location' => 'Lugar de marcación', 'payroll_direction' => 'Dirección', 'payroll_phone' => 'Teléfono', 'payroll_contact' => 'Encargado de asistencia'] as $field => $label)
                <label class='grid gap-1 text-sm font-medium'>{{ $label }}<input name='{{ $field }}' value='{{ request($field) }}' maxlength='{{ $field === "payroll_phone" ? 50 : 200 }}'></label>
                @endforeach
            </fieldset>
            @endif
            <fieldset class='grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 xl:grid-cols-4'>
                <legend class='sr-only'>Período y empleados</legend>
                <label class='grid gap-1 text-sm font-medium'>Desde<input id='report-from' type='date' name='from' value='{{ $from->toDateString() }}' required class='min-h-11 min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 focus:outline-2 focus:outline-emerald-500'></label>
                <label class='grid gap-1 text-sm font-medium'>Hasta<input id='report-to' type='date' name='to' value='{{ $to->toDateString() }}' required class='min-h-11 min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 focus:outline-2 focus:outline-emerald-500'></label>
                <label class='grid gap-1 text-sm font-medium'>Unidad<select id='report-unit' name='work_unit_id' onchange='filterReportEmployeeOptions()' class='min-h-11 min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2'><option value=''>Todas las unidades</option>@foreach($workUnits as $unit)<option value='{{ $unit->id }}' @selected(request('work_unit_id') == $unit->id)>{{ $unit->name }}</option>@endforeach</select></label>
                <label class='grid gap-1 text-sm font-medium'>Empleado<select id='report-employee' name='employee_id' class='min-h-11 min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2'><option value=''>Todos los empleados</option>@foreach($employeeOptions as $employee)<option value='{{ $employee->id }}' data-unit='{{ $employee->work_unit_id }}' @selected(request('employee_id') == $employee->id)>{{ $employee->last_name }} {{ $employee->maternal_last_name }} {{ $employee->first_name }}</option>@endforeach</select></label>
                <label class='grid gap-1 text-sm font-medium sm:col-span-2'>Buscar empleado<input type='search' name='search' value='{{ request('search') }}' placeholder='Nombre, apellido, código o documento' class='min-h-11 min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2'></label>
                <label class='grid gap-1 text-sm font-medium'>Biométrico<select name='device_id' class='min-h-11 min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2'><option value=''>Todos los biométricos</option>@foreach($devices as $device)<option value='{{ $device->id }}' @selected(request('device_id') == $device->id)>{{ $device->device_name }}</option>@endforeach</select></label>
                <label class='grid gap-1 text-sm font-medium'>Tipo de horario<select name='schedule_type' class='min-h-11 min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2'><option value=''>Todos los horarios</option><option value='continuous' @selected($scheduleType === 'continuous')>Continuo</option><option value='split' @selected($scheduleType === 'split')>Discontinuo</option></select></label>
                <div class='flex flex-wrap items-center gap-2 text-xs sm:col-span-2 xl:col-span-4'><span class='text-slate-500'>Períodos rápidos:</span><button type='button' data-from='{{ today()->startOfMonth()->toDateString() }}' data-to='{{ today()->endOfMonth()->toDateString() }}' onclick='setReportPeriod(this)' class='rounded-lg border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700'>Este mes</button><button type='button' data-from='{{ today()->subMonthNoOverflow()->startOfMonth()->toDateString() }}' data-to='{{ today()->subMonthNoOverflow()->endOfMonth()->toDateString() }}' onclick='setReportPeriod(this)' class='rounded-lg border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700'>Mes anterior</button></div>
            </fieldset>
            <details class='rounded-xl border border-slate-200 p-4'>
                <summary class='cursor-pointer text-sm font-semibold text-slate-800'>Personalizar información del reporte</summary>
                <div class='mt-4 grid gap-5'>
                    <fieldset class='grid gap-3 sm:grid-cols-2 lg:grid-cols-3'><legend class='mb-3 text-xs font-bold uppercase tracking-wide text-slate-500'>Datos del empleado</legend><label class='flex items-center gap-2 text-sm text-slate-500'><input type='checkbox' checked disabled class='size-4 accent-emerald-600'>Nombre del empleado (siempre incluido)</label>@foreach($fieldOptions as $key => $label)<label class='flex items-center gap-2 text-sm text-slate-700'><input type='checkbox' name='report_fields[]' value='{{ $key }}' @checked(in_array($key, $selectedFields, true)) class='size-4 accent-emerald-600'>{{ $label }}</label>@endforeach</fieldset>
                    <fieldset class='grid gap-3 sm:grid-cols-2'><legend class='mb-3 text-xs font-bold uppercase tracking-wide text-slate-500'>Columnas adicionales</legend>@foreach($columnOptions as $key => $label)<label class='flex items-center gap-2 text-sm text-slate-700'><input type='checkbox' name='extra_columns[]' value='{{ $key }}' @checked(in_array($key, $selectedColumns, true)) class='size-4 accent-emerald-600'>{{ $label }}</label>@endforeach</fieldset>
                    <p class='text-xs text-slate-500'>Fecha, día, marcaciones, permisos y feriados se incluyen siempre. Los minutos opcionales son los guardados en las marcaciones; la fórmula final de retrasos queda pendiente.</p>
                </div>
            </details>
            <div class='flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4'>
                <p class='text-xs text-slate-500'>La vista previa y el Excel aplican los mismos filtros y casillas.</p>
                <div class='flex flex-wrap gap-2'><a href='{{ route('reports.index') }}' class='inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600'>Limpiar</a><button type='submit' class='min-h-11 rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-700'>Ver reporte</button><button type='submit' formaction='{{ route('reports.daily-export') }}' @disabled($reportType === 'audit') class='min-h-11 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700'>Descargar Excel</button></div>
            </div>
        </form>
    </section>

    @if($reportRequested && $reportType === 'payroll')
    <section class='min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm'>
        <div class='border-b border-slate-200 p-5'><h2 class='font-bold'>Resumen para planillas · Borrador</h2><p class='mt-1 text-xs text-slate-500'>{{ $from->format('d/m/Y') }} — {{ $to->format('d/m/Y') }} · {{ $employees->count() }} empleados</p></div>
        <div class='grid gap-2 bg-slate-50 p-5 text-sm sm:grid-cols-2'>
            @foreach(['payroll_location' => 'Lugar de marcación', 'payroll_direction' => 'Dirección', 'payroll_phone' => 'Teléfono', 'payroll_contact' => 'Encargado de asistencia'] as $field => $label)
            <p><b>{{ $label }}:</b> {{ request($field) ?: 'Sin registrar' }}</p>
            @endforeach
        </div>
        <p class='border-y border-amber-200 bg-amber-50 p-4 text-sm text-amber-900'>Atraso: sanción por mes usando los minutos registrados dentro del periodo. Desde 121 minutos se requiere confirmar la primera ocurrencia anual. Faltas, abandonos y licencias SGH se toman del reporte de asistencia. Días trabajados = días calendario del periodo menos días de inasistencia.</p>
        @if($employees->count() > 10)<p class='p-4 text-xs text-slate-500'>Vista previa de los primeros 10 empleados. La descarga incluye todos los resultados.</p>@endif
        <details class='m-4 rounded-xl border border-slate-200 p-3'><summary class='text-sm font-bold'>Reglas de multas y descuentos</summary>
        <div class='mt-3 grid gap-3 text-sm sm:grid-cols-2'><div><p class='font-semibold'>Atraso acumulado por mes</p><ul class='mt-2 space-y-1'><li>0 a 30 minutos: sin sanción.</li><li>31 a 45: ½ día.</li><li>46 a 60: 1 día.</li><li>61 a 90: 2 días.</li><li>91 a 120: 3 días.</li><li>121 o más: 4 días la primera vez en la gestión. Reincidencia pendiente de definición.</li></ul></div>
        <div class='space-y-2'><p>Inasistencia: días de falta × 2.</p><p>Abandono: días de abandono × 1.</p><p>Licencia sin goce: días de licencia × 1.</p><p class='font-semibold'>Total = sanción por atraso + inasistencia × 2 + abandono + licencia sin goce.</p><p class='text-xs text-slate-500'>Se calcula el total solo cuando todas las incidencias están confirmadas. Los rangos parciales muestran sanciones preliminares.</p></div></div></details>
        <div class='max-h-[65vh] overflow-auto' tabindex='0' role='region' aria-label='Resumen de planillas'>
        <table class='min-w-full text-left text-xs'><thead class='sticky top-0 bg-slate-900 text-white'><tr>@foreach(\App\Services\PayrollReportService::Headings as $heading)<th class='min-w-32 border border-slate-600 p-3'>{{ $heading }}</th>@endforeach</tr></thead><tbody>
        @forelse($payrollRows as $row)<tr>@foreach($row as $value)<td class='border border-slate-200 p-3'>{{ $value }}</td>@endforeach</tr>
        @empty<tr><td colspan='{{ count(\App\Services\PayrollReportService::Headings) }}' class='p-6 text-center'>No hay empleados para estos filtros.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    @endif
    @if($reportRequested && $reportType === 'attendance')
    <section class='min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm' aria-label='Vista previa del Excel'>
        <div class='flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4'>
            <div><h2 class='font-bold'>Vista previa del Excel</h2><p class='mt-1 text-xs text-slate-500'>{{ $from->format('d/m/Y') }} — {{ $to->format('d/m/Y') }} · {{ $employees->count() }} empleados · {{ $totalMarks }} marcaciones</p></div>
            <span class='text-xs text-slate-500'>Desplázate dentro de la hoja para revisar las filas y columnas.</span>
        </div>
        @if($employees->count() > 10)<p class='border-b border-blue-100 bg-blue-50 px-5 py-2 text-xs text-blue-800'>Vista previa de los primeros 10 empleados. Filtra por nombre para revisar otro; la descarga incluye todos los resultados.</p>@endif
        @php
            $previewRowOffset = 0;
        @endphp
        <div @if($excelLayout === 'single_sheet') class='h-[520px] max-h-[65vh] overflow-auto' tabindex='0' role='region' aria-label='Hoja de todos los empleados' @endif>
        @forelse($dailyReports as $report)
            @php
                $sheet = new \App\Exports\DailyAttendanceSheet($report, $from, $to, $selectedColumns);
                $sheetRows = $sheet->array();
                $sheetHeaderIndex = array_search('Fecha', array_column($sheetRows, 0), true);
                $sheetColumnCount = count($sheetRows[$sheetHeaderIndex]);
                $sheetColors = [];
                $slotIndexes = $report['has_split'] ? [0, 1, 2, 3] : [0, 3];
                $slotOffset = in_array('schedule', $selectedColumns, true) ? 3 : 2;
                foreach ($report['days']->values() as $dayIndex => $day) {
                    foreach ($slotIndexes as $offset => $slotIndex) {
                        $sheetColors[$sheetHeaderIndex + 1 + $dayIndex][$slotOffset + $offset] = $day['cells'][$slotIndex];
                    }
                }
                $employeeExportUrl = route('reports.daily-export', [...request()->except('employee_id'), 'employee_id' => $report['employee']->id, 'excel_layout' => 'per_employee']);
            @endphp
            <div id='report-sheet-{{ $loop->index }}' data-report-sheet @if($excelLayout === 'per_employee' && !$loop->first) hidden @endif>
                <div class='flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-5 py-2'>
                    <p class='text-xs font-semibold text-slate-700'>{{ $report['employee']->full_name }}</p>
                    <a href='{{ $employeeExportUrl }}' class='rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs font-semibold text-emerald-700'>Descargar esta hoja</a>
                </div>
                <div class='{{ $excelLayout === 'per_employee' ? 'h-[520px] max-h-[65vh] overflow-auto' : '' }} overscroll-contain focus:outline-2 focus:outline-emerald-500' tabindex='0' role='region' aria-label='Hoja de {{ $report['employee']->full_name }}'>
                    <table class='w-max min-w-full border-separate border-spacing-0 text-left text-xs'>
                        <thead class='sticky top-0 z-20'><tr><th class='sticky left-0 z-30 min-w-12 border-b border-r border-slate-300 bg-slate-200 px-3 py-2 text-center text-slate-500' aria-label='Número de fila'></th>@for($column = 1; $column <= $sheetColumnCount; $column++)<th class='min-w-36 border-b border-r border-slate-300 bg-slate-100 px-3 py-2 text-center font-medium text-slate-500'>{{ \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column) }}</th>@endfor</tr></thead>
                        <tbody>
                            @foreach($sheetRows as $rowIndex => $sheetRow)
                                <tr>
                                    <th scope='row' class='sticky left-0 z-10 border-b border-r border-slate-300 bg-slate-100 px-3 py-2 text-center font-normal text-slate-500'>{{ $rowIndex + 1 + ($excelLayout === 'single_sheet' ? $previewRowOffset : 0) }}</th>
                                    @if($rowIndex === 0)
                                        <td colspan='{{ $sheetColumnCount }}' class='border-b border-slate-300 bg-slate-900 px-4 py-4 text-base font-bold text-white'>{{ $sheetRow[0] }}</td>
                                    @elseif($rowIndex > 0 && $rowIndex < $sheetHeaderIndex - 1)
                                        <td class='border-b border-r border-slate-200 px-3 py-2 font-semibold'>{{ $sheetRow[0] }}</td><td colspan='{{ $sheetColumnCount - 1 }}' class='border-b border-r border-slate-200 px-3 py-2'>{{ $sheetRow[1] ?? '' }}</td>
                                    @else
                                        @for($columnIndex = 0; $columnIndex < $sheetColumnCount; $columnIndex++)
                                            @php
                                                $previewCell = $sheetColors[$rowIndex][$columnIndex] ?? null;
                                                $previewColor = $previewCell['color'] ?? null;
                                                $isDayRow = $rowIndex > $sheetHeaderIndex && $rowIndex <= $sheetHeaderIndex + $report['days']->count();
                                                $isHoliday = $isDayRow && $report['days']->values()[$rowIndex - $sheetHeaderIndex - 1]['is_holiday'];
                                            @endphp
                                            <td class='max-w-72 border-b border-r border-slate-200 px-3 py-2 {{ $rowIndex === $sheetHeaderIndex ? "bg-slate-700 font-semibold text-white" : ($previewColor ? "font-bold text-white" : ($isHoliday ? "bg-rose-50" : "bg-white text-slate-700")) }}' @if($previewColor) style='background-color: {{ preg_match('/^#[0-9a-f]{6}$/i', $previewColor) ? $previewColor : '#2563EB' }}' @endif title='{{ $previewCell['description'] ?? '' }}'>{{ $sheetRow[$columnIndex] ?? '' }}</td>
                                        @endfor
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @php
                $previewRowOffset += count($sheetRows) + 2;
            @endphp
        @empty
            <div class='px-5 py-12 text-center'><p class='font-semibold'>No hay empleados para estos filtros.</p><p class='mt-2 text-sm text-slate-500'>Prueba con otra unidad, empleado o período.</p></div>
        @endforelse
        </div>
        @if($excelLayout === 'single_sheet' && $dailyReports->isNotEmpty())
            <div class='border-t border-slate-300 bg-slate-100 px-4 py-3 text-xs font-semibold text-emerald-700'>Todos los empleados · Una sola hoja</div>
        @endif
        @if($excelLayout === 'per_employee' && $dailyReports->isNotEmpty())
            <div class='flex overflow-x-auto border-t border-slate-300 bg-slate-100 px-3 pt-2' role='tablist' aria-label='Hojas por empleado'>
                @foreach($dailyReports as $report)
                    <button type='button' id='report-tab-{{ $loop->index }}' role='tab' aria-controls='report-sheet-{{ $loop->index }}' aria-selected='{{ $loop->first ? "true" : "false" }}' data-report-tab onclick='selectReportSheet({{ $loop->index }})' class='shrink-0 rounded-t-lg border border-slate-200 px-4 py-3 text-xs font-semibold {{ $loop->first ? "bg-white text-emerald-700" : "bg-slate-100 text-slate-500" }}'>{{ $report['employee']->full_name }}</button>
                @endforeach
            </div>
        @endif
    </section>

    <section class='rounded-2xl border border-slate-200 bg-white p-5 sm:p-6'>
        <h2 class='text-sm font-bold'>Leyenda de permisos y feriados</h2><div class='mt-3 flex flex-wrap gap-2'><span class='rounded-lg bg-rose-700 px-3 py-1.5 text-xs font-bold text-white'>FE · Feriado</span>@foreach($leaveTypes as $leaveType)<span class='rounded-lg px-3 py-1.5 text-xs font-bold text-white' style='background-color: {{ $leaveType->color }}'>{{ $leaveType->abbreviation }} · {{ $leaveType->name }}</span>@endforeach<span class='rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700'>SIN MARCADO · Registro faltante</span></div>
        <p class='mt-3 text-xs text-slate-500'>Si existe una marcación real y un permiso en la misma celda, se conservan la hora y el código del permiso.</p>
    </section>
    <details class='rounded-2xl border border-slate-200 bg-white p-5 sm:p-6'><summary class='cursor-pointer text-sm font-semibold'>Feriados dentro del período · {{ $holidays->count() }}</summary><div class='mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3'>@forelse($holidays as $occurrence)<div class='rounded-xl border border-rose-100 bg-rose-50 p-3'><p class='text-sm font-semibold'>{{ $occurrence['holiday']->name }}</p><p class='mt-1 text-xs text-rose-700'>{{ $occurrence['date']->format('d/m/Y') }} · {{ ucfirst($occurrence['holiday']->scope) }}</p></div>@empty<p class='text-sm text-slate-500'>No hay feriados dentro del período seleccionado.</p>@endforelse</div></details>
    <details class='rounded-2xl border border-slate-200 bg-white p-5 sm:p-6'><summary class='cursor-pointer text-sm font-semibold'>Horarios aplicados dentro del período</summary><div class='mt-4 overflow-x-auto'><table class='w-full text-left text-sm'><thead class='bg-slate-50 text-xs text-slate-500'><tr><th class='p-3'>Empleado</th><th class='p-3'>Horario</th><th class='p-3'>Tipo</th><th class='p-3'>Horas</th><th class='p-3'>Desde</th><th class='p-3'>Hasta</th></tr></thead><tbody class='divide-y divide-slate-100'>@forelse($scheduleHistory as $period)<tr><td class='p-3'>{{ $period['employee']->last_name }} {{ $period['employee']->first_name }}</td><td class='p-3'>{{ $period['schedule']->name }}</td><td class='p-3'>{{ $period['type'] }}</td><td class='whitespace-nowrap p-3'>{{ $period['hours'] }}</td><td class='whitespace-nowrap p-3'>{{ $period['starts_on']->format('d/m/Y') }}</td><td class='whitespace-nowrap p-3'>{{ $period['ends_on']->format('d/m/Y') }}</td></tr>@empty<tr><td colspan='6' class='p-5 text-sm text-slate-500'>No hay asignaciones de horario dentro del período.</td></tr>@endforelse</tbody></table></div></details>
    @endif
</div>
<script>
function selectReportSheet(index) {
    document.querySelectorAll('[data-report-sheet]').forEach((sheet, position) => {
        sheet.hidden = position !== index;
    });
    document.querySelectorAll('[data-report-tab]').forEach((tab, position) => {
        const selected = position === index;
        tab.setAttribute('aria-selected', String(selected));
        tab.classList.toggle('bg-white', selected);
        tab.classList.toggle('text-emerald-700', selected);
        tab.classList.toggle('bg-slate-100', !selected);
        tab.classList.toggle('text-slate-500', !selected);
    });
}
function setReportPeriod(button) {
    document.getElementById('report-from').value = button.dataset.from;
    document.getElementById('report-to').value = button.dataset.to;
}
function filterReportEmployeeOptions() {
    const unit = document.getElementById('report-unit').value;
    const employees = document.getElementById('report-employee');
    for (const option of employees.options) {
        const isAvailable = !option.value || !unit || option.dataset.unit === unit;
        option.hidden = !isAvailable;
        option.disabled = !isAvailable;
        if (!isAvailable && option.selected) employees.value = '';
    }
}
filterReportEmployeeOptions();
</script>
@endsection
