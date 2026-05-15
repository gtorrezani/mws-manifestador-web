<?php

namespace Tests\Feature\Agent;

use App\Enums\ActivationStatus;
use App\Enums\AgentStatus;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Models\Agent;
use App\Models\AgentActivation;
use App\Models\AgentCertificate;
use App\Models\AgentCommand;
use App\Models\AgentCredential;
use App\Models\Company;
use App\Models\CompanyCertificate;
use App\Models\CompanyFiscalState;
use App\Models\SefazConnectivityTest;
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

    public function test_list_certificates_result_updates_agent_inventory(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'type' => CommandType::ListCertificates,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
        ]);

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])->assertOk();

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/complete", [
            'result' => $this->listCertificatesFixture(),
        ])->assertOk()->assertJsonPath('status', CommandStatus::Completed->value);

        $this->assertDatabaseHas('agent_certificates', [
            'tenant_id' => $agent->tenant_id,
            'agent_id' => $agent->id,
            'company_id' => $agent->company_id,
            'thumbprint' => 'ABC123456789',
            'cnpj' => '12345678000195',
            'subject' => 'CN=Empresa Teste:12345678000195',
            'issuer' => 'CN=AC Teste',
            'store_location' => 'CurrentUser',
            'store_scope' => 'current_user',
            'has_private_key' => true,
            'is_expired' => false,
            'is_valid' => true,
        ]);
    }

    public function test_list_certificates_result_sanitizes_sensitive_raw_payload(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'type' => CommandType::ListCertificates,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
        ]);
        $fixture = $this->listCertificatesFixture();
        $fixture['certificates'][0]['private_key'] = 'secret-key-material';

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])->assertOk();
        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/complete", [
            'result' => $fixture,
        ])->assertOk();

        /** @var AgentCertificate $certificate */
        $certificate = AgentCertificate::query()->where('thumbprint', 'ABC123456789')->firstOrFail();

        $rawPayload = $certificate->raw_payload;
        $this->assertIsArray($rawPayload);
        $this->assertArrayNotHasKey('private_key', $rawPayload);
    }

    public function test_list_certificates_result_rejects_known_sensitive_fields(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'type' => CommandType::ListCertificates,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
        ]);
        $fixture = $this->listCertificatesFixture();
        $fixture['certificates'][0]['pin'] = '123456';

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])->assertOk();
        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/complete", [
            'result' => $fixture,
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('agent_certificates', [
            'agent_id' => $agent->id,
            'thumbprint' => 'ABC123456789',
        ]);
    }

    public function test_list_certificates_result_updates_existing_certificate_by_thumbprint_and_store(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();
        AgentCertificate::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'thumbprint' => 'ABC123456789',
            'store_location' => 'CurrentUser',
            'subject' => 'Old subject',
        ]);
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'type' => CommandType::ListCertificates,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
        ]);

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])->assertOk();
        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/complete", [
            'result' => $this->listCertificatesFixture(),
        ])->assertOk();

        $this->assertSame(2, AgentCertificate::query()->where('agent_id', $agent->id)->count());
        $this->assertDatabaseHas('agent_certificates', [
            'agent_id' => $agent->id,
            'thumbprint' => 'ABC123456789',
            'store_location' => 'CurrentUser',
            'subject' => 'CN=Empresa Teste:12345678000195',
        ]);
    }

    public function test_test_certificate_complete_updates_agent_certificate(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();
        $certificate = AgentCertificate::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'thumbprint' => 'ABC123456789',
            'store_location' => 'CurrentUser',
        ]);
        $otherCertificate = AgentCertificate::factory()->create([
            'thumbprint' => 'ABC123456789',
            'last_test_status' => null,
        ]);
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'type' => CommandType::TestCertificate,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
            'payload' => [
                'thumbprint' => 'ABC123456789',
                'store_location' => 'CurrentUser',
                'correlation_id' => fake()->uuid(),
                'agent_certificate_uuid' => $certificate->uuid,
            ],
        ]);

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])->assertOk();
        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/complete", [
            'result' => $this->test_certificate_fixture(),
        ])->assertOk();

        $this->assertDatabaseHas('agent_certificates', [
            'id' => $certificate->id,
            'last_test_status' => 'valid',
            'last_test_message' => 'Certificado validado com sucesso.',
            'last_test_error_code' => null,
        ]);
        $otherCertificate->refresh();
        $this->assertNull($otherCertificate->last_test_status);
    }

    public function test_test_certificate_fail_updates_agent_certificate(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();
        $certificate = AgentCertificate::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'thumbprint' => 'ABC123456789',
            'store_location' => 'CurrentUser',
        ]);
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'type' => CommandType::TestCertificate,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
            'max_attempts' => 3,
            'payload' => [
                'thumbprint' => 'ABC123456789',
                'store_location' => 'CurrentUser',
                'correlation_id' => fake()->uuid(),
                'agent_certificate_uuid' => $certificate->uuid,
            ],
        ]);

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])->assertOk();
        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/fail", [
            'error_code' => 'CERTIFICATE_WITHOUT_PRIVATE_KEY',
            'error_message' => 'Certificate does not have a private key.',
        ])->assertOk();

        $this->assertDatabaseHas('agent_certificates', [
            'id' => $certificate->id,
            'last_test_status' => 'failed',
            'last_test_error_code' => 'CERTIFICATE_WITHOUT_PRIVATE_KEY',
            'last_test_message' => 'Certificate does not have a private key.',
        ]);
    }

    public function test_test_certificate_result_updates_company_certificate(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();
        $certificate = CompanyCertificate::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'thumbprint' => 'ABC123456789',
            'store_scope' => 'current_user',
            'last_test_status' => null,
        ]);
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'type' => CommandType::TestCertificate,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
            'payload' => [
                'thumbprint' => 'ABC123456789',
                'store_location' => 'CurrentUser',
                'correlation_id' => fake()->uuid(),
                'company_certificate_uuid' => $certificate->uuid,
            ],
        ]);

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])->assertOk();
        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/complete", [
            'result' => $this->test_certificate_fixture(),
        ])->assertOk();

        $this->assertDatabaseHas('company_certificates', [
            'id' => $certificate->id,
            'last_test_status' => 'valid',
            'last_test_message' => 'Certificado validado com sucesso.',
        ]);
    }

    public function test_test_certificate_result_sanitizes_sensitive_payload(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();
        $certificate = AgentCertificate::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'thumbprint' => 'ABC123456789',
            'store_location' => 'CurrentUser',
        ]);
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'type' => CommandType::TestCertificate,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
            'payload' => [
                'thumbprint' => 'ABC123456789',
                'store_location' => 'CurrentUser',
                'agent_certificate_uuid' => $certificate->uuid,
            ],
        ]);
        $fixture = $this->test_certificate_fixture();
        $fixture['certificate']['private_key'] = 'secret-key-material';

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])->assertOk();
        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/complete", [
            'result' => $fixture,
        ])->assertOk();

        $certificate->refresh();
        $payload = $certificate->last_test_payload;
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('private_key', $payload['certificate'] ?? []);
    }

    public function test_sefaz_connectivity_complete_updates_history(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();
        $certificate = CompanyCertificate::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'thumbprint' => 'ABC123456789',
        ]);
        $test = SefazConnectivityTest::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'company_certificate_id' => $certificate->id,
        ]);
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'type' => CommandType::TestSefazConnectivity,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
            'payload' => [
                'thumbprint' => 'ABC123456789',
                'sefaz_connectivity_test_uuid' => $test->uuid,
            ],
        ]);
        $test->forceFill(['agent_command_id' => $command->id])->save();

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])->assertOk();
        $this->assertDatabaseHas('sefaz_connectivity_tests', ['id' => $test->id, 'status' => 'processing']);

        $fixture = $this->test_sefaz_connectivity_fixture();
        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/complete", [
            'result' => $fixture,
            'sefaz_message' => $fixture['sefaz_message'],
            'duration_ms' => $fixture['duration_ms'],
        ])->assertOk();

        $this->assertDatabaseHas('sefaz_connectivity_tests', [
            'id' => $test->id,
            'status' => 'success',
            'endpoint' => 'https://hom1.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx',
            'sefaz_message' => 'Configuração validada com sucesso.',
            'duration_ms' => 0,
        ]);
    }

    public function test_sefaz_connectivity_fail_updates_history(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();
        $test = SefazConnectivityTest::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
        ]);
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'type' => CommandType::TestSefazConnectivity,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
            'max_attempts' => 3,
            'payload' => [
                'thumbprint' => 'ABC123456789',
                'sefaz_connectivity_test_uuid' => $test->uuid,
            ],
        ]);
        $test->forceFill(['agent_command_id' => $command->id])->save();

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])->assertOk();
        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/fail", [
            'error_code' => 'SEFAZ_LIVE_TEST_NOT_CONFIGURED',
            'error_message' => 'Live test is not configured.',
            'duration_ms' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('sefaz_connectivity_tests', [
            'id' => $test->id,
            'status' => 'failed',
            'error_code' => 'SEFAZ_LIVE_TEST_NOT_CONFIGURED',
            'error_message' => 'Live test is not configured.',
            'duration_ms' => 1,
        ]);
    }

    public function test_sync_fiscal_documents_complete_persists_documents_and_nsu_state(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'type' => CommandType::SyncFiscalDocuments,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
            'payload' => [
                'cnpj' => '12345678000195',
                'uf' => 'SP',
                'environment' => 'homologation',
                'last_nsu' => '000000000000010',
            ],
        ]);

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])->assertOk();

        $fixture = $this->sync_fiscal_documents_fixture();
        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/complete", [
            'result' => $fixture,
            'sefaz_status_code' => '138',
            'sefaz_message' => 'Documento localizado',
            'duration_ms' => 25,
            'request_xml' => [
                'storage_disk' => 'local',
                'storage_path' => 'tmp/request.xml',
                'content_hash' => hash('sha256', 'request'),
            ],
            'response_xml' => [
                'storage_disk' => 'local',
                'storage_path' => 'tmp/response.xml',
                'content_hash' => hash('sha256', 'response'),
            ],
        ])->assertOk()->assertJsonPath('status', CommandStatus::Completed->value);

        $this->assertDatabaseHas('company_fiscal_states', [
            'company_id' => $agent->company_id,
            'environment' => 'homologation',
            'uf' => 'SP',
            'service' => 'nfe_distribution',
            'last_nsu' => '000000000000011',
            'max_nsu' => '000000000000011',
            'last_status_code' => '138',
        ]);
        $this->assertDatabaseHas('fiscal_documents', [
            'company_id' => $agent->company_id,
            'access_key' => '35260512345678000195550010000000011000000010',
            'nsu' => '000000000000011',
            'issuer_cnpj' => '12345678000195',
        ]);
        $this->assertDatabaseHas('fiscal_document_summaries', [
            'company_id' => $agent->company_id,
            'content_hash' => hash('sha256', (string) $fixture['documents'][0]['summary_xml']),
        ]);
        $this->assertDatabaseHas('sefaz_requests', [
            'company_id' => $agent->company_id,
            'service' => 'nfe_distribution',
            'environment' => 'homologation',
        ]);
        $this->assertDatabaseHas('sefaz_responses', [
            'company_id' => $agent->company_id,
            'sefaz_status_code' => '138',
        ]);
    }

    public function test_sync_fiscal_documents_consumption_denied_records_error_without_advancing_nsu(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();
        CompanyFiscalState::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'environment' => 'homologation',
            'uf' => 'SP',
            'service' => 'nfe_distribution',
            'last_nsu' => '000000000000010',
            'max_nsu' => '000000000000010',
        ]);
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'type' => CommandType::SyncFiscalDocuments,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
            'max_attempts' => 1,
            'payload' => [
                'cnpj' => '12345678000195',
                'uf' => 'SP',
                'environment' => 'homologation',
                'last_nsu' => '000000000000010',
            ],
        ]);

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])->assertOk();
        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/fail", [
            'error_code' => 'SEFAZ_DISTRIBUTION_CONSUMPTION_DENIED',
            'error_message' => 'Consumo indevido',
            'sefaz_status_code' => '656',
            'sefaz_message' => 'Consumo indevido',
            'duration_ms' => 10,
        ])->assertOk()
            ->assertJsonPath('status', CommandStatus::Failed->value)
            ->assertJsonPath('final', true);

        $this->assertDatabaseHas('company_fiscal_states', [
            'company_id' => $agent->company_id,
            'last_nsu' => '000000000000010',
            'last_status_code' => '656',
            'last_message' => 'Consumo indevido',
            'distribution_block_reason' => 'consumption_denied',
        ]);
        $this->assertNotNull(CompanyFiscalState::query()->where('company_id', $agent->company_id)->firstOrFail()->distribution_blocked_until);
    }

    public function test_sync_fiscal_documents_no_documents_applies_safe_cooldown(): void
    {
        config(['sefaz.distribution.no_documents_cooldown_minutes' => 60]);
        [$agent, $secret] = $this->createAgentWithSecret();
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'type' => CommandType::SyncFiscalDocuments,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
            'payload' => [
                'cnpj' => '12345678000195',
                'uf' => 'SP',
                'environment' => 'homologation',
                'last_nsu' => '000000000000010',
            ],
        ]);

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])->assertOk();
        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/complete", [
            'result' => [
                'service' => 'nfe_distribution',
                'environment' => 'homologation',
                'uf' => 'SP',
                'last_nsu' => '000000000000010',
                'max_nsu' => '000000000000010',
                'sefaz_status_code' => '137',
                'sefaz_message' => 'Nenhum documento localizado',
                'distribution_result' => 'no_documents',
                'documents' => [],
            ],
            'sefaz_status_code' => '137',
            'sefaz_message' => 'Nenhum documento localizado',
        ])->assertOk();

        $state = CompanyFiscalState::query()->where('company_id', $agent->company_id)->firstOrFail();

        $this->assertSame('000000000000010', $state->last_nsu);
        $this->assertSame('137', $state->last_distribution_status_code);
        $this->assertSame('no_documents', $state->distribution_block_reason);
        $this->assertNotNull($state->next_distribution_available_at);
    }

    public function test_sync_fiscal_documents_documents_found_with_pending_nsu_allows_continuation(): void
    {
        config(['sefaz.distribution.allow_immediate_continue_when_nsu_pending' => true]);
        [$agent, $secret] = $this->createAgentWithSecret();
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'type' => CommandType::SyncFiscalDocuments,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
            'payload' => [
                'cnpj' => '12345678000195',
                'uf' => 'SP',
                'environment' => 'homologation',
                'last_nsu' => '000000000000010',
            ],
        ]);

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])->assertOk();
        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/complete", [
            'result' => [
                'service' => 'nfe_distribution',
                'environment' => 'homologation',
                'uf' => 'SP',
                'last_nsu' => '000000000000010',
                'max_nsu' => '000000000000011',
                'sefaz_status_code' => '138',
                'sefaz_message' => 'Documento localizado',
                'distribution_result' => 'documents_found',
                'documents' => [],
            ],
            'sefaz_status_code' => '138',
            'sefaz_message' => 'Documento localizado',
        ])->assertOk();

        $state = CompanyFiscalState::query()->where('company_id', $agent->company_id)->firstOrFail();

        $this->assertNull($state->next_distribution_available_at);
        $this->assertNull($state->distribution_blocked_until);
    }

    public function test_sync_fiscal_documents_documents_found_at_max_nsu_applies_safe_cooldown(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'type' => CommandType::SyncFiscalDocuments,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
            'payload' => [
                'cnpj' => '12345678000195',
                'uf' => 'SP',
                'environment' => 'homologation',
                'last_nsu' => '000000000000010',
            ],
        ]);

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])->assertOk();
        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/complete", [
            'result' => [
                'service' => 'nfe_distribution',
                'environment' => 'homologation',
                'uf' => 'SP',
                'last_nsu' => '000000000000011',
                'max_nsu' => '000000000000011',
                'sefaz_status_code' => '138',
                'sefaz_message' => 'Documento localizado',
                'distribution_result' => 'documents_found',
                'documents' => [],
            ],
            'sefaz_status_code' => '138',
            'sefaz_message' => 'Documento localizado',
        ])->assertOk();

        $state = CompanyFiscalState::query()->where('company_id', $agent->company_id)->firstOrFail();

        $this->assertSame('documents_found_completed', $state->distribution_block_reason);
        $this->assertNotNull($state->next_distribution_available_at);
    }

    public function test_sync_fiscal_documents_schema_validation_failure_records_technical_error_without_advancing_nsu(): void
    {
        [$agent, $secret] = $this->createAgentWithSecret();
        CompanyFiscalState::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'environment' => 'homologation',
            'uf' => 'SP',
            'service' => 'nfe_distribution',
            'last_nsu' => '000000000000010',
            'max_nsu' => '000000000000010',
        ]);
        $command = AgentCommand::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'type' => CommandType::SyncFiscalDocuments,
            'status' => CommandStatus::Locked,
            'locked_by_agent_id' => $agent->id,
            'lock_expires_at' => now()->addMinutes(5),
            'max_attempts' => 1,
            'payload' => [
                'cnpj' => '12345678000195',
                'uf' => 'SP',
                'environment' => 'homologation',
                'last_nsu' => '000000000000010',
            ],
        ]);

        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/start", [])->assertOk();
        $this->postJsonSigned($agent, $secret, "/api/agent/v1/commands/{$command->uuid}/fail", [
            'error_code' => 'SEFAZ_XML_SCHEMA_INVALID',
            'error_message' => 'XML rejected by technical schema validation.',
            'error_details' => [
                'schema_name' => 'distDFeInt_v1.01.xsd',
                'root_element' => 'distDFeInt',
                'validation_errors' => [
                    ['message' => 'The element distDFeInt has invalid child element.'],
                ],
            ],
            'duration_ms' => 10,
        ])->assertOk()->assertJsonPath('status', CommandStatus::Failed->value);

        $this->assertDatabaseHas('agent_command_attempts', [
            'agent_command_id' => $command->id,
            'error_code' => 'SEFAZ_XML_SCHEMA_INVALID',
        ]);
        $this->assertDatabaseHas('company_fiscal_states', [
            'company_id' => $agent->company_id,
            'last_nsu' => '000000000000010',
            'last_status_code' => null,
            'last_message' => 'XML rejeitado pela validação técnica antes do envio à SEFAZ.',
            'distribution_block_reason' => 'technical_error',
        ]);
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

    /** @return array{certificates: list<array<string, mixed>>} */
    private function listCertificatesFixture(): array
    {
        $contents = file_get_contents(base_path('tests/Fixtures/list-certificates-result.json'));
        $this->assertIsString($contents);

        /** @var array{certificates: list<array<string, mixed>>} $payload */
        $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }

    /** @return array{certificate: array<string, mixed>} */
    private function test_certificate_fixture(): array
    {
        $contents = file_get_contents(base_path('tests/Fixtures/test-certificate-result.json'));
        $this->assertIsString($contents);

        /** @var array{certificate: array<string, mixed>} $payload */
        $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }

    /** @return array<string, mixed> */
    private function test_sefaz_connectivity_fixture(): array
    {
        $contents = file_get_contents(base_path('tests/Fixtures/test-sefaz-connectivity-result.json'));
        $this->assertIsString($contents);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }

    /** @return array<string, mixed> */
    private function sync_fiscal_documents_fixture(): array
    {
        $contents = file_get_contents(base_path('tests/Fixtures/sync-fiscal-documents-result.json'));
        $this->assertIsString($contents);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $payload;
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
