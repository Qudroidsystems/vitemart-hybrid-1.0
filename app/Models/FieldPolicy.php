<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * What a branch may do with a head-office product field:
 *   locked   – only head office changes it
 *   request  – the branch asks; head office approves (prices)
 *   override – the branch sets its own value (reorder level, sold here or not)
 */
class FieldPolicy extends Model
{
    protected $fillable = ['field', 'label', 'branch_rule', 'help'];

    public const RULES = ['locked', 'request', 'override'];

    public static function map(): array
    {
        return Cache::remember('field_policies', 300, function () {
            return Schema::hasTable('field_policies') ? static::pluck('branch_rule', 'field')->all() : [];
        });
    }

    public static function rule(string $field): string
    {
        return static::map()[$field] ?? 'locked';
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('field_policies'));
    }
}
