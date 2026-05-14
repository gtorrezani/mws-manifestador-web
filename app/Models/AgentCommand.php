<?php

namespace App\Models;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\AgentCommandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentCommand extends Model
{
    /** @use HasFactory<AgentCommandFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => CommandType::class,
        'status' => CommandStatus::class,
        'payload' => 'array',
        'available_at' => 'immutable_datetime',
        'locked_at' => 'immutable_datetime',
        'lock_expires_at' => 'immutable_datetime',
        'completed_at' => 'immutable_datetime',
        'failed_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** @return HasMany<AgentCommandAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(AgentCommandAttempt::class);
    }
}
