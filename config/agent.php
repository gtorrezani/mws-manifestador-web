<?php

return [
    'polling_interval_seconds' => (int) env('MWS_AGENT_POLLING_INTERVAL_SECONDS', 30),

    'auth' => [
        'timestamp_tolerance_seconds' => (int) env('MWS_AGENT_HMAC_TIMESTAMP_TOLERANCE_SECONDS', 300),
    ],

    'commands' => [
        'lock_seconds' => (int) env('MWS_AGENT_COMMAND_LOCK_SECONDS', 300),
    ],
];
