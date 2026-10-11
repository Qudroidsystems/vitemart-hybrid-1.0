<?php

namespace App\Exceptions;

class InsufficientStockException extends \RuntimeException
{
    public function __construct(public string $item, public float $available, public float $requested)
    {
        $fmt = fn ($n) => rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
        parent::__construct("Not enough stock for {$item}. Available: {$fmt($available)}, requested: {$fmt($requested)}.");
    }
}
