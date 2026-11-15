<?php

namespace Sunstech\LaravelOps\Console;

use Illuminate\Console\Command;
use Sunstech\LaravelOps\Heartbeat;

class HeartbeatCommand extends Command
{
    protected $signature = 'ops:heartbeat';

    protected $description = 'Zamanlayıcı nabzını şimdi yazar (normalde zamanlayıcı her dakika yazar)';

    public function handle(): int
    {
        Heartbeat::record();
        $this->info('Nabız yazıldı: '.Heartbeat::last());

        return self::SUCCESS;
    }
}
