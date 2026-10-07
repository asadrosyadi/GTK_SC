<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AiAssistantService;
use App\Services\MultiTitikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Dashboard\Models\SensorIot;
use Modules\Dashboard\Models\SensorKamera;
use Modules\Dashboard\Models\HybridInference;

class DashboardController extends Controller
{
    public function __construct(private MultiTitikService $multiTitik)
    {
        parent::__construct();
    }

    private function selectedTitik(Request $request): int
    {
        $titik = (int) $request->input('titik', 0);

        return $titik === 0 || array_key_exists($titik, $this->multiTitik->titikList()) ? $titik : 0;
    }

    public function index(Request $request): View
    {
        set_time_limit(120);

        $iot_id = auth()->user()->iot_id;
        $titik = $this->selectedTitik($request);

        $maxDays = (int) config('monitoring.sensor_forecast.max_days', 30);
        $days = max(1, min($maxDays, (int) $request->input('days', 3)));

        $sensorForecast = $titik === 0
            ? $this->multiTitik->combineForecast(collect($this->multiTitik->titikList())
                ->mapWithKeys(fn ($info, $no) => [$no => $this->multiTitik->sensorForecast($iot_id, $no, $days)])->all())
            : $this->multiTitik->sensorForecast($iot_id, $titik, $days);

        return view('dashboard::index', [
            'user' => $request->user(),
            'titik' => $titik,
            'titikList' => $this->multiTitik->titikList(),
            'days' => $days,
            'maxDays' => $maxDays,
            'sensorForecast' => $sensorForecast,
        ]);
    }

    public function aiSummary(AiAssistantService $ai): JsonResponse
    {
        set_time_limit(120);
        try {
            return response()->json(['enabled' => true] + $ai->summary(auth()->user()->iot_id));
        } catch (\RuntimeException $e) {
            return response()->json(['enabled' => $ai->enabled(), 'error' => $e->getMessage()], $ai->enabled() ? 502 : 200);
        }
    }

    public function aiChat(Request $request, AiAssistantService $ai): JsonResponse
    {
        set_time_limit(120);
        $data = $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'array|max:20',
            'history.*.role' => 'in:user,assistant',
            'history.*.content' => 'string|max:4000',
        ]);
        try {
            return response()->json(['answer' => $ai->chat(auth()->user()->iot_id, $data['message'], $data['history'] ?? [])]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], $ai->enabled() ? 502 : 200);
        }
    }

    public function getDataPrediksi()
    {
        $iot_id = auth()->user()->iot_id;
        $result = $this->multiTitik->recommend($iot_id);

        $recommendations = collect($result['summary'] ?? [])->map(fn ($r) => [
            'type' => $r['type'] ?? 'info',
            'icon' => $r['icon'] ?? 'bx bx-info-circle',
            'title' => $r['title'] ?? '-',
            'message' => $r['message'] ?? '',
            'petak' => $r['petak'] ?? [],
            'probability' => $r['probability'] ?? null,
            'reasons_by_petak' => $r['reasons_by_petak'] ?? (object) [],
            'sources' => $r['sources'] ?? [],
        ])->values()->all();

        if (! $recommendations) {
            $recommendations = [[
                'type' => 'info',
                'icon' => 'bx bx-info-circle',
                'title' => 'Belum ada rekomendasi',
                'message' => 'Belum ada data sensor yang cukup untuk memberikan rekomendasi.',
                'petak' => [],
            ]];
        }

        $hybrid = [];
        foreach (array_keys($this->multiTitik->titikList()) as $no) {
            $hybrid[$no] = HybridInference::where('iot_id', $iot_id)->where('titik', $no)->orderByDesc('id')->first();
        }

        $petak = $result['petak'] ?? [];
        $firstPetak = $petak[array_key_first($petak) ?? 1] ?? null;

        return response()->json([
            'model' => $result['model'] ?? null,
            'generated_at' => $result['generated_at'] ?? null,
            'recommendations' => $recommendations,
            'petak' => $petak,
            'actuation' => $firstPetak['actuation'] ?? null,
            'hybridInference' => $hybrid[1] ?? null,
            'hybridInferences' => $hybrid,
        ]);
    }

    public function getData(Request $request)
    {
        $iot_id = auth()->user()->iot_id;
        $snapshot = $this->multiTitik->snapshot($iot_id);

        $sensorData = SensorIot::where('iot_id', $iot_id)
            ->where('titik', config('monitoring.weather_titik', 1))
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $data = $sensorData->map(fn ($item) => [
            'titik' => $item->titik,
            'temperature' => $item->temperature,
            'humidity' => $item->humidity,
            'windspeed' => $item->windspeed,
            'rainfall' => $item->rainfall,
            'light_intensity' => $item->light_intensity,
            'ph' => $item->ph,
            'soil_moisture' => $item->soil_moisture,
            'ec' => $item->ec,
            'tds' => $item->tds,
            'soil_temp' => $item->soil_temp,
            'pressure' => $item->pressure,
            'feromon' => $item->feromon,
            'battery_level' => $item->battery_level,
            'signal_strength' => $item->signal_strength,
            'nitrogen' => $item->Nitrogen_Level,
            'fosfor' => $item->Phosphorus_Level,
            'potassium' => $item->Potassium_Level,
            'created_at' => optional($item->created_at)->toDateTimeString(),
        ]);

        return response()->json([
            'weather' => $snapshot['weather'],
            'weather_at' => $snapshot['weather_at'],
            'titik' => $snapshot['titik'],
            'dummy' => $snapshot['dummy'],
            'data' => $data,
            'total_records' => $data->count(),
        ]);
    }

    public function getDataKamera(Request $request)
    {
        $iot_id = auth()->user()->iot_id;

        $map = fn ($item) => [
            'titik' => $item->titik,
            'image_url' => $item->image,
            'penyakit' => $item->penyakit,
            'probabilitas' => $item->probabilitas,
            'created_at' => optional($item->created_at)->toDateTimeString(),
        ];

        $perTitik = [];
        foreach (array_keys($this->multiTitik->titikList()) as $no) {
            $latest = $this->multiTitik->latestCamera($iot_id, $no);
            $perTitik[$no] = $latest ? $map($latest) : null;
        }

        $kameraData = SensorKamera::where('iot_id', $iot_id)->orderByDesc('id')->limit(20)->get();

        return response()->json([
            'titik' => $perTitik,
            'data' => $kameraData->map($map),
            'total_records' => $kameraData->count(),
        ]);
    }
}
