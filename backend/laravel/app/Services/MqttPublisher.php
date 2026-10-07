<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

class MqttPublisher
{
    public function enabled(): bool
    {
        return (bool) config('mqtt.enabled', false);
    }

    public function topicFor(string $iotId, string $leaf = 'inference'): string
    {
        $prefix = trim((string) config('mqtt.topic_prefix', 'taniverse'), '/');
        return "{$prefix}/{$iotId}/{$leaf}";
    }

    public function publish(string $topic, array $payload): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            if (class_exists(\PhpMqtt\Client\MqttClient::class)) {
                return $this->publishWithPackage($topic, $json);
            }
            return $this->publishWithSocket($topic, $json);
        } catch (Throwable $e) {
            Log::warning('MQTT publish failed', ['topic' => $topic, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function publishWithPackage(string $topic, string $json): bool
    {
        $class = \PhpMqtt\Client\MqttClient::class;
        $client = new $class(
            (string) config('mqtt.host'),
            (int) config('mqtt.port'),
            (string) config('mqtt.client_id') . '-' . substr(md5(uniqid('', true)), 0, 6),
        );

        $settingsClass = \PhpMqtt\Client\ConnectionSettings::class;
        $settings = new $settingsClass();
        if (config('mqtt.username')) {
            $settings = $settings
                ->setUsername((string) config('mqtt.username'))
                ->setPassword((string) config('mqtt.password'));
        }
        $settings = $settings->setKeepAliveInterval((int) config('mqtt.keepalive', 60));

        $client->connect($settings, true);
        $client->publish($topic, $json, 0, false);
        $client->disconnect();

        return true;
    }

    private function publishWithSocket(string $topic, string $json): bool
    {
        $host    = (string) config('mqtt.host');
        $port    = (int) config('mqtt.port', 1883);
        $timeout = (int) config('mqtt.timeout', 3);

        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (! $fp) {
            Log::warning("MQTT socket connect failed: {$errstr} ({$errno})");
            return false;
        }
        stream_set_timeout($fp, $timeout);

        try {
            $clientId = (string) config('mqtt.client_id', 'petaniasik-laravel')
                . '-' . substr(md5(uniqid('', true)), 0, 6);

            $protocol   = $this->encodeString('MQTT');
            $level      = chr(0x04);
            $user       = (string) config('mqtt.username');
            $pass       = (string) config('mqtt.password');
            $flags      = 0x02;
            if ($user !== '') { $flags |= 0x80; }
            if ($pass !== '') { $flags |= 0x40; }
            $keepAlive  = pack('n', (int) config('mqtt.keepalive', 60));

            $payload = $this->encodeString($clientId);
            if ($user !== '') { $payload .= $this->encodeString($user); }
            if ($pass !== '') { $payload .= $this->encodeString($pass); }

            $variable = $protocol . $level . chr($flags) . $keepAlive;
            $body     = $variable . $payload;
            fwrite($fp, chr(0x10) . $this->encodeLength(strlen($body)) . $body);

            $connack = fread($fp, 4);
            if (strlen($connack) < 4 || ord($connack[3]) !== 0) {
                Log::warning('MQTT CONNACK rejected or missing');
                return false;
            }

            $pubBody = $this->encodeString($topic) . $json;
            fwrite($fp, chr(0x30) . $this->encodeLength(strlen($pubBody)) . $pubBody);

            fwrite($fp, chr(0xE0) . chr(0x00));

            return true;
        } finally {
            fclose($fp);
        }
    }

    private function encodeString(string $s): string
    {
        return pack('n', strlen($s)) . $s;
    }

    private function encodeLength(int $len): string
    {
        $out = '';
        do {
            $byte = $len % 128;
            $len  = intdiv($len, 128);
            if ($len > 0) {
                $byte |= 0x80;
            }
            $out .= chr($byte);
        } while ($len > 0);
        return $out;
    }
}
