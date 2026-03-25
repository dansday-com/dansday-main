<?php

namespace App\Http\Controllers;

use App\Services\EmbeddingService;
use Illuminate\Http\JsonResponse;

class EmbeddingController extends Controller
{
    public function embedAll(): JsonResponse
    {
        set_time_limit(300);
        $result = EmbeddingService::embedAll();
        return response()->json($result);
    }

    public function embedAllAsync(): JsonResponse
    {
        $total = EmbeddingService::countPending();
        dispatch(function () {
            set_time_limit(300);
            EmbeddingService::embedAll();
        })->afterResponse();

        return response()->json(['started' => true, 'total' => $total]);
    }

    public function embedStatus(): JsonResponse
    {
        $status = EmbeddingService::getStatus();
        return response()->json($status);
    }
}
