<?php

return [
    'enabled'      => env('MQTT_ENABLED', false),
    'host'         => env('MQTT_HOST', '127.0.0.1'),
    'port'         => (int) env('MQTT_PORT', 1883),
    'username'     => env('MQTT_USERNAME'),
    'password'     => env('MQTT_PASSWORD'),
    'client_id'    => env('MQTT_CLIENT_ID', 'petaniasik-laravel'),
    'topic_prefix' => env('MQTT_TOPIC_PREFIX', 'taniverse'),
    'keepalive'    => (int) env('MQTT_KEEPALIVE', 60),
    'timeout'      => (int) env('MQTT_TIMEOUT', 3),
];
