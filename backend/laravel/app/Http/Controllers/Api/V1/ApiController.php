<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function iotId(Request $request): string
    {
        $user = $request->user();
        $requested = $request->query('iot_id');

        if ($requested !== null && $requested !== $user->iot_id) {
            abort_unless($user->hasRole('SuperAdmin'), 403, 'Tidak berhak mengakses perangkat lain');

            return (string) $requested;
        }
        abort_if(empty($user->iot_id), 404, 'Akun ini belum terhubung ke perangkat (iot_id)');

        return $user->iot_id;
    }

    protected function validTitik(int $titik): int
    {
        abort_unless(array_key_exists($titik, config('monitoring.titik', [])), 404, 'Titik tidak dikenal');

        return $titik;
    }

    protected function history(Request $request, Builder $query): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $valid = $request->validate([
            'titik' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $query
            ->when(isset($valid['titik']), fn ($q) => $q->where('titik', $this->validTitik((int) $valid['titik'])))
            ->when(isset($valid['from']), fn ($q) => $q->where('created_at', '>=', $valid['from']))
            ->when(isset($valid['to']), fn ($q) => $q->where('created_at', '<=', $valid['to']))
            ->orderByDesc('id')
            ->paginate($valid['per_page'] ?? 25)
            ->withQueryString();
    }
}
