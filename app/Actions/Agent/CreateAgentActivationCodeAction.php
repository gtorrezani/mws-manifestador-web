<?php

namespace App\Actions\Agent;

use App\Enums\ActivationStatus;
use App\Models\AgentActivation;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAgentActivationCodeAction
{
    /** @return array{activation: AgentActivation, code: string} */
    public function execute(Company $company, ?int $requestedBy = null): array
    {
        $code = (string) random_int(100000, 999999);

        $activation = AgentActivation::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'requested_by' => $requestedBy,
            'code_hash' => Hash::make($code),
            'status' => ActivationStatus::Pending,
            'expires_at' => now()->addMinutes((int) config('agent.activation_code_ttl_minutes', 30)),
        ]);

        return [
            'activation' => $activation,
            'code' => $code,
        ];
    }
}
