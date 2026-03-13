<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTuyaWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TuyaWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();
        ProcessTuyaWebhookJob::dispatch($payload);

        return response()->json(['success' => true]);
    }
}
