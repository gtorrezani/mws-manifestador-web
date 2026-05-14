<?php

namespace App\Actions\Agent;

use App\DTOs\Agent\AgentLogData;
use App\Models\Agent;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class StoreAgentLogsAction
{
    public function execute(Agent $agent, AgentLogData $data, Request $request): int
    {
        foreach ($data->entries as $entry) {
            AuditLog::query()->create([
                'tenant_id' => $agent->tenant_id,
                'company_id' => $agent->company_id,
                'agent_id' => $agent->id,
                'event' => 'agent.log.'.$entry['level'],
                'ip_address' => $request->ip(),
                'metadata' => [
                    'message' => $entry['message'],
                    'context' => $entry['context'] ?? null,
                ],
                'occurred_at' => $entry['occurred_at'] ?? now(),
            ]);
        }

        return count($data->entries);
    }
}
