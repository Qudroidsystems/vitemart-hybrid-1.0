<?php

namespace App\Http\Controllers;

use App\Models\ProductImport;
use App\Services\AuditLogger;
use App\Services\Catalogue\ProductImporter;
use App\Services\Catalogue\ProductSpreadsheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Products ⇄ Excel: download the catalogue (or a blank template), edit it, upload it back.
 * Uploads are checked first (preview), then applied in steps so large files don't time out.
 */
class ProductSpreadsheetController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:View product|Create product|Update product|Import products', ['only' => ['index', 'export', 'template']]);
        $this->middleware('permission:Import products', ['except' => ['index', 'export', 'template']]);
    }

    public function index()
    {
        $pagetitle = 'Import / Export Products';
        $categories = DB::table('categories')->orderBy('name')->get(['id', 'name']);
        $counts = DB::table('products')->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')->first();
        $recent = ProductImport::with('user:id,first_name,last_name')->latest()->limit(8)->get();
        $canImport = auth()->user()->can('Import products');
        return view('products.spreadsheet', compact('pagetitle', 'categories', 'counts', 'recent', 'canImport'));
    }

    public function export(Request $request, ProductSpreadsheet $sheet)
    {
        $filters = $request->validate([
            'status' => 'nullable|in:all,active,inactive',
            'category_id' => 'nullable|integer',
            'format' => 'nullable|in:xlsx,csv',
        ]);
        $name = 'products-' . now()->format('Y-m-d-His');
        AuditLogger::log('exported', 'Products downloaded as ' . strtoupper($filters['format'] ?? 'xlsx'), 'products');

        if (($filters['format'] ?? 'xlsx') === 'csv') {
            $cols = ProductSpreadsheet::columns();
            return response()->streamDownload(function () use ($sheet, $filters, $cols) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, array_map(fn ($c) => $c[0], $cols));
                foreach ($sheet->productRows($filters) as $row) {
                    fputcsv($out, array_map(function ($k) use ($row) {
                        $v = $row[$k] ?? '';
                        return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
                    }, array_keys($cols)));
                }
                fclose($out);
            }, $name . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $path = tempnam(sys_get_temp_dir(), 'vmx') . '.xlsx';
        $sheet->export($path, $filters);
        return response()->download($path, $name . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function template(ProductSpreadsheet $sheet)
    {
        $path = tempnam(sys_get_temp_dir(), 'vmt') . '.xlsx';
        $sheet->export($path, ['template' => true]);
        return response()->download($path, 'product-upload-template.xlsx')->deleteFileAfterSend(true);
    }

    /** Step 1: upload → staged and checking. */
    public function upload(Request $request, ProductImporter $importer)
    {
        $request->validate([
            'file' => 'required|file|max:51200',
        ], ['file.max' => 'The file is bigger than 50 MB – split it into smaller files.']);
        $options = [
            'create_new' => $request->boolean('create_new', true),
            'update_existing' => $request->boolean('update_existing', true),
            'create_missing' => $request->boolean('create_missing', true),
        ];
        try {
            $imp = $importer->stage($request->file('file'), $options, $request->user()->id);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        return response()->json(['success' => true, 'import' => $this->state($imp)]);
    }

    /** Steps 2 and 3: the page calls this repeatedly until the check or the save is finished. */
    public function step(ProductImport $import, ProductImporter $importer)
    {
        $lock = Cache::lock('product-import-' . $import->id, 120);
        if (!$lock->get()) {
            return response()->json(['success' => true, 'import' => $this->state($import->fresh()), 'busy' => true]);
        }
        try {
            $start = microtime(true);
            do {
                $import = $import->status === 'checking' ? $importer->checkStep($import) : $importer->applyStep($import);
            } while (in_array($import->status, ['checking', 'applying'], true) && microtime(true) - $start < 8);
        } catch (\Throwable $e) {
            report($e);
            $import->update(['status' => 'failed', 'error' => $e->getMessage()]);
        } finally {
            $lock->release();
        }
        return response()->json(['success' => true, 'import' => $this->state($import->fresh())]);
    }

    public function apply(ProductImport $import, ProductImporter $importer)
    {
        if ($import->status !== 'checked') {
            return response()->json(['success' => false, 'message' => 'This upload has already been ' . ($import->status === 'done' ? 'saved' : $import->status) . '.'], 422);
        }
        if (($import->counts['create'] ?? 0) + ($import->counts['update'] ?? 0) + ($import->counts['stock'] ?? 0) === 0) {
            return response()->json(['success' => false, 'message' => 'Nothing to save – every row is unchanged or has a problem.'], 422);
        }
        return $this->step($import, $importer);
    }

    public function show(ProductImport $import)
    {
        return response()->json(['success' => true, 'import' => $this->state($import)]);
    }

    public function issues(ProductImport $import, ProductImporter $importer)
    {
        $csv = $importer->issuesCsv($import);
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="problems-' . pathinfo($import->original_name, PATHINFO_FILENAME) . '.csv"',
        ]);
    }

    public function cancel(ProductImport $import, ProductImporter $importer)
    {
        if (in_array($import->status, ['checking', 'checked', 'failed'], true)) {
            $import->update(['status' => 'cancelled']);
            $importer->discard($import);
        }
        return response()->json(['success' => true]);
    }

    protected function state(ProductImport $i): array
    {
        $total = max(1, (int) $i->total_rows);
        $phase = $i->result['phase'] ?? null;
        $progress = match ($i->status) {
            'checking' => min(99, (int) floor($i->processed / $total * 100)),
            'applying' => $phase === 'rows' ? min(95, (int) floor($i->processed / $total * 95)) : 97,
            default => 100,
        };
        return [
            'id' => $i->id,
            'status' => $i->status,
            'name' => $i->original_name,
            'total' => (int) $i->total_rows,
            'processed' => (int) $i->processed,
            'progress' => $progress,
            'phase' => match (true) {
                $i->status === 'checking' => 'Checking row ' . number_format(min($i->processed, $i->total_rows)) . ' of ' . number_format($i->total_rows),
                $i->status === 'applying' && $phase === 'rows' => 'Saving row ' . number_format(min($i->processed, $i->total_rows)) . ' of ' . number_format($i->total_rows),
                $i->status === 'applying' => 'Recording stock changes…',
                default => null,
            },
            'counts' => $i->counts,
            'issues' => $i->issues ?? [],
            'preview' => $i->preview,
            'columns' => $i->columns,
            'result' => $i->result,
            'error' => $i->error,
            'seen_before' => $i->status === 'checked' ? ($i->options['seen_before'] ?? null) : null,
            'urls' => [
                'step' => route('products.spreadsheet.step', $i),
                'apply' => route('products.spreadsheet.apply', $i),
                'issues' => route('products.spreadsheet.issues', $i),
                'cancel' => route('products.spreadsheet.cancel', $i),
                'adjustment' => \Illuminate\Support\Facades\Route::has('adjustments.show') ? route('adjustments.show', '__ID__') : null,
            ],
        ];
    }
}
