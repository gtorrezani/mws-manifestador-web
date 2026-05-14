<?php

namespace App\Http\Controllers\Api\Agent\V1;

use App\Actions\Agent\StoreAgentLogsAction;
use App\DTOs\Agent\AgentLogData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\V1\StoreLogsRequest;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;

class AgentLogController extends Controller
{
    public function __invoke(StoreLogsRequest $request, StoreAgentLogsAction $action): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');

        return response()->json([
            'status' => 'accepted',
            'stored' => $action->execute($agent, AgentLogData::fromRequest($request), $request),
        ], 202);
    }
}
