<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\AgentCertificateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentCertificate extends Model
{
    use BelongsToCompany;

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
        'not_before' => 'immutable_datetime',
        'not_after' => 'immutable_datetime',
        'has_private_key' => 'boolean',
        'is_expired' => 'boolean',
        'is_certificate_authority' => 'boolean',
        'is_fiscal_candidate' => 'boolean',
        'is_icp_brasil' => 'boolean',
        'is_usable_for_client_auth' => 'boolean',
        'is_valid' => 'boolean',
        'last_seen_at' => 'immutable_datetime',
        'last_tested_at' => 'immutable_datetime',
        'rejection_reasons' => 'array',
        'warnings' => 'array',
        'metadata' => 'array',
        'raw_payload' => 'array',
        'last_test_payload' => 'array',
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
