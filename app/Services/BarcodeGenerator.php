<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

/**
 * Generates in-store EAN-13 barcodes and decodes scale (weight/price-embedded) barcodes.
 *
 * GS1 reserves prefixes 20–29 for "restricted circulation" numbers – codes that are
 * only valid inside your own store. We use prefix 20 (configurable) for products,
 * variations and packs, and 21/22 for labels printed by a weighing scale.
 */
class BarcodeGenerator
{
    public static function checkDigit(string $twelve): int
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $d = (int) $twelve[$i];
            $sum += ($i % 2 === 0) ? $d : $d * 3;
        }
        return (10 - ($sum % 10)) % 10;
    }

    public static function isValidEan13(string $code): bool
    {
        return (bool) preg_match('/^\d{13}$/', $code)
            && static::checkDigit(substr($code, 0, 12)) === (int) $code[12];
    }

    /** Returns a new unique EAN-13 such as 2000000000015. */
    public static function next(): string
    {
        // In-store prefix (20) + this branch's 2-digit number, so two branches creating
        // products offline can never produce the same code.
        $prefix = preg_replace('/\D/', '', (string) SystemSetting::get('barcode_prefix', '20')) ?: '20';
        // Head office uses 29 00 …: the old single-store codes (20 00 …) live on in the first branch.
        $lead = \App\Services\MultiBranch\Install::isCentral() ? '29' : substr($prefix, 0, 2);
        $prefix = $lead . str_pad((string) \App\Services\MultiBranch\Install::branchNumber(), 2, '0', STR_PAD_LEFT);
        $bodyLen = 12 - strlen($prefix);

        return DB::transaction(function () use ($prefix, $bodyLen) {
            $seqKey = 'barcode_next_sequence_' . $prefix;
            $row = DB::table('system_settings')->where('key', $seqKey)->lockForUpdate()->first();
            $seq = $row ? (int) json_decode($row->value, true) : static::guessStartSequence($prefix, $bodyLen);

            for ($tries = 0; $tries < 1000; $tries++, $seq++) {
                $twelve = $prefix . str_pad((string) $seq, $bodyLen, '0', STR_PAD_LEFT);
                $code = $twelve . static::checkDigit($twelve);
                if (!static::exists($code)) {
                    SystemSetting::set($seqKey, $seq + 1);
                    return $code;
                }
            }
            throw new \RuntimeException('Unable to allocate a unique barcode.');
        });
    }

    protected static function guessStartSequence(string $prefix, int $bodyLen): int
    {
        // Continue after the highest in-store code already in the database.
        $max = 0;
        foreach ([['products', 'barcode'], ['product_variations', 'barcode'], ['product_unit', 'barcode']] as [$t, $c]) {
            $v = DB::table($t)->where($c, 'like', $prefix . '%')->whereRaw("LENGTH($c) = 13")->max($c);
            if ($v) {
                $max = max($max, (int) substr($v, strlen($prefix), $bodyLen));
            }
        }
        return $max + 1;
    }

    public static function exists(string $code, ?string $ignoreTable = null, $ignoreId = null): bool
    {
        // The barcode index (product_barcodes) covers primary and extra codes.
        if (\App\Services\Catalogue\BarcodeIndex::ready()) {
            $row = \App\Models\ProductBarcode::where('barcode', $code)->first();
            if ($row) {
                $ownRow = $ignoreId && $row->kind === 'primary' && match ($ignoreTable) {
                    'products' => $row->product_id == $ignoreId && !$row->product_variation_id && !$row->product_unit_id,
                    'product_variations' => $row->product_variation_id == $ignoreId,
                    'product_unit' => $row->product_unit_id == $ignoreId,
                    default => false,
                };
                if (!$ownRow) {
                    return true;
                }
            }
        }

        $checks = [
            'products' => Product::query(),
            'product_variations' => ProductVariation::query(),
            'product_unit' => ProductUnit::query(),
        ];
        foreach ($checks as $table => $q) {
            $q->where('barcode', $code);
            if ($ignoreTable === $table && $ignoreId) {
                $q->where('id', '!=', $ignoreId);
            }
            if ($q->exists()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Find who owns a barcode: ['type' => product|variation|pack, 'model' => …]
     */
    public static function owner(string $code): ?array
    {
        if (\App\Services\Catalogue\BarcodeIndex::ready()
            && ($row = \App\Models\ProductBarcode::with('product', 'variation', 'pack')->where('barcode', $code)->first())
            && $row->product) {
            if ($row->product_variation_id && $row->variation) {
                return ['type' => 'variation', 'model' => $row->variation, 'kind' => $row->kind];
            }
            if ($row->product_unit_id && $row->pack) {
                return ['type' => 'pack', 'model' => $row->pack, 'kind' => $row->kind];
            }
            return ['type' => 'product', 'model' => $row->product, 'kind' => $row->kind];
        }
        if ($p = Product::where('barcode', $code)->first()) {
            return ['type' => 'product', 'model' => $p];
        }
        if ($v = ProductVariation::where('barcode', $code)->first()) {
            return ['type' => 'variation', 'model' => $v];
        }
        if ($u = ProductUnit::where('barcode', $code)->first()) {
            return ['type' => 'pack', 'model' => $u];
        }
        return null;
    }

    /**
     * Decode a scale label: PP IIIII VVVVV C
     *   PP    = prefix (21/22 by default)
     *   IIIII = product PLU code
     *   VVVVV = weight in grams (mode=weight) or price in kobo/cents (mode=price)
     */
    public static function parseScaleBarcode(string $code): ?array
    {
        if (!SystemSetting::get('scale_barcode_enabled', true) || !static::isValidEan13($code)) {
            return null;
        }
        $prefixes = (array) SystemSetting::get('scale_barcode_prefixes', ['21', '22']);
        $prefix = substr($code, 0, 2);
        if (!in_array($prefix, $prefixes, true)) {
            return null;
        }
        $plu = ltrim(substr($code, 2, 5), '0');
        $raw = (int) substr($code, 7, 5);
        $mode = SystemSetting::get('scale_barcode_mode', 'weight');
        $decimals = (int) SystemSetting::get('scale_barcode_decimals', $mode === 'weight' ? 3 : 2);

        return [
            'plu' => $plu,
            'mode' => $mode,
            'value' => $raw / (10 ** $decimals),
        ];
    }

    /** Build a scale barcode (used by tests / label printing for weighed items). */
    public static function makeScaleBarcode(string $plu, float $value, string $prefix = '21'): string
    {
        $mode = SystemSetting::get('scale_barcode_mode', 'weight');
        $decimals = (int) SystemSetting::get('scale_barcode_decimals', $mode === 'weight' ? 3 : 2);
        $twelve = $prefix . str_pad($plu, 5, '0', STR_PAD_LEFT) . str_pad((string) round($value * 10 ** $decimals), 5, '0', STR_PAD_LEFT);
        return $twelve . static::checkDigit($twelve);
    }

    /** SKU like "BEV-COKE-0042" from category + title. */
    public static function suggestSku(string $title, ?string $categoryName = null): string
    {
        $clean = fn ($s, $n) => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $s), 0, $n));
        $base = trim(($categoryName ? $clean($categoryName, 3) . '-' : '') . $clean($title, 6), '-') ?: 'PRD';
        $n = 1;
        do {
            $sku = $base . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            $n++;
        } while (Product::where('sku', $sku)->exists() || ProductVariation::where('sku', $sku)->exists());
        return $sku;
    }
}
