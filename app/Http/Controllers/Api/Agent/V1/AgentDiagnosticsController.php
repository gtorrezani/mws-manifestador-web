<?php

namespace App\Http\Controllers\Api\Agent\V1;

use App\Actions\Agent\StoreAgentDiagnosticsAction;
use App\DTOs\Agent\DiagnosticsData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\V1\StoreDiagnosticsRequest;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;

class AgentDiagnosticsController extends Controller
{
    public function __invoke(StoreDiagnosticsRequest $request, StoreAgentDiagnosticsAction $action): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');
        $diagnostic = $action->execute($agent, DiagnosticsData::fromRequest($request), $request);

        return response()->json([
            'status' => 'accepted',
            'diagnostic_id' => $diagnostic->uuid,
        ], 202);
    }
}
