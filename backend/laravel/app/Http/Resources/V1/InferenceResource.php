<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titik' => (int) $this->titik,
            'pest' => [
                'label' => $this->pest_label,
                'class' => $this->pest_class,
                'confidence' => $this->pest_confidence,
            ],
            'health' => [
                'status' => $this->health_status,
                'class' => $this->health_class,
                'confidence' => $this->health_confidence,
            ],
            'vpd_kpa' => $this->vpd_kpa,
            'microclimate' => $this->microclimate,
            'fusion_weights' => ['w1' => $this->fusion_w1, 'w2' => $this->fusion_w2],
            'actuation' => [
                'action' => $this->actuation_action,
                'actuators' => $this->actuators,
                'transmitted_via' => $this->transmitted_via,
            ],
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
