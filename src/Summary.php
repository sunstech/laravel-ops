<?php

namespace Sunstech\LaravelOps;

use Illuminate\Support\Facades\Cache;

/** Ops özetinin tamamı (uç ve ops:summary komutu aynı sonucu verir). */
final class Summary
{
    public function __construct(private SystemHealth $health) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $checks = $this->health->checks();
        $databaseOk = $checks['database'] === 'ok';

        return [
            'generated_at' => now()->toIso8601String(),
            'app' => [
                'name' => config('app.name'),
                'release' => config('ops.release') ?: null,
                'version' => config('ops.version') ?: null,
                'environment' => app()->environment(),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'timezone' => config('app.timezone'),
            ],
            'health' => ['status' => in_array('error', $checks, true) ? 'degraded' : 'ok'] + $checks,
            // Hata izleme SUNS CMS'e özgü; merkez null görünce Hatalar bölümünü gizler.
            'errors' => null,
            'queue' => $databaseOk ? $this->health->queue() : null,
            'scheduler' => $this->health->scheduler(),
            // Dosya listesi (S3 vb.) yavaş olabilir; merkez 5 dakikada bir yokluyor.
            'backup' => $this->rememberBackup(),
            'disk' => $this->health->disk(),
            'server' => $this->health->server(),
        ];
    }

    private function rememberBackup(): ?array
    {
        try {
            return Cache::remember('ops:summary:backup', 300, fn () => $this->health->backup());
        } catch (\Throwable) {
            return $this->health->backup();
        }
    }
}
