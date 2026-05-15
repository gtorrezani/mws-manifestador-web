<?php

namespace App\Rules;

use App\Support\Cpf;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidCpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Cpf::isValid(is_scalar($value) ? (string) $value : null)) {
            $fail('O CPF informado é inválido.');
        }
    }
}
