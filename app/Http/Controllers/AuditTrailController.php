<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditTrailController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:View audit trail', ['only' => ['index', 'show']]);
        $this->middleware('permission:Export audit trail|View audit trail', ['only' => ['export']]);
    }

    protected function filtered(Request $request)
    {
        $q = AuditLog::query()->latest('created_at')->latest('id');

        if ($request->filled('user_id')) {
            $q->where('user_id', $request->user_id);
        }
        if ($request->filled('module')) {
            $q->where('module', $request->module);
        }
        if ($request->filled('event')) {
            $q->where('event', $request->event);
        }
        if ($request->filled('from')) {
            $q->where('created_at', '>=', $request->from . ' 00:00:00');
        }
        if ($request->filled('to')) {
            $q->where('created_at', '<=', $request->to . ' 23:59:59');
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn ($w) => $w->where('description', 'like', "%$s%")->orWhere('auditable_label', 'like', "%$s%")
                ->orWhere('user_name', 'like', "%$s%")->orWhere('ip_address', $s)->orWhere('auditable_id', $s));
        }
        if ($request->filled('subject_type') && $request->filled('subject_id')) {
            $q->where('auditable_type', $request->subject_type)->where('auditable_id', $request->subject_id);
        }

        return $q;
    }

    public function index(Request $request)
    {
        $pagetitle = 'Audit Trail';
        $logs = $this->filtered($request)->paginate(50)->withQueryString();
        $users = User::whereIn('id', AuditLog::query()->select('user_id')->distinct())->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        $modules = AuditLog::query()->select('module')->distinct()->whereNotNull('module')->orderBy('module')->pluck('module');
        $events = AuditLog::query()->select('event')->distinct()->orderBy('event')->pluck('event');

        $today = AuditLog::where('created_at', '>=', today());
        $stats = [
            'today' => (clone $today)->count(),
            'failed_logins' => (clone $today)->where('event', 'login_failed')->count(),
            'price_changes' => (clone $today)->where('event', 'price_changed')->count()
                + \App\Models\PriceHistory::where('created_at', '>=', today())->count(),
            'voids' => (clone $today)->whereIn('event', ['voided', 'returned'])->count(),
        ];

        return view('audit.index', compact('pagetitle', 'logs', 'users', 'modules', 'events', 'stats'));
    }

    public function show(AuditLog $log)
    {
        return response()->json([
            'id' => $log->id,
            'when' => $log->created_at->format('d M Y, h:i:s A'),
            'user' => $log->user_name ?: 'System',
            'event' => $log->event,
            'module' => $log->module,
            'subject' => $log->auditable_label,
            'subject_type' => $log->auditable_type ? class_basename($log->auditable_type) : null,
            'subject_id' => $log->auditable_id,
            'description' => $log->description,
            'ip' => $log->ip_address,
            'agent' => $log->user_agent,
            'url' => $log->url,
            'method' => $log->method,
            'old' => $log->old_values,
            'new' => $log->new_values,
            'keys' => $log->changedKeys(),
        ]);
    }

    public function export(Request $request)
    {
        $file = 'audit_trail_' . now()->format('Y-m-d_His') . '.csv';
        $query = $this->filtered($request);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'User', 'Event', 'Module', 'Subject', 'Description', 'Old values', 'New values', 'IP address']);
            $query->chunk(1000, function ($logs) use ($out) {
                foreach ($logs as $l) {
                    fputcsv($out, [
                        $l->created_at->format('Y-m-d H:i:s'), $l->user_name, $l->event, $l->module, $l->auditable_label,
                        $l->description, $l->old_values ? json_encode($l->old_values) : '', $l->new_values ? json_encode($l->new_values) : '',
                        $l->ip_address,
                    ]);
                }
            });
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv']);
    }
}
