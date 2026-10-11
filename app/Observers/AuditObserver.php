<?php

namespace App\Observers;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Generic created/updated/deleted auditing for models we don't own (e.g. Spatie Role). */
class AuditObserver
{
    protected function module(Model $m): string
    {
        return match (class_basename($m)) {
            'User', 'Role' => 'security',
            'StoreSetting' => 'settings',
            'StockLocation' => 'inventory',
            default => Str::plural(strtolower(class_basename($m))),
        };
    }

    protected function filter(array $v): array
    {
        return array_diff_key($v, array_flip(array_merge(AuditLogger::HIDDEN, [
            'last_login_at', 'last_login_ip', 'last_notification_at', 'notification_count', 'force_logout_at',
        ])));
    }

    public function created(Model $m): void
    {
        AuditLogger::log('created', class_basename($m) . ' created', $this->module($m), $m, null, $this->filter($m->getAttributes()));
    }

    public function updated(Model $m): void
    {
        $changes = $this->filter($m->getChanges());
        if (!$changes) {
            return;
        }
        $old = [];
        foreach (array_keys($changes) as $k) {
            $old[$k] = $m->getOriginal($k);
        }
        AuditLogger::log('updated', class_basename($m) . ' updated: ' . implode(', ', array_keys($changes)), $this->module($m), $m, $old, $changes);
    }

    public function deleted(Model $m): void
    {
        AuditLogger::log('deleted', class_basename($m) . ' deleted', $this->module($m), $m, $this->filter($m->getAttributes()), null);
    }
}
