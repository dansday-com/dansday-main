<?php

namespace App\Http\Controllers;

use App\Services\EmbeddingService;
use Illuminate\Http\JsonResponse;

class EmbeddingController extends Controller
{
    public function embedAll(): JsonResponse
    {
        $result = EmbeddingService::embedAll();
        return response()->json($result);
    }
}
