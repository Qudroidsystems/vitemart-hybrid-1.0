<?php

namespace App\Traits;

use App\Services\AuditLogger;

/**
 * Add to any Eloquent model to record create / update / delete in the audit trail
 * with before-and-after values for the fields that actually changed.
 *
 * Optional properties on the model:
 *   protected string $auditModule = 'products';
 *   protected array  $auditExclude = ['stock'];
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLogger::log('created', class_basename($model) . ' created', $model->auditModuleName(), $model, null,
                $model->auditFilter($model->getAttributes()));
        });

        static::updated(function ($model) {
            $changes = $model->auditFilter($model->getChanges());
            if (empty($changes)) {
                return;
            }
            $old = [];
            foreach (array_keys($changes) as $key) {
                $old[$key] = $model->getOriginal($key);
            }
            AuditLogger::log('updated', class_basename($model) . ' updated: ' . implode(', ', array_keys($changes)),
                $model->auditModuleName(), $model, $old, $changes);
        });

        static::deleted(function ($model) {
            AuditLogger::log('deleted', class_basename($model) . ' deleted', $model->auditModuleName(), $model,
                $model->auditFilter($model->getAttributes()), null);
        });
    }

    public function auditModuleName(): string
    {
        return property_exists($this, 'auditModule') ? $this->auditModule : strtolower(\Illuminate\Support\Str::plural(class_basename($this)));
    }

    public function auditFilter(array $values): array
    {
        $exclude = array_merge(AuditLogger::HIDDEN, property_exists($this, 'auditExclude') ? $this->auditExclude : []);
        return array_diff_key($values, array_flip($exclude));
    }
}
