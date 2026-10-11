<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Central writer for the audit trail. Never throws – auditing must not break a sale.
 */
class AuditLogger
{
    /** Attributes that are never written to the audit trail. */
    public const HIDDEN = ['password', 'remember_token', 'wpassword', 'pos_pin', 'fcm_tokens', 'updated_at', 'created_at'];

    protected static ?bool $tableExists = null;
    protected static bool $muted = false;

    public static function mute(callable $callback)
    {
        $prev = static::$muted;
        static::$muted = true;
        try {
            return $callback();
        } finally {
            static::$muted = $prev;
        }
    }

    public static function log(
        string $event,
        ?string $description = null,
        ?string $module = null,
        $subject = null,
        ?array $old = null,
        ?array $new = null,
        $user = null
    ): ?AuditLog {
        if (static::$muted) {
            return null;
        }
        try {
            if (static::$tableExists === null) {
                static::$tableExists = Schema::hasTable('audit_logs');
            }
            if (!static::$tableExists) {
                return null;
            }

            $user = $user ?? Auth::user();
            $request = app()->runningInConsole() ? null : request();

            $type = null;
            $id = null;
            $label = null;
            if ($subject instanceof Model) {
                $type = get_class($subject);
                $id = (string) $subject->getKey();
                $label = method_exists($subject, 'auditLabel')
                    ? $subject->auditLabel()
                    : ($subject->title ?? $subject->name ?? $subject->reference ?? null);
            } elseif (is_array($subject)) {
                [$type, $id, $label] = array_pad($subject, 3, null);
            }

            return AuditLog::create([
                'user_id' => $user?->id,
                'user_name' => $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->email ?? null) : null,
                'event' => $event,
                'module' => $module,
                'auditable_type' => $type,
                'auditable_id' => $id,
                'auditable_label' => $label ? mb_substr((string) $label, 0, 250) : null,
                'old_values' => $old ? static::clean($old) : null,
                'new_values' => $new ? static::clean($new) : null,
                'description' => $description ? mb_substr($description, 0, 500) : null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : 'console',
                'url' => $request ? mb_substr($request->fullUrl(), 0, 500) : null,
                'method' => $request?->method(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Audit log failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function clean(array $values): array
    {
        $out = [];
        foreach ($values as $k => $v) {
            if (in_array($k, self::HIDDEN, true)) {
                continue;
            }
            if ($v instanceof \DateTimeInterface) {
                $v = $v->format('Y-m-d H:i:s');
            } elseif (is_object($v)) {
                $v = method_exists($v, 'toArray') ? $v->toArray() : (string) json_encode($v);
            }
            if (is_string($v) && mb_strlen($v) > 1000) {
                $v = mb_substr($v, 0, 1000) . '…';
            }
            $out[$k] = $v;
        }
        return $out;
    }
}
