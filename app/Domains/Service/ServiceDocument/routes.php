<?php

use App\Domains\Service\ServiceDocument\Controllers\ServiceDocumentController;
use Illuminate\Support\Facades\Route;

Route::apiResource('service-documents', ServiceDocumentController::class);
