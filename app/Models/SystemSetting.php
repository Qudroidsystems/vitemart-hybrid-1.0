<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Simple key/value settings store (login control, POS rules, barcode options…).
 * Values are JSON encoded so arrays/booleans round-trip correctly.
 */
class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public const CACHE_KEY = 'system_settings_all';

    /** Defaults used when a key has never been saved. */
    public static array $defaults = [
        // Login control
        'login_hours_enabled'   => false,
        'login_start_time'      => '07:00',
        'login_end_time'        => '22:00',
        'login_days'            => [1, 2, 3, 4, 5, 6, 7],
        'auto_logout_enabled'   => false,
        'auto_logout_time'      => '22:30',
        'idle_timeout_minutes'  => 0,
        'single_session'        => false,
        'sessions_valid_after'  => null,   // unix timestamp – sessions started before this are invalid
        'maintenance_lock'      => false,  // only Super Admin can log in
        'maintenance_message'   => 'The system is closed for maintenance. Please try again later.',

        // Barcodes
        'barcode_prefix'        => '20',   // GS1 in-store (restricted circulation) prefix
        'barcode_next_sequence' => 1,
        'scale_barcode_enabled' => true,
        'scale_barcode_prefixes' => ['21', '22'],
        'scale_barcode_mode'    => 'weight', // weight | price
        'scale_barcode_decimals' => 3,

        // Pricing / POS
        'block_sale_below_cost' => true,
        'max_discount_percent'  => 10,
        'require_open_shift'    => false,
        'allow_negative_stock'  => false,
        'adjustment_requires_approval' => true,
        'receipt_footer'        => 'Thank you for shopping with us!',
        'price_rounding'        => 0,      // round bulk prices to nearest N (0 = none)
    ];

    public static function all_settings(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            if (!Schema::hasTable('system_settings')) {
                return [];
            }
            return static::query()->pluck('value', 'key')
                ->map(fn ($v) => json_decode($v, true))
                ->all();
        });
    }

    public static function get(string $key, $default = null)
    {
        $all = static::all_settings();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }
        return $default ?? (static::$defaults[$key] ?? null);
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        Cache::forget(self::CACHE_KEY);
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $k => $v) {
            static::updateOrCreate(['key' => $k], ['value' => json_encode($v)]);
        }
        Cache::forget(self::CACHE_KEY);
    }
}
