<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->singleton(MqttClient::class, function () {
            $client = new \PhpMqtt\Client\MqttClient(
                config('mqtt.host'),
                (int)config('mqtt.port'),
                'connectlife-api',
                \PhpMqtt\Client\MqttClient::MQTT_3_1,
                null,
                Log::getLogger()
            );

            $settings = (new ConnectionSettings)
                ->setUsername(config('mqtt.user'))
                ->setPassword(config('mqtt.password'))
                ->setUseTls(config('mqtt.ssl'));

            $client->connect($settings);

            return $client;
        });
    }
}
