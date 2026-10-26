<?php

namespace App\Rules;

use App\Models\User;
use App\Support\TcKimlik;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Kontrol haneleri doğru, 11 haneli ve başka hesapta kayıtlı olmayan TC kimlik numarası */
class TcKimlikNo implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! TcKimlik::isValid($value)) {
            $fail('Geçerli bir TC kimlik numarası girin (11 hane).');
        } elseif (User::tcTaken($value)) {
            $fail('Bu TC kimlik numarasıyla kayıtlı bir hesap var.');
        }
    }
}
