<?php

namespace App\Console\Commands;

use App\Services\Catalogue\BarcodeIndex;
use App\Services\StockService;
use Illuminate\Console\Command;

class VitemartRebuildStock extends Command
{
    protected $signature = 'vitemart:rebuild-stock {--barcodes : Also rebuild the barcode index}';
    protected $description = 'Recalculate stock balances (on hand, reserved, quarantined, in transit) from the stock ledger';

    public function handle(StockService $stock): int
    {
        $n = $stock->rebuildAll();
        $this->info("Stock balances rebuilt ({$n} products).");
        if ($this->option('barcodes')) {
            $this->info('Barcode index: ' . BarcodeIndex::rebuild() . ' codes checked.');
        }
        return self::SUCCESS;
    }
}
