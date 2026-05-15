<?php

return [
    'polling_interval_seconds' => (int) env('MWS_AGENT_POLLING_INTERVAL_SECONDS', 30),
    'heartbeat_timeout_seconds' => (int) env('MWS_AGENT_HEARTBEAT_TIMEOUT_SECONDS', 120),
    'minimum_supported_version' => env('MWS_AGENT_MINIMUM_SUPPORTED_VERSION', null),
    'installer_download_url' => env('MWS_AGENT_INSTALLER_DOWNLOAD_URL', null),
    'installer_local_disk' => env('MWS_AGENT_INSTALLER_LOCAL_DISK', 'local'),
    'installer_local_path' => env('MWS_AGENT_INSTALLER_LOCAL_PATH', null),
    'installer_file_name' => env('MWS_AGENT_INSTALLER_FILE_NAME', 'MWS-Manifestador-Agent-Setup.msi'),
    'installer_version' => env('MWS_AGENT_INSTALLER_VERSION', null),
    'installer_sha256' => env('MWS_AGENT_INSTALLER_SHA256', null),
    'show_advanced_install_commands' => (bool) env('MWS_AGENT_SHOW_ADVANCED_INSTALL_COMMANDS', false),
    'local_diagnostics_port' => (int) env('MWS_AGENT_LOCAL_DIAGNOSTICS_PORT', 8787),
    'activation_code_ttl_minutes' => (int) env('MWS_AGENT_ACTIVATION_CODE_TTL_MINUTES', 30),

    'auth' => [
        'timestamp_tolerance_seconds' => (int) env('MWS_AGENT_HMAC_TIMESTAMP_TOLERANCE_SECONDS', 300),
    ],

    'commands' => [
        'lock_seconds' => (int) env('MWS_AGENT_COMMAND_LOCK_SECONDS', 300),
    ],
];
