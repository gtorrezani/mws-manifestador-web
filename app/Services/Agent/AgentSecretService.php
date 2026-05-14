<?php

namespace App\Services\Agent;

use App\Models\AgentCredential;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AgentSecretService
{
    public function createSecret(): string
    {
        return Str::random(64);
    }

    public function applyInitialSecret(AgentCredential $credential, string $secret): void
    {
        $credential->forceFill([
            'secret_hash' => Hash::make($secret),
            'encrypted_secret_payload' => Crypt::encryptString($secret),
            'last_rotated_at' => now(),
        ])->save();
    }

    public function prepareRotation(AgentCredential $credential): string
    {
        $secret = $this->createSecret();

        $credential->forceFill([
            'pending_secret_hash' => Hash::make($secret),
            'pending_encrypted_secret_payload' => Crypt::encryptString($secret),
            'pending_secret_expires_at' => now()->addDay(),
        ])->save();

        return $secret;
    }
}
