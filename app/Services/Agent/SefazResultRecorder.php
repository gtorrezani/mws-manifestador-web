<?php

namespace App\Services\Agent;

use App\DTOs\Agent\CommandResultData;
use App\Enums\FiscalEnvironment;
use App\Enums\SefazRequestStatus;
use App\Models\AgentCommand;
use App\Models\SefazRequest;
use App\Models\SefazResponse;

class SefazResultRecorder
{
    public function recordSuccessfulResult(AgentCommand $command, CommandResultData $data): void
    {
        if (! $data->requestXml && ! $data->responseXml && ! $data->sefazStatusCode) {
            return;
        }

        $request = SefazRequest::query()->create([
            'tenant_id' => $command->tenant_id,
            'company_id' => $command->company_id,
            'agent_command_id' => $command->id,
            'service' => $data->sefaz['service'] ?? $this->serviceFromCommand($command),
            'environment' => $data->sefaz['environment'] ?? FiscalEnvironment::Production->value,
            'endpoint' => $data->sefaz['endpoint'] ?? 'agent-local',
            'soap_action' => $data->sefaz['soap_action'] ?? null,
            'request_xml_storage_disk' => $data->requestXml['storage_disk'] ?? null,
            'request_xml_storage_path' => $data->requestXml['storage_path'] ?? null,
            'request_hash' => $data->requestXml['content_hash'] ?? null,
            'correlation_id' => $data->sefaz['correlation_id'] ?? $command->uuid,
            'sent_at' => $data->sefaz['sent_at'] ?? now(),
        ]);

        SefazResponse::query()->create([
            'tenant_id' => $command->tenant_id,
            'company_id' => $command->company_id,
            'sefaz_request_id' => $request->id,
            'status' => SefazRequestStatus::Succeeded,
            'http_status_code' => $data->sefaz['http_status_code'] ?? null,
            'sefaz_status_code' => $data->sefazStatusCode,
            'sefaz_message' => $data->sefazMessage,
            'response_xml_storage_disk' => $data->responseXml['storage_disk'] ?? null,
            'response_xml_storage_path' => $data->responseXml['storage_path'] ?? null,
            'response_hash' => $data->responseXml['content_hash'] ?? null,
            'received_at' => $data->sefaz['received_at'] ?? now(),
            'duration_ms' => $data->durationMs,
        ]);
    }

    private function serviceFromCommand(AgentCommand $command): string
    {
        return str_starts_with($command->type->value, 'manifest_')
            ? 'NFeRecepcaoEvento4'
            : 'NFeDistribuicaoDFe';
    }
}
