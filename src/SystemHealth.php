<?php

namespace Sunstech\LaravelOps;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Uygulamanın çalışma durumu ölçümleri. Hata mesajları dışarı verilmez, yalnızca durum.
 * Biçim SUNS CMS'in ops özetiyle aynıdır; merkez iki kaynağı aynı şekilde okur.
 */
class SystemHealth
{
    /** @return array{database: string, cache: string, storage: string} */
    public function checks(): array
    {
        return [
            'database' => $this->database() ? 'ok' : 'error',
            'cache' => $this->cache() ? 'ok' : 'error',
            'storage' => $this->storage() ? 'ok' : 'error',
        ];
    }

    public function database(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function cache(): bool
    {
        try {
            $key = 'ops_health_'.Str::random(8);
            Cache::put($key, 'ok', 10);
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);

            return $ok;
        } catch (\Throwable) {
            return false;
        }
    }

    public function storage(): bool
    {
        try {
            $disk = Storage::disk((string) config('ops.storage_disk'));
            $file = 'ops_health_'.Str::random(8).'.txt';
            $disk->put($file, 'ok');
            $ok = $disk->exists($file);
            $disk->delete($file);

            return $ok;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * pending/stalled yalnızca database sürücüsünde anlamlı; failed_jobs tablosu yoksa sıfır.
     * Kuyruk tabloları hiç yoksa ya da okunamazsa null.
     *
     * @return array{driver: string, pending: ?int, failed_24h: int, failed_total: int, stalled: bool}|null
     */
    public function queue(): ?array
    {
        $driver = (string) config('queue.default');

        try {
            $failedTable = (string) config('queue.failed.table', 'failed_jobs');
            $hasFailed = Schema::hasTable($failedTable);
            $jobsTable = (string) config("queue.connections.{$driver}.table", 'jobs');
            $isDatabase = config("queue.connections.{$driver}.driver") === 'database' && Schema::hasTable($jobsTable);

            return [
                'driver' => $driver,
                'pending' => $isDatabase ? DB::table($jobsTable)->count() : null,
                'failed_24h' => $hasFailed ? DB::table($failedTable)->where('failed_at', '>=', now()->subDay())->count() : 0,
                'failed_total' => $hasFailed ? DB::table($failedTable)->count() : 0,
                'stalled' => $isDatabase && DB::table($jobsTable)
                    ->whereNull('reserved_at')
                    ->where('available_at', '<', now()->subMinutes((int) config('ops.queue_stale_minutes'))->timestamp)
                    ->exists(),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{last_heartbeat_at: ?string, stale: bool}|null */
    public function scheduler(): ?array
    {
        if (! config('ops.scheduler')) {
            return null;
        }

        $last = Heartbeat::last();
        $stale = ! $last || Carbon::parse($last)->lt(now()->subMinutes((int) config('ops.scheduler_stale_minutes')));

        return ['last_heartbeat_at' => $last, 'stale' => $stale];
    }

    /**
     * spatie/laravel-backup kuruluysa tüm hedef disklerdeki en yeni yedek (yalnızca dosya listesi).
     *
     * @return array{last_backup_at: ?string, size_bytes: ?int, age_hours: ?float, healthy: bool}|null
     */
    public function backup(): ?array
    {
        $destination = 'Spatie\\Backup\\BackupDestination\\BackupDestination';
        if (! config('ops.backup') || ! class_exists($destination)) {
            return null;
        }

        try {
            $name = (string) config('backup.backup.name');
            $newest = null;
            foreach ((array) config('backup.backup.destination.disks') as $disk) {
                $backup = $destination::create($disk, $name)->newestBackup();
                if ($backup && (! $newest || $backup->date()->gt($newest->date()))) {
                    $newest = $backup;
                }
            }
        } catch (\Throwable) {
            return ['last_backup_at' => null, 'size_bytes' => null, 'age_hours' => null, 'healthy' => false];
        }

        if (! $newest) {
            return ['last_backup_at' => null, 'size_bytes' => null, 'age_hours' => null, 'healthy' => false];
        }

        $date = Carbon::instance($newest->date());
        $age = round($date->diffInSeconds(now(), true) / 3600, 1);

        return [
            'last_backup_at' => $date->toIso8601String(),
            'size_bytes' => (int) $newest->sizeInBytes(),
            'age_hours' => $age,
            'healthy' => $age < (int) config('ops.backup_max_age_hours'),
        ];
    }

    /** @return array{free_bytes: int, total_bytes: int, used_percent: float} */
    public function disk(): array
    {
        $free = (int) @disk_free_space(base_path());
        $total = (int) @disk_total_space(base_path());

        return [
            'free_bytes' => $free,
            'total_bytes' => $total,
            'used_percent' => $total > 0 ? round(($total - $free) / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Sunucunun yükü ve belleği (Linux /proc; konteynerde ana makinenin değerleri).
     * /proc yoksa (Windows, macOS) alanlar null.
     *
     * @return array<string, int|float|null>
     */
    public function server(): array
    {
        $load = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: null) : null;
        $uptime = self::readProc('/proc/uptime');
        $cpuinfo = self::readProc('/proc/cpuinfo');

        return [
            'cpus' => $cpuinfo !== null ? (preg_match_all('/^processor\s*:/m', $cpuinfo) ?: null) : null,
            'load_1' => $load ? round((float) $load[0], 2) : null,
            'load_5' => $load ? round((float) $load[1], 2) : null,
            'load_15' => $load ? round((float) $load[2], 2) : null,
            ...self::parseMeminfo(self::readProc('/proc/meminfo')),
            'uptime_seconds' => $uptime !== null ? (int) (float) strtok($uptime, ' ') : null,
        ];
    }

    /** @return array{memory_total_bytes: ?int, memory_available_bytes: ?int, memory_used_percent: ?float} */
    public static function parseMeminfo(?string $meminfo): array
    {
        $value = fn (string $key) => $meminfo !== null && preg_match('/^'.$key.':\s+(\d+)\s*kB/m', $meminfo, $m)
            ? (int) $m[1] * 1024
            : null;

        $total = $value('MemTotal');
        $available = $value('MemAvailable');

        return [
            'memory_total_bytes' => $total,
            'memory_available_bytes' => $available,
            'memory_used_percent' => $total && $available !== null ? round(($total - $available) / $total * 100, 1) : null,
        ];
    }

    private static function readProc(string $path): ?string
    {
        if (! @is_readable($path)) {
            return null;
        }
        $content = @file_get_contents($path);

        return $content === false ? null : $content;
    }
}
