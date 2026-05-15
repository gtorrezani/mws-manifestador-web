<?php

namespace App\Models;

use App\Enums\ActivationStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\AgentActivationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentActivation extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<AgentActivationFactory> */
    use HasFactory;
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => ActivationStatus::class,
        'expires_at' => 'immutable_datetime',
        'used_at' => 'immutable_datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Agent, $this> */
    public function usedByAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'used_by_agent_id');
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
