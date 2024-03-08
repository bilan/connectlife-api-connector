<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Contracts\MqttClient;

class MqttService
{
    /** @var array<AcDevice> */
    private array $acDevices;

    public function __construct(
        private MqttClient            $client,
        private ConnectlifeApiService $connectlifeApiService
    )
    {
        foreach ($this->connectlifeApiService->getOnlineAcDevices() as $device) {
            $this->acDevices[$device->id] = $device;
        }
    }

    public function setupHaDiscovery()
    {
        foreach ($this->acDevices as $device) {
            /** @var AcDevice $device */
            $haData = $device->toHomeAssistantDiscoveryArray();

            Log::info("Publishing discovery msg for device: $device->id", [$haData]);

            $this->client->publish(
                "homeassistant/climate/$device->id/config",
                json_encode($haData),
                0,
                true
            );
        }
    }

    public function getMqttClient(): MqttClient
    {
        return $this->client;
    }

    public function setupSubscribes(): void
    {
        foreach ($this->acDevices as $device) {
            $this->setupDeviceSubscribes($device->id);
        }
    }

    public function setupDeviceSubscribes(string $id): void
    {
        $options = ['mode', 'temperature', 'fan', 'swing', 'power'];

        foreach ($options as $option) {
            $topic = "$id/ac/$option/set";
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
        $acDevice = $this->getAcDevice($topic[0]);
        $case = $topic[2];

        match ($case) {
            'power' => $message === '1' ?: $acDevice->mode = 'off',
            'mode' => $acDevice->mode = $message,
            'temperature' => $acDevice->temperature = (int)$message,
            'fan' => $acDevice->fanSpeed = $message,
            'swing' => $acDevice->swing = $message
        };

        $this->updateAcDevice($acDevice);
    }

    private function getAcDevice(string $deviceId): AcDevice
    {
        return $this->acDevices[$deviceId];
    }

    public function updateAcDevice(AcDevice $acDevice)
    {
        $this->connectlifeApiService->updateDevice($acDevice->id, $acDevice->toConnectLifeApiPropertiesArray());
    }

    public function updateDevicesState()
    {
        foreach ($this->connectlifeApiService->getOnlineAcDevices() as $device) {
            Log::info("Updating HA device state", [$device->id]);

            $this->acDevices[$device->id] = $device;

            $this->client->publish("$device->id/ac/mode/get", $device->mode);
            $this->client->publish("$device->id/ac/temperature/get", $device->temperature);
            $this->client->publish("$device->id/ac/current-temperature/get", $device->currentTemperature);
            $this->client->publish("$device->id/ac/attributes/get", json_encode($device->raw['statusList']));

            if (isset($device->fanSpeed)) {
                $this->client->publish("$device->id/ac/fan/get", $device->fanSpeed);
            }

            if (isset($device->swing)) {
                $this->client->publish("$device->id/ac/swing/get", $device->swing);
            }
        }
    }
}
