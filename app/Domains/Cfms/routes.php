<?php

use App\Domains\Cfms\Controllers\ClerkMemberStatementController;
use Illuminate\Support\Facades\Route;

Route::get('cfms/member-statement/{member_id}/{scheme_id}', [ClerkMemberStatementController::class, 'show'])
    ->name('cfms.member-statement');
