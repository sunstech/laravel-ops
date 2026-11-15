<?php

namespace Sunstech\LaravelOps\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Sunstech\LaravelOps\OpsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected const TOKEN = 'ops-test-anahtari-0123456789abcdefghijklmn';

    protected function getPackageProviders($app): array
    {
        return [OpsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.name', 'Örnek Uygulama');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'database');
        $app['config']->set('queue.connections.database', ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90]);
        $app['config']->set('ops.token', self::TOKEN);
        $app['config']->set('ops.version', '2.1.0');
        $app['config']->set('ops.release', 'abc1234');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }
}
