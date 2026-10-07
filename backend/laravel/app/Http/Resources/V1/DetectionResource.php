<?php

namespace App\Http\Resources\V1;

use App\Services\DetectionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titik' => (int) $this->titik,
            'penyakit' => $this->penyakit,
            'probabilitas' => is_numeric($this->probabilitas) ? (float) $this->probabilitas : null,
            'healthy' => DetectionService::isHealthy($this->penyakit),
            'status' => DetectionService::isHealthy($this->penyakit)
                ? ['code' => 'healthy', 'label' => 'Sehat', 'level' => 'success']
                : ['code' => 'action', 'label' => 'Perlu tindakan', 'level' => 'danger'],
            'advice' => DetectionService::advice($this->penyakit),
            'image_url' => $this->image ? asset('img/hama_padi/' . rawurlencode($this->image)) : null,
            'detected_at' => optional($this->created_at)->toIso8601String(),
            'is_dummy' => (bool) $this->is_dummy,
        ];
    }
}
