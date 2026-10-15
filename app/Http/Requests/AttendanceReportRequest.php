<?php

namespace App\Http\Requests;

use App\Services\DailyAttendanceReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AttendanceReportRequest extends FormRequest
{
    public bool $reportRequested = false;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->reportRequested = $this->query() !== [];

        $customFields = $this->boolean('custom_fields');
        $this->merge([
            'from' => $this->input('from') ?: today()->startOfMonth()->toDateString(),
            'to' => $this->input('to') ?: today()->endOfMonth()->toDateString(),
            'report_fields' => $this->input('report_fields', $customFields ? [] : DailyAttendanceReportService::DefaultFields),
            'extra_columns' => $this->input('extra_columns', $customFields ? [] : DailyAttendanceReportService::DefaultColumns),
        ]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->when($this->filled('work_unit_id'), fn ($rule) => $rule->where('work_unit_id', $this->integer('work_unit_id')))],
            'work_unit_id' => ['nullable', 'integer', 'exists:work_units,id'],
            'device_id' => ['nullable', 'integer', 'exists:biometric_devices,id'],
            'schedule_type' => ['nullable', Rule::in(['continuous', 'split'])],
            'custom_fields' => ['nullable', 'boolean'],
            'payroll_location' => ['nullable', 'string', 'max:200'],
            'payroll_direction' => ['nullable', 'string', 'max:200'],
            'payroll_phone' => ['nullable', 'string', 'max:50'],
            'payroll_contact' => ['nullable', 'string', 'max:200'],
            'report_type' => ['nullable', Rule::in(['attendance', 'payroll', 'audit'])],
            'excel_layout' => ['nullable', Rule::in(['per_employee', 'single_sheet'])],
            'report_fields' => ['present', 'array'],
            'report_fields.*' => ['string', 'distinct', Rule::in(array_keys(DailyAttendanceReportService::Fields))],
            'extra_columns' => ['present', 'array'],
            'extra_columns.*' => ['string', 'distinct', Rule::in(array_keys(DailyAttendanceReportService::Columns))],
        ];
    }

    /** @return array<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->hasAny(['from', 'to']) && Carbon::parse($this->input('from'))->diffInDays(Carbon::parse($this->input('to'))) > 365) {
                $validator->errors()->add('to', 'Seleccione un período de hasta 366 días.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'to.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'employee_id.exists' => 'El empleado no existe o no pertenece a la unidad seleccionada.',
        ];
    }
}
