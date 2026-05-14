<?php

namespace App\Http\Controllers\Api\Agent\V1;

use App\Actions\Agent\ActivateAgentAction;
use App\DTOs\Agent\ActivateAgentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\V1\ActivateAgentRequest;
use Illuminate\Http\JsonResponse;

class ActivationController extends Controller
{
    public function __invoke(ActivateAgentRequest $request, ActivateAgentAction $action): JsonResponse
    {
        $response = $action->execute(ActivateAgentData::fromRequest($request));

        return response()->json($response->toArray(), 201);
    }
}
