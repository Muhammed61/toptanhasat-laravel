<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            // Yeni şifre kayıttaki kurallarla aynı; eskisiyle aynı olamaz
            'password' => ['required', 'string', 'min:8', 'regex:/\pL/u', 'regex:/\d/', 'confirmed', 'different:current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Mevcut şifrenizi girin.',
            'current_password.current_password' => 'Mevcut şifreniz hatalı.',
            'password.required' => 'Yeni şifrenizi girin.',
            'password.min' => 'Şifre en az 8 karakter olmalı.',
            'password.regex' => 'Şifre en az bir harf ve bir rakam içermeli.',
            'password.confirmed' => 'Şifreler eşleşmiyor.',
            'password.different' => 'Yeni şifre mevcut şifrenizden farklı olmalı.',
        ];
    }
}
