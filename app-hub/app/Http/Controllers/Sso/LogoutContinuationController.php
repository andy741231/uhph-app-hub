<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutContinuationController extends Controller
{
    public function __invoke(Request $request, GlobalLogout $logout): JsonResponse
    {
        if (app()->isProduction() && ! $request->secure()) {
            return response()->json(['error' => 'https_required'], 400);
        }

        return $logout->continue($request);
    }
}
