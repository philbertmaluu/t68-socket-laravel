<?php

namespace App\Domains\Bot\Enums;

enum BotRoleMode: string
{
    case SUPERVISOR = 'supervisor';
    case CLERK = 'clerk';
}
