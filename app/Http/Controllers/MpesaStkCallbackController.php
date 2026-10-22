<?php

namespace App\Http\Controllers;

use App\Services\DarajaStkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MpesaStkCallbackController extends Controller
{
    public function __invoke(Request $request, DarajaStkService $daraja): JsonResponse
    {
        $daraja->handleCallback($request->all());

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    }
}
