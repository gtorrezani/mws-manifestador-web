<?php

namespace App\Http\Controllers\Api\Agent\V1;

use App\Actions\Agent\CompleteAgentCommandAction;
use App\Actions\Agent\FailAgentCommandAction;
use App\Actions\Agent\StartAgentCommandAction;
use App\DTOs\Agent\CommandFailureData;
use App\DTOs\Agent\CommandResultData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\V1\CompleteCommandRequest;
use App\Http\Requests\Agent\V1\FailCommandRequest;
use App\Http\Requests\Agent\V1\StartCommandRequest;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;

class CommandLifecycleController extends Controller
{
    public function start(StartCommandRequest $request, string $commandUuid, StartAgentCommandAction $action): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');

        return response()->json($action->execute($agent, $commandUuid));
    }

    public function complete(CompleteCommandRequest $request, string $commandUuid, CompleteAgentCommandAction $action): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');

        return response()->json($action->execute($agent, $commandUuid, CommandResultData::fromRequest($request)));
    }

    public function fail(FailCommandRequest $request, string $commandUuid, FailAgentCommandAction $action): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');

        return response()->json($action->execute($agent, $commandUuid, CommandFailureData::fromRequest($request)));
    }
}
