<?php

namespace App\Services\MultiBranch;

use App\Models\Branch;
use App\Models\Installation;
use App\Models\Organization;
use Illuminate\Support\Facades\Schema;

/**
 * Who is this server?  standalone | central | branch
 *
 *  - standalone: the original single-store ViteMart (no sync)
 *  - central:    the headquarters installation
 *  - branch:     a store server enrolled with headquarters
 */
class Install
{
    protected static ?Installation $current = null;
    protected static bool $loaded = false;

    public static function current(): ?Installation
    {
        if (!static::$loaded) {
            static::$loaded = true;
            try {
                static::$current = Schema::hasTable('installation') ? Installation::with('branch', 'organization')->first() : null;
            } catch (\Throwable $e) {
                static::$current = null;
            }
        }
        return static::$current;
    }

    /** Drop the cached identity without querying (next call reloads it). */
    public static function forget(): void
    {
        static::$loaded = false;
        static::$current = null;
    }

    public static function refresh(): ?Installation
    {
        static::$loaded = false;
        static::$current = null;
        return static::current();
    }

    public static function mode(): string
    {
        return static::current()?->mode ?? 'standalone';
    }

    public static function isCentral(): bool
    {
        return static::mode() === 'central';
    }

    public static function isBranch(): bool
    {
        return static::mode() === 'branch';
    }

    public static function isStandalone(): bool
    {
        return static::mode() === 'standalone';
    }

    /** Sync is on for enrolled branches and for HQ. */
    public static function isMultiBranch(): bool
    {
        return in_array(static::mode(), ['central', 'branch'], true);
    }

    public static function branch(): ?Branch
    {
        return static::current()?->branch;
    }

    public static function branchId(): ?int
    {
        return static::current()?->branch_id;
    }

    public static function branchCode(): string
    {
        return static::branch()?->code ?? 'MAIN';
    }

    public static function branchNumber(): int
    {
        return (int) (static::branch()?->number ?? 1);
    }

    public static function organization(): ?Organization
    {
        return static::current()?->organization;
    }
}
