<?php

namespace App\Services\Catalogue;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Services\AuditLogger;
use App\Services\PricingService;
use App\Services\ProductService;
use App\Services\StockAdjustmentService;
use App\Support\Xlsx\SheetReader;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Product spreadsheet upload, in three stages so even 100,000-row files work
 * without a queue worker and without timing out:
 *
 *  1. stage()      – read the file once, recognise the columns, flag duplicate
 *                    SKUs/barcodes inside the file, save the rows to disk.
 *  2. checkStep()  – called repeatedly: works out for every row whether it will
 *                    add, change or skip a product and lists any problems.
 *                    Nothing is saved.
 *  3. applyStep()  – called repeatedly: saves the products, then posts the stock
 *                    columns as stock adjustments (so stock history stays correct).
 */
class ProductImporter
{
    public const CHECK_BATCH = 2000;
    public const APPLY_BATCH = 200;
    public const STOCK_BATCH = 1000;
    public const MAX_ISSUES = 300;
    public const STEP_SECONDS = 12;

    protected const IGNORED = ['stock', 'product_type'];
    protected const CLEARABLE = ['cost_price', 'wholesale_price', 'promo_price', 'promo_starts_at', 'promo_ends_at', 'category', 'brand', 'description', 'plu_code'];

    protected array $maps = [];

    public function __construct(
        protected ProductService $products,
        protected PricingService $pricing,
        protected StockAdjustmentService $adjustments,
    ) {
    }

    // =================================================================== 1. stage

    public function stage(UploadedFile $file, array $options, ?int $userId): ProductImport
    {
        @set_time_limit(600);
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');
        if ($ext === 'txt') {
            $ext = 'csv';
        }
        if (!in_array($ext, ['xlsx', 'csv'], true)) {
            throw ValidationException::withMessages(['file' => 'Please upload an Excel (.xlsx) or .csv file. For an old .xls file, open it in Excel and "Save as" .xlsx.']);
        }

        $imp = ProductImport::create([
            'user_id' => $userId, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 250), 'ext' => $ext,
            'status' => 'checking', 'options' => $options,
        ]);
        File::ensureDirectoryExists($imp->dir(), 0750);
        $src = $imp->dir() . '/source.' . $ext;
        copy($file->getRealPath(), $src);

        // Same file saved before? (re-uploading would add "received" stock twice)
        $hash = sha1_file($src);
        $before = ProductImport::where('status', 'done')->where('id', '!=', $imp->id)->latest('applied_at')->limit(100)->get()
            ->first(fn ($x) => ($x->options['hash'] ?? null) === $hash);
        $imp->options = array_merge($options, ['hash' => $hash, 'seen_before' => $before ? ['id' => $before->id, 'at' => $before->applied_at?->toDateTimeString()] : null]);
        $imp->save();

        try {
            $this->stageRows($imp, $src);
        } catch (\Throwable $e) {
            $imp->update(['status' => 'failed', 'error' => $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage()]);
            throw $e instanceof ValidationException ? $e : ValidationException::withMessages(['file' => $e->getMessage()]);
        }
        return $imp->fresh();
    }

    protected function stageRows(ProductImport $imp, string $src): void
    {
        $map = ProductSpreadsheet::headerMap();
        $colKey = null;
        $columns = ['recognised' => [], 'ignored' => []];
        $out = fopen($imp->rowsFile(), 'w');
        $seen = ['id' => [], 'sku' => [], 'barcode' => []];
        $total = 0;

        foreach (SheetReader::rows($src, $imp->ext, ProductSpreadsheet::SHEET) as $rowNum => $cells) {
            if ($colKey === null) {
                $filled = array_filter($cells, fn ($v) => $v !== '');
                if (count($filled) < 2) {
                    continue; // title rows / blank lines above the header
                }
                $colKey = [];
                foreach ($cells as $i => $h) {
                    $k = $map[ProductSpreadsheet::norm($h)] ?? null;
                    if ($k && !in_array($k, $colKey, true)) {
                        $colKey[$i] = $k;
                        $columns['recognised'][] = $h;
                    } elseif (trim($h) !== '') {
                        $columns['ignored'][] = $h;
                    }
                }
                if (!array_intersect($colKey, ['title', 'id', 'sku', 'barcode'])) {
                    throw ValidationException::withMessages(['file' => 'Couldn\'t find the column headings. The first row should have headings like "Product name", "SKU", "Barcode", "Selling price" – download the template to see them.']);
                }
                continue;
            }

            $row = [];
            foreach ($colKey as $i => $k) {
                if (in_array($k, self::IGNORED, true)) {
                    continue;
                }
                $v = trim((string) ($cells[$i] ?? ''));
                if ($v !== '') {
                    $row[$k] = mb_substr($v, 0, 5000);
                }
            }
            if (!$row) {
                continue;
            }

            // Same product twice in the file?
            $dups = [];
            foreach (['id' => $row['id'] ?? null, 'sku' => isset($row['sku']) ? strtoupper($row['sku']) : null] as $k => $v) {
                if ($v !== null && $v !== '-') {
                    if (isset($seen[$k][$v])) {
                        $dups[] = strtoupper($k) . ' ' . $v . ' is also on row ' . $seen[$k][$v];
                    } else {
                        $seen[$k][$v] = $rowNum;
                    }
                }
            }
            foreach (['barcode', 'pack1_barcode', 'pack2_barcode'] as $k) {
                $v = $row[$k] ?? null;
                if ($v !== null && $v !== '-') {
                    if (isset($seen['barcode'][$v])) {
                        $dups[] = 'Barcode ' . $v . ' is also on row ' . $seen['barcode'][$v];
                    } else {
                        $seen['barcode'][$v] = $rowNum;
                    }
                }
            }
            if ($dups) {
                $row['_dup'] = implode('; ', $dups);
            }

            fwrite($out, json_encode([$rowNum, $row], JSON_UNESCAPED_UNICODE) . "\n");
            $total++;
        }
        fclose($out);

        if ($colKey === null) {
            throw ValidationException::withMessages(['file' => 'The file is empty.']);
        }
        if ($total === 0) {
            throw ValidationException::withMessages(['file' => 'There are no product rows under the headings.']);
        }
        $columns['recognised'] = array_values(array_unique($columns['recognised']));
        $imp->update(['total_rows' => $total, 'columns' => $columns, 'counts' => $this->emptyCounts(), 'issues' => [], 'preview' => ['creates' => [], 'updates' => []]]);
        @unlink($imp->dir() . '/issues-check.jsonl');
    }

    // =================================================================== 2. check

    public function checkStep(ProductImport $imp): ProductImport
    {
        if ($imp->status !== 'checking') {
            return $imp;
        }
        @set_time_limit(120);
        $this->loadMaps();
        $counts = $imp->counts ?: $this->emptyCounts();
        $issues = $imp->issues ?: [];
        $preview = $imp->preview ?: ['creates' => [], 'updates' => []];
        $issueFile = fopen($imp->dir() . '/issues-check.jsonl', 'a');
        $opts = $imp->options ?? [];

        [$rows, $cursor, $eof] = $this->readRows($imp, self::CHECK_BATCH);
        $ctx = $this->context($rows);
        foreach ($rows as [$rowNum, $r]) {
            $plan = $this->plan($r, $ctx, $opts, true);
            $this->tally($counts, $plan);
            $this->collectIssues($issues, $issueFile, $rowNum, $r, $plan, $counts);
            if ($plan['action'] === 'create' && count($preview['creates']) < 25) {
                $preview['creates'][] = ['row' => $rowNum, 'title' => $plan['data']['title'] ?? '', 'price' => $plan['data']['price'] ?? null,
                    'category' => $r['category'] ?? null, 'unit' => $r['unit'] ?? null, 'stock' => $plan['stock'] ? $this->stockText($plan['stock']) : null];
            }
            if ($plan['action'] === 'update' && count($preview['updates']) < 40) {
                $preview['updates'][] = ['row' => $rowNum, 'id' => $plan['product']->id, 'title' => $plan['product']->title,
                    'changes' => array_slice($plan['changes'], 0, 8, true), 'more' => max(0, count($plan['changes']) - 8),
                    'stock' => $plan['stock'] ? $this->stockText($plan['stock']) : null];
            }
        }
        fclose($issueFile);

        $imp->fill(['counts' => $counts, 'issues' => $issues, 'preview' => $preview, 'cursor' => $cursor, 'processed' => $imp->processed + count($rows)]);
        if ($eof) {
            $imp->fill(['status' => 'checked', 'cursor' => 0, 'processed' => 0]);
        }
        $imp->save();
        return $imp;
    }

    // =================================================================== 3. apply

    public function applyStep(ProductImport $imp): ProductImport
    {
        if (!in_array($imp->status, ['checked', 'applying'], true)) {
            return $imp;
        }
        @set_time_limit(300);
        $result = $imp->result ?: ['phase' => 'rows', 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0, 'stock_rows' => 0, 'adjustments' => []];
        if ($imp->status === 'checked') {
            $imp->fill(['status' => 'applying', 'cursor' => 0, 'processed' => 0, 'issues' => []]);
            @unlink($imp->dir() . '/issues-apply.jsonl');
            @unlink($imp->dir() . '/stock-add.jsonl');
            @unlink($imp->dir() . '/stock-set.jsonl');
        }
        $started = microtime(true);

        if ($result['phase'] === 'rows') {
            $this->loadMaps();
            $issues = $imp->issues ?: [];
            $issueFile = fopen($imp->dir() . '/issues-apply.jsonl', 'a');
            $stockFiles = ['add' => fopen($imp->dir() . '/stock-add.jsonl', 'a'), 'set' => fopen($imp->dir() . '/stock-set.jsonl', 'a')];
            $opts = $imp->options ?? [];
            $counts = ['error' => 0, 'warning' => 0];

            do {
                [$rows, $cursor, $eof] = $this->readRows($imp, self::APPLY_BATCH);
                $ctx = $this->context($rows);
                foreach ($rows as [$rowNum, $r]) {
                    $plan = $this->plan($r, $ctx, $opts, false);
                    try {
                        $product = match ($plan['action']) {
                            'create' => $this->create($plan),
                            'update' => $this->update($plan),
                            default => $plan['product'],
                        };
                        $result[match ($plan['action']) { 'create' => 'created', 'update' => 'updated', 'unchanged' => 'unchanged', 'error' => 'failed', default => 'skipped' }]++;
                        if ($product && $plan['stock'] && $plan['action'] !== 'error') {
                            foreach ($plan['stock'] as $mode => $qty) {
                                fwrite($stockFiles[$mode], json_encode(['product_id' => $product->id, 'quantity' => $qty, 'unit_cost' => $product->cost_price]) . "\n");
                            }
                            $result['stock_rows']++;
                        }
                        if ($plan['action'] === 'create' && $product) {
                            $ctx['created'][] = $product;
                        }
                    } catch (ValidationException $e) {
                        $plan['errors'][] = collect($e->errors())->flatten()->implode(' ');
                        $plan['action'] = 'error';
                        $result['failed']++;
                    } catch (\Throwable $e) {
                        report($e);
                        $plan['errors'][] = 'Could not save: ' . $e->getMessage();
                        $plan['action'] = 'error';
                        $result['failed']++;
                    }
                    $this->collectIssues($issues, $issueFile, $rowNum, $r, $plan, $counts);
                }
                $imp->fill(['cursor' => $cursor, 'processed' => $imp->processed + count($rows), 'issues' => $issues]);
                if ($eof) {
                    $result['phase'] = 'stock_add';
                    $imp->cursor = 0;
                    break;
                }
            } while (microtime(true) - $started < self::STEP_SECONDS);

            fclose($issueFile);
            array_map('fclose', $stockFiles);
        } elseif (in_array($result['phase'], ['stock_add', 'stock_set'], true)) {
            $mode = $result['phase'] === 'stock_add' ? 'add' : 'set';
            $file = $imp->dir() . '/stock-' . $mode . '.jsonl';
            $items = [];
            $eof = true;
            if (is_file($file)) {
                $h = fopen($file, 'r');
                fseek($h, $imp->cursor);
                while (($line = fgets($h)) !== false) {
                    $items[] = json_decode($line, true) + ['mode' => $mode];
                    if (count($items) >= self::STOCK_BATCH) {
                        break;
                    }
                }
                $imp->cursor = ftell($h);
                $eof = feof($h) || fgets($h) === false;
                fclose($h);
            }
            if ($items) {
                $part = count($result['adjustments']) + 1;
                try {
                    $adj = $this->adjustments->create([
                        'type' => $mode === 'add' ? 'receive' : 'count',
                        'reason' => 'Spreadsheet upload',
                        'notes' => 'From ' . $imp->original_name . ($eof && $part === 1 ? '' : ' (part ' . $part . ')'),
                        'items' => $items,
                    ], 'submit');
                    $result['adjustments'][] = ['id' => $adj->id, 'reference' => $adj->reference, 'type' => $mode === 'add' ? 'Stock received' : 'Stock count', 'status' => $adj->status, 'items' => $adj->items_count];
                } catch (\Throwable $e) {
                    report($e);
                    $result['adjustments'][] = ['id' => null, 'reference' => null, 'type' => $mode === 'add' ? 'Stock received' : 'Stock count', 'status' => 'failed', 'items' => count($items), 'error' => $e->getMessage()];
                }
            }
            if ($eof) {
                $imp->cursor = 0;
                $result['phase'] = $mode === 'add' ? 'stock_set' : 'done';
            }
        }

        if ($result['phase'] === 'done') {
            $imp->fill(['status' => 'done', 'applied_at' => now()]);
            AuditLogger::log('imported', sprintf('Product spreadsheet "%s": %d added, %d changed, %d unchanged, %d skipped, %d failed%s',
                $imp->original_name, $result['created'], $result['updated'], $result['unchanged'], $result['skipped'], $result['failed'],
                $result['adjustments'] ? '; stock: ' . collect($result['adjustments'])->pluck('reference')->filter()->implode(', ') : ''), 'products');
        }
        $imp->result = $result;
        $imp->save();
        return $imp;
    }

    // =================================================================== planning

    /**
     * Work out what a row will do. Never writes to the database.
     * @return array{action:string,product:?Product,data:array,packs:array,base_unit_id:?int,stock:array,changes:array,errors:array,warnings:array,governed:bool}
     */
    protected function plan(array $r, array &$ctx, array $opts, bool $dryRun): array
    {
        $res = ['action' => 'error', 'product' => null, 'data' => [], 'packs' => [], 'base_unit_id' => null, 'stock' => [],
            'changes' => [], 'errors' => [], 'warnings' => [], 'governed' => false, 'new_names' => []];
        if (!empty($r['_dup'])) {
            $res['errors'][] = 'Duplicate in the file – ' . $r['_dup'] . '.';
        }

        // ---------------- which product?
        $p = null;
        $id = $r['id'] ?? '';
        if ($id !== '' && $id !== '-') {
            if (!ctype_digit($id)) {
                $res['errors'][] = "ID \"{$id}\" isn't a number.";
            } elseif (!($p = $ctx['byId'][(int) $id] ?? null)) {
                $res['errors'][] = "There's no product with ID {$id}. To add it as a new product, clear the ID cell.";
            }
        } elseif (!empty($r['sku']) && ($m = $ctx['bySku'][strtoupper($r['sku'])] ?? null)) {
            $p = $m;
        } elseif (!empty($r['barcode']) && ($m = $ctx['byBarcode'][$r['barcode']] ?? null)) {
            $p = $m;
        } elseif (empty($r['sku']) && empty($r['barcode']) && !empty($r['title'])) {
            // No ID, SKU or barcode: match an existing product with exactly this name.
            $same = $ctx['byTitle'][mb_strtolower(trim($r['title']))] ?? [];
            if (count($same) === 1) {
                $p = $same[0];
            } elseif (count($same) > 1) {
                $res['errors'][] = count($same) . ' products are called "' . $r['title'] . '" – add the ID or SKU so the right one is changed.';
            }
        }
        $res['product'] = $p;
        $creating = !$p && !$res['errors'];
        $governed = $p && CatalogueRequests::governed($p);
        $res['governed'] = $governed;

        // ---------------- fields
        $d = [];
        $err = function (string $m) use (&$res) { $res['errors'][] = $m; };

        if (isset($r['title'])) {
            if ($r['title'] === '-') {
                $err('Product name can\'t be removed.');
            } else {
                $d['title'] = mb_substr($r['title'], 0, 255);
            }
        }
        if (isset($r['sku']) && $r['sku'] !== '-') {
            $sku = strtoupper(mb_substr($r['sku'], 0, 100));
            $owner = $ctx['bySku'][$sku] ?? null;
            if ($owner && (!$p || $owner->id !== $p->id)) {
                $err("SKU {$sku} already belongs to \"{$owner->title}\".");
            } else {
                $d['sku'] = $sku;
            }
        }
        if (isset($r['barcode']) && $r['barcode'] !== '-') {
            $code = preg_replace('/\s+/', '', $r['barcode']);
            if (strlen($code) > 64) {
                $err('Barcode is too long.');
            } elseif ($o = $this->barcodeOwner($code, $ctx)) {
                if (!$p || $o['product_id'] !== $p->id || $o['type'] !== 'product') {
                    $err("Barcode {$code} already belongs to " . $this->ownerName($o, $ctx) . '.');
                }
            }
            $d['barcode'] = $code;
        }
        foreach (['category' => 'category_id', 'brand' => 'brand_id'] as $k => $field) {
            if (!isset($r[$k])) {
                continue;
            }
            if ($r[$k] === '-') {
                $d[$field] = null;
                continue;
            }
            $idv = $this->lookup($k === 'category' ? 'categories' : 'brands', $r[$k]);
            if ($idv === null) {
                if (!empty($opts['create_missing'])) {
                    $d[$field] = ['new' => $r[$k]];
                    $res['new_names'][] = ucfirst($k) . ' "' . $r[$k] . '"';
                } else {
                    $err(ucfirst($k) . " \"{$r[$k]}\" doesn't exist. Tick \"Create missing categories, brands and units\" or fix the name.");
                }
            } else {
                $d[$field] = $idv;
            }
        }
        if (isset($r['unit']) && $r['unit'] !== '-') {
            $u = $this->lookup('units', $r['unit']);
            if ($u === null && empty($opts['create_missing'])) {
                $err("Unit \"{$r['unit']}\" doesn't exist. Tick \"Create missing categories, brands and units\" or fix the name.");
            } else {
                $res['base_unit_id'] = $u ?? ['new' => $r['unit']];
                if ($u === null) {
                    $res['new_names'][] = 'Unit "' . $r['unit'] . '"';
                }
            }
        }
        foreach (['price', 'cost_price', 'wholesale_price', 'promo_price'] as $k) {
            if (!isset($r[$k])) {
                continue;
            }
            if ($r[$k] === '-') {
                $k === 'price' ? $err('Selling price can\'t be removed.') : $d[$k] = null;
                continue;
            }
            $n = $this->money($r[$k]);
            $n === null ? $err($this->label($k) . " \"{$r[$k]}\" isn't a valid amount.") : $d[$k] = $n;
        }
        foreach (['promo_starts_at', 'promo_ends_at'] as $k) {
            if (!isset($r[$k])) {
                continue;
            }
            if ($r[$k] === '-') {
                $d[$k] = null;
                continue;
            }
            $date = SheetReader::excelDate($r[$k]);
            $date === null ? $err($this->label($k) . " \"{$r[$k]}\" isn't a date (use 2026-12-31).") : $d[$k] = $date;
        }
        $ps = array_key_exists('promo_starts_at', $d) ? $d['promo_starts_at'] : ($p ? substr((string) $p->getRawOriginal('promo_starts_at'), 0, 10) : null);
        $pe = array_key_exists('promo_ends_at', $d) ? $d['promo_ends_at'] : ($p ? substr((string) $p->getRawOriginal('promo_ends_at'), 0, 10) : null);
        if ($ps && $pe && $pe < $ps) {
            $err('Promo ends before it starts.');
        }
        if (isset($r['reorder_level']) && $r['reorder_level'] !== '-') {
            $n = $this->number($r['reorder_level']);
            $n === null || $n < 0 ? $err("Reorder level \"{$r['reorder_level']}\" isn't a valid number.") : $d['reorder_level'] = $n;
        }
        foreach (['is_weighted', 'track_stock', 'allow_price_override', 'is_active'] as $k) {
            if (!isset($r[$k])) {
                continue;
            }
            $b = $this->bool($r[$k]);
            $b === null ? $err($this->label($k) . ": write Yes or No (not \"{$r[$k]}\").") : $d[$k] = $b;
        }
        if (isset($r['plu_code'])) {
            if ($r['plu_code'] === '-') {
                $d['plu_code'] = null;
            } else {
                $plu = mb_substr($r['plu_code'], 0, 10);
                $owner = $ctx['byPlu'][$plu] ?? null;
                if ($owner && (!$p || $owner->id !== $p->id)) {
                    $err("Scale PLU {$plu} is already used by \"{$owner->title}\".");
                } else {
                    $d['plu_code'] = $plu;
                }
            }
        }
        if (isset($r['description'])) {
            $d['description'] = $r['description'] === '-' ? null : $r['description'];
        }

        // ---------------- packs
        $existingPacks = $p ? ($ctx['packs'][$p->id] ?? collect()) : collect();
        $basePack = $existingPacks->firstWhere('is_base', true) ?? $existingPacks->firstWhere('is_base', 1);
        $baseUnit = is_int($res['base_unit_id']) ? $res['base_unit_id'] : ($basePack->unit_id ?? null);
        foreach ([1, 2] as $n) {
            $unitRaw = $r["pack{$n}_unit"] ?? null;
            $has = $unitRaw !== null || isset($r["pack{$n}_qty"]) || isset($r["pack{$n}_price"]) || isset($r["pack{$n}_barcode"]);
            if (!$has) {
                continue;
            }
            if ($unitRaw === null || $unitRaw === '-') {
                $err("Pack {$n}: the unit is missing (e.g. Carton).");
                continue;
            }
            $unitId = $this->lookup('units', $unitRaw);
            if ($unitId === null && empty($opts['create_missing'])) {
                $err("Pack {$n}: unit \"{$unitRaw}\" doesn't exist.");
                continue;
            }
            if ($unitId !== null && $unitId === $baseUnit) {
                $err("Pack {$n} uses the same unit as the product itself – use a bigger unit like Carton.");
                continue;
            }
            $existing = $unitId ? $existingPacks->first(fn ($x) => !$x->is_base && (int) $x->unit_id === $unitId) : null;
            $pack = ['n' => $n, 'unit_id' => $unitId ?? ['new' => $unitRaw], 'unit_name' => $unitRaw, 'existing' => $existing];
            if ($unitId === null) {
                $res['new_names'][] = 'Unit "' . $unitRaw . '"';
            }
            if (isset($r["pack{$n}_qty"])) {
                $q = $this->number($r["pack{$n}_qty"]);
                if ($q === null || $q <= 0) {
                    $err("Pack {$n} contains: \"{$r["pack{$n}_qty"]}\" isn't a valid quantity.");
                    continue;
                }
                $pack['quantity_per_unit'] = $q;
            } elseif (!$existing) {
                $err("Pack {$n}: say how many it contains (Pack {$n} contains).");
                continue;
            }
            if (isset($r["pack{$n}_price"])) {
                if ($r["pack{$n}_price"] === '-') {
                    $pack['price'] = null;
                } else {
                    $m = $this->money($r["pack{$n}_price"]);
                    $m === null ? $err("Pack {$n} price \"{$r["pack{$n}_price"]}\" isn't a valid amount.") : $pack['price'] = $m;
                }
            }
            if (isset($r["pack{$n}_barcode"]) && $r["pack{$n}_barcode"] !== '-') {
                $code = preg_replace('/\s+/', '', $r["pack{$n}_barcode"]);
                $o = $this->barcodeOwner($code, $ctx);
                if ($o && !($existing && $o['type'] === 'pack' && $o['pack_id'] === $existing->id)) {
                    $err("Pack {$n} barcode {$code} already belongs to " . $this->ownerName($o, $ctx) . '.');
                } else {
                    $pack['barcode'] = $code;
                }
            }
            $res['packs'][] = $pack;
        }

        // ---------------- stock
        $add = isset($r['receive_qty']) && $r['receive_qty'] !== '-' ? $this->number($r['receive_qty']) : null;
        $set = isset($r['count_qty']) && $r['count_qty'] !== '-' ? $this->number($r['count_qty']) : null;
        if (isset($r['receive_qty']) && $r['receive_qty'] !== '-' && ($add === null || $add < 0)) {
            $err("Add stock \"{$r['receive_qty']}\" isn't a valid quantity.");
        }
        if (isset($r['count_qty']) && $r['count_qty'] !== '-' && ($set === null || $set < 0)) {
            $err("Stock count \"{$r['count_qty']}\" isn't a valid quantity.");
        }
        if ($add && $set !== null) {
            $err('Use either "Add stock" or "Stock count" on a row, not both.');
        }
        $tracks = array_key_exists('track_stock', $d) ? $d['track_stock'] : ($p ? $p->track_stock !== false : true);
        $variable = $p && $p->product_type === 'variable';
        if (($add || $set !== null) && (!$tracks || $variable)) {
            $res['warnings'][] = $variable ? 'Stock for products with variations is changed per variation – the stock columns were ignored.' : 'This product doesn\'t track stock – the stock columns were ignored.';
        } else {
            if ($add) {
                $res['stock']['add'] = $add;
            }
            if ($set !== null) {
                $res['stock']['set'] = $set;
            }
        }

        // ---------------- head-office products at a branch
        if ($governed) {
            $allowed = array_intersect_key($d, array_flip(['reorder_level', 'is_active']));
            $blocked = array_diff_key($d, $allowed);
            $changedBlocked = array_filter($blocked, fn ($v, $k) => $this->differs($p, $k, $v), ARRAY_FILTER_USE_BOTH);
            if ($changedBlocked || $res['packs'] || ($res['base_unit_id'] && $res['base_unit_id'] !== ($basePack->unit_id ?? null))) {
                $res['warnings'][] = 'Head-office product: only Reorder level, Active and stock can be changed here – other changes were ignored (ask head office, or request a price change).';
            }
            $d = $allowed;
            $res['packs'] = [];
            $res['base_unit_id'] = null;
        }

        if ($creating) {
            if (empty($d['title'])) {
                $err('Product name is missing.');
            }
            if (!array_key_exists('price', $d)) {
                $err('Selling price is missing.');
            }
        }
        $res['data'] = $d;

        if ($res['errors']) {
            $res['action'] = 'error';
            return $res;
        }

        if ($creating) {
            if (isset($opts['create_new']) && !$opts['create_new']) {
                $res['action'] = 'skip';
                $res['warnings'][] = 'New product skipped ("Add new products" is turned off).';
                return $res;
            }
            $res['action'] = 'create';
            return $res;
        }

        // ---------------- what changes?
        foreach ($d as $k => $v) {
            if ($this->differs($p, $k, $v)) {
                $res['changes'][$this->label($this->fieldKey($k))] = [$this->display($p, $k, null, true), $this->display($p, $k, $v)];
            }
        }
        if (is_int($res['base_unit_id']) && $basePack && $res['base_unit_id'] !== (int) $basePack->unit_id) {
            $res['changes']['Unit'] = [$this->unitName((int) $basePack->unit_id), $this->unitName($res['base_unit_id'])];
        } elseif (is_array($res['base_unit_id']) || ($res['base_unit_id'] && !$basePack)) {
            $res['changes']['Unit'] = [$basePack ? $this->unitName((int) $basePack->unit_id) : '—', is_array($res['base_unit_id']) ? $res['base_unit_id']['new'] : $this->unitName($res['base_unit_id'])];
        }
        foreach ($res['packs'] as $pk) {
            $e = $pk['existing'];
            $label = 'Pack "' . $pk['unit_name'] . '"';
            if (!$e) {
                $res['changes'][$label] = ['—', 'new: ' . $this->qtyText($pk['quantity_per_unit'] ?? 0) . ' for ' . (isset($pk['price']) && $pk['price'] !== null ? money($pk['price']) : 'auto price')];
                continue;
            }
            if (isset($pk['quantity_per_unit']) && abs((float) $e->quantity_per_unit - $pk['quantity_per_unit']) > 0.0001) {
                $res['changes'][$label . ' contains'] = [$this->qtyText($e->quantity_per_unit), $this->qtyText($pk['quantity_per_unit'])];
            }
            if (array_key_exists('price', $pk) && $this->moneyDiff($e->price, $pk['price'])) {
                $res['changes'][$label . ' price'] = [$e->price === null ? '—' : money($e->price), $pk['price'] === null ? '—' : money($pk['price'])];
            }
            if (isset($pk['barcode']) && (string) $e->barcode !== $pk['barcode']) {
                $res['changes'][$label . ' barcode'] = [$e->barcode ?: '—', $pk['barcode']];
            }
        }

        if (!$res['changes']) {
            $res['action'] = 'unchanged';
            return $res;
        }
        if (isset($opts['update_existing']) && !$opts['update_existing']) {
            $res['action'] = 'skip';
            $res['warnings'][] = 'Changes skipped ("Update existing products" is turned off).';
            return $res;
        }
        $res['action'] = 'update';
        return $res;
    }

    // =================================================================== writing

    protected function create(array $plan): Product
    {
        $d = $plan['data'];
        $weighted = (bool) ($d['is_weighted'] ?? false);
        $unitId = $this->resolveNew('units', $plan['base_unit_id'])
            ?? $this->lookup('units', $weighted ? 'Kilogram' : 'Piece')
            ?? $this->resolveNew('units', ['new' => $weighted ? 'Kilogram' : 'Piece']);

        $input = [
            'title' => $d['title'],
            'sku' => $d['sku'] ?? null,
            'barcode' => $d['barcode'] ?? null,
            'price' => $d['price'],
            'cost_price' => $d['cost_price'] ?? null,
            'wholesale_price' => $d['wholesale_price'] ?? null,
            'promo_price' => $d['promo_price'] ?? null,
            'promo_starts_at' => $d['promo_starts_at'] ?? null,
            'promo_ends_at' => $d['promo_ends_at'] ?? null,
            'reorder_level' => $d['reorder_level'] ?? 10,
            'category_id' => $this->resolveNew('categories', $d['category_id'] ?? null),
            'brand_id' => $this->resolveNew('brands', $d['brand_id'] ?? null),
            'description' => $d['description'] ?? null,
            'plu_code' => $d['plu_code'] ?? null,
            'product_type' => 'simple',
            'base_unit_id' => $unitId,
            'is_weighted' => $weighted ? '1' : '0',
            'track_stock' => ($d['track_stock'] ?? true) ? '1' : '0',
            'allow_price_override' => ($d['allow_price_override'] ?? false) ? '1' : '0',
            'is_active' => ($d['is_active'] ?? true) ? '1' : '0',
            'packs' => [],
        ];
        foreach ($plan['packs'] as $pk) {
            $input['packs'][] = [
                'unit_id' => $this->resolveNew('units', $pk['unit_id']),
                'quantity_per_unit' => $pk['quantity_per_unit'],
                'price' => $pk['price'] ?? null,
                'barcode' => $pk['barcode'] ?? null,
            ];
        }
        return $this->products->save(Request::create('/', 'POST', array_filter($input, fn ($v) => $v !== null)));
    }

    protected function update(array $plan): Product
    {
        /** @var Product $p */
        $p = $plan['product'];
        DB::transaction(function () use ($p, $plan) {
            $d = $plan['data'];
            foreach (['category_id' => 'categories', 'brand_id' => 'brands'] as $f => $t) {
                if (array_key_exists($f, $d)) {
                    $d[$f] = $this->resolveNew($t, $d[$f]);
                }
            }
            $changes = array_filter($d, fn ($v, $k) => $this->differs($p, $k, $v), ARRAY_FILTER_USE_BOTH);
            if ($changes) {
                $before = $p->only(ProductService::PRICE_FIELDS);
                $p->fill($changes)->save();
                $this->pricing->recordDiff($p, $before, 'import', 'Spreadsheet upload');
            }
            if ($plan['governed']) {
                foreach (array_intersect(array_keys($changes), ['reorder_level', 'is_active']) as $f) {
                    if (Schema::hasTable('product_local_overrides')) {
                        DB::table('product_local_overrides')->insertOrIgnore(['product_id' => $p->id, 'field' => $f, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }

            // base unit
            if ($plan['base_unit_id']) {
                $unitId = $this->resolveNew('units', $plan['base_unit_id']);
                $base = ProductUnit::where('product_id', $p->id)->where('is_base', true)->first();
                if (!$base) {
                    ProductUnit::create(['product_id' => $p->id, 'unit_id' => $unitId, 'quantity_per_unit' => 1, 'is_base' => true, 'is_sellable' => true, 'sort_order' => 0]);
                } elseif ((int) $base->unit_id !== $unitId) {
                    $base->update(['unit_id' => $unitId]);
                }
            }

            // packs: matched by unit, never deleted
            foreach ($plan['packs'] as $pk) {
                $unitId = $this->resolveNew('units', $pk['unit_id']);
                $pack = ProductUnit::where('product_id', $p->id)->where('is_base', false)->where('unit_id', $unitId)->first();
                if ($pack) {
                    $before = $pack->only(['price', 'wholesale_price', 'cost_price']);
                    $fill = [];
                    if (isset($pk['quantity_per_unit'])) {
                        $fill['quantity_per_unit'] = $pk['quantity_per_unit'];
                    }
                    if (array_key_exists('price', $pk)) {
                        $fill['price'] = $pk['price'];
                    }
                    if (isset($pk['barcode'])) {
                        $fill['barcode'] = $pk['barcode'];
                    }
                    $pack->fill($fill)->save();
                    $this->pricing->recordDiff($pack, $before, 'import', 'Spreadsheet upload');
                } else {
                    $pack = ProductUnit::create([
                        'product_id' => $p->id, 'unit_id' => $unitId, 'quantity_per_unit' => $pk['quantity_per_unit'],
                        'price' => $pk['price'] ?? null, 'barcode' => $pk['barcode'] ?? null, 'is_base' => false, 'is_sellable' => true,
                        'sort_order' => (int) ProductUnit::where('product_id', $p->id)->max('sort_order') + 1,
                    ]);
                    if ($pack->price) {
                        $this->pricing->recordHistory($pack, 'price', null, (float) $pack->price, 'import', 'Spreadsheet upload');
                    }
                }
            }
        });
        return $p->fresh();
    }

    // =================================================================== helpers

    protected function readRows(ProductImport $imp, int $limit): array
    {
        $h = fopen($imp->rowsFile(), 'r');
        if (!$h) {
            throw new \RuntimeException('The uploaded rows are missing – please upload the file again.');
        }
        fseek($h, (int) $imp->cursor);
        $rows = [];
        while (count($rows) < $limit && ($line = fgets($h)) !== false) {
            $row = json_decode($line, true);
            if ($row) {
                $rows[] = $row;
            }
        }
        $cursor = ftell($h);
        $eof = fgets($h) === false;
        fclose($h);
        return [$rows, $cursor, $eof];
    }

    /** Everything the rows of one batch need, fetched with a handful of queries. */
    protected function context(array $rows): array
    {
        $ids = $skus = $barcodes = $plus = $titles = [];
        foreach ($rows as [, $r]) {
            if (!empty($r['id']) && ctype_digit((string) $r['id'])) {
                $ids[] = (int) $r['id'];
            }
            if (!empty($r['sku']) && $r['sku'] !== '-') {
                $skus[] = strtoupper($r['sku']);
            }
            foreach (['barcode', 'pack1_barcode', 'pack2_barcode'] as $k) {
                if (!empty($r[$k]) && $r[$k] !== '-') {
                    $barcodes[] = preg_replace('/\s+/', '', $r[$k]);
                }
            }
            if (!empty($r['plu_code']) && $r['plu_code'] !== '-') {
                $plus[] = $r['plu_code'];
            }
            if (empty($r['id']) && empty($r['sku']) && empty($r['barcode']) && !empty($r['title'])) {
                $titles[] = trim($r['title']);
            }
        }
        $byTitle = [];
        if ($titles) {
            foreach (Product::whereIn('title', array_unique($titles))->get() as $p) {
                $byTitle[mb_strtolower(trim($p->title))][] = $p;
            }
        }
        $byId = $ids ? Product::whereIn('id', array_unique($ids))->get()->keyBy('id')->all() : [];
        $bySku = [];
        if ($skus) {
            foreach (Product::whereIn('sku', array_unique($skus))->get() as $p) {
                $bySku[strtoupper((string) $p->sku)] = $p;
            }
        }
        $byBarcode = $barcodes ? Product::whereIn('barcode', array_unique($barcodes))->get()->keyBy('barcode')->all() : [];
        $byPlu = $plus ? Product::whereIn('plu_code', array_unique($plus))->get()->keyBy('plu_code')->all() : [];

        $owners = [];
        if ($barcodes) {
            $codes = array_values(array_unique($barcodes));
            foreach (DB::table('products')->whereIn('barcode', $codes)->get(['id', 'barcode', 'title']) as $x) {
                $owners[$x->barcode] = ['type' => 'product', 'product_id' => (int) $x->id, 'pack_id' => null, 'title' => $x->title];
            }
            foreach (DB::table('product_unit')->whereIn('barcode', $codes)->get(['id', 'product_id', 'barcode']) as $x) {
                $owners[$x->barcode] ??= ['type' => 'pack', 'product_id' => (int) $x->product_id, 'pack_id' => (int) $x->id];
            }
            if (Schema::hasTable('product_variations')) {
                foreach (DB::table('product_variations')->whereIn('barcode', $codes)->get(['id', 'product_id', 'barcode']) as $x) {
                    $owners[$x->barcode] ??= ['type' => 'variation', 'product_id' => (int) $x->product_id, 'pack_id' => null];
                }
            }
            if (Schema::hasTable('product_barcodes')) {
                foreach (DB::table('product_barcodes')->whereIn('barcode', $codes)->get(['product_id', 'product_unit_id', 'product_variation_id', 'barcode', 'kind']) as $x) {
                    $owners[$x->barcode] ??= ['type' => $x->product_unit_id ? 'pack' : ($x->product_variation_id ? 'variation' : ($x->kind === 'primary' ? 'product' : 'extra')),
                        'product_id' => (int) $x->product_id, 'pack_id' => $x->product_unit_id ? (int) $x->product_unit_id : null];
                }
            }
        }

        $matched = array_merge(array_keys($byId), array_map(fn ($p) => $p->id, $bySku), array_map(fn ($p) => $p->id, $byBarcode),
            collect($byTitle)->flatten()->pluck('id')->all());
        $packs = $matched ? ProductUnit::whereIn('product_id', array_unique($matched))->get()->groupBy('product_id')->all() : [];
        $packs = array_map(fn ($c) => collect($c), $packs);

        return compact('byId', 'bySku', 'byBarcode', 'byPlu', 'byTitle', 'owners', 'packs') + ['created' => []];
    }

    protected function barcodeOwner(string $code, array $ctx): ?array
    {
        return $ctx['owners'][$code] ?? null;
    }

    protected function ownerName(array $o, array $ctx): string
    {
        $title = $o['title'] ?? (Product::whereKey($o['product_id'])->value('title') ?? 'another product');
        return '"' . $title . '"' . ($o['type'] === 'pack' ? ' (a pack)' : ($o['type'] === 'variation' ? ' (a variation)' : ''));
    }

    protected function loadMaps(): void
    {
        $this->maps = ['categories' => [], 'brands' => [], 'units' => []];
        foreach (DB::table('categories')->get(['id', 'name']) as $c) {
            $this->maps['categories'][mb_strtolower(trim($c->name))] ??= (int) $c->id;
            $this->maps['categories']['#' . $c->id] = (int) $c->id;
        }
        if (Schema::hasTable('brands')) {
            foreach (DB::table('brands')->get(['id', 'name']) as $b) {
                $this->maps['brands'][mb_strtolower(trim($b->name))] ??= (int) $b->id;
                $this->maps['brands']['#' . $b->id] = (int) $b->id;
            }
        }
        foreach (DB::table('units')->get(['id', 'name', 'short_name']) as $u) {
            $this->maps['units'][mb_strtolower(trim($u->name))] ??= (int) $u->id;
            if (!empty($u->short_name)) {
                $this->maps['units'][mb_strtolower(trim($u->short_name))] ??= (int) $u->id;
            }
        }
        // common spellings
        foreach (['kg' => 'kilogram', 'kgs' => 'kilogram', 'kilo' => 'kilogram', 'pcs' => 'piece', 'pc' => 'piece', 'pieces' => 'piece', 'ltr' => 'litre', 'l' => 'litre', 'liter' => 'litre', 'ctn' => 'carton', 'cartons' => 'carton'] as $a => $full) {
            if (!isset($this->maps['units'][$a]) && isset($this->maps['units'][$full])) {
                $this->maps['units'][$a] = $this->maps['units'][$full];
            }
        }
    }

    protected function lookup(string $table, string $name): ?int
    {
        $n = mb_strtolower(trim($name));
        if (isset($this->maps[$table][$n])) {
            return $this->maps[$table][$n];
        }
        if (ctype_digit($n) && isset($this->maps[$table]['#' . $n])) {
            return $this->maps[$table]['#' . $n];
        }
        return null;
    }

    /** An id, or ['new' => name] → creates it once, then returns its id. */
    protected function resolveNew(string $table, $value): ?int
    {
        if ($value === null || is_int($value)) {
            return $value;
        }
        $name = trim((string) ($value['new'] ?? ''));
        if ($name === '') {
            return null;
        }
        if ($id = $this->lookup($table, $name)) {
            return $id;
        }
        $model = match ($table) {
            'categories' => Category::create(['name' => $name]),
            'brands' => Brand::create(['name' => $name]),
            'units' => Unit::create(['name' => $name, 'short_name' => mb_strtolower(mb_substr(preg_replace('/[^A-Za-z]/', '', $name) ?: $name, 0, 4)), 'is_active' => true, 'is_default' => false]),
        };
        $this->maps[$table][mb_strtolower($name)] = (int) $model->id;
        return (int) $model->id;
    }

    protected function differs(Product $p, string $k, $v): bool
    {
        $cur = $p->getRawOriginal($k);
        return match (true) {
            in_array($k, ['price', 'cost_price', 'wholesale_price', 'promo_price'], true) => $this->moneyDiff($cur, $v),
            in_array($k, ['promo_starts_at', 'promo_ends_at'], true) => ($cur ? substr((string) $cur, 0, 10) : null) !== $v,
            in_array($k, ['is_weighted', 'allow_price_override'], true) => (bool) $cur !== (bool) $v,
            in_array($k, ['track_stock', 'is_active'], true) => ($cur === null ? true : (bool) $cur) !== (bool) $v,
            $k === 'reorder_level' => $cur === null || abs((float) $cur - (float) $v) > 0.0001,
            in_array($k, ['category_id', 'brand_id'], true) => is_array($v) || ($cur === null ? null : (int) $cur) !== $v,
            default => trim((string) $cur) !== trim((string) $v),
        };
    }

    protected function moneyDiff($a, $b): bool
    {
        $a = $a === null || $a === '' ? null : round((float) $a, 2);
        $b = $b === null || $b === '' ? null : round((float) $b, 2);
        return $a !== $b;
    }

    protected function display(Product $p, string $k, $v, bool $old = false): string
    {
        $val = $old ? $p->getRawOriginal($k) : $v;
        if ($val === null || $val === '') {
            return '—';
        }
        return match (true) {
            in_array($k, ['price', 'cost_price', 'wholesale_price', 'promo_price'], true) => money($val),
            in_array($k, ['promo_starts_at', 'promo_ends_at'], true) => substr((string) $val, 0, 10),
            in_array($k, ['is_weighted', 'allow_price_override', 'track_stock', 'is_active'], true) => $val ? 'Yes' : 'No',
            $k === 'reorder_level' => $this->qtyText($val),
            $k === 'category_id' => is_array($val) ? $val['new'] . ' (new)' : (string) (DB::table('categories')->where('id', $val)->value('name') ?? '#' . $val),
            $k === 'brand_id' => is_array($val) ? $val['new'] . ' (new)' : (string) (DB::table('brands')->where('id', $val)->value('name') ?? '#' . $val),
            $k === 'description' => mb_strimwidth(strip_tags((string) $val), 0, 60, '…'),
            default => (string) $val,
        };
    }

    protected function fieldKey(string $k): string
    {
        return ['category_id' => 'category', 'brand_id' => 'brand'][$k] ?? $k;
    }

    protected function label(string $key): string
    {
        $c = ProductSpreadsheet::columns()[$key] ?? null;
        return $c ? trim(str_replace(['*', '(info)', '(don\'t change)'], '', $c[0])) : ucfirst(str_replace('_', ' ', $key));
    }

    protected function unitName(int $id): string
    {
        return (string) (DB::table('units')->where('id', $id)->value('name') ?? '#' . $id);
    }

    protected function money(string $v): ?float
    {
        $s = preg_replace('/[^\d.,\-]/u', '', $v);
        if ($s === '' || $s === '-') {
            return null;
        }
        // 1.234,50 (European) → 1234.50 ; 1,234.50 → 1234.50
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d{1,2}$/', $s)) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        } else {
            $s = str_replace(',', '', $s);
        }
        if (!is_numeric($s) || (float) $s < 0) {
            return null;
        }
        return round((float) $s, 2);
    }

    protected function number(string $v): ?float
    {
        $s = str_replace([',', ' '], '', trim($v));
        return is_numeric($s) ? round((float) $s, 3) : null;
    }

    protected function bool(string $v): ?bool
    {
        $v = mb_strtolower(trim($v));
        return match (true) {
            in_array($v, ['yes', 'y', 'true', '1', 'on', 'active', 'ok', 'x', '✓'], true) => true,
            in_array($v, ['no', 'n', 'false', '0', 'off', 'inactive'], true) => false,
            default => null,
        };
    }

    protected function qtyText($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.');
    }

    protected function stockText(array $stock): string
    {
        return isset($stock['set']) ? 'count → ' . $this->qtyText($stock['set']) : '+' . $this->qtyText($stock['add']) . ' received';
    }

    protected function emptyCounts(): array
    {
        return ['create' => 0, 'update' => 0, 'unchanged' => 0, 'skip' => 0, 'error' => 0, 'warning' => 0, 'stock' => 0, 'new_names' => []];
    }

    protected function tally(array &$counts, array $plan): void
    {
        $counts[$plan['action']] = ($counts[$plan['action']] ?? 0) + 1;
        if ($plan['stock'] && $plan['action'] !== 'error') {
            $counts['stock']++;
        }
        foreach ($plan['new_names'] as $n) {
            if (count($counts['new_names'] ?? []) < 60 && !in_array($n, $counts['new_names'] ?? [], true)) {
                $counts['new_names'][] = $n;
            }
        }
    }

    protected function collectIssues(array &$issues, $file, int $rowNum, array $r, array $plan, array &$counts): void
    {
        $title = $r['title'] ?? ($plan['product']->title ?? ($r['sku'] ?? ($r['barcode'] ?? '')));
        foreach ($plan['errors'] as $m) {
            $line = ['row' => $rowNum, 'level' => 'error', 'title' => $title, 'message' => $m];
            fwrite($file, json_encode($line, JSON_UNESCAPED_UNICODE) . "\n");
            if (count($issues) < self::MAX_ISSUES) {
                $issues[] = $line;
            }
        }
        foreach ($plan['warnings'] as $m) {
            $counts['warning'] = ($counts['warning'] ?? 0) + 1;
            $line = ['row' => $rowNum, 'level' => 'warning', 'title' => $title, 'message' => $m];
            fwrite($file, json_encode($line, JSON_UNESCAPED_UNICODE) . "\n");
            if (count($issues) < self::MAX_ISSUES) {
                $issues[] = $line;
            }
        }
    }

    /** All problems of the check (or of the apply, once it has run) as CSV. */
    public function issuesCsv(ProductImport $imp): string
    {
        $file = $imp->dir() . '/issues-' . (in_array($imp->status, ['applying', 'done'], true) ? 'apply' : 'check') . '.jsonl';
        $h = fopen('php://temp', 'r+');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, ['Row', 'Type', 'Product', 'Problem']);
        if (is_file($file)) {
            $in = fopen($file, 'r');
            while (($line = fgets($in)) !== false) {
                $x = json_decode($line, true);
                fputcsv($h, [$x['row'], $x['level'] === 'error' ? 'Error – row not saved' : 'Warning', $x['title'], $x['message']]);
            }
            fclose($in);
        }
        rewind($h);
        $csv = stream_get_contents($h);
        fclose($h);
        return $csv;
    }

    public function discard(ProductImport $imp): void
    {
        File::deleteDirectory($imp->dir());
    }
}
