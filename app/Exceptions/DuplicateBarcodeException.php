<?php

namespace App\Exceptions;

class DuplicateBarcodeException extends \RuntimeException
{
    public function __construct(public string $barcode)
    {
        parent::__construct("Barcode {$barcode} is already used by another item.");
    }
}
