<?php

namespace Tests\Integration;

use Illuminate\Http\Response;
use Tests\TestCase;

class ConnectlifeApiTest extends TestCase
{
    public function test_get_devices_list()
    {
        $this->get('/api/devices-list')->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                 "*" => [
                     'id',
                     'name',
                     'type',
                     'status'
                 ]
            ]);
    }

    public function test_get_devices_status()
    {
        $this->get('/api/devices')->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                "*" => [
                    'properties' => [
                        'Power',
                        'TemperatureUnit'
                    ],
                    'id',
                    'name',
                    'type',
                    'status'
                ]
            ]);
    }

    public function test_update_device()
    {
        $deviceId = env('TESTS_DEVICE_ID', 'X-X');

        $data = [
            'Power' => '0',
            'TemperatureUnit' => '0',
            'SetTemperature' => '32',
            'Mode' => '1',
            'FanSpeed' => '0'
        ];

        $this->post("/api/devices/$deviceId", $data)
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'id',
                'name',
                'type',
                'status'
            ]);
    }
}
