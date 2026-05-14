<?php

namespace App\Services\Fiscal;

readonly class ManifestationRequestContext
{
    public function __construct(
        public bool $explicitUserConfirmation = false,
        public bool $isAutomatic = false,
        public bool $automaticRuleConfigured = false,
        public bool $administrativelyConfirmed = false,
        public bool $allowRepeatConclusive = false,
    ) {}
}
