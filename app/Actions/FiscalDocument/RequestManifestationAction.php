<?php

namespace App\Actions\FiscalDocument;

use App\Enums\ManifestationEventType;
use App\Enums\ManifestationRecordStatus;
use App\Enums\ManifestationStatus;
use App\Models\AgentCommand;
use App\Models\Company;
use App\Models\CompanyCertificate;
use App\Models\FiscalDocument;
use App\Models\ManifestationAttempt;
use App\Models\RecipientManifestation;
use App\Services\Fiscal\ManifestationRequestContext;
use App\Services\Fiscal\ManifestationRequestValidator;
use App\Services\Sefaz\ActiveA1CertificateResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class RequestManifestationAction
{
    public function __construct(
        private readonly CreateFiscalCommandAction $createFiscalCommandAction,
        private readonly ManifestationRequestValidator $validator,
        private readonly ActiveA1CertificateResolver $activeA1CertificateResolver,
        private readonly SendA1ManifestationAction $sendA1ManifestationAction,
    ) {}

    public function execute(
        FiscalDocument $document,
        ManifestationEventType $eventType,
        ?string $justification,
        ManifestationRequestContext $context,
        ?int $createdBy,
    ): RecipientManifestation {
        [
            $manifestation,
            $attempt,
            $a1Certificate,
            $company,
            $lockedDocument,
            $correlationId,
        ] = DB::transaction(function () use ($document, $eventType, $justification, $context, $createdBy): array {
            $document = FiscalDocument::query()
                ->with('company')
                ->whereKey($document->id)
                ->lockForUpdate()
                ->firstOrFail();

            $transition = $this->validator->validate($document, $eventType, $justification, $context);
            $company = $this->requireCompany($document);
            $a1Certificate = $this->activeA1CertificateResolver->resolve($company);
            $correlationId = (string) Str::uuid();

            /** @var RecipientManifestation $manifestation */
            $manifestation = RecipientManifestation::query()->create([
                'tenant_id' => $document->tenant_id,
                'company_id' => $document->company_id,
                'fiscal_document_id' => $document->id,
                'event_type' => $eventType,
                'status' => ManifestationRecordStatus::Pending,
                'justification' => $justification,
                'created_by' => $createdBy,
                'created_by_user_id' => $createdBy,
            ]);

            $command = null;
            if (! $a1Certificate instanceof CompanyCertificate) {
                $command = $this->createFiscalCommandAction->execute($company, $transition->commandType, [
                    'access_key' => $document->access_key,
                    'cnpj' => $document->recipient_cnpj,
                    'uf' => $company->uf,
                    'environment' => $company->fiscal_environment->value,
                    'justification' => $justification,
                    'recipient_manifestation_uuid' => $manifestation->uuid,
                    'previous_manifestation_status' => $transition->previousStatus->value,
                    'requested_manifestation_status' => $transition->requestedStatus->value,
                    'correlation_id' => $correlationId,
                ], $document, $createdBy);
            }

            $attempt = $this->createAttempt($manifestation, $command, $transition->previousStatus, $transition->requestedStatus);

            $document->forceFill([
                'manifestation_status' => $transition->requestedStatus,
            ])->save();

            return [$manifestation, $attempt, $a1Certificate, $company, $document, $correlationId];
        });

        if ($a1Certificate instanceof CompanyCertificate) {
            $this->sendA1ManifestationAction->execute(
                company: $company,
                document: $lockedDocument,
                manifestation: $manifestation,
                attempt: $attempt,
                certificate: $a1Certificate,
                eventType: $eventType,
                justification: $justification,
                correlationId: $correlationId,
            );
        }

        return $manifestation;
    }

    private function requireCompany(FiscalDocument $document): Company
    {
        $company = $document->company;

        if (! $company instanceof Company) {
            throw new LogicException('Fiscal document must have an associated company.');
        }

        return $company;
    }

    private function createAttempt(
        RecipientManifestation $manifestation,
        ?AgentCommand $command,
        ManifestationStatus $previousStatus,
        ManifestationStatus $requestedStatus,
    ): ManifestationAttempt {
        $nextAttemptNumber = ((int) ManifestationAttempt::query()
            ->where('recipient_manifestation_id', $manifestation->id)
            ->max('attempt_number')) + 1;

        /** @var ManifestationAttempt $attempt */
        $attempt = ManifestationAttempt::query()->create([
            'tenant_id' => $manifestation->tenant_id,
            'recipient_manifestation_id' => $manifestation->id,
            'agent_command_id' => $command?->id,
            'attempt_number' => $nextAttemptNumber,
            'status' => ManifestationRecordStatus::Pending,
            'previous_manifestation_status' => $previousStatus,
            'new_manifestation_status' => $requestedStatus,
        ]);

        return $attempt;
    }
}
