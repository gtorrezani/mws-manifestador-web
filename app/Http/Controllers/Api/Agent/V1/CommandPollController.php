<?php

namespace App\Http\Controllers\Api\Agent\V1;

use App\Actions\Agent\PollAgentCommandsAction;
use App\DTOs\Agent\PollCommandsData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\V1\PollCommandsRequest;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;

class CommandPollController extends Controller
{
    public function __invoke(PollCommandsRequest $request, PollAgentCommandsAction $action): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');

        return response()->json($action->execute($agent, PollCommandsData::fromRequest($request)));
    }
}
