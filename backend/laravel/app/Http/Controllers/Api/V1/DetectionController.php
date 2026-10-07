<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\DetectionResource;
use App\Services\DetectionService;
use Illuminate\Http\Request;
use Modules\Dashboard\Models\SensorKamera;

class DetectionController extends ApiController
{
    public function index(Request $request)
    {
        return DetectionResource::collection(
            $this->history($request, SensorKamera::where('iot_id', $this->iotId($request)))
        );
    }

    public function latest(Request $request)
    {
        $iotId = $this->iotId($request);
        $rows = collect(array_keys(config('monitoring.titik', [])))
            ->map(fn ($titik) => SensorKamera::where('iot_id', $iotId)->where('titik', $titik)->orderByDesc('id')->first())
            ->filter()
            ->values();

        return DetectionResource::collection($rows);
    }

    public function summary(Request $request, DetectionService $service)
    {
        $valid = $request->validate(['titik' => 'nullable|integer|min:0', 'days' => 'nullable|integer|min:1|max:30']);
        $titik = (int) ($valid['titik'] ?? 0);
        if ($titik > 0) {
            $this->validTitik($titik);
        }
        $days = (int) ($valid['days'] ?? 7);
        [$rows, $last] = $service->detections($this->iotId($request), $titik, $days);

        return response()->json(['data' => [
            'titik' => $titik,
            'days' => $days,
            'last_detection_at' => $last?->toIso8601String(),
            'summary' => $service->summary($rows),
            'distribution' => $service->distribution($rows)
                ->map(fn ($perPetak, $penyakit) => ['penyakit' => $penyakit, 'total' => $perPetak->sum(), 'per_petak' => $perPetak])
                ->values(),
            'items' => $rows->map(fn ($r) => $r + ['image_url' => $r['image']])->values(),
        ]]);
    }

    public function show(Request $request, int $id)
    {
        return new DetectionResource(
            SensorKamera::where('iot_id', $this->iotId($request))->findOrFail($id)
        );
    }
}
