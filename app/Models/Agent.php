<?php

namespace App\Models;

use App\Enums\AgentStatus;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory;

    use HasPublicUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => AgentStatus::class,
        'last_seen_at' => 'immutable_datetime',
        'activated_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasOne<AgentCredential, $this> */
    public function credential(): HasOne
    {
        return $this->hasOne(AgentCredential::class);
    }

    /** @return HasMany<AgentHeartbeat, $this> */
    public function heartbeats(): HasMany
    {
        return $this->hasMany(AgentHeartbeat::class);
    }

    /** @return HasMany<AgentCertificate, $this> */
    public function certificates(): HasMany
    {
        return $this->hasMany(AgentCertificate::class);
    }
}
