<?php

namespace App\Console\Commands;

use App\Models\ReportSchedule;
use App\Services\AuditLogger;
use App\Services\Reports\ReportMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Sends the sales-report emails that are due. Runs every minute from the scheduler.
 * If the server was off when a report was due, it is sent once when the server is back
 * (covering the period it would have covered), then the schedule moves on.
 */
class SendReportEmails extends Command
{
    protected $signature = 'vitemart:report-emails {--schedule= : send this schedule now, whether due or not}';
    protected $description = 'Email the scheduled sales reports that are due';

    public function handle(ReportMailer $mailer): int
    {
        if (!Schema::hasTable('report_schedules')) {
            return self::SUCCESS;
        }
        $q = ReportSchedule::query();
        if ($id = $this->option('schedule')) {
            $q->whereKey($id);
        } else {
            $q->where('is_active', true)->whereNotNull('next_run_at')->where('next_run_at', '<=', now());
        }

        foreach ($q->get() as $schedule) {
            $lock = Cache::lock('report-schedule-' . $schedule->id, 600);
            if (!$lock->get()) {
                continue;
            }
            try {
                $this->runOne($schedule, $mailer, (bool) $this->option('schedule'));
            } finally {
                $lock->release();
            }
        }
        return self::SUCCESS;
    }

    protected function runOne(ReportSchedule $schedule, ReportMailer $mailer, bool $manual): void
    {
        $runAt = $manual || !$schedule->next_run_at ? now() : ($schedule->retry_for ?? $schedule->next_run_at)->copy();
        [$from, $to] = $schedule->periodFor($runAt);
        try {
            $r = $mailer->send($schedule->recipients ?? [], $from, $to, $schedule->reportOptions(), $schedule->name, (bool) $schedule->skip_if_empty);
            $schedule->forceFill([
                'last_status' => $r['status'], 'last_message' => $r['message'],
                'last_sent_at' => $r['status'] === 'sent' ? now() : $schedule->last_sent_at,
            ]);
            $this->info("[{$schedule->name}] {$r['message']}");
            $failed = false;
            if ($r['status'] === 'sent') {
                AuditLogger::log('emailed', "Sales report \"{$schedule->name}\" emailed: {$r['message']}", 'reports');
            }
        } catch (\Throwable $e) {
            report($e);
            $schedule->forceFill(['last_status' => 'failed', 'last_message' => mb_substr($e->getMessage(), 0, 1000)]);
            $this->error("[{$schedule->name}] failed: {$e->getMessage()}");
            $failed = true;
        }
        if (!$manual) {
            $regular = $schedule->nextRunAfter(now());
            if ($failed && now()->diffInHours($runAt, true) < 6) {
                // Try again in 15 minutes (same period), but never past the next regular report.
                $schedule->retry_for = $runAt;
                $schedule->next_run_at = now()->addMinutes(15)->min($regular);
                $schedule->last_message = 'Will retry at ' . $schedule->next_run_at->format('H:i') . '. ' . $schedule->last_message;
            } else {
                $schedule->retry_for = null;
                $schedule->scheduleNext(now());
            }
        }
        $schedule->save();
    }
}
