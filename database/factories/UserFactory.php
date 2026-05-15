<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'cpf' => $this->validCpf(),
            'password' => 'password',
            'is_active' => true,
            'blocked_at' => null,
            'last_login_at' => null,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function blocked(): self
    {
        return $this->state(fn (): array => ['blocked_at' => now()]);
    }

    private function validCpf(): string
    {
        do {
            $base = str_pad((string) fake()->unique()->numberBetween(100000000, 999999999), 9, '0', STR_PAD_LEFT);
        } while (preg_match('/^(\d)\1{8}$/', $base) === 1);

        $firstDigit = $this->calculateDigit($base, 10);
        $secondDigit = $this->calculateDigit($base.$firstDigit, 11);

        return $base.$firstDigit.$secondDigit;
    }

    private function calculateDigit(string $digits, int $weight): int
    {
        $sum = 0;

        foreach (str_split($digits) as $digit) {
            $sum += ((int) $digit) * $weight;
            $weight--;
        }

        $rest = ($sum * 10) % 11;

        return $rest === 10 ? 0 : $rest;
    }
}
