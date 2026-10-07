<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\AiAssistantService;
use Illuminate\Http\Request;
use RuntimeException;

class AiController extends ApiController
{
    public function __construct(private AiAssistantService $ai)
    {
    }

    public function summary(Request $request)
    {
        set_time_limit(120);
        $meta = ['provider' => $this->ai->providerName(), 'enabled' => $this->ai->enabled()];
        try {
            return response()->json(['data' => $meta + $this->ai->summary($this->iotId($request))]);
        } catch (RuntimeException $e) {
            return response()->json(['data' => $meta, 'message' => $e->getMessage()], $this->ai->enabled() ? 502 : 503);
        }
    }

    public function chat(Request $request)
    {
        set_time_limit(120);
        $data = $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'array|max:20',
            'history.*.role' => 'required|in:user,assistant',
            'history.*.content' => 'required|string|max:4000',
        ]);
        try {
            return response()->json(['data' => [
                'provider' => $this->ai->providerName(),
                'answer' => $this->ai->chat($this->iotId($request), $data['message'], $data['history'] ?? []),
            ]]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $this->ai->enabled() ? 502 : 503);
        }
    }
}
