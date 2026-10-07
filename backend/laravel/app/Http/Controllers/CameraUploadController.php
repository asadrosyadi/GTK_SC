<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CameraUploadController extends Controller
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    public function store(Request $request)
    {
        $iotId = (string) $request->header('X-IOT-ID', '');
        $token = (string) $request->header('X-IOT-TOKEN', '');
        $titik = (int) $request->header('X-TITIK', 1);

        $authorized = $iotId !== '' && $token !== ''
            && User::where('iot_id', $iotId)->where('iot_token', $token)->exists();
        if (! $authorized) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (! array_key_exists($titik, config('monitoring.titik', []))) {
            return response()->json(['message' => 'Titik tidak dikenal'], 422);
        }

        $jpeg = $request->getContent();
        $size = strlen($jpeg);
        if ($size < 4 || $size > self::MAX_BYTES || substr($jpeg, 0, 2) !== "\xFF\xD8") {
            return response()->json(['message' => 'Body harus gambar JPEG, maksimal 2 MB'], 422);
        }

        $url = config('monitoring.detect.url') . '?' . http_build_query(['titik' => $titik, 'iot_id' => $iotId]);
        try {
            $response = Http::timeout(config('monitoring.detect.timeout'))
                ->withBody($jpeg, 'image/jpeg')
                ->post($url);
        } catch (ConnectionException $e) {
            return response()->json(['message' => 'Layanan deteksi kamera tidak tersedia'], 503);
        }

        if (! $response->successful()) {
            return response()->json([
                'message' => 'Layanan deteksi kamera gagal memproses gambar',
                'detail' => $response->json('error'),
            ], 502);
        }

        return response()->json($response->json(), $response->status() === 201 ? 201 : 200);
    }
}
