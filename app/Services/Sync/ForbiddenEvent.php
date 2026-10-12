<?php

namespace App\Services\Sync;

/** A branch sent a change to something it doesn't own (another branch's record). Rejected, never retried. */
class ForbiddenEvent extends \RuntimeException
{
}
