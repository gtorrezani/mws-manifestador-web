<?php

namespace App\Models;

use App\Support\Cpf;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $cpf
 * @property bool $is_active
 * @property Carbon|null $blocked_at
 * @property Carbon|null $last_login_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    protected $fillable = [
        'name',
        'cpf',
        'password',
        'is_active',
        'blocked_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
        'blocked_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function setCpfAttribute(string $value): void
    {
        $this->attributes['cpf'] = Cpf::normalize($value);
    }

    public function canLogin(): bool
    {
        return $this->is_active && $this->blocked_at === null;
    }
}
