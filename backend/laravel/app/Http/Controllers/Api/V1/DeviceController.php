<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Support\SensorStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Dashboard\Models\SensorIot;
use Modules\Yolo\Http\Controllers\YoloController;

class DeviceController extends ApiController
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole('SuperAdmin'), 403, 'Khusus SuperAdmin');

        $data = User::whereNotNull('iot_id')->where('iot_id', '!=', '')->orderBy('name')->get()
            ->map(function (User $u) {
                $last = SensorIot::where('iot_id', $u->iot_id)->max('created_at');
                $minutes = $last ? Carbon::parse($last)->diffInMinutes(now(), true) : null;

                return [
                    'user_id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'iot_id' => $u->iot_id,
                    'last_seen_at' => $last ? Carbon::parse($last)->toIso8601String() : null,
                    'online' => $minutes !== null && $minutes <= SensorStatus::OFFLINE_AFTER_MINUTES,
                ];
            })->values();

        return response()->json(['data' => $data]);
    }

    public function exportDetections(Request $request)
    {
        $request->attributes->set('api_iot_id', $this->iotId($request));

        return app(YoloController::class)->exportDataYolo($request);
    }
}
