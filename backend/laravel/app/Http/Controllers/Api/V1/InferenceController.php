<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\InferenceResource;
use Illuminate\Http\Request;
use Modules\Dashboard\Models\HybridInference;

class InferenceController extends ApiController
{
    public function index(Request $request)
    {
        return InferenceResource::collection(
            $this->history($request, HybridInference::where('iot_id', $this->iotId($request)))
        );
    }

    public function latest(Request $request)
    {
        $iotId = $this->iotId($request);
        $rows = collect(array_keys(config('monitoring.titik', [])))
            ->map(fn ($titik) => HybridInference::where('iot_id', $iotId)->where('titik', $titik)->orderByDesc('id')->first())
            ->filter()
            ->values();

        return InferenceResource::collection($rows);
    }

    public function show(Request $request, int $id)
    {
        return new InferenceResource(
            HybridInference::where('iot_id', $this->iotId($request))->findOrFail($id)
        );
    }
}
