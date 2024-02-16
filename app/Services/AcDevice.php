<?php

namespace App\Services;

use App\Services\Enum\TemperatureUnit;

class AcDevice
{
    public string $id;
    public string $name;
    public TemperatureUnit $temperatureUnit;
    public int $temperature;
    public int $currentTemperature;
    public string $mode;
    public string $fanSpeed;

    public array $modeOptions;
    public array $fanSpeedOptions;

    public function __construct(array $connectLifeAcDeviceStatus, array $connectLifeAcDeviceMetadata)
    {
        $this->id = $connectLifeAcDeviceStatus['id'];
        $this->name = $connectLifeAcDeviceStatus['name'];
        $this->temperatureUnit = TemperatureUnit::from($connectLifeAcDeviceStatus['properties']['TemperatureUnit']);
        $this->temperature = (int)$connectLifeAcDeviceStatus['properties']['SetTemperature'];
        $this->currentTemperature = (int)$connectLifeAcDeviceStatus['properties']['CurrentTemperature'];

        $this->modeOptions = $this->extractMetadata($connectLifeAcDeviceMetadata, 'Mode');
        $this->fanSpeedOptions = $this->extractMetadata($connectLifeAcDeviceMetadata, 'FanSpeed');

        $this->fanSpeed = array_search($connectLifeAcDeviceStatus['properties']['FanSpeed'], $this->fanSpeedOptions);

        $this->mode = $connectLifeAcDeviceStatus['properties']['Power'] === '0'
            ? 'off'
            : array_search($connectLifeAcDeviceStatus['properties']['Mode'], $this->modeOptions);
    }


    private function extractMetadata(
        array $connectLifeAcDeviceMetadata,
        string $metadataKey
    ): array
    {
        foreach ($connectLifeAcDeviceMetadata['propertyMetadata'] as $values) {
            if ($values['key'] !== $metadataKey) {
                continue;
            }
            $options = $values['enumValues'];
            break;
        }

        foreach ($options as $key => $value) {
            $modeKey = str_replace(' ', '_', strtolower($value['key']));
            $metadataOptions[$modeKey] = (string)$key;
        }

        return $metadataOptions;
    }


    public function toConnectLifeApiPropertiesArray(): array
    {
        $data = [
            'Power' => $this->mode === 'off' ? '0' : '1',
            'TemperatureUnit' => $this->temperatureUnit->value,
            'SetTemperature' => (string)$this->temperature,
            'FanSpeed' => $this->fanSpeedOptions[$this->fanSpeed] ?? '0',
        ];

        if ($this->mode !== 'off') {
            $data['Mode'] = $this->modeOptions[$this->mode];
        }

        return $data;
    }

    private function getHaModesSubset(): array
    {
        $options = array_keys($this->modeOptions);
        array_push($options, 'off');

        return $options;
    }

    public function toHomeAssistantDiscoveryArray(): array
    {
        return [
            'name' => $this->name ?? $this->id,
            'unique_id' => $this->id,
            'modes' => $this->getHaModesSubset(),
            'fan_modes' => array_keys($this->fanSpeedOptions),
            'payload_on' => '1',
            'payload_off' => '0',
            'power_command_topic' => "$this->id/ac/power/set",
            'mode_command_topic' => "$this->id/ac/mode/set",
            'mode_state_topic' => "$this->id/ac/mode/get",
            'temperature_command_topic' => "$this->id/ac/temperature/set",
            'temperature_state_topic' => "$this->id/ac/temperature/get",
            'current_temperature_topic' => "$this->id/ac/current-temperature/get",
            'fan_mode_command_topic' => "$this->id/ac/fan/set",
            'fan_mode_state_topic' => "$this->id/ac/fan/get",
            'precision' => 0.5,
            'max_temp' => $this->temperatureUnit === TemperatureUnit::celsius ? 32 : 90,
            'min_temp' => $this->temperatureUnit === TemperatureUnit::celsius ? 16 : 61,
            'temp_step' => 1,
            'device' => [
                'identifiers' => [$this->id]
            ]
        ];
    }
}
