<?php

namespace App\Http\Controllers\Web;

use App\Actions\Certificates\LinkA3CertificateAction;
use App\Actions\Certificates\StoreA1CertificateAction;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Certificate\LinkA3CertificateRequest;
use App\Http\Requests\Certificate\StoreA1CertificateRequest;
use App\Models\Agent;
use App\Models\AgentCertificate;
use App\Models\AgentCommand;
use App\Models\Company;
use App\Models\CompanyCertificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Certificates/Index', [
            'companies' => Company::query()
                ->where('is_active', true)
                ->orderBy('legal_name')
                ->get(['id', 'tenant_id', 'legal_name', 'trade_name', 'cnpj', 'uf', 'fiscal_environment', 'is_active']),
            'agents' => Agent::query()
                ->with('company:id,legal_name,cnpj')
                ->orderBy('name')
                ->get(['id', 'uuid', 'tenant_id', 'company_id', 'name', 'machine_name', 'version', 'status', 'last_seen_at']),
            'agentCertificates' => AgentCertificate::query()
                ->with(['agent:id,name,machine_name,status', 'company:id,legal_name,cnpj'])
                ->latest('last_seen_at')
                ->paginate(20)
                ->withQueryString(),
            'companyCertificates' => CompanyCertificate::query()
                ->with(['company:id,legal_name,cnpj', 'agent:id,name,machine_name,status', 'agentCertificate:id,last_seen_at,has_private_key'])
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function requestAgentInventory(Agent $agent): RedirectResponse
    {
        if (! $agent->company_id) {
            return back()->with('success', 'Agente sem empresa vinculada. Vincule uma empresa antes de listar certificados.');
        }

        AgentCommand::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'type' => CommandType::ListCertificates,
            'status' => CommandStatus::Pending,
            'priority' => 5,
            'payload' => [],
            'available_at' => now(),
            'max_attempts' => 2,
            'idempotency_key' => 'list-certificates:'.$agent->id.':'.Str::uuid(),
        ]);

        return back()->with('success', 'Comando para listar certificados enviado ao agente.');
    }

    public function linkA3(LinkA3CertificateRequest $request, LinkA3CertificateAction $action): RedirectResponse
    {
        /** @var Company $company */
        $company = Company::query()->findOrFail((int) $request->validated('company_id'));
        /** @var AgentCertificate $agentCertificate */
        $agentCertificate = AgentCertificate::query()->findOrFail((int) $request->validated('agent_certificate_id'));

        if ($agentCertificate->tenant_id !== $company->tenant_id) {
            abort(422, 'Certificado não pertence ao mesmo tenant da empresa.');
        }

        $action->execute(
            $company,
            $agentCertificate,
            is_string($request->validated('name')) ? $request->validated('name') : null,
        );

        return back()->with('success', 'Certificado A3 vinculado à empresa.');
    }

    public function storeA1(StoreA1CertificateRequest $request, StoreA1CertificateAction $action): RedirectResponse
    {
        /** @var Company $company */
        $company = Company::query()->findOrFail((int) $request->validated('company_id'));
        $file = $request->file('certificate_file');
        if (! $file) {
            abort(422, 'Arquivo do certificado não informado.');
        }

        $action->execute(
            $company,
            $file,
            (string) $request->validated('password'),
            is_string($request->validated('name')) ? $request->validated('name') : null,
        );

        return back()->with('success', 'Certificado A1 validado e armazenado com segurança.');
    }

    public function test(CompanyCertificate $certificate): RedirectResponse
    {
        if ($certificate->type->value === 'a1') {
            $certificate->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => 'valid',
                'last_test_message' => 'Certificado A1 já foi validado no cadastro.',
                'last_validated_at' => now(),
            ])->save();

            return back()->with('success', 'Certificado A1 validado localmente.');
        }

        if (! $certificate->agent_id || ! $certificate->thumbprint) {
            return back()->with('success', 'Certificado A3 sem agente ou thumbprint para teste.');
        }

        AgentCommand::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $certificate->tenant_id,
            'company_id' => $certificate->company_id,
            'agent_id' => $certificate->agent_id,
            'type' => CommandType::TestCertificate,
            'status' => CommandStatus::Pending,
            'priority' => 5,
            'payload' => ['thumbprint' => $certificate->thumbprint],
            'available_at' => now(),
            'max_attempts' => 1,
            'idempotency_key' => 'test-certificate:'.$certificate->id.':'.Str::uuid(),
        ]);

        return back()->with('success', 'Comando de teste do certificado enviado ao agente.');
    }
}
