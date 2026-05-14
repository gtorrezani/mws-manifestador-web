<?php

use App\Http\Controllers\Api\Agent\V1\ActivationController;
use App\Http\Controllers\Api\Agent\V1\AgentDiagnosticsController;
use App\Http\Controllers\Api\Agent\V1\AgentLogController;
use App\Http\Controllers\Api\Agent\V1\CommandLifecycleController;
use App\Http\Controllers\Api\Agent\V1\CommandPollController;
use App\Http\Controllers\Api\Agent\V1\HeartbeatController;
use App\Http\Middleware\AuthenticateAgentHmac;
use Illuminate\Support\Facades\Route;

Route::prefix('agent/v1')->group(function (): void {
    Route::post('activate', ActivationController::class);

    Route::middleware(AuthenticateAgentHmac::class)->group(function (): void {
        Route::post('heartbeat', HeartbeatController::class);
        Route::post('commands/poll', CommandPollController::class);
        Route::post('commands/{commandUuid}/start', [CommandLifecycleController::class, 'start']);
        Route::post('commands/{commandUuid}/complete', [CommandLifecycleController::class, 'complete']);
        Route::post('commands/{commandUuid}/fail', [CommandLifecycleController::class, 'fail']);
        Route::post('logs', AgentLogController::class);
        Route::post('diagnostics', AgentDiagnosticsController::class);
    });
});
