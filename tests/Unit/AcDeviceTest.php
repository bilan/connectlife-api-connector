<?php

namespace Tests\Unit;

use App\Services\AcDevice;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AcDeviceTest extends TestCase
{
    public function test_creating_ac_device_from_connectlife_api()
    {
        putenv(
            'DEVICES_CONFIG=' .
            File::get(base_path("/tests/hijuconn-api-data/devices-config.json"))
        );

        $deviceFeatureCodes = ['117', '104', '109'];

        foreach ($deviceFeatureCodes as $deviceFeatureCode) {
            $acDevice = new AcDevice(
                File::json(base_path("/tests/hijuconn-api-data/device-status-$deviceFeatureCode.json"))[0]
            );

            $this->assertGreaterThan(0, count($acDevice->fanSpeedOptions));
            $this->assertIsArray($acDevice->toHomeAssistantDiscoveryArray());
            $this->assertIsArray($acDevice->toConnectLifeApiPropertiesArray());
        }
    }
}
