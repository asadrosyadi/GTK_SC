<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\DetectionService;
use App\Services\MultiTitikService;
use App\Support\SensorStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function __construct(private MultiTitikService $multiTitik)
    {
    }

    public function __invoke(Request $request)
    {
        $iotId = $this->iotId($request);
        $snapshot = $this->multiTitik->snapshot($iotId);
        $rec = $this->multiTitik->recommend($iotId);

        $w = $snapshot['weather'] ?? null;
        $weather = null;
        if ($w) {
            $values = $w;
            $values['vpd'] = SensorStatus::vpd($w['temperature'] ?? null, $w['humidity'] ?? null);
            $items = SensorStatus::items(SensorStatus::WEATHER, $values, ['temperature' => $w['temperature'] ?? null]);
            foreach ($items as &$item) {
                if ($item['key'] === 'pressure' && $item['value'] !== null) {
                    $item['value'] = round($item['value'] / 100, 1);
                }
            }
            unset($item);
            $weather = [
                'source_titik' => (int) config('monitoring.weather_titik', 1),
                'recorded_at' => $this->iso($snapshot['weather_at'] ?? null),
                'items' => $items,
            ];
        }

        $kelas = ['Healthy' => 'Sehat', 'Moderate Stress' => 'Stres Sedang', 'High Stress' => 'Stres Tinggi'];
        $levelKelas = ['Healthy' => 'success', 'Moderate Stress' => 'warning', 'High Stress' => 'danger'];
        $plots = [];
        foreach ($snapshot['titik'] ?? [] as $no => $t) {
            $st = $t['status'] ?? [];
            $seenAt = $t['soil_at'] ?? null;
            $minutes = $seenAt ? Carbon::parse($seenAt)->diffInMinutes(now(), true) : null;
            $cam = $t['camera'] ?? null;
            $h = $rec['petak'][$no]['health'] ?? null;
            $top = is_array($h) && $h ? array_search(max($h), $h) : null;

            $plots[] = [
                'titik' => (int) $no,
                'label' => $t['label'] ?? "Petak {$no}",
                'fase' => $rec['petak'][$no]['fase'] ?? null,
                'hst' => $rec['petak'][$no]['hst'] ?? null,
                'soil' => SensorStatus::items(SensorStatus::SOIL, $t['soil'] ?? []),
                'device' => [
                    'battery_level' => is_numeric($st['battery_level'] ?? null) ? (float) $st['battery_level'] : null,
                    'signal_strength' => is_numeric($st['signal_strength'] ?? null) ? (float) $st['signal_strength'] : null,
                    'last_seen_at' => $this->iso($seenAt),
                    'minutes_ago' => $minutes !== null ? (int) round($minutes) : null,
                    'online' => $minutes !== null && $minutes <= SensorStatus::OFFLINE_AFTER_MINUTES,
                ],
                'camera' => $cam ? [
                    'penyakit' => $cam['penyakit'],
                    'skor_deteksi' => $cam['probabilitas'] !== null ? round($cam['probabilitas'], 1) : null,
                    'healthy' => DetectionService::isHealthy($cam['penyakit']),
                    'status' => DetectionService::isHealthy($cam['penyakit'])
                        ? ['code' => 'healthy', 'label' => 'Sehat', 'level' => 'success']
                        : ['code' => 'action', 'label' => 'Perlu tindakan', 'level' => 'danger'],
                    'advice' => DetectionService::advice($cam['penyakit']),
                    'image_url' => $cam['image'] ? asset('img/hama_padi/' . rawurlencode($cam['image'])) : null,
                    'detected_at' => $this->iso($cam['created_at'] ?? null),
                ] : null,
                'health' => $top ? [
                    'class' => $top,
                    'label' => $kelas[$top] ?? $top,
                    'level' => $levelKelas[$top] ?? 'secondary',
                    'p_healthy' => round(($h['Healthy'] ?? 0) * 100, 1),
                    'p_moderate' => round(($h['Moderate Stress'] ?? 0) * 100, 1),
                    'p_high' => round(($h['High Stress'] ?? 0) * 100, 1),
                ] : null,
            ];
        }

        return response()->json(['data' => [
            'generated_at' => now()->toIso8601String(),
            'dummy' => (bool) ($snapshot['dummy'] ?? false),
            'weather' => $weather,
            'plots' => $plots,
        ]]);
    }

    private function iso(?string $dt): ?string
    {
        return $dt ? Carbon::parse($dt)->toIso8601String() : null;
    }
}
