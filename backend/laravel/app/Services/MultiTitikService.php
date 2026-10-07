<?php

namespace App\Services;

use App\Http\Controllers\RecommendationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Dashboard\Models\SensorIot;
use Modules\Dashboard\Models\SensorKamera;

class MultiTitikService
{
    public function titikList(): array
    {
        return config('monitoring.titik', [1 => ['label' => 'Petak 1', 'weather' => true]]);
    }

    public function label(int $titik): string
    {
        return $this->titikList()[$titik]['label'] ?? "Petak {$titik}";
    }

    public function latestWeather(string $iotId): ?SensorIot
    {
        return SensorIot::where('iot_id', $iotId)
            ->where('titik', config('monitoring.weather_titik', 1))
            ->whereNotNull('temperature')
            ->orderByDesc('id')
            ->first();
    }

    public function latestSensor(string $iotId, int $titik): ?SensorIot
    {
        return SensorIot::where('iot_id', $iotId)->where('titik', $titik)->orderByDesc('id')->first();
    }

    public function latestCamera(string $iotId, int $titik): ?SensorKamera
    {
        return SensorKamera::where('iot_id', $iotId)->where('titik', $titik)->orderByDesc('id')->first();
    }

    public function snapshot(string $iotId): array
    {
        $weather = $this->latestWeather($iotId);
        $weatherFields = config('monitoring.weather_fields', []);
        $soilFields = config('monitoring.soil_fields', []);

        $titik = [];
        foreach ($this->titikList() as $no => $info) {
            $sensor = $this->latestSensor($iotId, $no);
            $camera = $this->latestCamera($iotId, $no);

            $titik[(string) $no] = [
                'label' => $info['label'] ?? "Petak {$no}",
                'soil' => $sensor ? $this->numbers($sensor, $soilFields) : null,
                'soil_at' => optional($sensor?->created_at)->toDateTimeString(),
                'camera' => $camera ? [
                    'penyakit' => $camera->penyakit,
                    'probabilitas' => is_numeric($camera->probabilitas) ? (float) $camera->probabilitas : null,
                    'image' => $camera->image,
                    'created_at' => optional($camera->created_at)->toDateTimeString(),
                ] : null,
                'tanam' => ! empty($info['tanggal_tanam']) ? [
                    'tanggal_tanam' => $info['tanggal_tanam'],
                    'umur_varietas' => $info['umur_varietas'] ?? 115,
                    'umur_bibit' => $info['umur_bibit'] ?? 21,
                ] : null,
                'status' => $sensor ? [
                    'battery_level' => $sensor->battery_level,
                    'signal_strength' => $sensor->signal_strength,
                    'feromon' => $sensor->feromon,
                ] : null,
            ];
        }

        $weatherValues = $weather ? $this->numbers($weather, $weatherFields) : null;
        if ($weatherValues !== null) {
            $weatherValues['rainfall_last'] = $weatherValues['rainfall'] ?? null;
            $weatherValues['rainfall'] = $this->rainfall24h($iotId);
        }

        return [
            'iot_id' => $iotId,
            'weather' => $weatherValues,
            'weather_at' => optional($weather?->created_at)->toDateTimeString(),
            'dummy' => (bool) ($weather?->is_dummy) || collect($this->titikList())->keys()
                ->contains(fn ($no) => (bool) $this->latestSensor($iotId, $no)?->is_dummy),
            'titik' => $titik,
        ];
    }

    public function recommend(string $iotId): array
    {
        $snapshot = $this->snapshot($iotId);

        return Cache::remember("rekomendasi:{$iotId}:" . md5(json_encode($snapshot)), 30, function () use ($snapshot) {
            try {
                $response = Http::timeout(config('monitoring.recommendation.timeout', 15))
                    ->acceptJson()
                    ->post(config('monitoring.recommendation.url'), $snapshot);

                if ($response->successful() && is_array($response->json('petak'))) {
                    return $response->json() + ['snapshot' => $snapshot];
                }
            } catch (\Throwable $e) {
                report($e);
            }

            return $this->fallback($snapshot) + ['snapshot' => $snapshot];
        });
    }

    public function sensorForecast(string $iotId, int $titik, int $days): ?array
    {
        try {
            $response = Http::timeout(config('monitoring.sensor_forecast.timeout', 30))->acceptJson()
                ->get(config('monitoring.sensor_forecast.url') . $titik, ['days' => $days]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        $data = $response->successful() ? $response->json() : null;
        if (! is_array($data) || ! isset($data['forecast']) || ($data['iot_id'] ?? null) !== $iotId) {
            return null;
        }

        $rainPer4h = false;
        foreach ($data['variables'] ?? [] as $i => $var) {
            if (($var['unit'] ?? null) === '% VWC') {
                $data['variables'][$i]['unit'] = '%';
            }
            if (($var['key'] ?? null) === 'rainfall' && ($var['unit'] ?? null) === 'mm/4 jam') {
                $data['variables'][$i]['unit'] = 'mm/hari';
                $rainPer4h = true;
            }
        }
        if ($rainPer4h) {
            $data = $this->rainfallPerDay($data, intdiv(24, (int) ($data['resolution_hours'] ?? 4)));
        }

        $health = $this->healthTrend($iotId, $titik, $days, (int) ($data['resolution_hours'] ?? 4));
        foreach ($data['forecast'] as $i => $row) {
            foreach ($row['status'] ?? [] as $j => $status) {
                $data['forecast'][$i]['status'][$j]['reason'] = $this->statusReason($status['code'] ?? '', $row);
                $data['forecast'][$i]['status'][$j]['label'] = self::STATUS_LABELS[$status['code'] ?? ''] ?? $status['label'];
            }
            $data['forecast'][$i]['health'] = $health[$this->blockKey($row['date'], (int) ($data['resolution_hours'] ?? 4))] ?? null;
            $data['forecast'][$i]['conclusion'] = $this->conclusion($data['forecast'][$i]);
        }

        $data['measured'] = $this->measuredRows($iotId, $titik, 5, $health['_current'] ?? null);

        return $data;
    }

    public const SENSOR_VARIABLES = [
        ['key' => 'temperature', 'label' => 'Suhu udara', 'unit' => '°C'],
        ['key' => 'humidity', 'label' => 'Kelembapan udara', 'unit' => '%'],
        ['key' => 'vpd', 'label' => 'VPD', 'unit' => 'kPa'],
        ['key' => 'windspeed', 'label' => 'Kecepatan angin', 'unit' => 'm/s'],
        ['key' => 'rainfall', 'label' => 'Curah hujan', 'unit' => 'mm/hari'],
        ['key' => 'light_intensity', 'label' => 'Intensitas cahaya', 'unit' => 'lux'],
        ['key' => 'pressure', 'label' => 'Tekanan udara', 'unit' => 'Pa'],
        ['key' => 'ph', 'label' => 'pH tanah', 'unit' => ''],
        ['key' => 'soil_moisture', 'label' => 'Kadar air tanah', 'unit' => '%'],
        ['key' => 'ec', 'label' => 'EC', 'unit' => 'µS/cm'],
        ['key' => 'tds', 'label' => 'TDS', 'unit' => 'ppm'],
        ['key' => 'soil_temp', 'label' => 'Suhu tanah', 'unit' => '°C'],
        ['key' => 'Nitrogen_Level', 'label' => 'Nitrogen (N)', 'unit' => 'mg/kg'],
        ['key' => 'Phosphorus_Level', 'label' => 'Fosfor (P)', 'unit' => 'mg/kg'],
        ['key' => 'Potassium_Level', 'label' => 'Kalium (K)', 'unit' => 'mg/kg'],
    ];

    public function measuredHistory(string $iotId, int $titik, int $days): ?array
    {
        $weatherTitik = (int) config('monitoring.weather_titik', 1);
        $last = SensorIot::where('iot_id', $iotId)->where('titik', $titik)->max('created_at');
        if (! $last) {
            return null;
        }
        $to = \Illuminate\Support\Carbon::parse($last);
        $from = $to->copy()->subDays($days);

        $readings = SensorIot::where('iot_id', $iotId)->where('titik', $titik)
            ->whereBetween('created_at', [$from, $to])->orderBy('created_at')->orderBy('id')->get();
        $weather = SensorIot::where('iot_id', $iotId)->where('titik', $weatherTitik)
            ->whereBetween('created_at', [$from->copy()->subDay(), $to])->orderBy('created_at')->orderBy('id')->get()->values();

        $num = fn ($v) => is_numeric($v) ? (float) $v : null;
        $weatherFields = ['temperature', 'humidity', 'windspeed', 'light_intensity', 'pressure'];
        $soilFields = ['ph', 'soil_moisture', 'ec', 'tds', 'soil_temp', 'Nitrogen_Level', 'Phosphorus_Level', 'Potassium_Level'];

        $lstm = $readings->isEmpty() ? [] : Cache::remember(
            "lstm_history:{$iotId}:{$titik}:{$readings->count()}:" . $readings->last()->created_at->timestamp,
            now()->addMinutes(5),
            fn () => $this->healthHistory($iotId, $titik, $readings->count())
        );

        $rows = [];
        $wi = -1;
        $start = 0;
        $rainSum = 0.0;
        foreach ($readings as $r) {
            $t = $r->created_at;
            while ($wi + 1 < $weather->count() && $weather[$wi + 1]->created_at <= $t) {
                $wi++;
                $rainSum += (float) $weather[$wi]->rainfall;
            }
            while ($start <= $wi && $weather[$start]->created_at < $t->copy()->subDay()) {
                $rainSum -= (float) $weather[$start]->rainfall;
                $start++;
            }
            $w = null;
            for ($k = $wi; $k >= 0; $k--) {
                if ($weather[$k]->temperature !== null) {
                    $w = $weather[$k];
                    break;
                }
            }

            $row = ['date' => $t->format('Y-m-d H:i')];
            foreach ($weatherFields as $f) {
                $row[$f] = $w ? $num($w->{$f}) : null;
            }
            foreach ($soilFields as $f) {
                $row[$f] = $num($r->{$f});
            }
            $row['vpd'] = ($row['temperature'] !== null && $row['humidity'] !== null)
                ? round(0.6108 * exp((17.27 * $row['temperature']) / ($row['temperature'] + 237.3)) * (1 - $row['humidity'] / 100), 3)
                : null;
            $row['rainfall'] = $row['rainfall_24h'] = $wi >= 0 ? round(max(0, $rainSum), 2) : null;
            $row['status'] = array_map(fn ($code) => [
                'code' => $code, 'label' => self::STATUS_LABELS[$code], 'reason' => $this->statusReason($code, $row),
            ], $this->statusCodes($row));
            $row['health'] = $lstm[$row['date']] ?? null;
            $row['conclusion'] = $this->conclusion($row);
            $rows[] = $row;
        }

        return [
            'titik' => $titik,
            'days' => $days,
            'variables' => self::SENSOR_VARIABLES,
            'history' => $rows,
            'measured' => $rows,
            'forecast' => [],
            'last_observation' => $to->format('Y-m-d H:i'),
        ];
    }

    public function combineForecast(array $byPetak): ?array
    {
        $byPetak = array_filter($byPetak);
        if (! $byPetak) {
            return null;
        }
        $base = reset($byPetak);
        $keys = array_column($base['variables'], 'key');
        $rank = ['aman' => 0, 'cek_lapangan' => 1, 'waspada' => 2, 'bahaya' => 3];

        $merge = function (string $part) use ($byPetak, $keys, $rank): array {
            $out = [];
            foreach ($byPetak as $no => $fc) {
                foreach ($fc[$part] ?? [] as $row) {
                    $out[$row['date']][$no] = $row;
                }
            }
            ksort($out);
            $rows = [];
            foreach ($out as $date => $perPetak) {
                $row = ['date' => $date];
                foreach (array_merge($keys, ['rainfall_24h']) as $k) {
                    $vals = array_values(array_filter(array_column($perPetak, $k), fn ($v) => $v !== null));
                    $row[$k] = $vals ? round(array_sum($vals) / count($vals), 3) : null;
                }
                $health = array_filter(array_column($perPetak, 'health'));
                $row['health'] = null;
                if ($health) {
                    $avg = fn ($f) => round(array_sum(array_column($health, $f)) / count($health), 1);
                    $p = ['Healthy' => $avg('p_healthy'), 'Moderate Stress' => $avg('p_moderate'), 'High Stress' => $avg('p_high')];
                    $row['health'] = $this->healthEntry(array_map(fn ($v) => $v / 100, $p), array_search(max($p), $p));
                }
                $row['status'] = [];
                foreach ($perPetak as $no => $r) {
                    foreach ($r['status'] ?? [] as $st) {
                        $label = $this->label((int) $no);
                        $row['status'][] = ['code' => $st['code'], 'label' => "{$label}: {$st['label']}",
                            'reason' => "{$label}: " . ($st['reason'] ?? '-')];
                    }
                }
                $worst = collect($perPetak)->pluck('conclusion')->filter()
                    ->sortByDesc(fn ($c) => $rank[$c['code']] ?? 0)->first();
                $row['conclusion'] = $worst ?? $this->conclusion($row);
                $rows[] = $row;
            }

            return $rows;
        };

        $base['titik'] = 0;
        $base['forecast'] = $merge('forecast');
        $base['measured'] = $merge('measured');
        $base['history'] = $merge('history');

        return $base;
    }

    private function measuredRows(string $iotId, int $titik, int $limit, ?array $currentHealth): array
    {
        $weatherTitik = (int) config('monitoring.weather_titik', 1);
        $readings = SensorIot::where('iot_id', $iotId)->where('titik', $titik)
            ->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get()->reverse()->values();

        $weatherFields = ['temperature', 'humidity', 'windspeed', 'light_intensity', 'pressure'];
        $soilFields = ['ph', 'soil_moisture', 'ec', 'tds', 'soil_temp', 'Nitrogen_Level', 'Phosphorus_Level', 'Potassium_Level'];
        $num = fn ($v) => is_numeric($v) ? (float) $v : null;
        $lstm = $this->healthHistory($iotId, $titik, $limit);

        $rows = [];
        foreach ($readings as $k => $r) {
            $w = $titik === $weatherTitik ? $r : SensorIot::where('iot_id', $iotId)->where('titik', $weatherTitik)
                ->where('created_at', '<=', $r->created_at)->whereNotNull('temperature')->orderByDesc('created_at')->first();

            $row = ['date' => $r->created_at->format('Y-m-d H:i')];
            foreach ($weatherFields as $f) {
                $row[$f] = $w ? $num($w->{$f}) : null;
            }
            foreach ($soilFields as $f) {
                $row[$f] = $num($r->{$f});
            }
            $t = $row['temperature'];
            $rh = $row['humidity'];
            $row['vpd'] = ($t !== null && $rh !== null)
                ? round(0.6108 * exp((17.27 * $t) / ($t + 237.3)) * (1 - $rh / 100), 3) : null;
            $row['rainfall'] = round((float) SensorIot::where('iot_id', $iotId)->where('titik', $weatherTitik)
                ->whereBetween('created_at', [$r->created_at->copy()->subDay(), $r->created_at])->sum('rainfall'), 2);
            $row['rainfall_24h'] = $row['rainfall'];

            $row['status'] = array_map(fn ($code) => [
                'code' => $code,
                'label' => self::STATUS_LABELS[$code],
                'reason' => $this->statusReason($code, $row),
            ], $this->statusCodes($row));
            $row['health'] = $lstm[$row['date']] ?? ($k === $readings->count() - 1 ? $currentHealth : null);
            $row['conclusion'] = $this->conclusion($row);
            $rows[] = $row;
        }

        return $rows;
    }

    private function statusCodes(array $row): array
    {
        $t = RecommendationService::THRESHOLDS;
        [$temp, $rh, $vwc, $ec, $tds, $ph, $wind, $rain] = [$row['temperature'], $row['humidity'], $row['soil_moisture'],
            $row['ec'], $row['tds'], $row['ph'], $row['windspeed'], $row['rainfall_24h']];
        $codes = [];
        if ($temp !== null && $temp >= $t['temp_heat']) {
            $codes[] = 'panas';
        }
        if ($temp !== null && $rh !== null && $rh >= $t['blast_rh'] && $temp >= $t['blast_t_min'] && $temp <= $t['blast_t_max']) {
            $codes[] = 'blas';
        }
        if ($temp !== null && $rh !== null && $rain !== null && $rain >= $t['blb_rain_24h'] && $rh > $t['blb_rh']
            && $temp >= $t['blb_t_min'] && $temp <= $t['blb_t_max']) {
            $codes[] = 'hawar_bakteri';
        }
        if ($vwc !== null && $vwc < $t['soil_moisture_irrigate']) {
            $codes[] = 'irigasi';
        }
        if (($ec !== null && $ec >= $t['ec_saline']) || ($tds !== null && $tds >= $t['tds_saline'])) {
            $codes[] = 'salin';
        }
        if ($ph !== null && $ph <= $t['ph_acid']) {
            $codes[] = 'ph_masam';
        } elseif ($ph !== null && $ph > $t['ph_alkaline']) {
            $codes[] = 'ph_basa';
        }
        if ($wind !== null && $wind >= $t['wind_lodging']) {
            $codes[] = 'angin';
        }

        return $codes;
    }

    private function healthTrend(string $iotId, int $titik, int $days, int $blockHours): array
    {
        try {
            $response = Http::timeout(config('monitoring.sensor_forecast.timeout', 30))->acceptJson()
                ->get(config('services.rnn.url') . ($days + 1) . '&titik=' . $titik);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        $data = $response->successful() ? $response->json() : null;
        if (! is_array($data) || ! is_array($data['forecast'] ?? null) || ($data['iot_id'] ?? null) !== $iotId) {
            return [];
        }

        $trend = [];
        foreach ($data['forecast'] as $row) {
            $c = $row['confidence'] ?? null;
            if (! is_array($c) || ! isset($row['date'], $c['High Stress'], $c['Healthy'], $c['Moderate Stress'])) {
                continue;
            }
            $key = ($row['forecast_type'] ?? null) === 'current' ? '_current' : $this->blockKey($row['date'], $blockHours);
            $trend[$key] = $this->healthEntry($c, $row['predicted_health'] ?? null);
        }

        return $trend;
    }

    private function healthEntry(array $c, ?string $label): array
    {
        return [
            'stress' => round($c['High Stress'] * 100, 1),
            'healthy' => round(($c['Healthy'] + $c['Moderate Stress']) * 100, 1),
            'label' => $label,
            'p_healthy' => round($c['Healthy'] * 100, 1),
            'p_moderate' => round($c['Moderate Stress'] * 100, 1),
            'p_high' => round($c['High Stress'] * 100, 1),
        ];
    }

    private function healthHistory(string $iotId, int $titik, int $limit): array
    {
        $base = preg_replace('#/forecast/.*$#', '', (string) config('services.rnn.url'));
        try {
            $data = Http::timeout(max(30, (int) config('monitoring.sensor_forecast.timeout', 30), intdiv($limit, 20)))->acceptJson()
                ->get("{$base}/health-history/{$titik}?n={$limit}")->json();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
        if (! is_array($data) || ($data['iot_id'] ?? null) !== $iotId) {
            return [];
        }

        $out = [];
        foreach ($data['history'] ?? [] as $h) {
            $c = $h['confidence'] ?? null;
            if (is_array($c) && isset($h['date'], $c['Healthy'], $c['Moderate Stress'], $c['High Stress'])) {
                $out[$h['date']] = $this->healthEntry($c, $h['predicted_health'] ?? null);
            }
        }

        return $out;
    }

    private function blockKey(string $date, int $blockHours): string
    {
        $t = \Illuminate\Support\Carbon::parse($date);

        return $t->setTime(intdiv($t->hour, max(1, $blockHours)) * max(1, $blockHours), 0)->format('Y-m-d H:i');
    }

    private const STATUS_LABELS = [
        'panas' => 'Udara terlalu panas',
        'blas' => 'Rawan penyakit blas',
        'hawar_bakteri' => 'Rawan penyakit kresek',
        'irigasi' => 'Tanah kering, perlu diairi',
        'salin' => 'Tanah/air terlalu asin',
        'ph_masam' => 'Tanah terlalu masam',
        'ph_basa' => 'Tanah terlalu basa',
        'angin' => 'Angin kencang, padi rawan rebah',
    ];

    private function conclusion(array $row): array
    {
        $warning = ! empty($row['status']);
        $highStress = ($row['health']['label'] ?? null) === 'High Stress';

        return match (true) {
            $warning && $highStress => ['code' => 'bahaya', 'label' => 'Bahaya: tindak lanjuti sekarang', 'color' => 'danger'],
            $warning => ['code' => 'waspada', 'label' => 'Waspada: ikuti rekomendasi Status', 'color' => 'warning'],
            $highStress => ['code' => 'cek_lapangan', 'label' => 'Cek lapangan: LSTM mendeteksi stres', 'color' => 'info'],
            default => ['code' => 'aman', 'label' => 'Aman', 'color' => 'success'],
        };
    }

    private function statusReason(string $code, array $row): ?string
    {
        $t = RecommendationService::THRESHOLDS;
        $f = fn ($v, int $d = 1) => $v === null ? '-' : rtrim(rtrim(number_format((float) $v, $d, '.', ''), '0'), '.');
        $temp = $f($row['temperature'] ?? null);
        $rh = $f($row['humidity'] ?? null, 0);

        return match ($code) {
            'panas' => "Suhu {$temp} °C ≥ {$t['temp_heat']} °C",
            'blas' => "Kelembapan {$rh}% ≥ {$t['blast_rh']}%, suhu {$temp} °C ({$t['blast_t_min']}–{$t['blast_t_max']} °C)",
            'hawar_bakteri' => 'Hujan ' . $f($row['rainfall_24h'] ?? $row['rainfall'] ?? null) . " mm/hari ≥ {$t['blb_rain_24h']}, "
                . "kelembapan {$rh}% > {$t['blb_rh']}%, suhu {$temp} °C ({$t['blb_t_min']}–{$t['blb_t_max']} °C)",
            'irigasi' => 'Kadar air tanah ' . $f($row['soil_moisture'] ?? null) . "% < {$t['soil_moisture_irrigate']}%",
            'salin' => (($row['ec'] ?? 0) >= $t['ec_saline'])
                ? 'EC ' . $f($row['ec'], 0) . " µS/cm ≥ {$t['ec_saline']}"
                : 'TDS ' . $f($row['tds'] ?? null, 0) . " ppm ≥ {$t['tds_saline']}",
            'ph_masam' => 'pH ' . $f($row['ph'] ?? null, 2) . " ≤ {$t['ph_acid']}",
            'ph_basa' => 'pH ' . $f($row['ph'] ?? null, 2) . " > {$t['ph_alkaline']}",
            'angin' => 'Angin ' . $f($row['windspeed'] ?? null) . " m/s ≥ {$t['wind_lodging']} m/s",
            default => null,
        };
    }

    private function rainfallPerDay(array $data, int $blocksPerDay): array
    {
        foreach ($data['forecast'] as $i => $row) {
            if (array_key_exists('rainfall_24h', $row)) {
                $data['forecast'][$i]['rainfall'] = $row['rainfall_24h'];
            }
        }

        $raw = array_column($data['history'] ?? [], 'rainfall');
        foreach ($data['history'] ?? [] as $i => $row) {
            $window = array_slice($raw, max(0, $i - $blocksPerDay + 1), min($blocksPerDay, $i + 1));
            $known = array_filter($window, fn ($v) => $v !== null);
            $data['history'][$i]['rainfall'] = ($i + 1 >= $blocksPerDay && $known) ? round(array_sum($known), 3) : null;
        }

        return $data;
    }

    public function rainfall24h(string $iotId, ?int $titik = null): float
    {
        return round((float) SensorIot::where('iot_id', $iotId)
            ->where('titik', $titik ?? (int) config('monitoring.weather_titik', 1))
            ->where('created_at', '>=', now()->subDay())
            ->sum('rainfall'), 2);
    }

    private function fallback(array $snapshot): array
    {
        $rules = new RecommendationService();
        $petak = [];
        $summary = [];

        foreach ($snapshot['titik'] as $no => $node) {
            if (! $snapshot['weather'] && ! $node['soil']) {
                continue;
            }
            $row = (new SensorIot())->forceFill(array_merge(
                $snapshot['weather'] ?? [],
                $node['soil'] ?? [],
            ));
            $cameraRows = $node['camera'] ? [(new SensorKamera())->forceFill($node['camera'])] : [];

            $recs = $rules->getRecommendations([$row], $cameraRows);
            $actuation = $rules->getActuation([$row], $cameraRows, null);

            $petak[$no] = [
                'label' => $node['label'],
                'recommendations' => $recs,
                'actuation' => $actuation,
                'health' => null,
                'camera' => $node['camera'],
            ];

            foreach ($recs as $rec) {
                $key = $rec['title'];
                $summary[$key] ??= $rec + ['petak' => []];
                $summary[$key]['petak'][] = $node['label'];
            }
        }

        return [
            'model' => ['source' => 'php_rules', 'name' => 'Aturan agronomi (cadangan)', 'version' => null],
            'generated_at' => now()->toIso8601String(),
            'petak' => $petak,
            'summary' => array_values($summary),
        ];
    }

    private function numbers(SensorIot $row, array $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            $out[$field] = is_null($row->{$field}) ? null : (float) $row->{$field};
        }

        return $out;
    }
}
