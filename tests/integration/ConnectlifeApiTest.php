<?php

namespace Tests\Integration;

use Illuminate\Http\Response;
use Tests\TestCase;

class ConnectlifeApiTest extends TestCase
{
    public function test_get_devices_status()
    {
        $this->get('/api/devices')->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                "*" => [
                    'deviceId',
                    'statusList',
                    'puid',
                    'deviceNickName'
                ]
            ]);
    }

    public function test_update_device()
    {
        $deviceId = env('TESTS_DEVICE_ID', 'X-X');

        $data = [
            't_beep' => '0',
            't_temp' => '32'
        ];

        $this->post("/api/devices/$deviceId", $data)
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'resultCode',
                'errorCode',
                'errorDesc'
            ]);
    }
}
