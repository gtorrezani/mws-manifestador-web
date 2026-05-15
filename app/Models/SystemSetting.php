<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\SystemSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemSetting extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<SystemSettingFactory> */
    use HasFactory;

    use HasPublicUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'value' => 'array',
        'is_encrypted' => 'boolean',
    ];

    public static function makeScopeKey(?int $tenantId, ?int $companyId): string
    {
        if ($companyId !== null) {
            return 'company:'.$companyId;
        }

        if ($tenantId !== null) {
            return 'tenant:'.$tenantId;
        }

        return 'global';
    }

    protected static function booted(): void
    {
        static::saving(function (SystemSetting $setting): void {
            $tenantId = $setting->tenant_id === null ? null : (int) $setting->tenant_id;
            $companyId = $setting->company_id === null ? null : (int) $setting->company_id;

            $setting->scope_key = self::makeScopeKey($tenantId, $companyId);
        });
    }
}
