<?php

namespace App\Services;

use App\Services\Enum\TemperatureUnit;
use Illuminate\Support\Facades\Log;

class AcDevice
{
    public string $id;
    public string $name;
    public TemperatureUnit $temperatureUnit;
    public int $temperature;
    public int $currentTemperature;
    public string $mode;
    public string $fanSpeed;
    public string $swing;
    public array $raw;

    public array $modeOptions;
    public array $fanSpeedOptions;
    public array $swingOptions;

    public function __construct(array $connectLifeAcDeviceStatus)
    {
        $this->id = $connectLifeAcDeviceStatus['puid'];
        $this->name = $connectLifeAcDeviceStatus['deviceNickName'];
        $this->temperatureUnit = TemperatureUnit::from($connectLifeAcDeviceStatus['statusList']['t_temp_type']);
        $this->temperature = (int)$connectLifeAcDeviceStatus['statusList']['t_temp'];
        $this->currentTemperature = (int)$connectLifeAcDeviceStatus['statusList']['f_temp_in'];

        $deviceConfiguration = $this->getDeviceConfiguration($connectLifeAcDeviceStatus['deviceFeatureCode']);

        $this->modeOptions = $this->extractMetadata($deviceConfiguration, 't_work_mode');
        $this->fanSpeedOptions = $this->extractMetadata($deviceConfiguration, 't_fan_speed');
        $this->swingOptions = $this->extractSwingModes($deviceConfiguration);
        $this->fanSpeed = array_search($connectLifeAcDeviceStatus['statusList']['t_fan_speed'], $this->fanSpeedOptions);

        foreach ($this->swingOptions as $k => $v) {
            if (
                $v['t_swing_direction'] === ($connectLifeAcDeviceStatus['statusList']['t_swing_direction'] ?? null) &&
                $v['t_swing_angle'] === ($connectLifeAcDeviceStatus['statusList']['t_swing_angle'] ?? null)
            ) {
                $this->swing = $k;
            }
        }

        $this->mode = $connectLifeAcDeviceStatus['statusList']['t_power'] === '0'
            ? 'off'
            : array_search($connectLifeAcDeviceStatus['statusList']['t_work_mode'], $this->modeOptions);

        $this->raw = $connectLifeAcDeviceStatus;
    }

    private function extractMetadata(
        array  $connectLifeAcDeviceMetadata,
        string $metadataKey
    ): array
    {
        $metadataOptions = [];

        foreach ($connectLifeAcDeviceMetadata[$metadataKey] as $key => $value) {
            $modeKey = str_replace(' ', '_', strtolower($value));
            $metadataOptions[$modeKey] = (string)$key;
        }

        return $metadataOptions;
    }

    private function extractSwingModes(array $deviceOptions): array
    {
        if (!isset($deviceOptions['t_swing_direction']) || !isset($deviceOptions['t_swing_angle'])) {
            return [];
        }

        $swingOptions = [];
        foreach ($deviceOptions['t_swing_direction'] as $keyDirection => $valueDirection) {
            foreach ($deviceOptions['t_swing_angle'] as $keyAngle => $valueAngle) {
                $swingOptions["$valueDirection - $valueAngle"] = [
                    't_swing_direction' => (string)$keyDirection,
                    't_swing_angle' => (string)$keyAngle
                ];
            }
        }

        return $swingOptions;
    }

    public function toConnectLifeApiPropertiesArray(): array
    {
        $data = [
            't_power' => $this->mode === 'off' ? 0 : 1,
            't_temp_type' => $this->temperatureUnit->value,
            't_temp' => $this->temperature,
            't_beep' => (int)env('BEEPING', 0)
        ];

        if ($this->swingFeatureEnabled()) {
            $data['t_swing_direction'] = (int)$this->swingOptions[$this->swing]['t_swing_direction'];
            $data['t_swing_angle'] = (int)$this->swingOptions[$this->swing]['t_swing_angle'];
        }

        if ($this->fanSpeedFeatureEnabled()) {
            $data['t_fan_speed'] = (int)$this->fanSpeedOptions[$this->fanSpeed] ?? 0;
        }

        if ($this->mode !== 'off') {
            $data['t_work_mode'] = (int)$this->modeOptions[$this->mode];
        }

        return $data;
    }

    private function swingFeatureEnabled()
    {
        return isset($this->swing);
    }

    public function fanSpeedFeatureEnabled()
    {
        return isset($this->fanSpeed);
    }

    public function toHomeAssistantDiscoveryArray(): array
    {
        $data = [
            'name' => $this->name ?? $this->id,
            'unique_id' => $this->id,
            'modes' => $this->getHaModesSubset(),
            'fan_modes' => array_keys($this->fanSpeedOptions),
            'swing_modes' => array_keys($this->swingOptions),
            'payload_on' => '1',
            'payload_off' => '0',
            'power_command_topic' => "$this->id/ac/power/set",
            'mode_command_topic' => "$this->id/ac/mode/set",
            'mode_state_topic' => "$this->id/ac/mode/get",
            'temperature_command_topic' => "$this->id/ac/temperature/set",
            'temperature_state_topic' => "$this->id/ac/temperature/get",
            'current_temperature_topic' => "$this->id/ac/current-temperature/get",
            'json_attributes_topic' => "$this->id/ac/attributes/get",
            'precision' => 0.5,
            'max_temp' => $this->temperatureUnit === TemperatureUnit::celsius ? 32 : 90,
            'min_temp' => $this->temperatureUnit === TemperatureUnit::celsius ? 16 : 61,
            'temp_step' => 1,
            'device' => [
                'identifiers' => [$this->id],
                'manufacturer' => 'Connectlife',
                'model' => ($this->raw['deviceTypeCode'] ?? '') . '-' . ($this->raw['deviceFeatureCode'] ?? '')
            ]
        ];

        if ($this->fanSpeedFeatureEnabled()) {
            $data += [
                'fan_mode_command_topic' => "$this->id/ac/fan/set",
                'fan_mode_state_topic' => "$this->id/ac/fan/get",
            ];
        } else {
            Log::info('Fan speed feature disabled.');
        }

        if ($this->swingFeatureEnabled()) {
            $data += [
                'swing_mode_command_topic' => "$this->id/ac/swing/set",
                'swing_mode_state_topic' => "$this->id/ac/swing/get",
            ];
        } else {
            Log::info('Swing feature disabled.');
        }

        return $data;
    }

    private function getHaModesSubset(): array
    {
        $options = array_keys($this->modeOptions);
        array_push($options, 'off');

        return $options;
    }

    private function getDeviceConfiguration(string $deviceTypeCode): array
    {
        $configuration = json_decode(env('DEVICES_CONFIG', '[]'), true);

        if (isset($configuration[$deviceTypeCode])) {
            return $configuration[$deviceTypeCode];
        }

        Log::debug('Device configuration not found, using default.');

        $defaultConfiguration = '{"t_work_mode":["fan only","heat","cool","dry","auto"],"t_fan_speed":{"0":"auto","5":"super low","6":"low","7":"medium","8":"high","9":"super high"}}';

        return json_decode($defaultConfiguration, true);
    }
}
