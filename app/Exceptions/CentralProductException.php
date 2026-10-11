<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

/** A branch tried to change something head office controls. */
class CentralProductException extends \RuntimeException
{
    public function __construct(public string $productTitle = '')
    {
        parent::__construct("“{$productTitle}” is a head-office product. Ask head office for a price change (Request price change).");
    }

    public function render($request)
    {
        if ($request->expectsJson()) {
            return new JsonResponse(['success' => false, 'message' => $this->getMessage(), 'central' => true], 422);
        }
        return back()->with('error', $this->getMessage());
    }
}
