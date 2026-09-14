<?php

return [
    'otp_ttl_minutes' => (int) env('SELF_SERVICE_OTP_TTL_MINUTES', 5),
    'otp_length' => (int) env('SELF_SERVICE_OTP_LENGTH', 6),
    'otp_max_attempts' => (int) env('SELF_SERVICE_OTP_MAX_ATTEMPTS', 5),
    'otp_resend_seconds' => (int) env('SELF_SERVICE_OTP_RESEND_SECONDS', 45),

    'cfms' => [
        'table' => env('SELF_SERVICE_CFMS_MEMBERS_TABLE', 'CFMSPRO.MEMBERS@CFMSPLUS'),
        'active_status' => env('SELF_SERVICE_CFMS_ACTIVE_STATUS', '1'),
        'api_base' => env('CFMS_API_BASE', 'https://cfmspro-api.nssf.go.tz/api'),
        'client_id' => env('CFMS_CLIENT_ID', env('ESB_CLIENT_ID')),
        'client_secret' => env('CFMS_CLIENT_SECRET', env('ESB_CLIENT_SECRET')),
        'statement_timeout' => (int) env('CFMS_STATEMENT_TIMEOUT', 90),
        'default_scheme_id' => (int) env('SELF_SERVICE_STATEMENT_SCHEME_ID', 1),
    ],
];
