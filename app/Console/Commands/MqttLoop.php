<?php

namespace App\Console\Commands;

use App\Services\MqttService;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Console\Command;

class MqttLoop extends Command
{
    protected $signature = 'app:mqtt-loop';
    private bool $interrupted = false;

    public function handle(MqttService $mqttService)
    {
        if (empty(config('mqtt.host'))) {
            $this->error('MQTT configuration not found.');
            return;
        }

        pcntl_signal(SIGINT, function (int $signal, $info) use ($mqttService) {
            $this->interrupted = true;
        });

        $mqttService->setupHaDiscovery();
        $this->info('Home Assistant discovery created.');

        $mqttService->setupSubscribes();
        $this->info('Home Assistant subscribes created.');

        $loopStartedAt = microtime(true);
        $lastUpdatedState = microtime(true) - 60;

        while (true) {
            if ($this->interrupted) {
                $this->interrupted = false;
                break;
            }

            if (microtime(true) - $lastUpdatedState >= 60) {
                try {
                    $mqttService->updateDevicesState();
                } catch (TransferException $e) {
                    $this->error($e->getMessage());
                }
                $lastUpdatedState = microtime(true);
            }

            $mqttService->getMqttClient()->loopOnce($loopStartedAt, true);
        }

        $mqttService->getMqttClient()->disconnect();
    }
}
