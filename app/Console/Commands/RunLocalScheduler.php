<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunLocalScheduler extends Command
{
    protected $signature = 'election:local-scheduler';
    protected $description = 'Jalankan scheduler lokal sampai skrip stop meminta berhenti';

    public function handle(): int
    {
        set_time_limit(0);
        $stopFile = base_path('.runtime/scheduler.stop');
        $lastMinute = null;
        while (true) {
            clearstatcache(true, $stopFile);
            if (is_file($stopFile)) {
                return self::SUCCESS;
            }
            $minute = (int) floor(time() / 60);
            if ($minute !== $lastMinute) {
                $lastMinute = $minute;
                $this->call('schedule:run', ['--no-interaction' => true]);
            }
            sleep(1);
        }
    }
}
