<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 (app móvil y clientes externos)
|--------------------------------------------------------------------------
| Autenticación: Bearer token de Sanctum. Ver docs/API.md.
*/

Route::prefix('v1')->name('api.v1.')->group(base_path('routes/api_v1.php'));
