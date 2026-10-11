<?php

namespace App\Services\Sync;

/** A row points at something this server hasn't received yet – retry later. */
class MissingReference extends \RuntimeException
{
    public function __construct(public string $type, public string $uid)
    {
        parent::__construct("Waiting for {$type} {$uid}");
    }
}
