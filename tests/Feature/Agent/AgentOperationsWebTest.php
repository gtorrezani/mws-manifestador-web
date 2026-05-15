<?php

namespace Tests\Feature\Agent;

use App\Enums\AgentStatus;
use App\Enums\CommandType;
use App\Models\Agent;
use App\Models\AgentCommand;
use App\Models\Company;
use App\Support\CompanyContext\CurrentCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentOperationsWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_agents_page_shows_only_selected_company_agents_with_operational_status(): void
    {
        [$current, $other] = $this->companies();
        config([
            'agent.installer_download_url' => null,
            'agent.installer_local_path' => null,
            'agent.installer_version' => null,
            'agent.installer_sha256' => null,
        ]);

        $visibleAgent = Agent::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
            'activated_at' => now()->subHour(),
            'last_seen_at' => now(),
            'status' => AgentStatus::Online,
        ]);
        Agent::factory()->create([
            'tenant_id' => $other->tenant_id,
            'company_id' => $other->id,
            'activated_at' => now()->subHour(),
            'last_seen_at' => now(),
            'status' => AgentStatus::Online,
        ]);

        $this
            ->withCurrentCompany($current)
            ->get('/agents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('agents.data', 1)
                ->where('agents.data.0.id', $visibleAgent->id)
                ->where('agents.data.0.operational_status', 'online')
                ->where('agents.data.0.can_request_diagnostics', true)
                ->where('agentConfig.installer_download_available', false)
                ->where('agentConfig.installer_download_url', null)
                ->where('agentConfig.installer_download_label', 'Instalador indisponivel')
                ->where('agentConfig.installer_version', null)
                ->where('agentConfig.installer_sha256', null)
                ->where('agentConfig.show_advanced_install_commands', false));
    }

    public function test_agents_page_exposes_installer_download_route_when_configured(): void
    {
        [$current] = $this->companies();
        config(['agent.installer_download_url' => 'https://downloads.example.com/mws-agent.msi']);

        $this
            ->withCurrentCompany($current)
            ->get('/agents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('agentConfig.installer_download_available', true)
                ->where('agentConfig.installer_download_url', route('agents.installer.download'))
                ->where('agentConfig.installer_download_label', 'Baixar instalador')
                ->where('agentConfig.show_advanced_install_commands', false));
    }

    public function test_agents_page_exposes_installer_metadata_when_configured(): void
    {
        [$current] = $this->companies();
        config([
            'agent.installer_download_url' => 'https://downloads.example.com/mws-agent.msi',
            'agent.installer_version' => '1.2.3',
            'agent.installer_sha256' => str_repeat('a', 64),
        ]);

        $this
            ->withCurrentCompany($current)
            ->get('/agents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('agentConfig.installer_download_available', true)
                ->where('agentConfig.installer_version', '1.2.3')
                ->where('agentConfig.installer_sha256', str_repeat('a', 64)));
    }

    public function test_installer_download_redirects_to_external_url(): void
    {
        [$current] = $this->companies();
        config(['agent.installer_download_url' => 'https://downloads.example.com/mws-agent.msi']);

        $this
            ->withCurrentCompany($current)
            ->get('/agents/installer/download')
            ->assertRedirect('https://downloads.example.com/mws-agent.msi');
    }

    public function test_installer_download_streams_local_msi_file(): void
    {
        [$current] = $this->companies();
        Storage::fake('local');
        Storage::disk('local')->put('installers/mws-agent.msi', 'fake msi');
        config([
            'agent.installer_download_url' => null,
            'agent.installer_local_disk' => 'local',
            'agent.installer_local_path' => 'installers/mws-agent.msi',
            'agent.installer_file_name' => 'MWS-Agent.msi',
        ]);

        $this
            ->withCurrentCompany($current)
            ->get('/agents/installer/download')
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_installer_download_streams_local_exe_file(): void
    {
        [$current] = $this->companies();
        Storage::fake('local');
        Storage::disk('local')->put('installers/mws-agent.exe', 'fake exe');
        config([
            'agent.installer_download_url' => null,
            'agent.installer_local_disk' => 'local',
            'agent.installer_local_path' => 'installers/mws-agent.exe',
            'agent.installer_file_name' => 'MWS-Agent.exe',
        ]);

        $this
            ->withCurrentCompany($current)
            ->get('/agents/installer/download')
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_installer_download_redirects_with_message_when_not_configured(): void
    {
        [$current] = $this->companies();
        config([
            'agent.installer_download_url' => null,
            'agent.installer_local_path' => null,
        ]);

        $this
            ->withCurrentCompany($current)
            ->get('/agents/installer/download')
            ->assertRedirect('/agents')
            ->assertSessionHas('error', 'Instalador oficial ainda nao configurado neste ambiente.');
    }

    public function test_installer_download_rejects_non_installer_extension(): void
    {
        [$current] = $this->companies();
        Storage::fake('local');
        Storage::disk('local')->put('installers/mws-agent.txt', 'not an installer');
        config([
            'agent.installer_download_url' => null,
            'agent.installer_local_disk' => 'local',
            'agent.installer_local_path' => 'installers/mws-agent.txt',
        ]);

        $this
            ->withCurrentCompany($current)
            ->get('/agents/installer/download')
            ->assertRedirect('/agents')
            ->assertSessionHas('error', 'Instalador oficial ainda nao configurado neste ambiente.');
    }

    public function test_installer_download_rejects_missing_local_file(): void
    {
        [$current] = $this->companies();
        Storage::fake('local');
        config([
            'agent.installer_download_url' => null,
            'agent.installer_local_disk' => 'local',
            'agent.installer_local_path' => 'installers/missing.msi',
        ]);

        $this
            ->withCurrentCompany($current)
            ->get('/agents/installer/download')
            ->assertRedirect('/agents')
            ->assertSessionHas('error', 'Instalador oficial ainda nao configurado neste ambiente.');
    }

    public function test_agents_ui_does_not_reference_development_package(): void
    {
        $contents = file_get_contents(resource_path('js/Pages/Agents/Index.vue'));

        $this->assertIsString($contents);
        $this->assertStringNotContainsString(implode(' ', ['pacote', 'de', 'desenvolvimento']), $contents);
        $this->assertStringNotContainsString(implode('-', ['MWS', 'Agent', 'Development', 'Package']), $contents);
    }

    public function test_diagnostics_command_is_not_sent_to_offline_agent(): void
    {
        [$current] = $this->companies();
        config(['agent.heartbeat_timeout_seconds' => 60]);
        $agent = Agent::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
            'activated_at' => now()->subHour(),
            'last_seen_at' => now()->subMinutes(5),
            'status' => AgentStatus::Online,
        ]);

        $this
            ->withCurrentCompany($current)
            ->post("/agents/{$agent->id}/diagnostics/request")
            ->assertRedirect()
            ->assertSessionHas('error', 'O agente esta offline. Nao e possivel enviar comandos ate que o servico local esteja em execucao.');

        $this->assertDatabaseMissing('agent_commands', [
            'agent_id' => $agent->id,
            'type' => CommandType::AgentDiagnosticsRequested->value,
        ]);
    }

    public function test_diagnostics_command_is_created_for_online_agent(): void
    {
        [$current] = $this->companies();
        $agent = Agent::factory()->create([
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
            'activated_at' => now()->subHour(),
            'last_seen_at' => now(),
            'status' => AgentStatus::Online,
        ]);

        $this
            ->withCurrentCompany($current)
            ->post("/agents/{$agent->id}/diagnostics/request")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('agent_commands', [
            'tenant_id' => $current->tenant_id,
            'company_id' => $current->id,
            'agent_id' => $agent->id,
            'type' => CommandType::AgentDiagnosticsRequested->value,
        ]);
    }

    public function test_agent_from_another_company_cannot_be_controlled(): void
    {
        [$current, $other] = $this->companies();
        $otherAgent = Agent::factory()->create([
            'tenant_id' => $other->tenant_id,
            'company_id' => $other->id,
            'activated_at' => now()->subHour(),
            'last_seen_at' => now(),
            'status' => AgentStatus::Online,
        ]);

        $this
            ->withCurrentCompany($current)
            ->post("/agents/{$otherAgent->id}/diagnostics/request")
            ->assertNotFound();

        $this->assertSame(0, AgentCommand::query()->count());
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
        return $this->withSession([CurrentCompanyContext::SESSION_KEY => $company->id]);
    }
}
