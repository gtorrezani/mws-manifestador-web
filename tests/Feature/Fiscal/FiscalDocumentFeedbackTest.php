<?php

namespace Tests\Feature\Fiscal;

use App\Enums\AgentStatus;
use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Models\Agent;
use App\Models\CompanyCertificate;
use App\Models\FiscalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalDocumentFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_xml_warns_when_no_certificate_or_online_agent_can_process(): void
    {
        $user = User::factory()->create();
        $document = FiscalDocument::factory()->create();

        $response = $this->actingAs($user)->post(route('fiscal-documents.download-xml', $document));

        $response
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('warning', function (string $message): bool {
                return str_contains($message, 'certificado ativo e valido')
                    && str_contains($message, 'Nenhum agente online');
            });
    }

    public function test_download_xml_explains_a1_still_uses_agent_queue(): void
    {
        $user = User::factory()->create();
        $document = FiscalDocument::factory()->create();

        CompanyCertificate::factory()->create([
            'tenant_id' => $document->tenant_id,
            'company_id' => $document->company_id,
            'type' => CertificateType::A1,
            'status' => CertificateStatus::Active,
            'valid_until' => now()->addMonth(),
        ]);

        Agent::factory()->create([
            'tenant_id' => $document->tenant_id,
            'company_id' => $document->company_id,
            'status' => AgentStatus::Online,
            'last_seen_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('fiscal-documents.download-xml', $document));

        $response
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('warning', function (string $message): bool {
                return str_contains($message, 'Certificado A1 ativo detectado')
                    && str_contains($message, 'consulta web direta para A1 ainda precisa ser implementada');
            });
    }
}
