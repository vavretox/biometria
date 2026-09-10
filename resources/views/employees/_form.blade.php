@csrf
@isset($employee) @method('PUT') @endisset
@php($record = $employee ?? null)

<div class='grid gap-8'>
    <section>
        <div class='mb-5 border-b border-slate-200 pb-4'><h2 class='text-lg font-bold'>Datos personales</h2><p class='mt-1 text-sm text-slate-500'>Información de identidad y datos familiares del empleado.</p></div>
        <div class='grid gap-5 md:grid-cols-2 xl:grid-cols-3'>
            <label class='grid gap-2 text-sm font-semibold'>Nombres *<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='first_name' value='{{ old('first_name', $record?->first_name) }}' required></label>
            <label class='grid gap-2 text-sm font-semibold'>Apellido paterno *<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='last_name' value='{{ old('last_name', $record?->last_name) }}' required></label>
            <label class='grid gap-2 text-sm font-semibold'>Apellido materno<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='maternal_last_name' value='{{ old('maternal_last_name', $record?->maternal_last_name) }}'></label>
            <div class='grid gap-2'><span class='text-sm font-semibold'>Cédula de identidad *</span><div class='grid grid-cols-[1fr_6rem] gap-2'><input class='rounded-xl border border-slate-300 px-4 py-3' name='identity_document' value='{{ old('identity_document', $record?->identity_document) }}' required><select class='rounded-xl border border-slate-300 px-3 py-3' name='identity_issued_in'><option value=''>Exp.</option>@foreach(['LP','OR','PT','CB','SC','BN','PA','TJ','CH','EXT'] as $place)<option value='{{ $place }}' @selected(old('identity_issued_in', $record?->identity_issued_in) === $place)>{{ $place }}</option>@endforeach</select></div></div>
            <label class='grid gap-2 text-sm font-semibold'>Fecha de nacimiento<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='birth_date' type='date' value='{{ old('birth_date', $record?->birth_date?->format('Y-m-d')) }}'></label>
            <label class='grid gap-2 text-sm font-semibold'>Estado civil<select class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='marital_status'><option value=''>Seleccione</option>@foreach(['none'=>'Ninguno(a)','single'=>'Soltero(a)','married'=>'Casado(a)','divorced'=>'Divorciado(a)','widowed'=>'Viudo(a)','domestic_partnership'=>'Unión libre'] as $value => $label)<option value='{{ $value }}' @selected(old('marital_status', $record?->marital_status) === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class='grid gap-2 text-sm font-semibold'>Género<select class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='gender'><option value=''>Seleccione</option>@foreach(['female'=>'Mujer','male'=>'Hombre','other'=>'Otro','not_specified'=>'Prefiere no indicar'] as $value => $label)<option value='{{ $value }}' @selected(old('gender', $record?->gender) === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class='flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold'><input class='size-5' name='has_children' type='checkbox' value='1' @checked(old('has_children', $record?->has_children))> Tiene hijos</label>
        </div>
    </section>

    <section>
        <div class='mb-5 border-b border-slate-200 pb-4'><h2 class='text-lg font-bold'>Contacto</h2><p class='mt-1 text-sm text-slate-500'>Domicilio y medios de contacto vigentes.</p></div>
        <div class='grid gap-5 md:grid-cols-2 xl:grid-cols-3'>
            <label class='grid gap-2 text-sm font-semibold md:col-span-2'>Domicilio<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='address' value='{{ old('address', $record?->address) }}'></label>
            <label class='grid gap-2 text-sm font-semibold'>Correo electrónico<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='email' type='email' value='{{ old('email', $record?->email) }}'></label>
            <label class='grid gap-2 text-sm font-semibold'>Teléfono fijo<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='phone' value='{{ old('phone', $record?->phone) }}'></label>
            <label class='grid gap-2 text-sm font-semibold'>Celular<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='mobile' value='{{ old('mobile', $record?->mobile) }}'></label>
        </div>
    </section>

    <section>
        <div class='mb-5 border-b border-slate-200 pb-4'><h2 class='text-lg font-bold'>Datos laborales y biométricos</h2><p class='mt-1 text-sm text-slate-500'>Identificación interna y asignación dentro de la institución.</p></div>
        <div class='grid gap-5 md:grid-cols-2 xl:grid-cols-3'>
            <label class='grid gap-2 text-sm font-semibold'>Unidad de oficina *<select class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='work_unit_id' required><option value=''>Seleccione una unidad</option>@foreach($workUnits as $unit)<option value='{{ $unit->id }}' @selected((int) old('work_unit_id', $record?->work_unit_id) === $unit->id)>{{ $unit->name }}</option>@endforeach</select></label>
            <label class='grid gap-2 text-sm font-semibold'>Código de empleado *<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='employee_code' value='{{ old('employee_code', $record?->employee_code) }}' required></label>
            <label class='grid gap-2 text-sm font-semibold'>Número de ítem *<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='item_number' value='{{ old('item_number', $record?->item_number) }}' required></label>
            <label class='grid gap-2 text-sm font-semibold'>Tipo de funcionario *<select class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='employee_type' required>@foreach(['permanent'=>'Permanente','contract'=>'Contrato','consultant'=>'Consultor','temporary'=>'Eventual'] as $value => $label)<option value='{{ $value }}' @selected(old('employee_type', $record?->employee_type) === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class='grid gap-2 text-sm font-semibold'>Partida presupuestaria<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='budget_line' value='{{ old('budget_line', $record?->budget_line) }}'></label>
            <label class='grid gap-2 text-sm font-semibold'>Cargo<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='position' value='{{ old('position', $record?->position) }}'></label>
            <label class='grid gap-2 text-sm font-semibold'>Unidad o departamento<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='department' value='{{ old('department', $record?->department) }}'></label>
            <label class='grid gap-2 text-sm font-semibold'>Modalidad de asistencia *<select id='attendance-method' class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='attendance_method' required><option value='biometric' @selected(old('attendance_method', $record?->attendance_method ?? 'biometric') === 'biometric')>Biometría</option><option value='access_code' @selected(old('attendance_method', $record?->attendance_method) === 'access_code')>Código de acceso</option><option value='automatic' @selected(old('attendance_method', $record?->attendance_method) === 'automatic')>Marcación automática · Ejecutivo</option></select></label>
            <label id='biometric-id-field' class='grid gap-2 text-sm font-semibold'>ID biométrico<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='biometric_id' type='number' min='1' value='{{ old('biometric_id', $record?->biometric_id) }}'><small class='font-normal text-slate-500'>Obligatorio para biometría y personal ejecutivo automático.</small></label>
            <label id='access-code-field' class='grid gap-2 text-sm font-semibold'>Código de acceso<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='access_code' type='password' minlength='4' maxlength='20' autocomplete='new-password' placeholder='{{ $record?->access_code ? 'Dejar vacío para conservar' : 'Código de 4 a 20 caracteres' }}'><small class='font-normal text-slate-500'>Se almacena cifrado y se envía como contraseña al dispositivo.</small></label>
            <label class='grid gap-2 text-sm font-semibold'>Fecha de ingreso<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='hire_date' type='date' value='{{ old('hire_date', $record?->hire_date?->format('Y-m-d')) }}'></label>
            <label class='grid gap-2 text-sm font-semibold'>Fecha de finalización<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='employment_end_date' type='date' value='{{ old('employment_end_date', $record?->employment_end_date?->format('Y-m-d')) }}'></label>
            <label class='grid gap-2 text-sm font-semibold'>Fecha de memo<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='memo_date' type='date' value='{{ old('memo_date', $record?->memo_date?->format('Y-m-d')) }}'></label>
            <label class='grid gap-2 text-sm font-semibold'>Número de memo<input class='rounded-xl border border-slate-300 px-4 py-3 font-normal' name='memo_number' value='{{ old('memo_number', $record?->memo_number) }}'></label>
            <label class='flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold'><input class='size-5' name='is_active' type='checkbox' value='1' @checked(old('is_active', $record?->is_active ?? true))> Empleado activo</label>
        </div>
        <div class='mt-5 grid gap-5 md:grid-cols-2'><label class='grid gap-2 text-sm font-semibold'>Descripción del cargo<textarea class='min-h-28 rounded-xl border border-slate-300 px-4 py-3 font-normal' name='job_description'>{{ old('job_description', $record?->job_description) }}</textarea></label><label class='grid gap-2 text-sm font-semibold'>Observaciones laborales<textarea class='min-h-28 rounded-xl border border-slate-300 px-4 py-3 font-normal' name='employment_observations'>{{ old('employment_observations', $record?->employment_observations) }}</textarea></label></div>
    </section>
</div>

<script>
const attendanceMethod = document.getElementById('attendance-method');
const biometricIdField = document.getElementById('biometric-id-field');
const accessCodeField = document.getElementById('access-code-field');

const synchronizeAttendanceFields = () => {
    const usesAccessCode = attendanceMethod.value === 'access_code';
    accessCodeField.classList.toggle('hidden', !usesAccessCode);
    biometricIdField.querySelector('input').required = !usesAccessCode;
};

attendanceMethod.addEventListener('change', synchronizeAttendanceFields);
synchronizeAttendanceFields();
</script>

<div class='mt-8 flex flex-wrap gap-3 border-t border-slate-200 pt-6'>
    <button class='rounded-xl bg-emerald-500 px-6 py-3 font-bold text-white'>Guardar empleado</button>
    <a href='{{ route('employees.index') }}' class='rounded-xl bg-slate-200 px-6 py-3 font-bold'>Cancelar</a>
</div>
