<?php

namespace App\Actions\Agent;

use App\DTOs\Agent\DiagnosticsData;
use App\Models\Agent;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class StoreAgentDiagnosticsAction
{
    public function execute(Agent $agent, DiagnosticsData $data, Request $request): AuditLog
    {
        return AuditLog::query()->create([
            'tenant_id' => $agent->tenant_id,
            'company_id' => $agent->company_id,
            'agent_id' => $agent->id,
            'event' => 'agent.diagnostics.'.$data->status,
            'ip_address' => $request->ip(),
            'metadata' => [
                'checks' => $data->checks,
                'environment' => $data->environment,
            ],
            'occurred_at' => now(),
        ]);
    }
}
