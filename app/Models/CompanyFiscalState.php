<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\CompanyFiscalStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyFiscalState extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<CompanyFiscalStateFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'next_distribution_available_at' => 'immutable_datetime',
        'distribution_blocked_until' => 'immutable_datetime',
        'last_distribution_attempt_at' => 'immutable_datetime',
        'last_distribution_success_at' => 'immutable_datetime',
        'last_distribution_error_at' => 'immutable_datetime',
        'last_success_at' => 'immutable_datetime',
        'last_error_at' => 'immutable_datetime',
        'consecutive_distribution_failures' => 'integer',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
