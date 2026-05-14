<?php

namespace App\Actions\Agent;

use App\DTOs\Agent\ActivateAgentData;
use App\DTOs\Agent\ActivationResponseData;
use App\Enums\ActivationStatus;
use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentActivation;
use App\Models\AgentCredential;
use App\Services\Agent\AgentSecretService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class ActivateAgentAction
{
    public function __construct(
        private readonly AgentSecretService $secretService,
    ) {}

    public function execute(ActivateAgentData $data): ActivationResponseData
    {
        /** @var AgentActivation|null $activation */
        $activation = AgentActivation::query()
            ->where('status', ActivationStatus::Pending)
            ->where('expires_at', '>', now())
            ->get()
            ->first(fn (AgentActivation $activation): bool => Hash::check($data->activationCode, $activation->code_hash));

        if (! $activation) {
            throw new UnauthorizedHttpException('', 'Invalid or expired activation code.');
        }

        return DB::transaction(function () use ($activation, $data): ActivationResponseData {
            /** @var Agent $agent */
            $agent = Agent::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $activation->tenant_id,
                'company_id' => $activation->company_id,
                'name' => $data->machineName,
                'machine_name' => $data->machineName,
                'installation_id' => $data->installationId,
                'version' => $data->version,
                'status' => AgentStatus::Online,
                'last_seen_at' => now(),
                'activated_at' => now(),
            ]);

            $secret = $this->secretService->createSecret();

            /** @var AgentCredential $credential */
            $credential = AgentCredential::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $activation->tenant_id,
                'agent_id' => $agent->id,
                'credential_id' => (string) Str::uuid(),
                'secret_hash' => 'pending',
                'encrypted_secret_payload' => 'pending',
                'last_rotated_at' => now(),
            ]);

            $this->secretService->applyInitialSecret($credential, $secret);

            $activation->forceFill([
                'status' => ActivationStatus::Used,
                'used_by_agent_id' => $agent->id,
                'used_at' => now(),
                'metadata' => [
                    'installation_id' => $data->installationId,
                    'machine_name' => $data->machineName,
                    'version' => $data->version,
                    'certificate_inventory' => $data->certificateInventory,
                ],
            ])->save();

            return new ActivationResponseData(
                agentId: $agent->uuid,
                secret: $secret,
                pollingIntervalSeconds: (int) config('agent.polling_interval_seconds', 30),
                timestampToleranceSeconds: (int) config('agent.auth.timestamp_tolerance_seconds', 300),
            );
        });
    }
}
