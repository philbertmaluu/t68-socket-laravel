<?php

namespace App\Domains\Dashboard\Enums;

enum Dashboard: string
{
    case SUPERVISOR = 'supervisor';
    case CLERK = 'clerk';
    case ADMIN = 'admin';
    case TENANT = 'tenant';

    public function label(): string
    {
        return match ($this) {
            self::SUPERVISOR => 'Supervisor Dashboard',
            self::CLERK => 'Clerk Dashboard',
            self::ADMIN => 'Admin Dashboard',
            self::TENANT => 'Tenant Dashboard',
        };
    }
}
