<?php

namespace Tests\Integration;

use App\Services\ConnectlifeApiService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
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

    public function test_getting_non_ac_device()
    {
        $nonAcDevice = File::json(base_path("/tests/hijuconn-api-data/device-status-non-ac.json"));

        /** @var ConnectlifeApiService $connectlifeApiService */
        $connectlifeApiService = $this->partialMock(
            ConnectlifeApiService::class,
            function (MockInterface $mock) use ($nonAcDevice) {
                $mock->shouldReceive('devices')->once()->andReturn($nonAcDevice);
            }
        );

        $this->assertEmpty($connectlifeApiService->getOnlineAcDevices());
    }

    public function test_getting_ac_device()
    {
        $nonAcDevice = File::json(base_path("/tests/hijuconn-api-data/device-status-117.json"));

        /** @var ConnectlifeApiService $connectlifeApiService */
        $connectlifeApiService = $this->partialMock(
            ConnectlifeApiService::class,
            function (MockInterface $mock) use ($nonAcDevice) {
                $mock->shouldReceive('devices')->once()->andReturn($nonAcDevice);
            }
        );

        $this->assertNotEmpty($connectlifeApiService->getOnlineAcDevices());
    }
}
