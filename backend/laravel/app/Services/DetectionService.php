<?php

namespace App\Services;

use App\Http\Controllers\RecommendationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Yolo\Models\HistoryYolo;

class DetectionService
{
    public static function isHealthy(?string $penyakit): bool
    {
        return (bool) preg_match('/normal|sehat/i', (string) $penyakit);
    }

    public static function advice(?string $penyakit): ?string
    {
        return self::isHealthy($penyakit)
            ? 'Tanaman terdeteksi sehat. Lanjutkan pemantauan rutin.'
            : (RecommendationService::PEST_ADVICE[$penyakit][1] ?? null);
    }

    public function detections(string $iotId, int $titik, int $days): array
    {
        $query = HistoryYolo::where('iot_id', $iotId)->when($titik > 0, fn ($q) => $q->where('titik', $titik));
        $last = (clone $query)->max('created_at');
        if (! $last) {
            return [collect(), null];
        }
        $to = Carbon::parse($last);
        $rows = (clone $query)->whereBetween('created_at', [$to->copy()->subDays($days), $to])
            ->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'timestamp' => $item->created_at?->format('Y-m-d H:i'),
                'titik' => (int) $item->titik,
                'penyakit' => $item->penyakit ?? '-',
                'probabilitas' => is_numeric($item->probabilitas) ? round((float) $item->probabilitas, 1) : null,
                'image' => $item->image ? asset('img/hama_padi/' . rawurlencode($item->image)) : null,
                'healthy' => self::isHealthy($item->penyakit),
                'advice' => self::advice($item->penyakit),
            ]);

        return [$rows, $to];
    }

    public function summary(Collection $rows): array
    {
        $sick = $rows->where('healthy', false);

        return [
            'total' => $rows->count(),
            'healthy' => $rows->where('healthy', true)->count(),
            'sick' => $sick->count(),
            'top' => $this->topDisease($sick->countBy('penyakit')),
            'avg' => $rows->whereNotNull('probabilitas')->avg('probabilitas'),
        ];
    }

    public function distribution(Collection $rows): Collection
    {
        return $rows->groupBy('penyakit')->map(fn ($g) => $g->countBy('titik'))->sortByDesc(fn ($c) => $c->sum());
    }

    private function topDisease(Collection $counts): ?string
    {
        if ($counts->isEmpty()) {
            return null;
        }
        $max = $counts->max();
        $top = $counts->filter(fn ($n) => $n === $max);

        return $top->count() === 1
            ? $top->keys()->first() . " ({$max}×)"
            : $top->count() . " jenis seri, masing-masing {$max}×";
    }
}
