<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\MultiTitikService;
use Illuminate\Http\Request;
use Modules\History\Http\Controllers\HistoryController;

class ForecastController extends ApiController
{
    public function __construct(private MultiTitikService $multiTitik)
    {
    }

    private function titik(Request $request, int $default = 0): int
    {
        $titik = (int) $request->query('titik', $default);

        return $titik === 0 ? 0 : $this->validTitik($titik);
    }

    public function forecast(Request $request)
    {
        $max = (int) config('monitoring.sensor_forecast.max_days', 30);
        $valid = $request->validate(['days' => "nullable|integer|min:1|max:{$max}"]);
        $titik = $this->titik($request);
        $days = (int) ($valid['days'] ?? 3);
        $iotId = $this->iotId($request);
        set_time_limit(180);

        $data = $titik === 0
            ? $this->multiTitik->combineForecast(collect($this->multiTitik->titikList())
                ->mapWithKeys(fn ($info, $no) => [$no => $this->multiTitik->sensorForecast($iotId, $no, $days)])->all())
            : $this->multiTitik->sensorForecast($iotId, $titik, $days);

        if ($data === null) {
            return response()->json(['message' => 'Ramalan belum tersedia (layanan peramalan mati atau data sensor belum ada)'], 503);
        }

        return response()->json(['data' => ['titik' => $titik, 'days' => $days] + $data]);
    }

    public function measured(Request $request)
    {
        $valid = $request->validate(['days' => 'nullable|integer|min:1|max:30']);
        $titik = $this->titik($request);
        $days = (int) ($valid['days'] ?? 1);
        $iotId = $this->iotId($request);
        set_time_limit(300);

        $data = $titik === 0
            ? $this->multiTitik->combineForecast(collect($this->multiTitik->titikList())
                ->mapWithKeys(fn ($info, $no) => [$no => $this->multiTitik->measuredHistory($iotId, $no, $days)])->all())
            : $this->multiTitik->measuredHistory($iotId, $titik, $days);

        if ($data === null) {
            return response()->json(['data' => ['titik' => $titik, 'days' => $days, 'variables' => MultiTitikService::SENSOR_VARIABLES, 'rows' => []]]);
        }

        return response()->json(['data' => [
            'titik' => $titik,
            'days' => $days,
            'last_observation' => $data['last_observation'] ?? null,
            'variables' => $data['variables'],
            'rows' => array_reverse($data['measured'] ?? []),
        ]]);
    }

    public function export(Request $request)
    {
        $request->attributes->set('api_iot_id', $this->iotId($request));

        return app(HistoryController::class)->exportData($request);
    }
}
