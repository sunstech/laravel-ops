<?php

namespace Sunstech\LaravelOps;

use Illuminate\Support\Facades\Cache;

/** Zamanlayıcının çalıştığını gösteren nabız (önbellekte son çalışma zamanı). */
final class Heartbeat
{
    public const KEY = 'ops:scheduler-heartbeat';

    public static function record(): void
    {
        Cache::forever(self::KEY, now()->toIso8601String());
    }

    public static function last(): ?string
    {
        try {
            return Cache::get(self::KEY) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
