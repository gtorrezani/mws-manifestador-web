<?php

namespace Tests\Unit\Agent;

use App\Enums\AgentOperationalStatus;
use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Services\Agent\AgentStatusResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentStatusResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_activation(): void
    {
        $agent = Agent::factory()->create(['activated_at' => null]);

        $this->assertSame(AgentOperationalStatus::PendingActivation, $this->resolver()->resolve($agent));
    }

    public function test_online(): void
    {
        config(['agent.heartbeat_timeout_seconds' => 120]);
        $agent = Agent::factory()->create([
            'activated_at' => now()->subHour(),
            'last_seen_at' => now()->subSeconds(30),
            'status' => AgentStatus::Online,
        ]);

        $this->assertSame(AgentOperationalStatus::Online, $this->resolver()->resolve($agent));
    }

    public function test_offline_by_timeout(): void
    {
        config(['agent.heartbeat_timeout_seconds' => 60]);
        $agent = Agent::factory()->create([
            'activated_at' => now()->subHour(),
            'last_seen_at' => now()->subSeconds(90),
            'status' => AgentStatus::Online,
        ]);

        $this->assertSame(AgentOperationalStatus::Offline, $this->resolver()->resolve($agent));
    }

    public function test_revoked(): void
    {
        $agent = Agent::factory()->create([
            'activated_at' => now()->subHour(),
            'last_seen_at' => now(),
            'status' => AgentStatus::Revoked,
            'revoked_at' => now(),
        ]);

        $this->assertSame(AgentOperationalStatus::Revoked, $this->resolver()->resolve($agent));
    }

    public function test_outdated(): void
    {
        config(['agent.minimum_supported_version' => '2.0.0']);
        $agent = Agent::factory()->create([
            'activated_at' => now()->subHour(),
            'last_seen_at' => now(),
            'version' => '1.9.9',
            'status' => AgentStatus::Online,
        ]);

        $this->assertSame(AgentOperationalStatus::Outdated, $this->resolver()->resolve($agent));
    }

    private function resolver(): AgentStatusResolver
    {
        return new AgentStatusResolver;
    }
}
