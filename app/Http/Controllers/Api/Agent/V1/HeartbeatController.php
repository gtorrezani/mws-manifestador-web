<?php

namespace App\Http\Controllers\Api\Agent\V1;

use App\Actions\Agent\RegisterHeartbeatAction;
use App\DTOs\Agent\HeartbeatData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\V1\HeartbeatRequest;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;

class HeartbeatController extends Controller
{
    public function __invoke(HeartbeatRequest $request, RegisterHeartbeatAction $action): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');
        $heartbeat = $action->execute($agent, HeartbeatData::fromRequest($request), $request);

        return response()->json([
            'status' => 'accepted',
            'heartbeat_id' => $heartbeat->uuid,
            'server_time' => now()->toISOString(),
        ]);
    }
}
