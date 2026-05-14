<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\AgentCertificateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentCertificate extends Model
{
    /** @use HasFactory<AgentCertificateFactory> */
    use HasFactory;

    use HasPublicUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => CertificateType::class,
        'status' => CertificateStatus::class,
        'valid_from' => 'immutable_datetime',
        'valid_until' => 'immutable_datetime',
        'has_private_key' => 'boolean',
        'last_seen_at' => 'immutable_datetime',
        'last_tested_at' => 'immutable_datetime',
        'metadata' => 'array',
    ];

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

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<CompanyCertificate, $this> */
    public function companyCertificates(): HasMany
    {
        return $this->hasMany(CompanyCertificate::class);
    }
}
