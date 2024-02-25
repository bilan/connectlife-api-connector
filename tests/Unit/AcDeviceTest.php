<?php

namespace Tests\Unit;

use App\Services\AcDevice;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AcDeviceTest extends TestCase
{
    public function test_creating_ac_device_from_connectlife_api()
    {
        $acDevice = new AcDevice(
            File::json(base_path('/tests/hijuconn-api-data/device-status.json'))[0],
            File::json(base_path('/tests/hijuconn-api-data/devices-config.json'))['117'],
        );

        $this->assertGreaterThan(0, count($acDevice->fanSpeedOptions));
        $this->assertIsArray($acDevice->toHomeAssistantDiscoveryArray());
        $this->assertIsArray($acDevice->toConnectLifeApiPropertiesArray());
    }
}
