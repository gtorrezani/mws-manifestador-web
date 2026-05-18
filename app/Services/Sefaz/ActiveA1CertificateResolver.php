<?php

namespace App\Services\Sefaz;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Models\Company;
use App\Models\CompanyCertificate;

class ActiveA1CertificateResolver
{
    public function resolve(Company $company): ?CompanyCertificate
    {
        /** @var CompanyCertificate|null $certificate */
        $certificate = $company->certificates()
            ->where('type', CertificateType::A1->value)
            ->where('status', CertificateStatus::Active->value)
            ->whereNotNull('storage_disk')
            ->whereNotNull('storage_path')
            ->whereNotNull('encrypted_password_payload')
            ->latest('last_validated_at')
            ->latest('id')
            ->first();

        return $certificate;
    }
}
