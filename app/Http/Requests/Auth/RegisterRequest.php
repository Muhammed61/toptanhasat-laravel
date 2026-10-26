<?php

namespace App\Http\Requests\Auth;

use App\Rules\TcKimlikNo;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            // Geçersizse olduğu gibi bırak; regex kuralı yakalar
            'phone' => Phone::normalize($this->input('phone')) ?? $this->input('phone'),
            'tc_kimlik' => preg_replace('/\D/', '', (string) $this->input('tc_kimlik')),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'regex:/^5\d{9}$/', 'unique:users,phone'],
            'tc_kimlik' => ['required', new TcKimlikNo],
            'password' => ['required', 'string', 'min:8', 'regex:/\pL/u', 'regex:/\d/', 'confirmed'],
            'accept_terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute zorunludur.',
            'max' => ':attribute en fazla :max karakter olabilir.',
            'email.email' => 'Geçerli bir e-posta adresi girin.',
            'email.unique' => 'Bu e-posta adresiyle kayıtlı bir hesap var.',
            'phone.regex' => 'Geçerli bir cep telefonu numarası girin (05XX XXX XX XX).',
            'phone.unique' => 'Bu telefon numarasıyla kayıtlı bir hesap var.',
            'password.min' => 'Şifre en az 8 karakter olmalı.',
            'password.regex' => 'Şifre en az bir harf ve bir rakam içermeli.',
            'password.confirmed' => 'Şifreler eşleşmiyor.',
            'accept_terms.accepted' => 'Devam etmek için kullanım koşullarını kabul etmelisiniz.',
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'Ad',
            'last_name' => 'Soyad',
            'email' => 'E-posta',
            'phone' => 'Telefon',
            'tc_kimlik' => 'TC kimlik numarası',
            'password' => 'Şifre',
        ];
    }
}
