<?php

namespace App\Traits;

use App\Services\Catalogue\BarcodeIndex;

/**
 * Keeps product_barcodes (the one place every barcode is looked up) in step with the
 * barcode column on products, product_variations and product_unit.
 */
trait IndexesBarcodes
{
    public static function bootIndexesBarcodes(): void
    {
        static::saved(function ($model) {
            if ($model->wasRecentlyCreated || $model->wasChanged('barcode')) {
                BarcodeIndex::syncPrimary($model);
            }
        });
        static::deleted(function ($model) {
            BarcodeIndex::forget($model);
        });
    }
}
