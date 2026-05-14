<?php

namespace App\Actions\Agent;

use App\DTOs\Agent\PollCommandsData;
use App\Models\Agent;
use App\Services\Agent\AgentCommandQueueService;

class PollAgentCommandsAction
{
    public function __construct(
        private readonly AgentCommandQueueService $queue,
    ) {}

    /** @return array{commands: list<array<string, mixed>>, server_time: string|null} */
    public function execute(Agent $agent, PollCommandsData $data): array
    {
        return [
            'commands' => $this->queue
                ->poll($agent, $data)
                ->map(fn ($command): array => $this->queue->serialize($command))
                ->values()
                ->all(),
            'server_time' => now()->toISOString(),
        ];
    }
}
