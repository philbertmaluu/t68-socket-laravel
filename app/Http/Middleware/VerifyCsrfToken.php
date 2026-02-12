<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/qms/auth/authenticate',
        'api/qms/auth/refresh-token',
        // Dev / API routes using Sanctum bearer tokens (no cookie-based CSRF needed)
        'api/qms/*',
    ];
}
