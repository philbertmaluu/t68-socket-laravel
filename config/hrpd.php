<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HRPD Oracle database link
    |--------------------------------------------------------------------------
    |
    | Intranet SSO writes ACCESSTOKEN on HRPD.EMPLOYEE@HRLIVE (same as CFMS Pro).
    | Pre-production QMS should set HRPD_DBLINK=preprod.
    | Leave empty to query local HRPD synonyms (no @dblink).
    |
    */
    'dblink' => env('HRPD_DBLINK', 'HRLIVE'),

];
