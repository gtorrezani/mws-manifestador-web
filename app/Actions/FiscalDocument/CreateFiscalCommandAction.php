<?php

namespace App\Actions\FiscalDocument;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Models\AgentCommand;
use App\Models\Company;
use App\Models\FiscalDocument;
use Illuminate\Support\Str;

class CreateFiscalCommandAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        Company $company,
        CommandType $type,
        array $payload,
        ?FiscalDocument $document = null,
        ?int $createdBy = null,
    ): AgentCommand {
        $idempotencyParts = [
            $company->id,
            $type->value,
            $document?->access_key,
            md5(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];

        return AgentCommand::query()->firstOrCreate(
            [
                'tenant_id' => $company->tenant_id,
                'idempotency_key' => implode(':', array_filter($idempotencyParts)),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'company_id' => $company->id,
                'agent_id' => $company->agents()->latest('last_seen_at')->value('id'),
                'type' => $type,
                'status' => CommandStatus::Pending,
                'priority' => $this->priorityFor($type),
                'payload' => $payload,
                'available_at' => now(),
                'attempts_count' => 0,
                'max_attempts' => 3,
                'created_by' => $createdBy,
            ],
        );
    }

    private function priorityFor(CommandType $type): int
    {
        return match ($type) {
            CommandType::ManifestConfirmation,
            CommandType::ManifestUnknown,
            CommandType::ManifestNotPerformed => 10,
            CommandType::ManifestAcknowledgement => 20,
            CommandType::DownloadXmlByAccessKey => 30,
            CommandType::ExportXmlZip => 60,
            default => 100,
        };
    }
}
