<?php

namespace App\Support;

class SensorStatus
{
    public const OFFLINE_AFTER_MINUTES = 35;

    public const WEATHER = [
        'temperature' => ['Suhu udara', '°C'],
        'humidity' => ['Kelembapan udara', '%'],
        'vpd' => ['VPD', 'kPa'],
        'windspeed' => ['Angin', 'm/s'],
        'rainfall' => ['Curah hujan 24 jam', 'mm'],
        'light_intensity' => ['Intensitas cahaya', 'lux'],
        'pressure' => ['Tekanan', 'hPa'],
    ];

    public const SOIL = [
        'ph' => ['pH', ''],
        'soil_moisture' => ['Kelembapan tanah', '%'],
        'ec' => ['EC', 'µS/cm'],
        'tds' => ['TDS', 'ppm'],
        'soil_temp' => ['Suhu tanah', '°C'],
        'Nitrogen_Level' => ['Nitrogen', 'mg/kg'],
        'Phosphorus_Level' => ['Fosfor', 'mg/kg'],
        'Potassium_Level' => ['Kalium', 'mg/kg'],
    ];

    private static function s(string $code, string $label, string $level): array
    {
        return ['code' => $code, 'label' => $label, 'level' => $level];
    }

    private static function normal(): array
    {
        return self::s('normal', 'Normal', 'success');
    }

    public static function vpd(?float $t, ?float $rh): ?float
    {
        return ($t === null || $rh === null) ? null : round(0.6108 * exp((17.27 * $t) / ($t + 237.3)) * (1 - $rh / 100), 3);
    }

    public static function seaLevelHpa(float $pa, ?float $tempC): float
    {
        $h = (float) config('monitoring.elevasi_stasiun', 0);
        $p = $pa / 100;
        $t = $tempC ?? 27;

        return $h > 0 ? $p * pow(1 - (0.0065 * $h) / ($t + 0.0065 * $h + 273.15), -5.257) : $p;
    }

    public static function of(string $key, ?float $v, array $context = []): ?array
    {
        if ($v === null) {
            return null;
        }

        return match ($key) {
            'temperature' => $v >= 35 ? self::s('high', 'Tinggi', 'danger') : self::normal(),
            'humidity' => $v >= 90 ? self::s('high', 'Tinggi', 'warning') : self::normal(),
            'vpd' => $v >= 1.5 ? self::s('high', 'Tinggi', 'danger') : ($v <= 0.5 ? self::s('low', 'Rendah', 'warning') : self::normal()),
            'windspeed' => $v >= 8 ? self::s('strong', 'Kencang', 'danger') : self::normal(),
            'rainfall' => $v >= 50 ? self::s('high', 'Tinggi', 'danger')
                : ($v >= 20 ? self::s('medium', 'Sedang', 'warning') : self::normal()),
            'light_intensity' => $v <= 0 ? self::s('night', 'Malam', 'secondary')
                : ($v < 32400 ? self::s('low', 'Rendah', 'warning') : self::normal()),
            'pressure' => self::seaLevelHpa($v, $context['temperature'] ?? null) <= 1000
                ? self::s('low', 'Rendah', 'warning') : self::normal(),
            'ph' => $v > 7.5 ? self::s('alkaline', 'Basa', 'danger')
                : ($v <= 5.5 ? self::s('acid', 'Asam', 'warning') : self::s('neutral', 'Netral', 'success')),
            'soil_moisture' => $v < 35 ? self::s('dry', 'Kering', 'warning') : self::normal(),
            'ec' => $v >= 3000 ? self::s('high', 'Tinggi', 'danger') : self::normal(),
            'tds' => $v >= 1920 ? self::s('high', 'Tinggi', 'danger') : self::normal(),
            'soil_temp' => $v > 34 ? self::s('high', 'Tinggi', 'danger')
                : ($v <= 17.5 ? self::s('low', 'Rendah', 'warning') : self::normal()),
            'Nitrogen_Level' => $v < 80 ? self::s('low', 'Rendah', 'warning')
                : ($v <= 200 ? self::s('medium', 'Sedang', 'success') : self::s('high', 'Tinggi', 'warning')),
            'Phosphorus_Level' => $v < 6 ? self::s('low', 'Rendah', 'warning') : self::normal(),
            'Potassium_Level' => $v < 78 ? self::s('low', 'Rendah', 'warning') : self::normal(),
            default => null,
        };
    }

    public static function items(array $defs, array $values, array $context = []): array
    {
        $out = [];
        foreach ($defs as $key => [$label, $unit]) {
            $v = isset($values[$key]) && is_numeric($values[$key]) ? (float) $values[$key] : null;
            $out[] = ['key' => $key, 'label' => $label, 'unit' => $unit, 'value' => $v, 'status' => self::of($key, $v, $context)];
        }

        return $out;
    }
}
