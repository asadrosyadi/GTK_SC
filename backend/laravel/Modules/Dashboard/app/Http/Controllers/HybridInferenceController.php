<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MqttPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Dashboard\Models\HybridInference;

class HybridInferenceController extends Controller
{
    private const MAX_ROWS = 10000;

    public function store(Request $request, MqttPublisher $mqtt)
    {
        $validated = $request->validate([
            'iot_id'            => 'required|string',
            'iot_token'         => 'required|string',
            'titik'             => 'nullable|integer|in:' . implode(',', array_keys(config('monitoring.titik', [1 => []]))),
            'image'             => 'nullable|string',
            'pest'              => 'nullable|array',
            'pest.label'        => 'nullable|string',
            'pest.class'        => 'nullable|string',
            'pest.confidence'   => 'nullable|numeric',
            'health'            => 'nullable|array',
            'health.status'     => 'nullable|string',
            'health.class'      => 'nullable|string',
            'health.confidence' => 'nullable|numeric',
            'vpd_kpa'           => 'nullable|numeric',
            'microclimate'      => 'nullable|array',
            'fusion'            => 'nullable|array',
            'fusion.w1'         => 'nullable|numeric',
            'fusion.w2'         => 'nullable|numeric',
            'actuation'         => 'nullable|array',
            'actuation.action'  => 'nullable|string',
            'actuation.actuators' => 'nullable|array',
        ]);

        $user = User::where('iot_id', $validated['iot_id'])
            ->where('iot_token', $validated['iot_token'])
            ->first();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $row = HybridInference::create([
            'iot_id'            => $validated['iot_id'],
            'titik'            => (int) ($validated['titik'] ?? 1),
            'image'            => $validated['image'] ?? null,
            'pest_label'       => data_get($validated, 'pest.label'),
            'pest_class'       => data_get($validated, 'pest.class'),
            'pest_confidence'  => data_get($validated, 'pest.confidence'),
            'health_status'    => data_get($validated, 'health.status'),
            'health_class'     => data_get($validated, 'health.class'),
            'health_confidence' => data_get($validated, 'health.confidence'),
            'vpd_kpa'          => $validated['vpd_kpa'] ?? data_get($validated, 'microclimate.vpd'),
            'microclimate'     => $validated['microclimate'] ?? null,
            'fusion_w1'        => data_get($validated, 'fusion.w1'),
            'fusion_w2'        => data_get($validated, 'fusion.w2'),
            'actuation_action' => data_get($validated, 'actuation.action'),
            'actuators'        => data_get($validated, 'actuation.actuators', []),
            'transmitted_via'  => 'rest',
        ]);

        $this->pruneIfNeeded();

        $topic = $mqtt->topicFor($validated['iot_id'], 'inference');
        $published = $mqtt->publish($topic, [
            'iot_id'      => $row->iot_id,
            'titik'       => $row->titik,
            'timestamp'   => optional($row->created_at)->toIso8601String(),
            'pest'        => ['label' => $row->pest_label, 'class' => $row->pest_class, 'confidence' => $row->pest_confidence],
            'health'      => ['status' => $row->health_status, 'confidence' => $row->health_confidence],
            'vpd_kpa'     => $row->vpd_kpa,
            'actuation'   => ['action' => $row->actuation_action, 'actuators' => $row->actuators],
            'fusion'      => ['w1' => $row->fusion_w1, 'w2' => $row->fusion_w2],
        ]);

        if ($published) {
            $row->update(['transmitted_via' => 'rest+mqtt']);
        }

        return response()->json([
            'message'        => 'Inference stored',
            'data'           => $row,
            'mqtt_published' => $published,
        ], 201);
    }

    public function latest(Request $request, string $iot_id)
    {
        $row = HybridInference::where('iot_id', $iot_id)
            ->when($request->filled('titik'), fn ($q) => $q->where('titik', (int) $request->query('titik')))
            ->orderByDesc('id')
            ->first();

        if (! $row) {
            return response()->json(['message' => 'No inference found for this iot_id'], 404);
        }

        return response()->json(['data' => $row], 200, [], JSON_PRETTY_PRINT);
    }

    private function pruneIfNeeded(): void
    {
        $excess = DB::table('hybrid_inferences')->count() - self::MAX_ROWS;
        if ($excess > 0) {
            $cutoffId = DB::table('hybrid_inferences')->orderBy('id')->skip($excess - 1)->value('id');
            DB::table('hybrid_inferences')->where('id', '<=', $cutoffId)->delete();
        }
    }
}
