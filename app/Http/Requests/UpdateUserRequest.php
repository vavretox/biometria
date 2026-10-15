<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends StoreUserRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [...parent::rules(),
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'password' => ['nullable', 'string', 'min:8', 'max:72', 'confirmed'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->user()->is($this->route('user'))) {
                if (! $this->boolean('is_admin')) {
                    $validator->errors()->add('is_admin', 'No puedes quitarte el rol de administrador.');
                }
                if (! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'No puedes desactivar tu propia cuenta.');
                }
            }
        }];
    }
}
