<?php

namespace App\Console\Commands;

use App\Services\Catalogue\CatalogueRequests;
use App\Services\MultiBranch\Install;
use Illuminate\Console\Command;

class VitemartCatalogueSync extends Command
{
    protected $signature = 'vitemart:catalogue-requests';
    protected $description = 'Branch: send price change requests / product submissions to head office and apply its decisions';

    public function handle(CatalogueRequests $service): int
    {
        if (!Install::isBranch()) {
            $this->line('Not a branch server – nothing to do.');
            return self::SUCCESS;
        }
        try {
            $sent = $service->push();
            $applied = $service->pull();
            $this->info("Sent {$sent}, applied {$applied} decision(s).");
        } catch (\Throwable $e) {
            $this->warn('Head office unreachable: ' . $e->getMessage());
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
