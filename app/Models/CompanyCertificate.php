<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\CompanyCertificateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyCertificate extends Model
{
    /** @use HasFactory<CompanyCertificateFactory> */
    use HasFactory;

    use HasPublicUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => CertificateType::class,
        'status' => CertificateStatus::class,
        'valid_from' => 'immutable_datetime',
        'valid_until' => 'immutable_datetime',
        'metadata' => 'array',
        'last_validated_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
