<?php

namespace Sunstech\LaravelOps\Console;

use Illuminate\Console\Command;
use Sunstech\LaravelOps\Summary;

class SummaryCommand extends Command
{
    protected $signature = 'ops:summary';

    protected $description = 'Merkezin göreceği ops özetini JSON olarak yazdırır (anahtar gerekmez)';

    public function handle(Summary $summary): int
    {
        $this->line((string) json_encode($summary->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->line(config('ops.token') ? 'OPS_TOKEN tanımlı.' : '<comment>OPS_TOKEN tanımlı değil: özet ucu 401 döner.</comment>');

        return self::SUCCESS;
    }
}
