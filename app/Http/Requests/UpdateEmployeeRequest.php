<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'work_unit_id' => ['required', Rule::exists('work_units', 'id')->where('is_active', true)],
            'employee_code' => ['required', 'string', 'max:50', Rule::unique('employees')->ignore($this->route('employee'))],
            'item_number' => ['required', 'string', 'max:50', Rule::unique('employees')->ignore($this->route('employee'))],
            'employee_type' => ['required', Rule::in(['permanent', 'contract', 'consultant', 'temporary'])],
            'budget_line' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'maternal_last_name' => ['nullable', 'string', 'max:100'],
            'identity_document' => ['required', 'string', 'max:30', Rule::unique('employees')->ignore($this->route('employee'))],
            'identity_issued_in' => ['nullable', Rule::in(['LP', 'OR', 'PT', 'CB', 'SC', 'BN', 'PA', 'TJ', 'CH', 'EXT'])],
            'has_children' => ['boolean'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees')->ignore($this->route('employee'))],
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:100'],
            'job_description' => ['nullable', 'string', 'max:1000'],
            'employment_observations' => ['nullable', 'string', 'max:2000'],
            'memo_date' => ['nullable', 'date'],
            'memo_number' => ['nullable', 'string', 'max:50'],
            'department' => ['nullable', 'string', 'max:100'],
            'biometric_id' => ['exclude'],
            'attendance_method' => ['required', Rule::in(['biometric', 'access_code', 'automatic'])],
            'access_code' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->input('attendance_method') === 'access_code'
                    && ! $this->route('employee')?->hasUsableAccessCode()),
                'string',
                'min:4',
                'max:20',
            ],
            'hire_date' => ['nullable', 'date'],
            'employment_end_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed', 'domestic_partnership', 'none'])],
            'gender' => ['nullable', Rule::in(['female', 'male', 'other', 'not_specified'])],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'has_children' => $this->boolean('has_children'),
            'is_active' => $this->boolean('is_active'),
            'access_code' => $this->filled('access_code') ? $this->string('access_code')->toString() : null,
        ]);
    }
}
