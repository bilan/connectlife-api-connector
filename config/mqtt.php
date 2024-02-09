<?php

return [
    'host' => env('MQTT_HOST'),
    'user' => env('MQTT_USER'),
    'password' => env('MQTT_PASSWORD'),
    'port' => env('MQTT_PORT', 1883),
    'ssl' => env('MQTT_SSL', false)
];