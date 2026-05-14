<?php

namespace App\Actions\Agent;

use App\DTOs\Agent\HeartbeatData;
use App\Models\Agent;
use App\Models\AgentHeartbeat;
use Illuminate\Http\Request;

class RegisterHeartbeatAction
{
    public function execute(Agent $agent, HeartbeatData $data, Request $request): AgentHeartbeat
    {
        $agent->forceFill([
            'status' => $data->status,
            'version' => $data->version,
            'machine_name' => $data->machineName ?: $agent->machine_name,
            'last_seen_at' => now(),
        ])->save();

        return AgentHeartbeat::query()->create([
            'tenant_id' => $agent->tenant_id,
            'agent_id' => $agent->id,
            'status' => $data->status,
            'version' => $data->version,
            'machine_name' => $data->machineName,
            'ip_address' => $request->ip(),
            'payload' => [
                'metrics' => $data->metrics,
                'certificate_inventory' => $data->certificateInventory,
            ],
            'received_at' => now(),
        ]);
    }
}
