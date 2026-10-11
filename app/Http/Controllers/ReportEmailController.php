<?php

namespace App\Http\Controllers;

use App\Models\ReportSchedule;
use App\Services\AuditLogger;
use App\Services\MultiBranch\Install;
use App\Services\Reports\ReportMailer;
use App\Services\Reports\SalesReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Sales report emails: schedules (end of day, weekly, monthly, every few hours)
 * plus one-off reports for any date range – send, preview or download.
 */
class ReportEmailController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:Manage report emails');
    }

    public function index()
    {
        $pagetitle = 'Sales Report Emails';
        $schedules = ReportSchedule::orderBy('name')->get();
        $branches = Install::isCentral() && Schema::hasTable('branches')
            ? DB::table('branches')->orderBy('number')->get(['id', 'code', 'name']) : collect();
        $mailer = config('mail.default');
        $lastTick = Cache::get('vm_scheduler_last_run');
        $schedulerOk = $lastTick && now()->diffInMinutes(Carbon::parse($lastTick), true) <= 3;
        $me = auth()->user()->email;

        return view('report-emails.index', compact('pagetitle', 'schedules', 'branches', 'mailer', 'schedulerOk', 'lastTick', 'me'));
    }

    public function store(Request $request)
    {
        $schedule = new ReportSchedule(['created_by' => $request->user()->id]);
        return $this->save($request, $schedule, 'created');
    }

    public function update(Request $request, ReportSchedule $schedule)
    {
        return $this->save($request, $schedule, 'updated');
    }

    public function destroy(ReportSchedule $schedule)
    {
        $schedule->delete();
        AuditLogger::log('deleted', "Sales report email \"{$schedule->name}\" deleted", 'reports');
        return response()->json(['success' => true, 'message' => 'Schedule deleted.']);
    }

    public function toggle(ReportSchedule $schedule)
    {
        $schedule->is_active = !$schedule->is_active;
        $schedule->retry_for = null;
        $schedule->scheduleNext();
        $schedule->save();
        AuditLogger::log('updated', "Sales report email \"{$schedule->name}\" " . ($schedule->is_active ? 'resumed' : 'paused'), 'reports');
        return response()->json(['success' => true, 'message' => $schedule->is_active ? 'Resumed – next report ' . $schedule->next_run_at->format('D j M, g:i A') . '.' : 'Paused.', 'reload' => true]);
    }

    /** Send a schedule's report now, for the period ending now. */
    public function sendNow(ReportSchedule $schedule, ReportMailer $mailer)
    {
        [$from, $to] = $schedule->periodFor(now());
        try {
            $r = $mailer->send($schedule->recipients ?? [], $from, $to, $schedule->reportOptions(), $schedule->name);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Could not send: ' . $e->getMessage() . ' – check the email settings in System Configuration.'], 422);
        }
        $schedule->forceFill(['last_sent_at' => now(), 'last_status' => 'sent', 'last_message' => $r['message']])->save();
        AuditLogger::log('emailed', "Sales report \"{$schedule->name}\" sent manually: {$r['message']}", 'reports');
        return response()->json(['success' => true, 'message' => $r['message'], 'reload' => true]);
    }

    public function previewSchedule(ReportSchedule $schedule, SalesReportService $reports)
    {
        return $this->preview(request(), $reports, $schedule);
    }

    public function preview(Request $request, SalesReportService $reports, ?ReportSchedule $schedule = null)
    {
        if ($schedule) {
            [$from, $to] = $schedule->periodFor(now());
            $options = $schedule->reportOptions();
            $title = $schedule->name;
        } else {
            [$from, $to, $options, $title] = $this->oneOff($request);
        }
        $report = $reports->build($from, $to, $options);
        $report['title'] = $title;
        return response(view('emails.sales-report', [
            'r' => $report,
            'period' => ReportMailer::periodLabel($report['from'], $report['to']),
            'fileName' => !empty($options['include_transactions']) ? 'sales-' . $from->format('Y-m-d') . '.xlsx' : null,
        ])->render());
    }

    /** One-off report for any period. */
    public function sendOnce(Request $request, ReportMailer $mailer)
    {
        [$from, $to, $options, $title] = $this->oneOff($request);
        $to_ = $this->recipients($request->input('recipients'));
        if (!$to_) {
            return response()->json(['success' => false, 'message' => 'Enter at least one email address.'], 422);
        }
        try {
            $r = $mailer->send($to_, $from, $to, $options, $title);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Could not send: ' . $e->getMessage() . ' – check the email settings in System Configuration.'], 422);
        }
        AuditLogger::log('emailed', "One-off sales report ({$from->format('d M H:i')} – {$to->format('d M H:i')}) sent: {$r['message']}", 'reports');
        return response()->json(['success' => true, 'message' => $r['message']]);
    }

    public function download(Request $request, ReportMailer $mailer, SalesReportService $reports)
    {
        [$from, $to, $options, $title] = $this->oneOff($request);
        $report = $reports->build($from, $to, array_merge($options, ['include_transactions' => true]));
        $report['title'] = $title;
        $file = $mailer->attachment($report);
        AuditLogger::log('exported', "Sales report spreadsheet downloaded ({$from->format('d M H:i')} – {$to->format('d M H:i')})", 'reports');
        return response($file['data'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'attachment; filename="' . $file['name'] . '"',
        ]);
    }

    // ------------------------------------------------------------------ helpers

    protected function save(Request $request, ReportSchedule $schedule, string $verb)
    {
        $d = $request->validate([
            'name' => 'required|string|max:80',
            'recipients' => 'required|string|max:2000',
            'frequency' => ['required', Rule::in(array_keys(ReportSchedule::FREQUENCIES))],
            'every_hours' => 'nullable|required_if:frequency,hourly|integer|between:1,24',
            'send_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'weekday' => 'nullable|required_if:frequency,weekly|integer|between:1,7',
            'month_day' => 'nullable|required_if:frequency,monthly|integer|between:1,31',
            'period' => ['required', Rule::in(array_keys(ReportSchedule::PERIODS))],
            'channels' => ['required', Rule::in(['pos', 'all'])],
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'integer',
        ]);
        $emails = $this->recipients($d['recipients']);
        if (!$emails) {
            return response()->json(['success' => false, 'message' => 'Enter at least one valid email address.', 'errors' => ['recipients' => ['Enter at least one valid email address.']]], 422);
        }
        $schedule->fill([
            'name' => $d['name'],
            'recipients' => $emails,
            'frequency' => $d['frequency'],
            'every_hours' => $d['frequency'] === 'hourly' ? (int) $d['every_hours'] : null,
            'send_time' => $d['send_time'],
            'weekday' => $d['frequency'] === 'weekly' ? (int) $d['weekday'] : null,
            'month_day' => $d['frequency'] === 'monthly' ? (int) $d['month_day'] : null,
            'period' => $d['period'],
            'channels' => $d['channels'],
            'branch_ids' => array_values(array_map('intval', $d['branch_ids'] ?? [])) ?: null,
            'include_profit' => $request->boolean('include_profit'),
            'include_shifts' => $request->boolean('include_shifts'),
            'include_products' => $request->boolean('include_products'),
            'include_transactions' => $request->boolean('include_transactions'),
            'skip_if_empty' => $request->boolean('skip_if_empty'),
            'is_active' => $request->boolean('is_active', true),
        ]);
        $schedule->retry_for = null;
        $schedule->scheduleNext();
        $schedule->save();
        AuditLogger::log($verb, "Sales report email \"{$schedule->name}\" {$verb}: {$schedule->describe()} to " . implode(', ', $emails), 'reports');

        return response()->json([
            'success' => true,
            'message' => 'Saved. ' . ($schedule->is_active ? 'Next report: ' . $schedule->next_run_at->format('D j M Y, g:i A') . '.' : 'It is paused.'),
            'reload' => true,
        ]);
    }

    /** "a@x.com, b@y.com; c@z.com" → valid unique addresses */
    protected function recipients($raw): array
    {
        return collect(preg_split('/[\s,;]+/', (string) $raw))->map(fn ($e) => strtolower(trim($e)))
            ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))->unique()->take(20)->values()->all();
    }

    protected function oneOff(Request $request): array
    {
        $d = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
            'channels' => ['nullable', Rule::in(['pos', 'all'])],
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'integer',
            'title' => 'nullable|string|max:80',
        ]);
        $from = Carbon::parse($d['from']);
        $to = Carbon::parse($d['to']);
        if (strlen($d['to']) <= 10) {
            $to = $to->endOfDay(); // a date without a time means "the whole day"
        }
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        abort_if($from->diffInDays($to, true) > 400, 422, 'Choose a period of at most about a year.');
        $options = [
            'channels' => $d['channels'] ?? 'pos',
            'branch_ids' => array_map('intval', $d['branch_ids'] ?? []),
            'include_profit' => $request->boolean('include_profit'),
            'include_shifts' => $request->boolean('include_shifts', true),
            'include_products' => $request->boolean('include_products', true),
            'include_transactions' => $request->boolean('include_transactions', true),
        ];
        return [$from, $to, $options, ($d['title'] ?? '') ?: 'Sales report'];
    }
}
