<?php

namespace App\Support;

final class Cnpj
{
    public static function normalize(?string $value): string
    {
        return preg_replace('/\D+/', '', $value ?? '') ?? '';
    }

    public static function format(?string $value): string
    {
        $cnpj = self::normalize($value);

        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($cnpj, 0, 2),
            substr($cnpj, 2, 3),
            substr($cnpj, 5, 3),
            substr($cnpj, 8, 4),
            substr($cnpj, 12, 2),
        );
    }
}
