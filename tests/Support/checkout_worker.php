<?php

/*
 * One till for the parallel-checkout acceptance test (AcceptanceOperationsTest, test 7).
 *   php tests/Support/checkout_worker.php {user_id} {product_id} {start_at_unix_float}
 * Waits until start_at so all tills hit the last units together, sells one, prints a JSON line.
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[$_, $userId, $productId, $startAt] = $argv;
auth()->setUser(App\Models\User::findOrFail((int) $userId));
while (microtime(true) < (float) $startAt) {
    usleep(200);
}
try {
    $price = (float) App\Models\Product::whereKey((int) $productId)->value('price');
    $order = app(App\Services\PosService::class)->checkout([
        'items' => [['product_id' => (int) $productId, 'qty' => 1]],
        'payments' => [['method' => 'cash', 'amount' => $price]],
    ]);
    echo json_encode(['ok' => true, 'order' => $order->id]);
} catch (App\Exceptions\InsufficientStockException $e) {
    echo json_encode(['ok' => false, 'error' => 'insufficient_stock']);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => get_class($e) . ': ' . $e->getMessage()]);
}
