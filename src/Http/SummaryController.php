<?php

namespace Sunstech\LaravelOps\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Sunstech\LaravelOps\Summary;

/** GET /api/v1/ops/summary: merkezin yokladığı salt okunur özet (Authorization: Bearer OPS_TOKEN). */
class SummaryController
{
    public function __invoke(Request $request, Summary $summary): JsonResponse
    {
        if (! self::validToken($request->bearerToken())) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json($summary->toArray())->header('Cache-Control', 'no-store');
    }

    /** Anahtar listesi "yeni,eski"; her biri sabit sürede karşılaştırılır, 32 karakterden kısası yok sayılır. */
    public static function validToken(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $valid = false;
        foreach (explode(',', (string) config('ops.token')) as $secret) {
            $secret = trim($secret);
            if (strlen($secret) >= 32) {
                $valid = hash_equals($secret, $token) || $valid;
            }
        }

        return $valid;
    }
}
