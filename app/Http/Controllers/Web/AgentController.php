<?php

namespace App\Http\Controllers\Web;

use App\Actions\Agent\CreateAgentActivationCodeAction;
use App\Enums\AgentStatus;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Http\Controllers\Concerns\AuthorizesCurrentCompany;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentActivation;
use App\Models\AgentCommand;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Agent\AgentStatusResolver;
use App\Support\CompanyContext\CurrentCompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentController extends Controller
{
    use AuthorizesCurrentCompany;

    /** @var array<int, string> */
    private const array ALLOWED_INSTALLER_EXTENSIONS = ['msi', 'exe'];

    public function index(CurrentCompanyContext $context, AgentStatusResolver $statusResolver): Response
    {
        $company = $context->company();
        $agents = Agent::query()
            ->forCompany($company)
            ->with(['company:id,legal_name,cnpj'])
            ->latest('last_seen_at')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Agent $agent) use ($statusResolver): array {
                $operationalStatus = $statusResolver->resolve($agent);

                return [
                    'id' => $agent->id,
                    'uuid' => $agent->uuid,
                    'tenant_id' => $agent->tenant_id,
                    'company_id' => $agent->company_id,
                    'name' => $agent->name,
                    'machine_name' => $agent->machine_name,
                    'installation_id' => $agent->installation_id,
                    'version' => $agent->version,
                    'status' => $agent->status->value,
                    'operational_status' => $operationalStatus->value,
                    'can_request_diagnostics' => $statusResolver->canReceiveCommands($agent),
                    'last_seen_at' => $agent->last_seen_at?->toISOString(),
                    'activated_at' => $agent->activated_at?->toISOString(),
                    'revoked_at' => $agent->revoked_at?->toISOString(),
                    'company' => $agent->company ? [
                        'id' => $agent->company->id,
                        'legal_name' => $agent->company->legal_name,
                        'cnpj' => $agent->company->cnpj,
                    ] : null,
                ];
            });

        return Inertia::render('Agents/Index', [
            'agents' => $agents,
            'latestActivations' => AgentActivation::query()
                ->forCompany($company)
                ->with(['company:id,legal_name,cnpj'])
                ->latest('created_at')
                ->limit(5)
                ->get(),
            'agentConfig' => [
                'heartbeat_timeout_seconds' => (int) config('agent.heartbeat_timeout_seconds', 120),
                'minimum_supported_version' => config('agent.minimum_supported_version'),
                'installer_download_url' => $this->installerIsConfigured() ? route('agents.installer.download') : null,
                'installer_download_available' => $this->installerIsConfigured(),
                'installer_download_label' => $this->installerDownloadLabel(),
                'installer_status_message' => $this->installerStatusMessage(),
                'installer_version' => config('agent.installer_version'),
                'installer_sha256' => config('agent.installer_sha256'),
                'local_diagnostics_port' => (int) config('agent.local_diagnostics_port', 8787),
                'activation_code_ttl_minutes' => (int) config('agent.activation_code_ttl_minutes', 30),
                'show_advanced_install_commands' => (bool) config('agent.show_advanced_install_commands', false),
                'install_command' => $this->installCommand(),
                'dev_command' => 'dotnet run --project src\Mws.Manifestador.Agent.Worker\Mws.Manifestador.Agent.Worker.csproj --environment Development',
            ],
        ]);
    }

    public function activate(
        Request $request,
        CreateAgentActivationCodeAction $action,
        CurrentCompanyContext $context,
    ): RedirectResponse {
        $result = $action->execute($context->company(), $this->authenticatedUserId($request));

        return back()->with('activationCode', [
            'code' => $result['code'],
            'expires_at' => $result['activation']->expires_at,
        ]);
    }

    public function revoke(Agent $agent, CurrentCompanyContext $context): RedirectResponse
    {
        $this->abortUnlessBelongsToCurrentCompany($agent, $context);

        $agent->forceFill([
            'status' => AgentStatus::Revoked,
            'revoked_at' => now(),
        ])->save();

        return back()->with('success', 'Agente revogado.');
    }

    public function downloadInstaller(): RedirectResponse|StreamedResponse
    {
        $externalUrl = $this->externalInstallerUrl();
        if ($externalUrl !== null) {
            return redirect()->away($externalUrl);
        }

        $localPath = $this->localInstallerPath();
        if ($localPath !== null) {
            $disk = (string) config('agent.installer_local_disk', 'public');
            $fileName = (string) config('agent.installer_file_name', basename($localPath));

            return Storage::disk($disk)->download($localPath, $fileName);
        }

        return redirect()
            ->route('agents.index')
            ->with('error', 'Instalador oficial ainda nao configurado neste ambiente.');
    }

    public function requestDiagnostics(
        Agent $agent,
        Request $request,
        CurrentCompanyContext $context,
        AgentStatusResolver $statusResolver,
    ): RedirectResponse {
        $this->abortUnlessBelongsToCurrentCompany($agent, $context);

        if (! $statusResolver->canReceiveCommands($agent)) {
            return back()->with('error', 'O agente esta offline. Nao e possivel enviar comandos ate que o servico local esteja em execucao.');
        }

        AgentCommand::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $agent->tenant_id,
            'company_id' => $context->companyId(),
            'agent_id' => $agent->id,
            'type' => CommandType::AgentDiagnosticsRequested,
            'status' => CommandStatus::Pending,
            'priority' => 1,
            'payload' => [
                'requested_at' => now()->toISOString(),
                'local_diagnostics_port' => (int) config('agent.local_diagnostics_port', 8787),
            ],
            'available_at' => now(),
            'max_attempts' => 1,
            'idempotency_key' => 'agent-diagnostics:'.$agent->id.':'.Str::uuid(),
            'created_by' => $this->authenticatedUserId($request),
            'created_by_user_id' => $this->authenticatedUserId($request),
        ]);

        return back()->with('success', 'Comando de diagnostico enviado ao agente.');
    }

    public function diagnostics(Agent $agent, CurrentCompanyContext $context): Response
    {
        $this->abortUnlessBelongsToCurrentCompany($agent, $context);

        return Inertia::render('Agents/Diagnostics', [
            'agent' => $agent->load('company:id,legal_name,cnpj'),
            'diagnostics' => AuditLog::query()
                ->where('company_id', $context->companyId())
                ->where('agent_id', $agent->id)
                ->where('event', 'like', 'agent.diagnostics.%')
                ->latest('occurred_at')
                ->paginate(20),
        ]);
    }

    private function authenticatedUserId(Request $request): ?int
    {
        $user = $request->user();

        return $user instanceof User ? $user->id : null;
    }

    private function installCommand(): string
    {
        $apiBaseUrl = rtrim(url('/'), '/');

        return '.\scripts\install-service.ps1 -ApiBaseUrl "'.$apiBaseUrl.'" -ActivationCode "<codigo>"';
    }

    private function installerIsConfigured(): bool
    {
        return $this->externalInstallerUrl() !== null
            || $this->localInstallerPath() !== null;
    }

    private function externalInstallerUrl(): ?string
    {
        $url = config('agent.installer_download_url');

        if (! is_string($url) || trim($url) === '' || trim($url) === '#') {
            return null;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $this->hasAllowedInstallerExtension($url) ? $url : null;
    }

    private function localInstallerPath(): ?string
    {
        $path = config('agent.installer_local_path');
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (! $this->hasAllowedInstallerExtension($path)) {
            return null;
        }

        $disk = (string) config('agent.installer_local_disk', 'local');

        return Storage::disk($disk)->exists($path) ? $path : null;
    }

    private function installerDownloadLabel(): string
    {
        if ($this->externalInstallerUrl() !== null || $this->localInstallerPath() !== null) {
            return 'Baixar instalador';
        }

        return 'Instalador indisponivel';
    }

    private function installerStatusMessage(): string
    {
        if ($this->externalInstallerUrl() !== null) {
            return 'Instalador oficial publicado por URL externa configurada.';
        }

        if ($this->localInstallerPath() !== null) {
            return 'Instalador oficial local configurado para este ambiente.';
        }

        return 'Instalador oficial ainda nao configurado neste ambiente.';
    }

    private function hasAllowedInstallerExtension(string $pathOrUrl): bool
    {
        $path = parse_url($pathOrUrl, PHP_URL_PATH);
        if (! is_string($path) || trim($path) === '') {
            $path = $pathOrUrl;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, self::ALLOWED_INSTALLER_EXTENSIONS, true);
    }
}
