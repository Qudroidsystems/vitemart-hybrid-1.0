<?php

use App\Models\StoreSetting;
use App\Models\SystemSetting;

if (!function_exists('currency_settings')) {
    /**
     * The store's currency, chosen in Settings → Store.
     * Returns ['code' => 'NGN', 'label' => '₦'].
     */
    function currency_settings(): array
    {
        static $cur = null;
        if ($cur === null) {
            try {
                $s = StoreSetting::getSettings();
            } catch (\Throwable $e) {
                $s = null;
            }
            $code = strtoupper(trim((string) ($s?->currency_code ?: 'NGN')));
            $label = trim((string) ($s?->currency_symbol ?? ''));
            if ($label === '') {
                $label = config("currencies.$code.2.0") ?? $code;
            }
            $cur = ['code' => $code, 'label' => $label];
        }
        return $cur;
    }
}

if (!function_exists('currency_label')) {
    /** The bare symbol, for headings and labels: "Price (₦)". */
    function currency_label(): string
    {
        return currency_settings()['label'];
    }
}

if (!function_exists('currency_symbol')) {
    /**
     * The symbol to put in front of an amount. Letter symbols of 2+ characters
     * get a space so they read well: "NGN 1,250.00", "KSh 500.00", but "₦1,250.00".
     */
    function currency_symbol(): string
    {
        $label = currency_label();
        return (mb_strlen($label) >= 2 && preg_match('/\p{L}$/u', $label)) ? $label . ' ' : $label;
    }
}

if (!function_exists('currency_code')) {
    /** ISO code, e.g. "NGN" – for Intl formatting and payment gateways. */
    function currency_code(): string
    {
        return currency_settings()['code'];
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
