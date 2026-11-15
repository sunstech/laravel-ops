<?php

namespace Sunstech\LaravelOps\Tests;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Sunstech\LaravelOps\Heartbeat;
use Sunstech\LaravelOps\SystemHealth;

class OpsTest extends TestCase
{
    public function test_summary_requires_a_valid_token(): void
    {
        $this->getJson('/api/v1/ops/summary')->assertUnauthorized();
        $this->withToken('yanlis')->getJson('/api/v1/ops/summary')->assertUnauthorized();
        $this->withToken(self::TOKEN)->getJson('/api/v1/ops/summary')->assertOk();
    }

    public function test_short_or_missing_token_disables_the_endpoint(): void
    {
        config(['ops.token' => 'kisa']);
        $this->withToken('kisa')->getJson('/api/v1/ops/summary')->assertUnauthorized();

        config(['ops.token' => null]);
        $this->withToken('')->getJson('/api/v1/ops/summary')->assertUnauthorized();
    }

    public function test_rotated_tokens_are_both_accepted(): void
    {
        $new = str_repeat('n', 40);
        config(['ops.token' => $new.', '.self::TOKEN]);

        $this->withToken($new)->getJson('/api/v1/ops/summary')->assertOk();
        $this->withToken(self::TOKEN)->getJson('/api/v1/ops/summary')->assertOk();
    }

    public function test_summary_has_the_suns_cms_shape(): void
    {
        $response = $this->withToken(self::TOKEN)->getJson('/api/v1/ops/summary')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonStructure([
                'generated_at',
                'app' => ['name', 'release', 'version', 'environment', 'php', 'laravel', 'timezone'],
                'health' => ['status', 'database', 'cache', 'storage'],
                'queue' => ['driver', 'pending', 'failed_24h', 'failed_total', 'stalled'],
                'scheduler' => ['last_heartbeat_at', 'stale'],
                'disk' => ['free_bytes', 'total_bytes', 'used_percent'],
                'server' => ['cpus', 'load_1', 'memory_used_percent', 'uptime_seconds'],
            ]);

        $response->assertJsonPath('app.name', 'Örnek Uygulama')
            ->assertJsonPath('app.version', '2.1.0')
            ->assertJsonPath('app.release', 'abc1234')
            ->assertJsonPath('health.status', 'ok')
            ->assertJsonPath('errors', null)
            ->assertJsonPath('backup', null)
            ->assertJsonPath('queue.driver', 'database');
    }

    public function test_queue_reports_pending_failed_and_stalled_jobs(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null,
            'available_at' => now()->subMinutes(30)->timestamp, 'created_at' => now()->subMinutes(30)->timestamp,
        ]);
        foreach ([now()->subHours(2), now()->subDays(3)] as $failedAt) {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
                'payload' => '{}', 'exception' => 'x', 'failed_at' => $failedAt,
            ]);
        }

        $this->withToken(self::TOKEN)->getJson('/api/v1/ops/summary')
            ->assertJsonPath('queue.pending', 1)
            ->assertJsonPath('queue.failed_24h', 1)
            ->assertJsonPath('queue.failed_total', 2)
            ->assertJsonPath('queue.stalled', true);
    }

    public function test_queue_without_tables_does_not_break_the_summary(): void
    {
        Schema::drop('jobs');
        Schema::drop('failed_jobs');

        $this->withToken(self::TOKEN)->getJson('/api/v1/ops/summary')
            ->assertOk()
            ->assertJsonPath('queue.pending', null)
            ->assertJsonPath('queue.failed_total', 0)
            ->assertJsonPath('queue.stalled', false);
    }

    public function test_scheduler_heartbeat_is_scheduled_and_reported(): void
    {
        $this->withToken(self::TOKEN)->getJson('/api/v1/ops/summary')
            ->assertJsonPath('scheduler.stale', true)
            ->assertJsonPath('scheduler.last_heartbeat_at', null);

        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => $event->description === 'ops:heartbeat');
        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events->first()->expression);

        $this->artisan('ops:heartbeat')->assertSuccessful();
        $this->withToken(self::TOKEN)->getJson('/api/v1/ops/summary')->assertJsonPath('scheduler.stale', false);

        $this->travel(11)->minutes();
        $this->withToken(self::TOKEN)->getJson('/api/v1/ops/summary')->assertJsonPath('scheduler.stale', true);
    }

    public function test_scheduler_can_be_turned_off(): void
    {
        config(['ops.scheduler' => false]);
        Heartbeat::record();

        $this->withToken(self::TOKEN)->getJson('/api/v1/ops/summary')->assertJsonPath('scheduler', null);
    }

    public function test_health_endpoint_is_public_and_reports_degraded_state(): void
    {
        $this->getJson('/api/health')->assertOk()->assertExactJson([
            'status' => 'ok', 'checks' => ['database' => 'ok', 'cache' => 'ok', 'storage' => 'ok'],
        ]);

        $this->app->instance(SystemHealth::class, new class extends SystemHealth
        {
            public function database(): bool
            {
                return false;
            }
        });

        $this->getJson('/api/health')->assertStatus(503)->assertJsonPath('status', 'degraded')->assertJsonPath('checks.database', 'error');
        $this->withToken(self::TOKEN)->getJson('/api/v1/ops/summary')
            ->assertJsonPath('health.status', 'degraded')
            ->assertJsonPath('queue', null);
    }

    public function test_summary_command_prints_the_same_json(): void
    {
        $this->artisan('ops:summary')
            ->expectsOutputToContain('"version": "2.1.0"')
            ->expectsOutputToContain('OPS_TOKEN tanımlı.')
            ->assertSuccessful();
    }

    public function test_meminfo_parsing(): void
    {
        $this->assertSame(
            ['memory_total_bytes' => 4096 * 1024, 'memory_available_bytes' => 1024 * 1024, 'memory_used_percent' => 75.0],
            SystemHealth::parseMeminfo("MemTotal:        4096 kB\nMemFree:  10 kB\nMemAvailable:    1024 kB\n"),
        );
        $this->assertSame(['memory_total_bytes' => null, 'memory_available_bytes' => null, 'memory_used_percent' => null], SystemHealth::parseMeminfo(null));
    }
}
