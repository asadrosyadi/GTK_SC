<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\DetectionResource;
use App\Http\Resources\V1\SensorReadingResource;
use App\Services\MultiTitikService;
use Illuminate\Http\Request;

class PlotController extends ApiController
{
    public function __construct(private MultiTitikService $multiTitik)
    {
    }

    public function index()
    {
        $data = collect(config('monitoring.titik', []))
            ->map(fn ($info, $titik) => $this->plotInfo((int) $titik, $info))
            ->values();

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, int $titik)
    {
        $iotId = $this->iotId($request);
        $info = config('monitoring.titik')[$this->validTitik($titik)];
        $sensor = $this->multiTitik->latestSensor($iotId, $titik);
        $camera = $this->multiTitik->latestCamera($iotId, $titik);
        $weather = empty($info['weather']) ? $this->multiTitik->latestWeather($iotId) : null;

        return response()->json(['data' => $this->plotInfo($titik, $info) + [
            'latest_reading' => $sensor ? (new SensorReadingResource($sensor))->resolve($request) : null,
            'area_weather' => $weather ? (new SensorReadingResource($weather))->resolve($request)['weather']
                + ['recorded_at' => optional($weather->created_at)->toIso8601String(),
                   'source_titik' => (int) config('monitoring.weather_titik', 1)] : null,
            'latest_detection' => $camera ? (new DetectionResource($camera))->resolve($request) : null,
        ]]);
    }

    public function forecast(Request $request, int $titik)
    {
        $this->validTitik($titik);
        $max = (int) config('monitoring.sensor_forecast.max_days', 30);
        $valid = $request->validate(['days' => "nullable|integer|min:1|max:{$max}"]);
        $forecast = $this->multiTitik->sensorForecast($this->iotId($request), $titik, (int) ($valid['days'] ?? 3));

        if ($forecast === null) {
            return response()->json(['message' => 'Ramalan sensor belum tersedia (layanan peramalan mati atau data belum ada)'], 503);
        }

        return response()->json(['data' => $forecast]);
    }

    public function recommendations(Request $request)
    {
        $result = $this->multiTitik->recommend($this->iotId($request));
        $rank = ['danger' => 0, 'warning' => 1, 'info' => 2, 'success' => 3];
        $levelLabel = ['danger' => 'Mendesak', 'warning' => 'Perlu dilakukan', 'info' => 'Pantau', 'success' => 'Aman'];

        $items = collect($result['summary'] ?? [])
            ->map(fn ($r) => [
                'action' => $r['action'] ?? null,
                'title' => $r['title'] ?? '-',
                'message' => $r['message'] ?? '',
                'type' => $r['type'] ?? 'info',
                'level_label' => $levelLabel[$r['type'] ?? 'info'] ?? 'Pantau',
                'icon' => $r['icon'] ?? 'bx bx-info-circle',
                'petak' => $r['petak'] ?? [],
                'probability' => isset($r['probability']) ? round((float) $r['probability'], 3) : null,
                'reasons_by_petak' => (object) ($r['reasons_by_petak'] ?? []),
            ])
            ->sort(fn ($a, $b) => [($rank[$a['type']] ?? 9), -($a['probability'] ?? 0)] <=> [($rank[$b['type']] ?? 9), -($b['probability'] ?? 0)])
            ->values();

        $urgent = $items->where('type', 'danger')->count();
        $todo = $items->where('type', 'warning')->count();
        $worst = $urgent ? 'danger' : ($todo ? 'warning' : 'success');

        $plots = collect($result['petak'] ?? [])->map(function ($p, $no) use ($items, $levelLabel) {
            $label = $p['label'] ?? "Petak {$no}";
            $actions = $items->filter(fn ($r) => in_array($label, $r['petak'], true))->values();
            $level = $actions->first()['type'] ?? 'success';

            return [
                'titik' => (int) $no,
                'label' => $label,
                'fase' => $p['fase'] ?? null,
                'hst' => $p['hst'] ?? null,
                'level' => $level,
                'level_label' => $levelLabel[$level] ?? 'Aman',
                'actions' => $actions->map(fn ($r) => [
                    'title' => $r['title'],
                    'type' => $r['type'],
                    'level_label' => $r['level_label'],
                    'reasons' => ((array) $r['reasons_by_petak'])[$label] ?? [],
                ])->values(),
            ];
        })->values();

        $source = $result['model']['source'] ?? null;

        return response()->json(['data' => [
            'generated_at' => $result['generated_at'] ?? null,
            'model' => [
                'source' => $source,
                'label' => $source === 'random_forest' ? 'Model Random Forest' : 'Aturan cadangan',
            ],
            'overall' => [
                'level' => $worst,
                'headline' => $urgent ? "{$urgent} aksi mendesak perlu segera dilakukan"
                    : ($todo ? "{$todo} aksi perlu dilakukan" : 'Semua petak aman, lanjutkan pemantauan rutin'),
                'total' => $items->count(),
                'urgent' => $urgent,
                'todo' => $todo,
            ],
            'items' => $items,
            'plots' => $plots,
        ]]);
    }

    public function snapshot(Request $request)
    {
        return response()->json(['data' => $this->multiTitik->snapshot($this->iotId($request))]);
    }

    private function plotInfo(int $titik, array $info): array
    {
        return [
            'titik' => $titik,
            'label' => $info['label'] ?? "Petak {$titik}",
            'has_weather_station' => (bool) ($info['weather'] ?? false),
            'tanggal_tanam' => $info['tanggal_tanam'] ?? null,
            'umur_varietas' => $info['umur_varietas'] ?? null,
            'umur_bibit' => $info['umur_bibit'] ?? null,
        ];
    }
}
