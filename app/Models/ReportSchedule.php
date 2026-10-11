<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * A sales report emailed on a schedule (end of day, weekly, monthly or every few hours).
 */
class ReportSchedule extends Model
{
    public const FREQUENCIES = [
        'daily' => 'Every day',
        'weekly' => 'Every week',
        'monthly' => 'Every month',
        'hourly' => 'Every few hours',
    ];

    public const PERIODS = [
        'today' => 'Today, up to the send time',
        'yesterday' => 'Yesterday (whole day)',
        'last_24_hours' => 'The last 24 hours',
        'last_n_hours' => 'Since the previous report (every few hours)',
        'last_7_days' => 'The last 7 days',
        'previous_week' => 'Last week (Monday – Sunday)',
        'this_month' => 'This month so far',
        'previous_month' => 'Last month (whole month)',
    ];

    public const WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    protected $fillable = [
        'name', 'recipients', 'frequency', 'every_hours', 'send_time', 'weekday', 'month_day', 'period', 'channels',
        'branch_ids', 'include_profit', 'include_shifts', 'include_products', 'include_transactions', 'skip_if_empty',
        'is_active', 'next_run_at', 'retry_for', 'last_sent_at', 'last_status', 'last_message', 'created_by',
    ];

    protected $casts = [
        'recipients' => 'array',
        'branch_ids' => 'array',
        'include_profit' => 'boolean',
        'include_shifts' => 'boolean',
        'include_products' => 'boolean',
        'include_transactions' => 'boolean',
        'skip_if_empty' => 'boolean',
        'is_active' => 'boolean',
        'next_run_at' => 'datetime',
        'retry_for' => 'datetime',
        'last_sent_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The first scheduled time strictly after $after. */
    public function nextRunAfter(CarbonInterface $after): Carbon
    {
        $after = Carbon::instance($after);
        [$h, $m] = array_map('intval', explode(':', $this->send_time ?: '22:00') + [0, 0]);
        $at = fn (Carbon $d) => $d->copy()->setTime($h, $m, 0);

        switch ($this->frequency) {
            case 'hourly':
                $n = max(1, min(24, (int) ($this->every_hours ?: 8)));
                // Slots line up with the send time: e.g. 06:00 every 8 h → 06:00, 14:00, 22:00
                $t = $at($after)->subHours($n * (int) ceil(24 / $n));
                while ($t->lte($after)) {
                    $t->addHours($n);
                }
                return $t;

            case 'weekly':
                $wd = (int) ($this->weekday ?: 1);
                $t = $at($after);
                while ($t->dayOfWeekIso !== $wd || $t->lte($after)) {
                    $t = $at($t->addDay());
                }
                return $t;

            case 'monthly':
                $md = (int) ($this->month_day ?: 1);
                $candidate = function (Carbon $month) use ($md, $at) {
                    $d = $month->copy()->startOfMonth();
                    $day = $md >= 29 ? $d->daysInMonth : $md;
                    return $at($d->setDay(min($day, $d->daysInMonth)));
                };
                $t = $candidate($after);
                if ($t->lte($after)) {
                    $t = $candidate($after->copy()->startOfMonth()->addMonth());
                }
                return $t;

            default: // daily
                $t = $at($after);
                return $t->lte($after) ? $at($after->copy()->addDay()) : $t;
        }
    }

    /** [from, to] covered by a report sent at $runAt. */
    public function periodFor(CarbonInterface $runAt): array
    {
        $t = Carbon::instance($runAt)->copy();
        return match ($this->period) {
            'yesterday' => [$t->copy()->subDay()->startOfDay(), $t->copy()->subDay()->endOfDay()],
            'last_24_hours' => [$t->copy()->subDay(), $t],
            'last_n_hours' => [$t->copy()->subHours(max(1, (int) ($this->every_hours ?: 8))), $t],
            'last_7_days' => [$t->copy()->subDays(7), $t],
            'previous_week' => [$t->copy()->startOfWeek(CarbonInterface::MONDAY)->subWeek(), $t->copy()->startOfWeek(CarbonInterface::MONDAY)->subSecond()],
            'this_month' => [$t->copy()->startOfMonth(), $t],
            'previous_month' => [$t->copy()->startOfMonth()->subMonth(), $t->copy()->startOfMonth()->subSecond()],
            default => [$t->copy()->startOfDay(), $t], // today
        };
    }

    public function scheduleNext(?CarbonInterface $from = null): void
    {
        $this->next_run_at = $this->is_active ? $this->nextRunAfter($from ?? now()) : null;
    }

    /** "Every day at 22:00", "Every Monday at 08:00", … */
    public function describe(): string
    {
        $time = Carbon::createFromFormat('H:i', $this->send_time ?: '22:00')->format('g:i A');
        return match ($this->frequency) {
            'hourly' => 'Every ' . ((int) $this->every_hours ?: 8) . ' hours (from ' . $time . ')',
            'weekly' => 'Every ' . (self::WEEKDAYS[(int) $this->weekday] ?? 'Monday') . ' at ' . $time,
            'monthly' => ((int) $this->month_day >= 29 ? 'Last day' : 'Day ' . (int) $this->month_day) . ' of every month at ' . $time,
            default => 'Every day at ' . $time,
        };
    }

    public function periodLabel(): string
    {
        return self::PERIODS[$this->period] ?? $this->period;
    }

    /** Options handed to the report builder. */
    public function reportOptions(): array
    {
        return [
            'channels' => $this->channels ?: 'pos',
            'branch_ids' => array_values(array_filter((array) $this->branch_ids)),
            'include_profit' => (bool) $this->include_profit,
            'include_shifts' => (bool) $this->include_shifts,
            'include_products' => (bool) $this->include_products,
            'include_transactions' => (bool) $this->include_transactions,
        ];
    }
}
