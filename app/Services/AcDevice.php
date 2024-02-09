<?php

namespace App\Services;

use App\Services\Enum\FanSpeed;
use App\Services\Enum\Mode;
use App\Services\Enum\TemperatureUnit;

class AcDevice
{
    public string $id;
    public string $name;
    public TemperatureUnit $temperatureUnit;
    public int $temperature;
    public int $currentTemperature;
    public Mode $mode;
    public FanSpeed $fanSpeed;

    public static function fromConnectLifeApiResponse(array $data): self
    {
        $device = new AcDevice();
        $device->id = $data['id'];
        $device->name = $data['name'];
        $device->temperatureUnit = TemperatureUnit::from($data['properties']['TemperatureUnit']);
        $device->temperature = (int)$data['properties']['SetTemperature'];
        $device->currentTemperature = (int)$data['properties']['CurrentTemperature'];
        $device->fanSpeed = FanSpeed::from($data['properties']['FanSpeed']);

        $device->mode = $data['properties']['Power'] === '0'
            ? Mode::from('off')
            : Mode::from($data['properties']['Mode']);

        return $device;
    }

    public function toConnectLifeApiPropertiesArray(): array
    {
        $data = [
            'Power' => $this->mode === Mode::off ? '0' : '1',
            'TemperatureUnit' => $this->temperatureUnit->value,
            'SetTemperature' => (string)$this->temperature,
            'FanSpeed' => $this->fanSpeed->value,
        ];

        if ($this->mode !== Mode::off) {
            $data['Mode'] = $this->mode->value;
        }

        return $data;
    }
}
