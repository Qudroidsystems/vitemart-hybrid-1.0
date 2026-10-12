<?php

namespace App\Console\Commands;

use App\Services\Operations\BackupService;
use Illuminate\Console\Command;

class VitemartBackup extends Command
{
    protected $signature = 'vitemart:backup {--list : Show the backups on this server} {--verify= : Check that a backup file is complete}';
    protected $description = 'Back up this server\'s database (gzipped SQL in storage/app/vitemart-backups)';

    public function handle(BackupService $backups): int
    {
        if ($this->option('list')) {
            $rows = array_map(fn ($b) => [$b['name'], number_format($b['size'] / 1048576, 1) . ' MB', date('Y-m-d H:i', $b['time']), $b['path']], $backups->list());
            $this->table(['Backup', 'Size', 'Taken', 'Path'], $rows);
            return self::SUCCESS;
        }
        if ($f = $this->option('verify')) {
            $v = $backups->verify($f);
            $v['ok'] ? $this->info("OK – {$v['tables']} tables.") : $this->error('Incomplete or not a database backup.');
            return $v['ok'] ? self::SUCCESS : self::FAILURE;
        }
        $run = $backups->dump('manual');
        $this->info('Saved ' . $run->path . ' (' . number_format($run->size_bytes / 1048576, 1) . ' MB)');
        return self::SUCCESS;
    }
}
