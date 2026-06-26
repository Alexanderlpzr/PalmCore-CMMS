<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ImpersonationService;
use Illuminate\Http\JsonResponse;

class ImpersonationStatusController extends Controller
{
    public function __invoke(ImpersonationService $impersonation): JsonResponse
    {
        $context = $impersonation->context();

        return response()->json([
            'active' => $context !== null,
            'context' => $context,
        ]);
    }
}
