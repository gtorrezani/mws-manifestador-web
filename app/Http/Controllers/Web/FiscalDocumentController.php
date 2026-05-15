<?php

namespace App\Http\Controllers\Web;

use App\Actions\FiscalDocument\CreateFiscalCommandAction;
use App\Actions\FiscalDocument\RequestManifestationAction;
use App\Enums\AgentStatus;
use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Enums\CommandType;
use App\Enums\FiscalEnvironment;
use App\Enums\ManifestationEventType;
use App\Http\Controllers\Concerns\AuthorizesCurrentCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\FiscalDocument\BulkFiscalDocumentActionRequest;
use App\Http\Requests\FiscalDocument\ManifestFiscalDocumentRequest;
use App\Models\AgentCommand;
use App\Models\Company;
use App\Models\CompanyCertificate;
use App\Models\FiscalDocument;
use App\Models\User;
use App\Services\Fiscal\ManifestationRequestContext;
use App\Services\Sefaz\DistributionStateService;
use App\Support\CompanyContext\CurrentCompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class FiscalDocumentController extends Controller
{
    use AuthorizesCurrentCompany;

    public function index(
        Request $request,
        CurrentCompanyContext $context,
        DistributionStateService $distributionStateService,
    ): Response {
        $company = $context->company();
        $filters = $request->only([
            'period_from',
            'period_to',
            'issuer_name',
            'issuer_cnpj',
            'access_key',
            'manifestation_status',
            'xml_download_status',
        ]);

        $documents = FiscalDocument::query()
            ->forCompany($company)
            ->with(['company:id,legal_name,cnpj'])
            ->when($filters['period_from'] ?? null, fn ($query, $value) => $query->whereDate('issued_at', '>=', $value))
            ->when($filters['period_to'] ?? null, fn ($query, $value) => $query->whereDate('issued_at', '<=', $value))
            ->when($filters['issuer_name'] ?? null, fn ($query, $value) => $query->where('issuer_name', 'like', "%{$value}%"))
            ->when($filters['issuer_cnpj'] ?? null, fn ($query, $value) => $query->where('issuer_cnpj', preg_replace('/\D/', '', $value)))
            ->when($filters['access_key'] ?? null, fn ($query, $value) => $query->where('access_key', preg_replace('/\D/', '', $value)))
            ->when($filters['manifestation_status'] ?? null, fn ($query, $value) => $query->where('manifestation_status', $value))
            ->when($filters['xml_download_status'] ?? null, fn ($query, $value) => $query->where('xml_download_status', $value))
            ->latest('issued_at')
            ->paginate(20)
            ->withQueryString();

        $fiscalState = $distributionStateService->stateForCompany($company);

        return Inertia::render('FiscalDocuments/Index', [
            'documents' => $documents,
            'filters' => $filters,
            'fiscalState' => $fiscalState,
            'distributionAvailability' => $distributionStateService->availability($fiscalState)->toArray(),
            'canSyncFiscalDocuments' => CompanyCertificate::query()
                ->forCompany($company)
                ->where('type', 'a3')
                ->where('status', 'active')
                ->where('last_test_status', 'valid')
                ->whereNotNull('agent_id')
                ->whereNotNull('thumbprint')
                ->exists(),
        ]);
    }

    public function sync(
        CreateFiscalCommandAction $action,
        Request $request,
        CurrentCompanyContext $context,
        DistributionStateService $distributionStateService,
    ): RedirectResponse {
        $company = $context->company();
        $state = $distributionStateService->stateForCompany($company);
        $availability = $distributionStateService->availability($state);
        if (! $availability->allowed) {
            return back()->with('error', $availability->message);
        }

        $certificate = CompanyCertificate::query()
            ->forCompany($company)
            ->with('agent:id,tenant_id,company_id,status')
            ->where('type', 'a3')
            ->where('status', 'active')
            ->where('last_test_status', 'valid')
            ->whereNotNull('agent_id')
            ->whereNotNull('thumbprint')
            ->latest('last_tested_at')
            ->latest('id')
            ->first();

        if (! $certificate instanceof CompanyCertificate || ! $certificate->agent || $certificate->agent->company_id !== $company->id) {
            return back()->with('error', 'Vincule e teste um certificado A3 válido antes de consultar a SEFAZ.');
        }

        $action->execute($company, CommandType::SyncFiscalDocuments, [
            'cnpj' => $company->cnpj,
            'uf' => $company->uf,
            'environment' => $company->fiscal_environment->value,
            'certificate_thumbprint' => $certificate->thumbprint,
            'thumbprint' => $certificate->thumbprint,
            'store_location' => $this->storeLocation($certificate->store_scope),
            'last_nsu' => $state->last_nsu,
            'correlation_id' => (string) Str::uuid(),
        ], null, $this->authenticatedUserId($request));

        return back()->with('success', 'Comando de consulta SEFAZ criado.');
    }

    public function manifest(
        ManifestFiscalDocumentRequest $request,
        FiscalDocument $document,
        RequestManifestationAction $action,
        CurrentCompanyContext $context,
    ): RedirectResponse {
        $this->abortUnlessBelongsToCurrentCompany($document, $context);

        $manifestation = $action->execute(
            document: $document,
            eventType: ManifestationEventType::from((string) $request->validated('event_type')),
            justification: is_string($request->validated('justification')) ? $request->validated('justification') : null,
            context: new ManifestationRequestContext(
                explicitUserConfirmation: $request->boolean('confirmed'),
            ),
            createdBy: $this->authenticatedUserId($request),
        );

        $command = $manifestation->attempts()->with('agentCommand')->latest('attempt_number')->first()?->agentCommand;

        $manifestationDocument = $manifestation->fiscalDocument()->firstOrFail();

        return back()->with($this->feedbackForCommand(
            $command,
            $this->requireCompany($manifestationDocument),
            'Manifestacao registrada para envio a SEFAZ.',
        ));
    }

    public function downloadXml(
        FiscalDocument $document,
        CreateFiscalCommandAction $action,
        Request $request,
        CurrentCompanyContext $context,
    ): RedirectResponse {
        $this->abortUnlessBelongsToCurrentCompany($document, $context);
        $company = $this->requireCompany($document);

        $command = $action->execute($company, CommandType::DownloadXmlByAccessKey, [
            'access_key' => $document->access_key,
            'cnpj' => $document->recipient_cnpj,
            'uf' => $company->uf,
            'environment' => FiscalEnvironment::Production->value,
            'correlation_id' => (string) Str::uuid(),
        ], $document, $this->authenticatedUserId($request));

        return back()->with($this->feedbackForCommand(
            $command,
            $company,
            'Consulta do XML registrada para envio a SEFAZ.',
        ));
    }

    public function bulk(
        BulkFiscalDocumentActionRequest $request,
        CreateFiscalCommandAction $createFiscalCommandAction,
        RequestManifestationAction $requestManifestationAction,
        CurrentCompanyContext $context,
    ): RedirectResponse {
        /** @var list<int> $documentIds */
        $documentIds = array_map('intval', (array) $request->validated('document_ids'));

        $documents = FiscalDocument::query()
            ->forCompany($context->company())
            ->with('company')
            ->whereIn('id', $documentIds)
            ->get();

        $created = 0;
        $warnings = [];

        foreach ($documents as $document) {
            if ($request->validated('action') === 'acknowledge') {
                $manifestation = $requestManifestationAction->execute(
                    document: $document,
                    eventType: ManifestationEventType::OperationAcknowledgement,
                    justification: null,
                    context: new ManifestationRequestContext,
                    createdBy: $this->authenticatedUserId($request),
                );
                $command = $manifestation->attempts()->with('agentCommand')->latest('attempt_number')->first()?->agentCommand;
                $warnings = [
                    ...$warnings,
                    ...$this->warningsForCommand($command, $this->requireCompany($document)),
                ];
                $created++;

                continue;
            }

            $type = $request->validated('action') === 'export_zip'
                ? CommandType::ExportXmlZip
                : CommandType::DownloadXmlByAccessKey;

            $company = $this->requireCompany($document);

            $command = $createFiscalCommandAction->execute($company, $type, [
                'access_key' => $document->access_key,
                'cnpj' => $document->recipient_cnpj,
                'uf' => $company->uf,
                'environment' => FiscalEnvironment::Production->value,
                'correlation_id' => (string) Str::uuid(),
            ], $document, $this->authenticatedUserId($request));
            $warnings = [
                ...$warnings,
                ...$this->warningsForCommand($command, $company),
            ];
            $created++;
        }

        $feedback = ['success' => "{$created} solicitacao(oes) registrada(s) para processamento."];

        if ($warnings !== []) {
            $feedback['warning'] = implode(' ', array_values(array_unique($warnings)));
        }

        return back()->with($feedback);
    }

    private function requireCompany(FiscalDocument $document): Company
    {
        $company = $document->company;

        if (! $company instanceof Company) {
            throw new LogicException('Fiscal document must have an associated company.');
        }

        return $company;
    }

    private function authenticatedUserId(Request $request): ?int
    {
        $user = $request->user();

        return $user instanceof User ? $user->id : null;
    }

    private function storeLocation(mixed $value): ?string
    {
        return match ($value) {
            'CurrentUser', 'current_user' => 'CurrentUser',
            'LocalMachine', 'local_machine' => 'LocalMachine',
            default => null,
        };
    }

    /** @return array<string, string> */
    private function feedbackForCommand(?AgentCommand $command, Company $company, string $success): array
    {
        $feedback = [
            'success' => $success.' '.($command
                ? "Status atual: {$command->status->value}. Acompanhe o retorno no Historico."
                : 'Nao foi possivel localizar o comando vinculado.'),
        ];

        $warnings = $this->warningsForCommand($command, $company);

        if ($warnings !== []) {
            $feedback['warning'] = implode(' ', $warnings);
        }

        return $feedback;
    }

    /** @return list<string> */
    private function warningsForCommand(?AgentCommand $command, Company $company): array
    {
        $warnings = [];
        $certificate = $this->activeCertificateFor($company);

        if (! $certificate) {
            $warnings[] = 'Nao encontrei certificado ativo e valido para esta empresa; a SEFAZ pode rejeitar ou o processamento pode falhar.';
        } elseif ($certificate->type === CertificateType::A1) {
            $warnings[] = 'Certificado A1 ativo detectado. Nesta versao a acao ainda fica na fila do agente; a consulta web direta para A1 ainda precisa ser implementada.';
        }

        if (! $company->agents()->where('status', AgentStatus::Online->value)->exists()) {
            $warnings[] = 'Nenhum agente online vinculado a empresa agora; a solicitacao ficara pendente ate um agente compativel processar a fila.';
        }

        if ($command && ! $command->wasRecentlyCreated) {
            $warnings[] = 'Ja existia uma solicitacao igual para este documento, entao nao foi criada uma duplicata.';
        }

        return $warnings;
    }

    private function activeCertificateFor(Company $company): ?CompanyCertificate
    {
        return $company->certificates()
            ->where('status', CertificateStatus::Active->value)
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>=', now());
            })
            ->latest('valid_until')
            ->first();
    }
}
