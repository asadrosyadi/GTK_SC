<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\SensorReadingResource;
use Illuminate\Http\Request;
use Modules\Dashboard\Models\SensorIot;

class SensorReadingController extends ApiController
{
    public function index(Request $request)
    {
        return SensorReadingResource::collection(
            $this->history($request, SensorIot::where('iot_id', $this->iotId($request)))
        );
    }

    public function latest(Request $request)
    {
        $iotId = $this->iotId($request);
        $rows = collect(array_keys(config('monitoring.titik', [])))
            ->map(fn ($titik) => SensorIot::where('iot_id', $iotId)->where('titik', $titik)->orderByDesc('id')->first())
            ->filter()
            ->values();

        return SensorReadingResource::collection($rows);
    }

    public function show(Request $request, int $id)
    {
        return new SensorReadingResource(
            SensorIot::where('iot_id', $this->iotId($request))->findOrFail($id)
        );
    }
}
