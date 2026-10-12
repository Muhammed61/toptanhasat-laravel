<?php

namespace App\Http\Requests\Auth;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Üyenin kendi bilgileri (kayıttaki kurallar; e-posta/telefon başka hesapta olmamalı) */
class UpdateProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'phone' => Phone::normalize($this->input('phone')) ?? $this->input('phone'),
        ]);
    }

    public function rules(): array
    {
        $id = $this->user()->id;

        return [
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['required', 'regex:/^5\d{9}$/', Rule::unique('users', 'phone')->ignore($id)],
        ];
    }

    public function messages(): array
    {
        return (new RegisterRequest)->messages();
    }

    public function attributes(): array
    {
        return (new RegisterRequest)->attributes();
    }
}
