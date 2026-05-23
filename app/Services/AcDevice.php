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
    public string $presetMode = 'none';
    public array $presetOptions = [];
    public bool $beep = false;
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

        $statusList = $connectLifeAcDeviceStatus['statusList'];
        $deviceConfiguration = $this->getDeviceConfiguration($connectLifeAcDeviceStatus['deviceFeatureCode'], $statusList);

        $this->modeOptions = $this->extractMetadata($deviceConfiguration, 't_work_mode');
        $this->fanSpeedOptions = $this->extractMetadata($deviceConfiguration, 't_fan_speed');
        $this->swingOptions = $this->extractSwingModes($deviceConfiguration);
        $this->fanSpeed = array_search($statusList['t_fan_speed'], $this->fanSpeedOptions);

        foreach ($this->swingOptions as $k => $v) {
            if (isset($v['t_up_down'])) {
                if ($v['t_up_down'] === ($statusList['t_up_down'] ?? null)) {
                    $this->swing = $k;
                }
            } elseif (
                $v['t_swing_direction'] === ($statusList['t_swing_direction'] ?? null) &&
                $v['t_swing_angle'] === ($statusList['t_swing_angle'] ?? null)
            ) {
                $this->swing = $k;
            }
        }

        $this->mode = $statusList['t_power'] === '0'
            ? 'off'
            : array_search($statusList['t_work_mode'], $this->modeOptions);

        $this->presetOptions = $this->detectPresetOptions($statusList);
        $this->presetMode = $this->computePresetMode($statusList);
        $this->beep = (bool)(int)env('BEEPING', 0);

        $this->raw = $connectLifeAcDeviceStatus;
    }

    private function detectPresetOptions(array $statusList): array
    {
        $options = ['none'];
        if (array_key_exists('t_eco', $statusList)) $options[] = 'eco';
        if (array_key_exists('t_sleep', $statusList)) $options[] = 'sleep';
        if (array_key_exists('t_super', $statusList)) $options[] = 'boost';
        if (array_key_exists('t_fan_mute', $statusList)) $options[] = 'silent';
        return $options;
    }

    private function computePresetMode(array $statusList): string
    {
        if (($statusList['t_eco'] ?? '0') === '1') return 'eco';
        if (($statusList['t_sleep'] ?? '0') === '1') return 'sleep';
        if (($statusList['t_super'] ?? '0') === '1') return 'boost';
        if (($statusList['t_fan_mute'] ?? '0') === '1') return 'silent';
        return 'none';
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
        // t_up_down: single-axis vertical swing
        if (isset($deviceOptions['t_up_down'])) {
            $swingOptions = [];
            foreach ($deviceOptions['t_up_down'] as $key => $value) {
                $swingOptions[str_replace(' ', '_', strtolower($value))] = ['t_up_down' => (string)$key];
            }
            return $swingOptions;
        }

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

        if (in_array('eco', $this->presetOptions)) {
            $data['t_eco'] = $this->presetMode === 'eco' ? 1 : 0;
        }
        if (in_array('sleep', $this->presetOptions)) {
            $data['t_sleep'] = $this->presetMode === 'sleep' ? 1 : 0;
        }
        if (in_array('boost', $this->presetOptions)) {
            $data['t_super'] = $this->presetMode === 'boost' ? 1 : 0;
        }
        if (in_array('silent', $this->presetOptions)) {
            $data['t_fan_mute'] = $this->presetMode === 'silent' ? 1 : 0;
        }

        if ($this->swingFeatureEnabled()) {
            $swingValue = $this->swingOptions[$this->swing];
            if (isset($swingValue['t_up_down'])) {
                $data['t_up_down'] = (int)$swingValue['t_up_down'];
            } else {
                $data['t_swing_direction'] = (int)$swingValue['t_swing_direction'];
                $data['t_swing_angle'] = (int)$swingValue['t_swing_angle'];
            }
        }

        if ($this->fanSpeedFeatureEnabled()) {
            $data['t_fan_speed'] = (int)$this->fanSpeedOptions[$this->fanSpeed] ?? 0;
        }

        if ($this->mode !== 'off') {
            $data['t_work_mode'] = (int)$this->modeOptions[$this->mode];
        }

        return $data;
    }

    public function toMinimalApiProperties(string $changedProperty): array
    {
        $properties = match ($changedProperty) {
            'power' => ['t_power' => $this->mode === 'off' ? 0 : 1],
            'mode' => $this->mode === 'off'
                ? ['t_power' => 0]
                : ['t_power' => 1, 't_work_mode' => (int)$this->modeOptions[$this->mode]],
            'temperature' => $this->mode === 'fan_only'
                ? []
                : ['t_temp' => $this->temperature, 't_temp_type' => $this->temperatureUnit->value],
            'fan' => $this->fanSpeedFeatureEnabled() && $this->mode !== 'dry'
                ? $this->buildFanProperties()
                : [],
            'swing' => $this->swingAllowedInCurrentMode() ? $this->buildSwingProperties() : [],
            'preset' => $this->buildPresetProperties(),
            default => $this->toConnectLifeApiPropertiesArray(),
        };

        if (!empty($properties)) {
            $properties['t_beep'] = $this->beep ? 1 : 0;
        }

        return $properties;
    }

    private function buildFanProperties(): array
    {
        $value = (int)($this->fanSpeedOptions[$this->fanSpeed] ?? 0);
        $data = ['t_fan_speed' => $value];
        if (array_key_exists('t_fan_speed_s', $this->raw['statusList'])) {
            $data['t_fan_speed_s'] = $value;
        }
        return $data;
    }

    public function swingAllowedInCurrentMode(): bool
    {
        return !in_array($this->mode, ['dry', 'off']);
    }

    private function buildSwingProperties(): array
    {
        if (!$this->swingFeatureEnabled()) return [];
        $swingValue = $this->swingOptions[$this->swing];
        if (isset($swingValue['t_up_down'])) {
            return ['t_up_down' => (int)$swingValue['t_up_down']];
        }
        return [
            't_swing_direction' => (int)$swingValue['t_swing_direction'],
            't_swing_angle' => (int)$swingValue['t_swing_angle'],
        ];
    }

    private function buildPresetProperties(): array
    {
        $data = [];
        if (in_array('eco', $this->presetOptions)) $data['t_eco'] = $this->presetMode === 'eco' ? 1 : 0;
        if (in_array('sleep', $this->presetOptions)) $data['t_sleep'] = $this->presetMode === 'sleep' ? 1 : 0;
        if (in_array('boost', $this->presetOptions)) $data['t_super'] = $this->presetMode === 'boost' ? 1 : 0;
        if (in_array('silent', $this->presetOptions)) $data['t_fan_mute'] = $this->presetMode === 'silent' ? 1 : 0;
        return $data;
    }

    private function swingFeatureEnabled(): bool
    {
        return isset($this->swing);
    }

    public function fanSpeedFeatureEnabled(): bool
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
            'mode_command_topic' => "$this->id/ac/mode/set",
            'mode_state_topic' => "$this->id/ac/mode/get",
            'temperature_command_topic' => "$this->id/ac/temperature/set",
            'temperature_state_topic' => "$this->id/ac/temperature/get",
            'current_temperature_topic' => "$this->id/ac/current-temperature/get",
            'json_attributes_topic' => "$this->id/ac/attributes/get",
            'precision' => 0.5,
            'max_temp' => $this->temperatureUnit === TemperatureUnit::celsius ? 32 : 90,
            'min_temp' => $this->temperatureUnit === TemperatureUnit::celsius ? 15 : 59,
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

        if (count($this->presetOptions) > 1) {
            $data += [
                'preset_modes' => array_values(array_filter($this->presetOptions, fn($p) => $p !== 'none')),
                'preset_mode_command_topic' => "$this->id/ac/preset/set",
                'preset_mode_state_topic' => "$this->id/ac/preset/get",
            ];
        }

        return $data;
    }

    public function toHomeAssistantSensorDiscoveries(): array
    {
        $device = [
            'identifiers' => [$this->id],
            'manufacturer' => 'Connectlife',
            'model' => ($this->raw['deviceTypeCode'] ?? '') . '-' . ($this->raw['deviceFeatureCode'] ?? '')
        ];

        $sensors = [];
        $statusList = $this->raw['statusList'];

        if (array_key_exists('f_temp_in', $statusList)) {
            $sensors[] = [
                'topic' => "homeassistant/sensor/{$this->id}_temp/config",
                'payload' => [
                    'name' => $this->name . ' Temperature',
                    'unique_id' => "{$this->id}_temp",
                    'state_topic' => "$this->id/ac/current-temperature/get",
                    'unit_of_measurement' => '°C',
                    'device_class' => 'temperature',
                    'state_class' => 'measurement',
                    'device' => $device,
                ],
            ];
        }

        $errorKeys = array_filter(array_keys($statusList), fn($k) => str_starts_with($k, 'f_e_'));
        if (!empty($errorKeys)) {
            $sensors[] = [
                'topic' => "homeassistant/binary_sensor/{$this->id}_fault/config",
                'payload' => [
                    'name' => $this->name . ' Fault',
                    'unique_id' => "{$this->id}_fault",
                    'state_topic' => "$this->id/ac/fault/get",
                    'payload_on' => '1',
                    'payload_off' => '0',
                    'device_class' => 'problem',
                    'entity_category' => 'diagnostic',
                    'device' => $device,
                ],
            ];
        }

        $sensors[] = [
            'topic' => "homeassistant/switch/{$this->id}_beep/config",
            'payload' => [
                'name' => $this->name . ' Beep',
                'unique_id' => "{$this->id}_beep",
                'command_topic' => "$this->id/ac/beep/set",
                'state_topic' => "$this->id/ac/beep/get",
                'payload_on' => '1',
                'payload_off' => '0',
                'icon' => 'mdi:bell-outline',
                'entity_category' => 'config',
                'device' => $device,
            ],
        ];

        if (!empty($this->swingOptions)) {
            $sensors[] = [
                'topic' => "homeassistant/select/{$this->id}_swing/config",
                'payload' => [
                    'name' => $this->name . ' Swing',
                    'unique_id' => "{$this->id}_swing",
                    'command_topic' => "$this->id/ac/swing/set",
                    'state_topic' => "$this->id/ac/swing/get",
                    'availability_topic' => "$this->id/ac/swing/available",
                    'options' => array_keys($this->swingOptions),
                    'device' => $device,
                ],
            ];
        }

        if (array_key_exists('f_electricity', $statusList)) {
            $sensors[] = [
                'topic' => "homeassistant/sensor/{$this->id}_power/config",
                'payload' => [
                    'name' => $this->name . ' Power',
                    'unique_id' => "{$this->id}_power",
                    'state_topic' => "$this->id/ac/electricity/get",
                    'unit_of_measurement' => 'W',
                    'device_class' => 'power',
                    'state_class' => 'measurement',
                    'device' => $device,
                ],
            ];
        }

        if (array_key_exists('f_votage', $statusList)) {
            $sensors[] = [
                'topic' => "homeassistant/sensor/{$this->id}_voltage/config",
                'payload' => [
                    'name' => $this->name . ' Voltage',
                    'unique_id' => "{$this->id}_voltage",
                    'state_topic' => "$this->id/ac/voltage/get",
                    'unit_of_measurement' => 'V',
                    'device_class' => 'voltage',
                    'state_class' => 'measurement',
                    'entity_category' => 'diagnostic',
                    'device' => $device,
                ],
            ];
        }

        if (array_key_exists('daily_energy_kwh', $statusList)) {
            $sensors[] = [
                'topic' => "homeassistant/sensor/{$this->id}_energy/config",
                'payload' => [
                    'name' => $this->name . ' Daily Energy',
                    'unique_id' => "{$this->id}_energy",
                    'state_topic' => "$this->id/ac/energy_daily/get",
                    'unit_of_measurement' => 'kWh',
                    'device_class' => 'energy',
                    'state_class' => 'total_increasing',
                    'device' => $device,
                ],
            ];
        }

        if (array_key_exists('daily_runtime_minutes', $statusList)) {
            $sensors[] = [
                'topic' => "homeassistant/sensor/{$this->id}_runtime/config",
                'payload' => [
                    'name' => $this->name . ' Daily Runtime',
                    'unique_id' => "{$this->id}_runtime",
                    'state_topic' => "$this->id/ac/runtime_daily/get",
                    'unit_of_measurement' => 'min',
                    'icon' => 'mdi:timer-outline',
                    'state_class' => 'total_increasing',
                    'device' => $device,
                ],
            ];
        }

        return $sensors;
    }

    private function getHaModesSubset(): array
    {
        $options = array_keys($this->modeOptions);
        array_push($options, 'off');

        return $options;
    }

    private function getDeviceConfiguration(string $deviceFeatureCode, array $statusList = []): array
    {
        $configuration = json_decode(env('DEVICES_CONFIG', '[]'), true);

        if (isset($configuration[$deviceFeatureCode])) {
            return $configuration[$deviceFeatureCode];
        }

        Log::debug('Device configuration not found, using default.');

        $config = [
            't_work_mode' => ['0' => 'fan only', '1' => 'heat', '2' => 'cool', '3' => 'dry', '4' => 'auto'],
            't_fan_speed' => ['0' => 'auto', '5' => 'super low', '6' => 'low', '7' => 'medium', '8' => 'high', '9' => 'super high'],
        ];

        // Auto-detect t_up_down swing if the device has it but no swing config was provided
        if (array_key_exists('t_up_down', $statusList)) {
            $config['t_up_down'] = ['0' => 'swing', '1' => 'top', '2' => 'upper', '3' => 'middle', '4' => 'lower', '5' => 'bottom'];
        }

        return $config;
    }
}
