<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Modules\Dashboard\Models\SensorIot;
use Illuminate\Support\Facades\DB;



class SensorController extends Controller
{

    private const MAX_SENSOR_ROWS = 100000;

    public function cekDanBersihkanDataSensor()
    {
        $excess = DB::table('sensor_iots')->count() - self::MAX_SENSOR_ROWS;
        if ($excess > 0) {
            $cutoffId = DB::table('sensor_iots')->orderBy('id')->skip($excess - 1)->value('id');
            DB::table('sensor_iots')->where('id', '<=', $cutoffId)->delete();
        }
    }

    public function store(Request $request)
    {
        $titikList = array_keys(config('monitoring.titik', [1 => []]));

        $validated = $request->validate([
            'iot_id' => 'required|string',
            'iot_token' => 'required|string',
            'titik' => 'nullable|integer|in:' . implode(',', $titikList),
            'temperature' => 'nullable|numeric',
            'humidity' => 'nullable|numeric',
            'windspeed' => 'nullable|numeric',
            'rainfall' => 'nullable|numeric',
            'light_intensity' => 'nullable|integer',
            'ph' => 'nullable|numeric',
            'soil_moisture' => 'nullable|numeric',
            'ec' => 'nullable|numeric',
            'tds' => 'nullable|numeric',
            'soil_temp' => 'nullable|numeric',
            'pressure' => 'nullable|numeric',
            'feromon' => 'nullable|integer',
            'battery_level' => 'nullable|integer',
            'signal_strength' => 'nullable|integer',
            'Nitrogen_Level' => 'nullable|numeric',
            'Phosphorus_Level' => 'nullable|numeric',
            'Potassium_Level' => 'nullable|numeric',
        ]);

        $user = User::where('iot_id', $validated['iot_id'])
                    ->where('iot_token', $validated['iot_token'])
                    ->first();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $titik = (int) ($validated['titik'] ?? 1);

        if (! config("monitoring.titik.{$titik}.weather", true)) {
            foreach (config('monitoring.weather_fields', []) as $field) {
                $validated[$field] = null;
            }
        }

        $sensor = SensorIot::create([
            'iot_id' => $validated['iot_id'],
            'titik' => $titik,
            'temperature' => $validated['temperature'] ?? null,
            'humidity' => $validated['humidity'] ?? null,
            'windspeed' => $validated['windspeed'] ?? null,
            'rainfall' => $validated['rainfall'] ?? null,
            'light_intensity' => $validated['light_intensity'] ?? null,
            'ph' => $validated['ph'] ?? null,
            'soil_moisture' => $validated['soil_moisture'] ?? null,
            'ec' => $validated['ec'] ?? null,
            'tds' => $validated['tds'] ?? null,
            'soil_temp' => $validated['soil_temp'] ?? null,
            'pressure' => $validated['pressure'] ?? null,
            'feromon' => $validated['feromon'] ?? null,
            'battery_level' => $validated['battery_level'] ?? null,
            'signal_strength' => $validated['signal_strength'] ?? null,
            'Nitrogen_Level' => $validated['Nitrogen_Level'] ?? null,
            'Phosphorus_Level' => $validated['Phosphorus_Level'] ?? null,
            'Potassium_Level' => $validated['Potassium_Level'] ?? null,
        ]);

        $this->cekDanBersihkanDataSensor();
        return response()->json(['message' => 'Data berhasil disimpan', 'data' => $sensor], 201);
    }

    public function getByiot_id(Request $request)
    {
        $plantID = $request->query('iot_id');

        if (!$plantID) {
            return response()->json([
                'status' => 'error',
                'message' => 'iot_id parameter is required'
            ], 400);
        }

        $data = DB::table('sensor_iots')
            ->where('iot_id', $plantID)
            ->when($request->filled('titik'), fn ($q) => $q->where('titik', (int) $request->query('titik')))
            ->orderByDesc('id')
            ->get();

        return response()->json($data, 200, [], JSON_PRETTY_PRINT);
    }
}
