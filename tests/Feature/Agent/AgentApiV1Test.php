<?php

namespace Tests\Feature\Agent;

use App\Enums\ActivationStatus;
use App\Enums\AgentStatus;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Models\Agent;
use App\Models\AgentActivation;
use App\Models\AgentCommand;
use App\Models\AgentCredential;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AgentApiV1Test extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_be_activated_with_temporary_code(): void
    {
        $company = Company::factory()->create();
        AgentActivation::query()->create([
            'uuid' => fake()->uuid(),
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'code_hash' => Hash::make('123456'),
            'status' => ActivationStatus::Pending,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/agent/v1/activate', [
            'activation_code' => '123456',
            'installation_id' => 'install-001',
            'machine_name' => 'MWS-CLIENTE',
            'version' => '1.0.0',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'agent_id',
                'secret',
                'auth' => ['algorithm', 'canonical_format'],
                'polling_interval_seconds',
            ]);

        $this->assertDatabaseHas('agents', [
            'installation_id' => 'install-001',
            'status' => AgentStatus::Online->value,
        ]);
    }

    public function test_heartbeat_requires_hmac_signature(): void
    {
        $this->postJson('/api/agent/v1/heartbeat', [
            'status' => AgentStatus::Online->value,
            'version' => '1.0.0',
        ])->assertUnauthorized();
    }

    public function test_signed_heartbeat_is_recorded(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();

        $body = [
            'status' => AgentStatus::Online->value,
            'version' => '1.0.1',
            'machine_name' => 'MWS-CLIENTE',
        ];

        $this->postJsonSigned($agent, $secret, '/api/agent/v1/heartbeat', $body)
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        $this->assertDatabaseHas('agent_heartbeats', [
            'agent_id' => $agent->id,
            'version' => '1.0.1',
        ]);
    }

    public function test_invalid_signature_does_not_burn_nonce(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();
        $body = [
            'status' => AgentStatus::Online->value,
            'version' => '1.0.1',
        ];
        $timestamp = time();
        $nonce = 'fixed-nonce-for-invalid-signature-test';

        $this->postJsonSigned($agent, 'wrong-secret', '/api/agent/v1/heartbeat', $body, $timestamp, $nonce)
            ->assertUnauthorized();

        $this->postJsonSigned($agent, $secret, '/api/agent/v1/heartbeat', $body, $timestamp, $nonce)
            ->assertOk()
            ->assertJson(['status' => 'accepted']);
    }

    public function test_poll_respects_priority_and_locks_commands(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();

        $low = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'priority' => 200,
            'type' => CommandType::TestCertificate,
        ]);

        $high = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'priority' => 10,
            'type' => CommandType::ListCertificates,
        ]);

        $response = $this->postJsonSigned($agent, $secret, '/api/agent/v1/commands/poll', ['limit' => 2]);

        $response
            ->assertOk()
            ->assertJsonPath('commands.0.uuid', $high->uuid)
            ->assertJsonPath('commands.1.uuid', $low->uuid);

        $this->assertDatabaseHas('agent_commands', [
            'id' => $high->id,
            'status' => CommandStatus::Locked->value,
            'locked_by_agent_id' => $agent->id,
        ]);
    }

    public function test_command_can_be_started_and_completed_once(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
        ]);

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])
            ->assertOk()
            ->assertJsonPath('status', CommandStatus::Processing->value);

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/complete", [
            'result' => ['documents_synced' => 2],
            'protocol_number' => '135240000000001',
            'sefaz_status_code' => '135',
            'sefaz_message' => 'Evento registrado e vinculado a NF-e',
            'request_xml' => [
                'storage_disk' => 's3',
                'storage_path' => 'soap/requests/req.xml',
                'content_hash' => hash('sha256', 'request'),
            ],
            'response_xml' => [
                'storage_disk' => 's3',
                'storage_path' => 'soap/responses/res.xml',
                'content_hash' => hash('sha256', 'response'),
            ],
        ])->assertOk()->assertJsonPath('status', CommandStatus::Completed->value);

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/complete", [])
            ->assertOk()
            ->assertJsonPath('idempotent', true);
    }

    public function test_failed_command_records_technical_error_and_requeues_until_max_attempts(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
            'attempts_count' => 0,
            'max_attempts' => 3,
        ]);

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])->assertOk();

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/fail", [
            'error_code' => 'SEFAZ_TIMEOUT',
            'error_message' => 'Timeout while calling SEFAZ.',
        ])
            ->assertOk()
            ->assertJsonPath('status', CommandStatus::Pending->value)
            ->assertJsonPath('final', false);

        $this->assertDatabaseHas('agent_command_attempts', [
            'agent_command_id' => $command->id,
            'error_code' => 'SEFAZ_TIMEOUT',
        ]);
    }

    /** @return array{0: Agent, 1: string} */
    private function createAgentWithSecret(): array
    {
        $agent = Agent::factory()->create(['status' => AgentStatus::Online]);
        $secret = 'test-secret';

        AgentCredential::query()->create([
            'uuid' => fake()->uuid(),
            'tenant_id' => $agent->tenant_id,
            'agent_id' => $agent->id,
            'credential_id' => fake()->uuid(),
            'secret_hash' => Hash::make($secret),
            'encrypted_secret_payload' => Crypt::encryptString($secret),
            'last_rotated_at' => now(),
        ]);

        return [$agent, $secret];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<JsonResponse>
     */
    private function postJsonSigned(
        Agent $agent,
        string $secret,
        string $path,
        array $body,
        ?int $timestamp = null,
        ?string $nonce = null,
    ): TestResponse {
        $json = json_encode($body, JSON_THROW_ON_ERROR);
        $timestamp ??= time();
        $nonce ??= fake()->uuid();
        $bodyHash = hash('sha256', $json);
        $canonical = "POST\n{$path}\n{$timestamp}\n{$nonce}\n{$bodyHash}";
        $signature = hash_hmac('sha256', $canonical, $secret);

        return $this
            ->withHeaders([
                'X-MWS-Agent-Id' => $agent->uuid,
                'X-MWS-Timestamp' => (string) $timestamp,
                'X-MWS-Nonce' => $nonce,
                'X-MWS-Body-SHA256' => $bodyHash,
                'X-MWS-Signature' => $signature,
            ])
            ->postJson($path, $body);
    }
}
