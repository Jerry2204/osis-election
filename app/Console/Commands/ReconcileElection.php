<?php

namespace App\Console\Commands;

use App\Services\ElectionService;
use Illuminate\Console\Command;

class ReconcileElection extends Command
{
    protected $signature = 'election:reconcile';
    protected $description = 'Terapkan jadwal buka dan tutup pemilihan berdasarkan waktu server';

    public function handle(ElectionService $service): int
    {
        $setting = $service->reconcile();
        $this->info('Status pemilihan: '.$setting->status.'.');
        if ($notice = $service->scheduleNotice($setting)) {
            $this->warn($notice);
        }
        return self::SUCCESS;
    }
}
