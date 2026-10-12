<?php

namespace App\Http\Requests\Auth;

use App\Support\Phone;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required' => 'E-posta veya telefon numaranızı girin.',
            'password.required' => 'Şifrenizi girin.',
        ];
    }

    /**
     * E-posta veya telefon + şifre ile giriş dener. Aynı kimlik/IP için
     * art arda 5 hatalı denemeden sonra bir süre bekletir.
     */
    public function authenticate(): void
    {
        $key = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            event(new Lockout($this));

            throw ValidationException::withMessages([
                'identifier' => 'Çok fazla deneme yaptınız. Lütfen '.RateLimiter::availableIn($key).' saniye sonra tekrar deneyin.',
            ]);
        }

        if (! Auth::guard('web')->attempt([...$this->credentialField(), 'password' => $this->string('password')->value()], $this->boolean('remember'))) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages([
                'identifier' => 'E-posta/telefon veya şifre hatalı.',
            ]);
        }

        RateLimiter::clear($key);
    }

    /** @return array{email: string}|array{phone: string} */
    private function credentialField(): array
    {
        $identifier = trim($this->string('identifier')->value());

        if (str_contains($identifier, '@')) {
            return ['email' => mb_strtolower($identifier)];
        }

        // Geçersiz telefon da bir değer olarak aranır; eşleşmez ve "hatalı" mesajı döner
        return ['phone' => Phone::normalize($identifier) ?? $identifier];
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('identifier')->value()).'|'.$this->ip());
    }
}
