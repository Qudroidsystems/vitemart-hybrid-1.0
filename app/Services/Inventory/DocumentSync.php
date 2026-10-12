<?php

namespace App\Services\Inventory;

use App\Services\MultiBranch\Install;
use App\Services\Sync\Feed;
use App\Services\Sync\Outbox;

/** Share a transfer / purchase order after a local change: branch → outbox, head office → feed. */
class DocumentSync
{
    public static function share(string $entity, $doc): void
    {
        if (Install::isBranch()) {
            $payload = match ($entity) {
                'transfer' => DocumentPayload::transfer($doc->fresh('lines')),
                'purchase_order' => DocumentPayload::purchaseOrder($doc->fresh('lines')),
            };
            Outbox::recordEntity($entity, $doc->uid, $payload);
        } elseif (Install::isCentral()) {
            Feed::mark($entity, $doc->uid);
        }
    }
}
