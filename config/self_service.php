<?php

return [
    'otp_ttl_minutes' => (int) env('SELF_SERVICE_OTP_TTL_MINUTES', 5),
    'otp_length' => (int) env('SELF_SERVICE_OTP_LENGTH', 6),
    'otp_max_attempts' => (int) env('SELF_SERVICE_OTP_MAX_ATTEMPTS', 5),
    'otp_resend_seconds' => (int) env('SELF_SERVICE_OTP_RESEND_SECONDS', 45),

    /*
    | When true, unknown member numbers (except those ending in 0000) resolve
    | to the demo phone so kiosk OTP can be tested without MEMBERS data.
    */
    'demo_enabled' => (bool) env('SELF_SERVICE_DEMO_ENABLED', false),
    'demo_phone' => env('SELF_SERVICE_DEMO_PHONE', '0712345678'),
    'demo_name' => env('SELF_SERVICE_DEMO_NAME', 'Kelvin David Mahundi'),

    'oracle' => [
        'enabled' => (bool) env('SELF_SERVICE_ORACLE_MEMBERS', false),
        'table' => env('SELF_SERVICE_ORACLE_MEMBERS_TABLE', 'MEMBERS'),
        'member_number_column' => env('SELF_SERVICE_ORACLE_MEMBER_ID_COLUMN', 'MEMBER_ID'),
        'phone_column' => env('SELF_SERVICE_ORACLE_PHONE_COLUMN', 'PHONE_NUMBER'),
        'first_name_column' => env('SELF_SERVICE_ORACLE_FIRST_NAME_COLUMN', 'FIRST_NAME'),
        'middle_name_column' => env('SELF_SERVICE_ORACLE_MIDDLE_NAME_COLUMN', 'MIDDLE_NAME'),
        'last_name_column' => env('SELF_SERVICE_ORACLE_LAST_NAME_COLUMN', 'SURNAME'),
    ],
];
