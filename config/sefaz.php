<?php

return [
    'distribution' => [
        'no_documents_cooldown_minutes' => env('SEFAZ_DISTRIBUTION_NO_DOCUMENTS_COOLDOWN_MINUTES', 60),
        'consumption_denied_cooldown_minutes' => env('SEFAZ_DISTRIBUTION_CONSUMPTION_DENIED_COOLDOWN_MINUTES', 60),
        'technical_error_backoff_minutes' => env('SEFAZ_DISTRIBUTION_TECHNICAL_ERROR_BACKOFF_MINUTES', 5),
        'allow_immediate_continue_when_nsu_pending' => env('SEFAZ_DISTRIBUTION_ALLOW_IMMEDIATE_CONTINUE_WHEN_NSU_PENDING', true),
    ],
];
