<?php

namespace App\DTOs\Agent;

readonly class ActivationResponseData
{
    public function __construct(
        public string $agentId,
        public string $secret,
        public int $pollingIntervalSeconds,
        public int $timestampToleranceSeconds,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'agent_id' => $this->agentId,
            'secret' => $this->secret,
            'auth' => [
                'algorithm' => 'HMAC-SHA256',
                'signature_header' => 'X-MWS-Signature',
                'timestamp_header' => 'X-MWS-Timestamp',
                'nonce_header' => 'X-MWS-Nonce',
                'body_hash_header' => 'X-MWS-Body-SHA256',
                'canonical_format' => "METHOD\nPATH\nTIMESTAMP\nNONCE\nBODY_SHA256",
                'timestamp_tolerance_seconds' => $this->timestampToleranceSeconds,
            ],
            'polling_interval_seconds' => $this->pollingIntervalSeconds,
        ];
    }
}
