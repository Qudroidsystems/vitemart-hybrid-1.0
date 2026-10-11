<?php

namespace App\Exceptions;

/** Thrown at the till when a supervisor must approve something (discount, price change, void…). */
class PosAuthorizationRequired extends \RuntimeException
{
    public function __construct(public string $action, string $message)
    {
        parent::__construct($message);
    }
}
