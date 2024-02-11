<?php

namespace App\Services;

use App\Services\Enum\FanSpeed;
use App\Services\Enum\Mode;
use App\Services\Enum\TemperatureUnit;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Contracts\MqttClient;

class MqttService
{
    public function __construct(
        private MqttClient            $client,
        private ConnectlifeApiService $connectlifeApiService
    )
    {
    }

    public function setupHaDiscovery()
    {
        $temperatureUnit = TemperatureUnit::fromName(env('TEMPERATURE_UNIT', 'celsius'));

        foreach ($this->connectlifeApiService->devices() as $device) {
            $id = $device['id'];
            Log::info("Publishing discovery msg for device: $id");

            if ($device['type'] !== 'AirConditioner') {
                Log::info("Skipping non AC device: $id");
                continue;
            }

            $haDiscoveryData = [
                'name' => $device['name'] ?? $id,
                'unique_id' => $id,
                'modes' => ["fan_only", "heat", "cool", "dry", "auto", "off"],
                'fan_modes' => ["Auto", "SuperLow", "Low", "Medium", "High", "SuperHigh"],
                'payload_on' => '1',
                'payload_off' => '0',
                'power_command_topic' => "$id/ac/power/set",
                'mode_command_topic' => "$id/ac/mode/set",
                'mode_state_topic' => "$id/ac/mode/get",
                'temperature_command_topic' => "$id/ac/temperature/set",
                'temperature_state_topic' => "$id/ac/temperature/get",
                'current_temperature_topic' => "$id/ac/current-temperature/get",
                'fan_mode_command_topic' => "$id/ac/fan/set",
                'fan_mode_state_topic' => "$id/ac/fan/get",
                'precision' => 0.5,
                'max_temp' => $temperatureUnit === TemperatureUnit::celsius ? 32 : 89,
                'min_temp' => $temperatureUnit === TemperatureUnit::celsius ? 16 : 61,
                'temp_step' => 1,
                'device' => [
                    'identifiers' => [$id]
                ]
            ];

            $this->client->publish("homeassistant/climate/$id/config", json_encode($haDiscoveryData));
        }
    }

    public function getMqttClient(): MqttClient
    {
        return $this->client;
    }

    public function setupSubscribes(): void
    {
        foreach ($this->connectlifeApiService->devices() as $device) {
            $id = $device['id'];
            $this->setupDeviceSubscribes($id);
        }
    }

    public function setupDeviceSubscribes(string $id): void
    {
        $topics = [
            "$id/ac/mode/set",
            "$id/ac/temperature/set",
            "$id/ac/fan/set"
        ];

        foreach ($topics as $topic) {
            $this->client->subscribe($topic, function (string $topic, string $message, bool $retained) {
                Log::info("Mqtt: received a $retained on [$topic] {$message}");
                $this->client->publish(str_replace('/set', '/get', $topic), $message);

                $this->reactToMessageOnTopic($topic, $message);
            });
        }
    }

    private function reactToMessageOnTopic(string $topic, string $message): void
    {
        $topic = explode('/', $topic);
        $deviceId = $topic[0];
        $case = $topic[2];

        match ($case) {
            'power' => $this->updateDevicePower($deviceId, (bool)$message),
            'mode' => $this->updateDeviceMode($deviceId, Mode::fromName($message)),
            'temperature' => $this->updateDeviceTemperature($deviceId, (int)$message),
            'fan' => $this->updateDeviceFan($deviceId, FanSpeed::fromName($message)),
        };
    }

    public function updateDeviceMode(string $deviceId, Mode $mode): void
    {
        if ($mode === Mode::off) {
            $this->updateDevicePower($deviceId, false);
            return;
        }

        $this->connectlifeApiService->updateDevice($deviceId, [
            'Mode' => $mode->value,
            'Power' => "1"
        ]);
    }

    public function updateDevicePower(string $deviceId, bool $powerOn): void
    {
        $this->connectlifeApiService->updateDevice($deviceId, [
            'Power' => $powerOn ? '1' : '0'
        ]);
    }

    public function updateDeviceTemperature(string $deviceId, int $temp): void
    {
        $this->connectlifeApiService->updateDevice($deviceId, [
            'SetTemperature' => (string)$temp
        ]);
    }

    public function updateDeviceFan(string $deviceId, FanSpeed $fanSpeed): void
    {
        $this->connectlifeApiService->updateDevice($deviceId, [
            'FanSpeed' => $fanSpeed->value
        ]);
    }

    public function updateDevicesState()
    {
        foreach ($this->connectlifeApiService->status() as $deviceStatus) {
            $device = AcDevice::fromConnectLifeApiResponse($deviceStatus);

            Log::info("Updating device state", [$deviceStatus, $device->mode->name]);

            $this->client->publish("$device->id/ac/mode/get", $device->mode->name);
            $this->client->publish("$device->id/ac/temperature/get", $device->temperature);
            $this->client->publish("$device->id/ac/fan/get", $device->fanSpeed->name);
            $this->client->publish("$device->id/ac/current-temperature/get", $device->currentTemperature);
        }
    }
}
