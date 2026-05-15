<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\SefazConnectivityTestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SefazConnectivityTest extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<SefazConnectivityTestFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'sanitized_payload' => 'array',
        'requested_at' => 'immutable_datetime',
        'completed_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** @return BelongsTo<CompanyCertificate, $this> */
    public function companyCertificate(): BelongsTo
    {
        return $this->belongsTo(CompanyCertificate::class);
    }

    /** @return BelongsTo<AgentCommand, $this> */
    public function agentCommand(): BelongsTo
    {
        return $this->belongsTo(AgentCommand::class);
    }
}
