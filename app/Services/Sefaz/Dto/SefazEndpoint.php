<?php

namespace App\Services\Sefaz\Dto;

use App\Enums\FiscalEnvironment;

readonly class SefazEndpoint
{
    public function __construct(
        public FiscalEnvironment $environment,
        public string $uf,
        public string $url,
        public string $soapAction,
        public string $operationName,
        public string $operationNamespace,
    ) {}
}
