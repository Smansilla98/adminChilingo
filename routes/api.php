<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 (app móvil y clientes externos)
|--------------------------------------------------------------------------
| Autenticación: Bearer token de Sanctum. Ver docs/API.md.
*/

Route::get('/', function () {
    return response()->json([
        'ok' => true,
        'name' => config('app.name', 'La Chilinga'),
        'api' => 'v1',
        'health' => url('/api/v1/salud'),
    ]);
})->name('api.root');

Route::prefix('v1')->name('api.v1.')->group(base_path('routes/api_v1.php'));

// === Equipo E: servicio transcriptor (interno) ===
// El servicio baja la grabación con un enlace firmado y temporal, y avisa el resultado
// con el cuerpo firmado (HMAC-SHA256, TRANSCRIPTOR_SECRETO).
Route::get('/transcriptor/audio/{transcripcion}', [\App\Http\Controllers\TranscriptorCallbackController::class, 'audio'])
    ->middleware(['signed', 'throttle:30,1'])
    ->whereNumber('transcripcion')
    ->name('transcriptor.audio');
Route::post('/transcriptor/callback', [\App\Http\Controllers\TranscriptorCallbackController::class, 'aviso'])
    ->middleware('throttle:240,1')
    ->name('transcriptor.callback');
