<?php

namespace App\Http\Controllers;

use App\Services\ConnectlifeApiService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    public function __construct(private ConnectlifeApiService $connectlifeApiService)
    {
    }

    public function updateDevice(string $deviceId, Request $request)
    {
        return response()->json(
            $this->connectlifeApiService->updateDevice($deviceId, $request->json()->all())
        );
    }

    public function devices(?string $deviceId = null)
    {
        return response()->json($this->connectlifeApiService->devices($deviceId));
    }
}
