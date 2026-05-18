<?php

namespace App\Http\Controllers\Web;

use App\Actions\Certificates\LinkA3CertificateAction;
use App\Actions\Certificates\StoreA1CertificateAction;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Http\Controllers\Concerns\AuthorizesCurrentCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Certificate\LinkA3CertificateRequest;
use App\Http\Requests\Certificate\StoreA1CertificateRequest;
use App\Http\Requests\Certificate\TestSefazConnectivityRequest;
use App\Models\Agent;
use App\Models\AgentCertificate;
use App\Models\AgentCommand;
use App\Models\CompanyCertificate;
use App\Models\SefazConnectivityTest;
use App\Models\User;
use App\Support\Cnpj;
use App\Support\CompanyContext\CurrentCompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    use AuthorizesCurrentCompany;

    public function index(CurrentCompanyContext $context): Response
    {
        $company = $context->company();

        return Inertia::render('Certificates/Index', [
            'agents' => Agent::query()
                ->forCompany($company)
                ->with('company:id,legal_name,cnpj')
                ->orderBy('name')
                ->get(['id', 'uuid', 'tenant_id', 'company_id', 'name', 'machine_name', 'version', 'status', 'last_seen_at']),
            'agentCertificates' => AgentCertificate::query()
                ->forCompany($company)
                ->with(['agent:id,name,machine_name,status', 'company:id,legal_name,cnpj'])
                ->where('is_fiscal_candidate', true)
                ->latest('last_seen_at')
                ->paginate(20)
                ->withQueryString(),
            'ignoredAgentCertificates' => AgentCertificate::query()
                ->forCompany($company)
                ->with(['agent:id,name,machine_name,status', 'company:id,legal_name,cnpj'])
                ->where('is_fiscal_candidate', false)
                ->latest('last_seen_at')
                ->limit(20)
                ->get(),
            'companyCertificates' => CompanyCertificate::query()
                ->forCompany($company)
                ->with(['company:id,legal_name,cnpj', 'agent:id,name,machine_name,status', 'agentCertificate:id,last_seen_at,has_private_key'])
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'sefazConnectivityTests' => SefazConnectivityTest::query()
                ->forCompany($company)
                ->with(['companyCertificate:id,name,thumbprint', 'agent:id,name,machine_name,status'])
                ->latest('requested_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function requestAgentInventory(Agent $agent, CurrentCompanyContext $context, Request $request): RedirectResponse
    {
        $this->abortUnlessBelongsToCurrentCompany($agent, $context);
        $includeRejected = $request->boolean('include_rejected');
        $includeExpired = $request->boolean('include_expired');
        $userId = $this->authenticatedUserId($request);

        AgentCommand::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $agent->tenant_id,
            'company_id' => $context->companyId(),
            'agent_id' => $agent->id,
            'type' => CommandType::ListCertificates,
            'status' => CommandStatus::Pending,
            'priority' => 5,
            'payload' => [
                'include_rejected' => $includeRejected,
                'include_expired' => $includeExpired,
            ],
            'available_at' => now(),
            'max_attempts' => 2,
            'idempotency_key' => 'list-certificates:'.$agent->id.':'.Str::uuid(),
            'created_by' => $userId,
            'created_by_user_id' => $userId,
        ]);

        return back()->with('success', $includeRejected
            ? 'Comando de diagnóstico completo de certificados enviado ao agente.'
            : 'Comando para listar certificados fiscais candidatos enviado ao agente.');
    }

    public function linkA3(
        LinkA3CertificateRequest $request,
        LinkA3CertificateAction $action,
        CurrentCompanyContext $context,
    ): RedirectResponse {
        $company = $context->company();

        /** @var AgentCertificate $agentCertificate */
        $agentCertificate = AgentCertificate::query()
            ->with('agent:id,company_id')
            ->findOrFail((int) $request->validated('agent_certificate_id'));

        if ($agentCertificate->tenant_id !== $company->tenant_id) {
            abort(404);
        }

        if ($agentCertificate->company_id !== null && $agentCertificate->company_id !== $company->id) {
            abort(404);
        }

        if ($agentCertificate->agent instanceof Agent && $agentCertificate->agent->company_id !== $company->id) {
            abort(404);
        }

        if (! $this->isLinkableFiscalCandidate($agentCertificate, $company->cnpj)) {
            return back()->with('error', 'Este certificado local não foi classificado como certificado fiscal ICP-Brasil utilizável.');
        }

        $action->execute(
            $company,
            $agentCertificate,
            is_string($request->validated('name')) ? $request->validated('name') : null,
        );

        return back()->with('success', 'Certificado fiscal local vinculado a empresa.');
    }

    public function storeA1(
        StoreA1CertificateRequest $request,
        StoreA1CertificateAction $action,
        CurrentCompanyContext $context,
    ): RedirectResponse {
        $file = $request->file('certificate_file');
        if (! $file) {
            abort(422, 'Arquivo do certificado não informado.');
        }

        $action->execute(
            $context->company(),
            $file,
            (string) $request->validated('password'),
            is_string($request->validated('name')) ? $request->validated('name') : null,
        );

        return back()->with('success', 'Certificado A1 validado e armazenado com segurança.');
    }

    public function test(CompanyCertificate $certificate, CurrentCompanyContext $context, Request $request): RedirectResponse
    {
        $this->abortUnlessBelongsToCurrentCompany($certificate, $context);

        if ($certificate->type->value === 'a1') {
            $certificate->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => 'valid',
                'last_test_message' => 'Certificado A1 ja foi validado no cadastro.',
                'last_validated_at' => now(),
            ])->save();

            return back()->with('success', 'Certificado A1 validado localmente.');
        }

        if (! $certificate->agent_id || ! $certificate->thumbprint) {
            return back()->with('success', 'Certificado local sem agente ou thumbprint para teste.');
        }

        $userId = $this->authenticatedUserId($request);

        $agent = Agent::query()
            ->where('id', $certificate->agent_id)
            ->where('tenant_id', $certificate->tenant_id)
            ->where('company_id', $context->companyId())
            ->firstOrFail();

        AgentCommand::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $certificate->tenant_id,
            'company_id' => $context->companyId(),
            'agent_id' => $agent->id,
            'type' => CommandType::TestCertificate,
            'status' => CommandStatus::Pending,
            'priority' => 5,
            'payload' => [
                'thumbprint' => $certificate->thumbprint,
                'store_location' => $this->storeLocation($certificate->store_scope),
                'correlation_id' => (string) Str::uuid(),
                'company_certificate_uuid' => $certificate->uuid,
            ],
            'available_at' => now(),
            'max_attempts' => 1,
            'idempotency_key' => 'test-certificate:'.$certificate->id.':'.Str::uuid(),
            'created_by' => $userId,
            'created_by_user_id' => $userId,
        ]);

        return back()->with('success', 'Comando de teste do certificado enviado ao agente.');
    }

    public function testAgentCertificate(AgentCertificate $certificate, CurrentCompanyContext $context, Request $request): RedirectResponse
    {
        $this->abortUnlessBelongsToCurrentCompany($certificate, $context);

        if (! $certificate->thumbprint || ! $certificate->agent_id) {
            return back()->with('success', 'Certificado sem agente ou thumbprint para teste.');
        }

        if (! $this->isUsableFiscalCandidate($certificate)) {
            return back()->with('error', 'Este certificado local não foi classificado como certificado fiscal ICP-Brasil utilizável.');
        }

        $userId = $this->authenticatedUserId($request);

        $agent = Agent::query()
            ->where('id', $certificate->agent_id)
            ->where('tenant_id', $certificate->tenant_id)
            ->where('company_id', $context->companyId())
            ->firstOrFail();

        AgentCommand::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $certificate->tenant_id,
            'company_id' => $context->companyId(),
            'agent_id' => $agent->id,
            'type' => CommandType::TestCertificate,
            'status' => CommandStatus::Pending,
            'priority' => 5,
            'payload' => [
                'thumbprint' => $certificate->thumbprint,
                'store_location' => $this->storeLocation($certificate->store_location ?? $certificate->store_scope),
                'correlation_id' => (string) Str::uuid(),
                'agent_certificate_uuid' => $certificate->uuid,
            ],
            'available_at' => now(),
            'max_attempts' => 1,
            'idempotency_key' => 'test-agent-certificate:'.$certificate->id.':'.Str::uuid(),
            'created_by' => $userId,
            'created_by_user_id' => $userId,
        ]);

        return back()->with('success', 'Comando de teste do certificado enviado ao agente.');
    }

    public function testSefazConnectivity(
        TestSefazConnectivityRequest $request,
        CompanyCertificate $certificate,
        CurrentCompanyContext $context,
    ): RedirectResponse {
        $this->abortUnlessBelongsToCurrentCompany($certificate, $context);

        if ($certificate->type->value !== 'a3') {
            return back()->with('success', 'Teste de conectividade SEFAZ deve usar certificado local vinculado ao agente.');
        }

        if (! $certificate->agent_id || ! $certificate->thumbprint) {
            return back()->with('success', 'Certificado sem agente ou thumbprint para testar conectividade SEFAZ.');
        }

        if ($request->validated('mode') === 'live_homologation' && $certificate->last_test_status !== 'valid') {
            return back()->with('success', 'Homologação real exige certificado testado como válido.');
        }

        $company = $context->company();
        $userId = $this->authenticatedUserId($request);
        $agent = Agent::query()
            ->where('id', $certificate->agent_id)
            ->where('tenant_id', $certificate->tenant_id)
            ->where('company_id', $context->companyId())
            ->firstOrFail();

        $test = SefazConnectivityTest::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'company_certificate_id' => $certificate->id,
            'mode' => (string) $request->validated('mode'),
            'environment' => $company->fiscal_environment->value,
            'uf' => $company->uf,
            'status' => 'pending',
            'requested_by' => $userId,
            'requested_by_user_id' => $userId,
            'requested_at' => now(),
        ]);

        $command = AgentCommand::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'type' => CommandType::TestSefazConnectivity,
            'status' => CommandStatus::Pending,
            'priority' => 5,
            'payload' => [
                'mode' => $test->mode,
                'company_certificate_uuid' => $certificate->uuid,
                'sefaz_connectivity_test_uuid' => $test->uuid,
                'cnpj' => $company->cnpj,
                'uf' => $company->uf,
                'environment' => $company->fiscal_environment->value,
                'thumbprint' => $certificate->thumbprint,
                'store_location' => $this->storeLocation($certificate->store_scope),
                'correlation_id' => (string) Str::uuid(),
            ],
            'available_at' => now(),
            'max_attempts' => 1,
            'idempotency_key' => 'test-sefaz-connectivity:'.$test->id.':'.Str::uuid(),
            'created_by' => $userId,
            'created_by_user_id' => $userId,
        ]);

        $test->forceFill(['agent_command_id' => $command->id])->save();

        return back()->with('success', 'Comando de teste de conectividade SEFAZ enviado ao agente.');
    }

    private function storeLocation(mixed $value): ?string
    {
        $storeLocation = is_string($value) ? $value : null;
        if ($storeLocation === null) {
            return null;
        }

        return Arr::get([
            'CurrentUser' => 'CurrentUser',
            'LocalMachine' => 'LocalMachine',
            'current_user' => 'CurrentUser',
            'local_machine' => 'LocalMachine',
        ], $storeLocation);
    }

    private function authenticatedUserId(Request $request): ?int
    {
        $user = $request->user();

        return $user instanceof User ? $user->id : null;
    }

    private function isLinkableFiscalCandidate(AgentCertificate $certificate, ?string $companyCnpj): bool
    {
        $normalizedCompanyCnpj = Cnpj::normalize($companyCnpj);

        return $this->isUsableFiscalCandidate($certificate)
            && $certificate->document_type === 'cnpj'
            && is_string($certificate->cnpj)
            && $certificate->cnpj !== ''
            && $normalizedCompanyCnpj === Cnpj::normalize($certificate->cnpj);
    }

    private function isUsableFiscalCandidate(AgentCertificate $certificate): bool
    {
        return $certificate->is_fiscal_candidate === true
            && $certificate->is_icp_brasil === true
            && $certificate->is_usable_for_client_auth === true
            && $certificate->is_certificate_authority === false
            && $certificate->is_expired === false
            && $certificate->has_private_key === true
            && $certificate->classification === 'fiscal_candidate';
    }
}
