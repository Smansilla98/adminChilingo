<?php

namespace App\Http\Controllers;

use App\Services\RecordatorioChatbotService;
use Illuminate\Http\JsonResponse;

class RecordatorioChatbotController extends Controller
{
    public function __invoke(RecordatorioChatbotService $servicio): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return response()->json($servicio->build($user));
    }
}
