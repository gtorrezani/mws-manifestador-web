<?php

namespace App\Models;

use App\Enums\CommandStatus;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\AgentCommandAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCommandAttempt extends Model
{
    /** @use HasFactory<AgentCommandAttemptFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => CommandStatus::class,
        'started_at' => 'immutable_datetime',
        'finished_at' => 'immutable_datetime',
        'request_payload' => 'array',
        'result_payload' => 'array',
    ];

    /** @return BelongsTo<AgentCommand, $this> */
    public function command(): BelongsTo
    {
        return $this->belongsTo(AgentCommand::class, 'agent_command_id');
    }
}
