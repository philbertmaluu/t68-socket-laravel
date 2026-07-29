<?php

use App\Domains\ModuleAdmin\Controllers\ModuleAdminController;
use Illuminate\Support\Facades\Route;

// Modules CRUD
Route::get('/admin/modules',          [ModuleAdminController::class, 'indexModules']);
Route::post('/admin/modules',         [ModuleAdminController::class, 'storeModule']);
Route::put('/admin/modules/{id}',     [ModuleAdminController::class, 'updateModule']);
Route::delete('/admin/modules/{id}',  [ModuleAdminController::class, 'destroyModule']);

// Roles CRUD (nested under module)
Route::get('/admin/modules/{moduleId}/roles',              [ModuleAdminController::class, 'indexRoles']);
Route::post('/admin/modules/{moduleId}/roles',             [ModuleAdminController::class, 'storeRole']);
Route::put('/admin/modules/{moduleId}/roles/{roleId}',     [ModuleAdminController::class, 'updateRole']);
Route::delete('/admin/modules/{moduleId}/roles/{roleId}',  [ModuleAdminController::class, 'destroyRole']);
