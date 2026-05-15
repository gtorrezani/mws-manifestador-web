<?php

namespace App\Services\Agent;

use App\Enums\AgentOperationalStatus;
use App\Enums\AgentStatus;
use App\Models\Agent;
use Carbon\CarbonImmutable;

class AgentStatusResolver
{
    public function resolve(Agent $agent): AgentOperationalStatus
    {
        if ($agent->revoked_at !== null || $agent->status === AgentStatus::Revoked) {
            return AgentOperationalStatus::Revoked;
        }

        if ($agent->activated_at === null) {
            return AgentOperationalStatus::PendingActivation;
        }

        if ($this->isOutdated($agent)) {
            return AgentOperationalStatus::Outdated;
        }

        if ($agent->status === AgentStatus::Error) {
            return AgentOperationalStatus::Error;
        }

        if ($agent->status === AgentStatus::ServiceStopped) {
            return AgentOperationalStatus::ServiceStopped;
        }

        if ($agent->last_seen_at === null) {
            return AgentOperationalStatus::Offline;
        }

        $timeoutSeconds = max(1, (int) config('agent.heartbeat_timeout_seconds', 120));
        $lastSeenAt = CarbonImmutable::instance($agent->last_seen_at);

        if ($lastSeenAt->lt(now()->subSeconds($timeoutSeconds))) {
            return AgentOperationalStatus::Offline;
        }

        return AgentOperationalStatus::Online;
    }

    public function canReceiveCommands(Agent $agent): bool
    {
        return $this->resolve($agent) === AgentOperationalStatus::Online;
    }

    private function isOutdated(Agent $agent): bool
    {
        $minimumVersion = config('agent.minimum_supported_version');

        if (! is_string($minimumVersion) || trim($minimumVersion) === '') {
            return false;
        }

        if (! is_string($agent->version) || trim($agent->version) === '') {
            return false;
        }

        return version_compare($agent->version, $minimumVersion, '<');
    }
}
