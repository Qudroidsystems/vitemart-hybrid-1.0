<?php

namespace App\Console\Commands;

use App\Services\Operations\UpgradeService;
use Illuminate\Console\Command;

class VitemartUpgrade extends Command
{
    protected $signature = 'vitemart:upgrade
        {--dry-run : Only show what would change}
        {--force : Upgrade even if this branch would run ahead of head office}
        {--skip-backup : Do not back up first (not recommended)}
        {--path= : Extra migrations folder (used by tests)}';
    protected $description = 'Apply a new release: backup → maintenance → migrations → checks; rolls back automatically on failure';

    public function handle(UpgradeService $upgrade): int
    {
        $pending = $upgrade->pending($this->option('path'));
        if ($this->option('dry-run')) {
            $this->line('Version ' . config('vitemart.version') . ' · ' . count($pending) . ' database change(s) waiting:');
            foreach ($pending as $m) {
                $this->line('  ' . $m);
            }
            if ($b = $upgrade->blocker($this->option('force'))) {
                $this->warn($b);
            }
            return self::SUCCESS;
        }
        $r = $upgrade->run([
            'path' => $this->option('path'), 'skip_backup' => $this->option('skip-backup'), 'force' => $this->option('force'),
        ], fn ($m) => $this->line($m));
        return $r['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}
