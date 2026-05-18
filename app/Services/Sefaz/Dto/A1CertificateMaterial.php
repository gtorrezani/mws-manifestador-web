<?php

namespace App\Services\Sefaz\Dto;

readonly class A1CertificateMaterial
{
    public function __construct(
        public string $certificatePem,
        public string $privateKeyPem,
    ) {}
}
