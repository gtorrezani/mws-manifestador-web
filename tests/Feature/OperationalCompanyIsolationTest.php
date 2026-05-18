<?php

namespace Tests\Feature;

use App\Enums\AgentStatus;
use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Enums\CommandType;
use App\Enums\ManifestationEventType;
use App\Enums\ManifestationStatus;
use App\Enums\XmlDownloadStatus;
use App\Models\Agent;
use App\Models\AgentCertificate;
use App\Models\AgentCommand;
use App\Models\Company;
use App\Models\CompanyCertificate;
use App\Models\CompanyFiscalState;
use App\Models\FiscalDocument;
use App\Models\SefazRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\CompanyContext\CurrentCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationalCompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_metrics_are_scoped_to_current_company(): void
    {
        [$current, $other] = $this->companies();

        FiscalDocument::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
            'manifestation_status' => ManifestationStatus::NoManifestation,
            'xml_download_status' => XmlDownloadStatus::Available,
        ]);
        FiscalDocument::factory()->create([
            'tenant_id' => $other->tenant_id,
            'company_id' => $other->id,
            'manifestation_status' => ManifestationStatus::NoManifestation,
            'xml_download_status' => XmlDownloadStatus::Available,
        ]);
        Agent::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
            'status' => AgentStatus::Online,
        ]);
        Agent::factory()->create([
            'tenant_id' => $other->tenant_id,
            'company_id' => $other->id,
            'status' => AgentStatus::Online,
        ]);

        $this
            ->withCurrentCompany($current)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('metrics.documentsFound', 1)
                ->where('metrics.xmlDownloaded', 1)
                ->where('metrics.pendingAcknowledgement', 1)
                ->where('metrics.agentsOnline', 1));
    }

    public function test_fiscal_document_list_ignores_malicious_company_filter(): void
    {
        [$current, $other] = $this->companies();
        $visible = FiscalDocument::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
        ]);
        FiscalDocument::factory()->create([
            'tenant_id' => $other->tenant_id,
            'company_id' => $other->id,
        ]);

        $this
            ->withCurrentCompany($current)
            ->get('/fiscal-documents?company_id='.$other->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('documents.data', 1)
                ->where('documents.data.0.id', $visible->id)
                ->missing('companies'));
    }

    public function test_manifestation_for_document_from_another_company_is_blocked(): void
    {
        [$current, $other] = $this->companies();
        $document = FiscalDocument::factory()->create([
            'tenant_id' => $other->tenant_id,
            'company_id' => $other->id,
        ]);

        $this
            ->withCurrentCompany($current)
            ->post("/fiscal-documents/{$document->id}/manifest", [
                'event_type' => ManifestationEventType::OperationAcknowledgement->value,
                'confirmed' => true,
            ])
            ->assertNotFound();
    }

    public function test_bulk_actions_only_process_current_company_documents(): void
    {
        [$current, $other] = $this->companies();
        $currentDocument = FiscalDocument::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
        ]);
        $otherDocument = FiscalDocument::factory()->create([
            'tenant_id' => $other->tenant_id,
            'company_id' => $other->id,
        ]);

        $this
            ->withCurrentCompany($current)
            ->post('/fiscal-documents/bulk', [
                'action' => 'download_xml',
                'document_ids' => [$currentDocument->id, $otherDocument->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('agent_commands', [
            'company_id' => $current->id,
            'type' => CommandType::DownloadXmlByAccessKey->value,
        ]);
        $this->assertDatabaseMissing('agent_commands', [
            'company_id' => $other->id,
            'type' => CommandType::DownloadXmlByAccessKey->value,
        ]);
    }

    public function test_agents_are_scoped_and_activation_uses_current_company(): void
    {
        [$current, $other] = $this->companies();
        $visibleAgent = Agent::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
        ]);
        $otherAgent = Agent::factory()->create([
            'tenant_id' => $other->tenant_id,
            'company_id' => $other->id,
        ]);

        $this
            ->withCurrentCompany($current)
            ->get('/agents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('agents.data', 1)
                ->where('agents.data.0.id', $visibleAgent->id)
                ->missing('companies'));

        $this
            ->withCurrentCompany($current)
            ->post('/agents/activation-code', ['company_id' => $other->id])
            ->assertRedirect()
            ->assertSessionHas('activationCode');

        $this->assertDatabaseHas('agent_activations', ['company_id' => $current->id]);
        $this->assertDatabaseMissing('agent_activations', ['company_id' => $other->id]);

        $this
            ->withCurrentCompany($current)
            ->post("/agents/{$otherAgent->id}/revoke")
            ->assertNotFound();
    }

    public function test_certificates_are_scoped_and_cross_company_operations_are_blocked(): void
    {
        [$current, $other] = $this->companies();
        $currentCertificate = CompanyCertificate::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
        ]);
        $currentAgent = Agent::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
            'status' => AgentStatus::Online,
        ]);
        $currentCertificate->forceFill([
            'agent_id' => $currentAgent->id,
            'type' => CertificateType::A3,
            'status' => CertificateStatus::Active,
            'thumbprint' => 'COMPANYCERT123',
            'store_scope' => 'current_user',
            'last_test_status' => 'valid',
        ])->save();
        $currentAgentCertificate = AgentCertificate::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
            'agent_id' => $currentAgent->id,
            'subject' => 'CN=Current Company',
        ]);
        $otherCertificate = CompanyCertificate::factory()->create([
            'tenant_id' => $other->tenant_id,
            'company_id' => $other->id,
        ]);
        $otherAgent = Agent::factory()->create([
            'tenant_id' => $other->tenant_id,
            'company_id' => $other->id,
            'status' => AgentStatus::Online,
        ]);
        $otherAgentCertificate = AgentCertificate::factory()->create([
            'tenant_id' => $other->tenant_id,
            'company_id' => $other->id,
            'agent_id' => $otherAgent->id,
        ]);

        $this
            ->withCurrentCompany($current)
            ->get('/certificates')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('companyCertificates.data', 1)
                ->where('companyCertificates.data.0.id', $currentCertificate->id)
                ->has('agentCertificates.data', 1)
                ->where('agentCertificates.data.0.id', $currentAgentCertificate->id)
                ->has('agents', 1)
                ->where('agents.0.id', $currentAgent->id));

        $this
            ->withCurrentCompany($current)
            ->post('/certificates/a3/link', [
                'agent_certificate_id' => $otherAgentCertificate->id,
                'name' => 'Cross company certificate',
            ])
            ->assertNotFound();

        $this
            ->withCurrentCompany($current)
            ->post("/certificates/{$otherCertificate->id}/test")
            ->assertNotFound();

        $this
            ->withCurrentCompany($current)
            ->post("/certificates/{$currentCertificate->id}/test")
            ->assertRedirect();

        $companyCertificateCommand = AgentCommand::query()
            ->where('type', CommandType::TestCertificate)
            ->where('agent_id', $currentAgent->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($current->id, $companyCertificateCommand->company_id);
        $this->assertSame($currentCertificate->thumbprint, $companyCertificateCommand->payload['thumbprint'] ?? null);
        $this->assertSame('CurrentUser', $companyCertificateCommand->payload['store_location'] ?? null);
        $this->assertSame($currentCertificate->uuid, $companyCertificateCommand->payload['company_certificate_uuid'] ?? null);

        $this
            ->withCurrentCompany($current)
            ->post("/certificates/agent-certificate/{$currentAgentCertificate->id}/test")
            ->assertRedirect();

        $command = AgentCommand::query()
            ->where('type', CommandType::TestCertificate)
            ->where('agent_id', $currentAgent->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($current->id, $command->company_id);
        $this->assertSame($currentAgentCertificate->thumbprint, $command->payload['thumbprint'] ?? null);
        $this->assertSame('CurrentUser', $command->payload['store_location'] ?? null);
        $this->assertArrayNotHasKey('pin', $command->payload);

        $this
            ->withCurrentCompany($current)
            ->post("/certificates/agent-certificate/{$otherAgentCertificate->id}/test")
            ->assertNotFound();

        $this
            ->withCurrentCompany($current)
            ->post("/certificates/{$currentCertificate->id}/test-sefaz-connectivity", [
                'mode' => 'configuration_only',
            ])
            ->assertRedirect();

        $sefazCommand = AgentCommand::query()
            ->where('type', CommandType::TestSefazConnectivity)
            ->where('agent_id', $currentAgent->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($current->id, $sefazCommand->company_id);
        $this->assertSame($currentCertificate->thumbprint, $sefazCommand->payload['thumbprint'] ?? null);
        $this->assertSame('CurrentUser', $sefazCommand->payload['store_location'] ?? null);
        $this->assertArrayNotHasKey('pin', $sefazCommand->payload);
        $this->assertDatabaseHas('sefaz_connectivity_tests', [
            'company_id' => $current->id,
            'agent_id' => $currentAgent->id,
            'agent_command_id' => $sefazCommand->id,
            'status' => 'pending',
        ]);

        $this
            ->withCurrentCompany($current)
            ->post("/certificates/{$otherCertificate->id}/test-sefaz-connectivity", [
                'mode' => 'configuration_only',
            ])
            ->assertNotFound();

        $this
            ->withCurrentCompany($current)
            ->post("/certificates/agent/{$currentAgent->id}/list")
            ->assertRedirect();

        $this->assertDatabaseHas('agent_commands', [
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
            'agent_id' => $currentAgent->id,
            'type' => CommandType::ListCertificates->value,
        ]);

        $this
            ->withCurrentCompany($current)
            ->post("/certificates/agent/{$otherAgent->id}/list")
            ->assertNotFound();

        $this
            ->withCurrentCompany($current)
            ->post('/fiscal-documents/sync', ['company_id' => $other->id])
            ->assertRedirect();

        $syncCommand = AgentCommand::query()
            ->where('type', CommandType::SyncFiscalDocuments)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($current->id, $syncCommand->company_id);
        $this->assertSame($currentAgent->id, $syncCommand->agent_id);
        $this->assertSame($current->cnpj, $syncCommand->payload['cnpj'] ?? null);
        $this->assertSame('CurrentUser', $syncCommand->payload['store_location'] ?? null);
        $this->assertArrayNotHasKey('pin', $syncCommand->payload);
    }

    public function test_history_is_scoped_to_current_company(): void
    {
        [$current, $other] = $this->companies();
        $visibleCommand = AgentCommand::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
        ]);
        AgentCommand::factory()->create([
            'tenant_id' => $other->tenant_id,
            'company_id' => $other->id,
        ]);
        $visibleRequest = SefazRequest::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
        ]);
        SefazRequest::factory()->create([
            'tenant_id' => $other->tenant_id,
            'company_id' => $other->id,
        ]);

        $this
            ->withCurrentCompany($current)
            ->get('/history')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('commands.data', 1)
                ->where('commands.data.0.id', $visibleCommand->id)
                ->has('sefazRequests', 1)
                ->where('sefazRequests.0.id', $visibleRequest->id));
    }

    public function test_distribution_cooldown_blocks_new_sync_command_for_current_company(): void
    {
        [$current] = $this->companies();
        $agent = Agent::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
            'status' => AgentStatus::Online,
        ]);
        CompanyCertificate::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
            'agent_id' => $agent->id,
            'type' => CertificateType::A3,
            'status' => CertificateStatus::Active,
            'thumbprint' => 'COOLDOWNCERT123',
            'store_scope' => 'CurrentUser',
            'last_test_status' => 'valid',
        ]);
        CompanyFiscalState::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
            'environment' => $current->fiscal_environment->value,
            'uf' => $current->uf,
            'service' => 'nfe_distribution',
            'next_distribution_available_at' => now()->addHour(),
            'distribution_block_reason' => 'no_documents',
        ]);

        $this
            ->withCurrentCompany($current)
            ->post('/fiscal-documents/sync')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('agent_commands', [
            'company_id' => $current->id,
            'type' => CommandType::SyncFiscalDocuments->value,
        ]);
    }

    public function test_fiscal_documents_page_exposes_distribution_block_message(): void
    {
        [$current] = $this->companies();
        CompanyFiscalState::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
            'environment' => $current->fiscal_environment->value,
            'uf' => $current->uf,
            'service' => 'nfe_distribution',
            'distribution_blocked_until' => now()->addHour(),
            'distribution_block_reason' => 'consumption_denied',
        ]);

        $this
            ->withCurrentCompany($current)
            ->get('/fiscal-documents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('distributionAvailability.allowed', false)
                ->where('distributionAvailability.reason', 'consumption_denied'));
    }

    public function test_settings_are_scoped_to_current_company(): void
    {
        [$current, $other] = $this->companies();

        SystemSetting::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
            'key' => 'sync_frequency_minutes',
            'value' => ['value' => 15],
        ]);
        SystemSetting::factory()->create([
            'tenant_id' => $other->tenant_id,
            'company_id' => $other->id,
            'key' => 'sync_frequency_minutes',
            'value' => ['value' => 60],
        ]);

        $this
            ->withCurrentCompany($current)
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.sync_frequency_minutes.company_id', $current->id)
                ->where('settings.sync_frequency_minutes.value.value', 15)
                ->where('fiscalState.company_id', $current->id));

        $this
            ->withCurrentCompany($current)
            ->put('/settings', [
                'last_nsu' => '000000000000123',
                'retention_days' => 365,
                'sync_frequency_minutes' => 30,
                'automation_rules' => [],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('system_settings', [
            'company_id' => $current->id,
            'key' => 'retention_days',
        ]);
        $this->assertDatabaseHas('company_fiscal_states', [
            'company_id' => $current->id,
            'last_nsu' => '000000000000123',
        ]);
        $this->assertDatabaseMissing('system_settings', [
            'company_id' => $other->id,
            'key' => 'retention_days',
        ]);
    }

    public function test_agent_api_does_not_depend_on_session_company_context(): void
    {
        [$current, $other] = $this->companies();
        $agent = Agent::factory()->create([
            'tenant_id' => $other->tenant_id,
            'company_id' => $other->id,
            'status' => AgentStatus::Online,
        ]);

        $this
            ->withCurrentCompany($current)
            ->postJson('/api/agent/v1/heartbeat', [
                'status' => AgentStatus::Online->value,
                'version' => '1.0.0',
            ], [
                'X-MWS-Agent-Id' => $agent->uuid,
            ])
            ->assertUnauthorized();
    }

    /** @return array{0: Company, 1: Company} */
    private function companies(): array
    {
        $current = Company::factory()->create(['legal_name' => 'Current Company']);
        $other = Company::factory()->create(['tenant_id' => $current->tenant_id, 'legal_name' => 'Other Company']);

        return [$current, $other];
    }

    private function withCurrentCompany(Company $company): self
    {
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        return $this
            ->actingAs($user)
            ->withSession([CurrentCompanyContext::SESSION_KEY => $company->id]);
    }
}
