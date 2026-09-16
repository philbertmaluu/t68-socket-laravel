<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HR staff SSO lookup
    |--------------------------------------------------------------------------
    |
    | production / queuepro-api: local HRPD.VW_EMPLOYEE_DETAILS (TOKEN)
    | queue-dev-api: HRPD.*@preprod (EMPLOYEE.ACCESSTOKEN)
    |
    | Leave HRPD_STAFF_LOOKUP empty to infer from APP_URL.
    | Set explicitly if needed: preprod | local
    |
    */
    'staff_lookup' => env('HRPD_STAFF_LOOKUP'),

];
