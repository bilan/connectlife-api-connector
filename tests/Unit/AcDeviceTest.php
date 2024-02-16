<?php

namespace Tests\Unit;

use App\Services\AcDevice;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AcDeviceTest extends TestCase
{
    public function test_creating_ac_device_from_connectlife_api()
    {
        $types = ['standard', 'mobile', 'window'];

        foreach ($types as $type) {
            $acDevice = new AcDevice(
                $this->getConnectLifeResponse('get-appliance-id.json'),
                $this->getConnectLifeResponse("get-metadata-$type.json")
            );

            $this->assertGreaterThan(0, count($acDevice->fanSpeedOptions));
        }
    }

    private function getConnectLifeResponse(string $file): array
    {
        return File::json(base_path('/tests/connectlife-api-data/' . $file))[0];
    }
}
