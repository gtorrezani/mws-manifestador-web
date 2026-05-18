<?php

namespace App\Actions\Certificates;

use App\Enums\CertificateType;
use App\Models\AgentCertificate;
use App\Models\Company;
use App\Models\CompanyCertificate;
use App\Support\Cnpj;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LinkA3CertificateAction
{
    public function execute(Company $company, AgentCertificate $agentCertificate, ?string $name): CompanyCertificate
    {
        if (! $this->canLink($company, $agentCertificate)) {
            throw ValidationException::withMessages([
                'agent_certificate_id' => 'Certificado local não elegível para a empresa selecionada.',
            ]);
        }

        /** @var CompanyCertificate $certificate */
        $certificate = CompanyCertificate::query()->firstOrNew([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'type' => CertificateType::A3,
        ]);

        if (! $certificate->exists) {
            $certificate->uuid = (string) Str::uuid();
        }

        $certificate->fill([
            'status' => $agentCertificate->status,
            'agent_id' => $agentCertificate->agent_id,
            'agent_certificate_id' => $agentCertificate->id,
            'name' => $name ?: 'Certificado fiscal '.$company->legal_name,
            'subject_name' => $agentCertificate->subject_name,
            'issuer_name' => $agentCertificate->issuer_name,
            'serial_number' => $agentCertificate->serial_number,
            'thumbprint' => $agentCertificate->thumbprint,
            'valid_from' => $agentCertificate->valid_from,
            'valid_until' => $agentCertificate->valid_until,
            'store_scope' => $agentCertificate->store_scope,
            'metadata' => [
                'cnpj' => $agentCertificate->cnpj,
                'document' => $agentCertificate->document,
                'document_type' => $agentCertificate->document_type,
                'source' => 'agent_inventory',
                'classification' => $agentCertificate->classification,
                'type_estimate' => 'a1_a3_unconfirmed',
                'warnings' => $agentCertificate->warnings ?? [],
            ],
            'last_validated_at' => $agentCertificate->last_test_status === 'valid' ? now() : null,
            'last_tested_at' => $agentCertificate->last_tested_at,
            'last_test_status' => $agentCertificate->last_test_status,
            'last_test_message' => $agentCertificate->last_test_message,
        ])->save();

        return $certificate;
    }

    private function canLink(Company $company, AgentCertificate $agentCertificate): bool
    {
        $companyCnpj = Cnpj::normalize($company->cnpj);
        $certificateCnpj = Cnpj::normalize($agentCertificate->cnpj);

        return $agentCertificate->tenant_id === $company->tenant_id
            && $agentCertificate->company_id === $company->id
            && $agentCertificate->is_fiscal_candidate === true
            && $agentCertificate->is_icp_brasil === true
            && $agentCertificate->is_usable_for_client_auth === true
            && $agentCertificate->is_certificate_authority === false
            && $agentCertificate->is_expired === false
            && $agentCertificate->has_private_key === true
            && $agentCertificate->classification === 'fiscal_candidate'
            && $agentCertificate->document_type === 'cnpj'
            && $companyCnpj !== ''
            && $certificateCnpj === $companyCnpj;
    }
}
