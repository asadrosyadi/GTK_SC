<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SensorReadingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $num = fn ($v) => $v === null ? null : (float) $v;
        $t = $num($this->temperature);
        $rh = $num($this->humidity);

        return [
            'id' => $this->id,
            'titik' => (int) $this->titik,
            'recorded_at' => optional($this->created_at)->toIso8601String(),
            'is_dummy' => (bool) $this->is_dummy,
            'weather' => [
                'temperature' => $t,
                'humidity' => $rh,
                'vpd' => ($t !== null && $rh !== null)
                    ? round(0.6108 * exp(17.27 * $t / ($t + 237.3)) * (1 - $rh / 100), 3) : null,
                'windspeed' => $num($this->windspeed),
                'rainfall' => $num($this->rainfall),
                'light_intensity' => $num($this->light_intensity),
                'pressure' => $num($this->pressure),
            ],
            'soil' => [
                'ph' => $num($this->ph),
                'soil_moisture' => $num($this->soil_moisture),
                'ec' => $num($this->ec),
                'tds' => $num($this->tds),
                'soil_temp' => $num($this->soil_temp),
                'nitrogen' => $num($this->Nitrogen_Level),
                'phosphorus' => $num($this->Phosphorus_Level),
                'potassium' => $num($this->Potassium_Level),
            ],
            'device' => [
                'battery_level' => $this->battery_level === null ? null : (int) $this->battery_level,
                'signal_strength' => $this->signal_strength === null ? null : (int) $this->signal_strength,
            ],
        ];
    }
}
