<?php

namespace App\Http\Controllers;

use App\Services\DarajaB2cService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MpesaB2cResultController extends Controller
{
    public function result(Request $request, DarajaB2cService $daraja): JsonResponse
    {
        $daraja->handleResult($request->all());

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    }

    public function timeout(Request $request, DarajaB2cService $daraja): JsonResponse
    {
        $daraja->handleTimeout($request->all());

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    }
}
