<?php

namespace App\Support;

final class Cpf
{
    public static function normalize(?string $value): string
    {
        return preg_replace('/\D+/', '', $value ?? '') ?? '';
    }

    public static function isValid(?string $value): bool
    {
        $cpf = self::normalize($value);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        $digits = array_map('intval', str_split($cpf));

        $firstDigit = self::calculateDigit(array_slice($digits, 0, 9), 10);
        if ($firstDigit !== $digits[9]) {
            return false;
        }

        $secondDigit = self::calculateDigit(array_slice($digits, 0, 10), 11);

        return $secondDigit === $digits[10];
    }

    public static function format(?string $value): string
    {
        $cpf = self::normalize($value);

        if (strlen($cpf) !== 11) {
            return $cpf;
        }

        return sprintf(
            '%s.%s.%s-%s',
            substr($cpf, 0, 3),
            substr($cpf, 3, 3),
            substr($cpf, 6, 3),
            substr($cpf, 9, 2),
        );
    }

    /**
     * @param  array<int, int>  $digits
     */
    private static function calculateDigit(array $digits, int $weight): int
    {
        $sum = 0;

        foreach ($digits as $digit) {
            $sum += $digit * $weight;
            $weight--;
        }

        $rest = ($sum * 10) % 11;

        return $rest === 10 ? 0 : $rest;
    }
}
