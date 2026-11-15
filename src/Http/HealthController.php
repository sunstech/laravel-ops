<?php

namespace Sunstech\LaravelOps\Http;

use Illuminate\Http\JsonResponse;
use Sunstech\LaravelOps\SystemHealth;

/** GET /api/health: açık uç; {"status":"ok"} 200 ya da {"status":"degraded"} 503. Ayrıntı vermez. */
class HealthController
{
    public function __invoke(SystemHealth $health): JsonResponse
    {
        $checks = $health->checks();
        $ok = ! in_array('error', $checks, true);

        return response()
            ->json(['status' => $ok ? 'ok' : 'degraded', 'checks' => $checks], $ok ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }
}
