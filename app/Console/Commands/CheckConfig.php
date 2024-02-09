<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckConfig extends Command
{
    protected $signature = 'app:check-config';

    public function handle()
    {
        if (empty(env('CONNECTLIFE_LOGIN'))) {
            throw new \Exception('Empty CONNECTLIFE_LOGIN');
        }

        if (empty(env('CONNECTLIFE_PASSWORD'))) {
            throw new \Exception('Empty CONNECTLIFE_PASSWORD');
        }

        $this->info('Config OK');

        $this->info(
            'MQTT creds: ' . env('MQTT_HOST')
            . ' / ' . env('MQTT_USER')
            . ' / ' . env('MQTT_PORT')
            . ' / ' . (env('MQTT_SSL') ? 'ssl' : 'no-ssl')
        );
    }
}
