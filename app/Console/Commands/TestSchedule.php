<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestSchedule extends Command
{
    protected $signature = 'test:schedule';
    protected $description = 'Test if scheduler is working';

    public function handle()
    {
        Log::info('✅ Scheduler test ran at: ' . now()->format('Y-m-d H:i:s'));
        $this->info('✅ Schedule test completed at: ' . now()->format('Y-m-d H:i:s'));

        return Command::SUCCESS;
    }
}
