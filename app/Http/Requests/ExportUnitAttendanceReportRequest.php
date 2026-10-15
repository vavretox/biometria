<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class ExportUnitAttendanceReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'work_unit_id' => ['required', 'integer', 'exists:work_units,id'],
            'employee_id' => ['nullable', 'integer', $this->employeeExistsInSelectedUnit()],
            'schedule_type' => ['nullable', Rule::in(['continuous', 'split'])],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'work_unit_id.required' => 'Seleccione una unidad para exportar el reporte.',
            'employee_id.exists' => 'El empleado seleccionado no pertenece a la unidad indicada.',
            'to.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
        ];
    }

    private function employeeExistsInSelectedUnit(): Exists
    {
        return Rule::exists('employees', 'id')
            ->where('work_unit_id', $this->integer('work_unit_id'));
    }
}
