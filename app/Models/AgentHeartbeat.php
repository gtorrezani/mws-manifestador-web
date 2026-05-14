<?php

namespace App\Models;

use App\Enums\AgentStatus;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\AgentHeartbeatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentHeartbeat extends Model
{
    /** @use HasFactory<AgentHeartbeatFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => AgentStatus::class,
        'payload' => 'array',
        'received_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
