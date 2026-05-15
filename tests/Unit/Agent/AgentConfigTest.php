<?php

namespace Tests\Unit\Agent;

use Tests\TestCase;

class AgentConfigTest extends TestCase
{
    public function test_agent_config_is_loaded(): void
    {
        $this->assertIsInt(config('agent.heartbeat_timeout_seconds'));
        $this->assertTrue(array_key_exists('minimum_supported_version', config('agent')));
        $this->assertTrue(array_key_exists('installer_download_url', config('agent')));
        $this->assertTrue(array_key_exists('installer_local_disk', config('agent')));
        $this->assertTrue(array_key_exists('installer_local_path', config('agent')));
        $this->assertTrue(array_key_exists('installer_file_name', config('agent')));
        $this->assertTrue(array_key_exists('installer_version', config('agent')));
        $this->assertTrue(array_key_exists('installer_sha256', config('agent')));
        $this->assertTrue(array_key_exists('show_advanced_install_commands', config('agent')));
        $this->assertIsInt(config('agent.local_diagnostics_port'));
        $this->assertIsInt(config('agent.activation_code_ttl_minutes'));
    }
}
