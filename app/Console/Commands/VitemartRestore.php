<?php

namespace App\Console\Commands;

use App\Services\Operations\BackupService;
use App\Services\Operations\RestoreService;
use Illuminate\Console\Command;

class VitemartRestore extends Command
{
    protected $signature = 'vitemart:restore {file? : Backup file (.sql, .sql.gz or a backup .zip)} {--latest : Use the newest backup} {--force : Do not ask}';
    protected $description = 'Restore this server\'s database from a backup (a safety backup is taken first)';

    public function handle(BackupService $backups, RestoreService $restore): int
    {
        $file = $this->argument('file');
        if (!$file && $this->option('latest')) {
            $file = $backups->list(1)[0]['path'] ?? null;
        }
        if (!$file) {
            $list = $backups->list(15);
            if (!$list) {
                $this->error('No backups found.');
                return self::FAILURE;
            }
            $choice = $this->choice('Which backup?', array_map(fn ($b) => $b['name'] . '  (' . date('Y-m-d H:i', $b['time']) . ')', $list), 0);
            $file = $list[array_search($choice, array_map(fn ($b) => $b['name'] . '  (' . date('Y-m-d H:i', $b['time']) . ')', $list))]['path'];
        }
        if (!$this->option('force') && !$this->confirm("Replace this server's database with {$file}? (a safety backup is taken first)")) {
            return self::FAILURE;
        }
        try {
            $restore->run($file, fn ($m) => $this->line($m));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
