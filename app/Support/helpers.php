<?php

use App\Models\StoreSetting;
use App\Models\SystemSetting;

if (!function_exists('currency_symbol')) {
    function currency_symbol(): string
    {
        static $sym = null;
        if ($sym === null) {
            try {
                $sym = StoreSetting::getSettings()?->currency_symbol ?: '₦';
            } catch (\Throwable $e) {
                $sym = '₦';
            }
        }
        return $sym;
    }
}

if (!function_exists('money')) {
    function money($amount, int $decimals = 2): string
    {
        $amount = (float) $amount;
        return ($amount < 0 ? '-' : '') . currency_symbol() . number_format(abs($amount), $decimals);
    }
}

if (!function_exists('qty')) {
    /** 12.000 → "12", 1.250 → "1.25" */
    function qty($value): string
    {
        $value = (float) $value;
        return rtrim(rtrim(number_format($value, 3, '.', ','), '0'), '.');
    }
}

if (!function_exists('sys_setting')) {
    function sys_setting(string $key, $default = null)
    {
        return SystemSetting::get($key, $default);
    }
}
