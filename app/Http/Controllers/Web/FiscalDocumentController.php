<?php

namespace App\Http\Controllers\Web;

use App\Actions\FiscalDocument\CreateFiscalCommandAction;
use App\Actions\FiscalDocument\RequestManifestationAction;
use App\Enums\CommandType;
use App\Enums\ManifestationEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\FiscalDocument\BulkFiscalDocumentActionRequest;
use App\Http\Requests\FiscalDocument\ManifestFiscalDocumentRequest;
use App\Models\Company;
use App\Models\FiscalDocument;
use App\Models\User;
use App\Services\Fiscal\ManifestationRequestContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class FiscalDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only([
            'period_from',
            'period_to',
            'company_id',
            'issuer_name',
            'issuer_cnpj',
            'access_key',
            'manifestation_status',
            'xml_download_status',
        ]);

        $documents = FiscalDocument::query()
            ->with(['company:id,legal_name,cnpj'])
            ->when($filters['period_from'] ?? null, fn ($query, $value) => $query->whereDate('issued_at', '>=', $value))
            ->when($filters['period_to'] ?? null, fn ($query, $value) => $query->whereDate('issued_at', '<=', $value))
            ->when($filters['company_id'] ?? null, fn ($query, $value) => $query->where('company_id', $value))
            ->when($filters['issuer_name'] ?? null, fn ($query, $value) => $query->where('issuer_name', 'like', "%{$value}%"))
            ->when($filters['issuer_cnpj'] ?? null, fn ($query, $value) => $query->where('issuer_cnpj', preg_replace('/\D/', '', $value)))
            ->when($filters['access_key'] ?? null, fn ($query, $value) => $query->where('access_key', preg_replace('/\D/', '', $value)))
            ->when($filters['manifestation_status'] ?? null, fn ($query, $value) => $query->where('manifestation_status', $value))
            ->when($filters['xml_download_status'] ?? null, fn ($query, $value) => $query->where('xml_download_status', $value))
            ->latest('issued_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('FiscalDocuments/Index', [
            'documents' => $documents,
            'filters' => $filters,
            'companies' => Company::query()->where('is_active', true)->get(['id', 'legal_name', 'cnpj']),
        ]);
    }

    public function manifest(
        ManifestFiscalDocumentRequest $request,
        FiscalDocument $document,
        RequestManifestationAction $action,
    ): RedirectResponse {
        $action->execute(
            document: $document,
            eventType: ManifestationEventType::from((string) $request->validated('event_type')),
            justification: is_string($request->validated('justification')) ? $request->validated('justification') : null,
            context: new ManifestationRequestContext(
                explicitUserConfirmation: $request->boolean('confirmed'),
            ),
            createdBy: $this->authenticatedUserId($request),
        );

        return back()->with('success', 'Comando de manifestação criado.');
    }

    public function downloadXml(FiscalDocument $document, CreateFiscalCommandAction $action, Request $request): RedirectResponse
    {
        $company = $this->requireCompany($document);

        $action->execute($company, CommandType::DownloadXmlByAccessKey, [
            'access_key' => $document->access_key,
            'cnpj' => $document->recipient_cnpj,
            'uf' => $company->uf,
            'environment' => $company->fiscal_environment->value,
            'correlation_id' => (string) Str::uuid(),
        ], $document, $this->authenticatedUserId($request));

        return back()->with('success', 'Comando para baixar XML criado.');
    }

    public function bulk(
        BulkFiscalDocumentActionRequest $request,
        CreateFiscalCommandAction $createFiscalCommandAction,
        RequestManifestationAction $requestManifestationAction,
    ): RedirectResponse {
        /** @var list<int> $documentIds */
        $documentIds = array_map('intval', (array) $request->validated('document_ids'));

        $documents = FiscalDocument::query()->with('company')->whereIn('id', $documentIds)->get();

        foreach ($documents as $document) {
            if ($request->validated('action') === 'acknowledge') {
                $requestManifestationAction->execute(
                    document: $document,
                    eventType: ManifestationEventType::OperationAcknowledgement,
                    justification: null,
                    context: new ManifestationRequestContext,
                    createdBy: $this->authenticatedUserId($request),
                );

                continue;
            }

            $type = $request->validated('action') === 'export_zip'
                ? CommandType::ExportXmlZip
                : CommandType::DownloadXmlByAccessKey;

            $company = $this->requireCompany($document);

            $createFiscalCommandAction->execute($company, $type, [
                'access_key' => $document->access_key,
                'cnpj' => $document->recipient_cnpj,
                'uf' => $company->uf,
                'environment' => $company->fiscal_environment->value,
                'correlation_id' => (string) Str::uuid(),
            ], $document, $this->authenticatedUserId($request));
        }

        return back()->with('success', 'Comandos em lote criados.');
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
}
