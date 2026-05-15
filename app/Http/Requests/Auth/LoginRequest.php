<?php

namespace App\Http\Requests\Auth;

use App\Rules\ValidCpf;
use App\Support\Cpf;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cpf' => ['required', 'string', new ValidCpf],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cpf' => Cpf::normalize($this->input('cpf')),
            'remember' => $this->boolean('remember'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt([
            'cpf' => $this->string('cpf')->toString(),
            'password' => $this->string('password')->toString(),
        ], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'cpf' => 'As credenciais informadas são inválidas.',
            ]);
        }

        $user = $this->user();

        if ($user === null || ! $user->canLogin()) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

            Auth::guard('web')->logout();
            $this->session()->invalidate();
            $this->session()->regenerateToken();

            throw ValidationException::withMessages([
                'cpf' => 'Usuário bloqueado ou inativo. Entre em contato com o administrador.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'cpf' => "Muitas tentativas de login. Tente novamente em {$seconds} segundos.",
        ]);
    }

    public function throttleKey(): string
    {
        return static::throttleKeyFor($this->string('cpf')->toString(), $this->ip());
    }

    public static function throttleKeyFor(string $cpf, ?string $ip): string
    {
        return Str::transliterate(Str::lower(Cpf::normalize($cpf)).'|'.($ip ?? 'unknown'));
    }
}
