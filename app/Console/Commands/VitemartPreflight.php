<?php

namespace App\Console\Commands;

use App\Services\Operations\ReadinessService;
use Illuminate\Console\Command;

class VitemartPreflight extends Command
{
    protected $signature = 'vitemart:preflight {--json : Print the checks as JSON}';
    protected $description = 'Go-live check: what this server still needs before trading on the multi-branch setup';

    public function handle(ReadinessService $readiness): int
    {
        $checks = $readiness->run();
        $sum = $readiness->summary($checks);
        if ($this->option('json')) {
            $this->line(json_encode(['summary' => $sum, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return $sum['ready'] ? self::SUCCESS : self::FAILURE;
        }
        $mark = ['ok' => '<info>✔ ok</info>', 'warn' => '<comment>! check</comment>', 'fail' => '<error>✖ fix</error>', 'manual' => '<fg=cyan>☐ you</>'];
        foreach (collect($checks)->groupBy('group') as $group => $rows) {
            $this->newLine();
            $this->line("<options=bold>{$group}</>");
            $this->table(['', 'Check', 'Now', 'What to do'], $rows->map(fn ($c) => [
                $mark[$c['status']], $c['label'], $c['detail'], $c['status'] === 'ok' ? '' : $c['fix'],
            ])->all());
        }
        $this->newLine();
        $line = "{$sum['ok']} ok · {$sum['warn']} to look at · {$sum['fail']} to fix · {$sum['manual']} for you to confirm";
        $sum['ready'] ? $this->info('Ready to trade – ' . $line) : $this->error('Not ready yet – ' . $line);
        return $sum['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
