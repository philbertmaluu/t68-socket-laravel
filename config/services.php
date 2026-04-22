<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ictms' => [
        // ICTMS system (their API)
        'endpoint' => env('ICTMS_API_ENDPOINT', 'https://ictmspre-api.nssf.go.tz/api/send-notification'),
        'system' => env('ICTMS_SYSTEM', 'ICTMS'),
        'enabled' => env('ICTMS_SMS_ENABLED', true),
        'api_base' => env('ICTMS_API_BASE', 'https://ictmspre-api.nssf.go.tz'),
        'access_enabled' => env('ICTMS_ACCESS_ENABLED', true),
        // QMS system (our API – base URL ICTMS will call when integrating)
        'qms_base_url' => env('QMS_BASE_URL', env('APP_URL', 'https://queue-dev-api.nssf.go.tz')),
        'qms_short_code' => env('QMS_SHORT_CODE', 'QMS'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 30),
        'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 1200),
    ],

    'mcp' => [
        'enabled' => env('MCP_ENABLED', true),
        'server_name' => env('MCP_SERVER_NAME', 'qms-internal-mcp'),
        'strict_schema_validation' => env('MCP_STRICT_SCHEMA_VALIDATION', true),
    ],

];
