<?php

namespace App\Http\Controllers\Catalogue;

use App\Exceptions\DuplicateBarcodeException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\FieldPolicy;
use App\Models\PriceChangeRequest;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductSubmission;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Services\AuditLogger;
use App\Services\Catalogue\BarcodeIndex;
use App\Services\Catalogue\CatalogueRequests;
use App\Services\Catalogue\PriceListResolver;
use App\Services\Catalogue\ProductPayload;
use App\Services\MultiBranch\Install;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Branch prices, requests between branches and head office, field rules and extra barcodes.
 */
class CatalogueController extends Controller
{
    public function __construct(protected CatalogueRequests $requests, protected PriceListResolver $prices)
    {
        $this->middleware('permission:Update product|Create product', ['only' => ['barcodes', 'addBarcode', 'removeBarcode']]);
        $this->middleware('permission:Submit product to head office', ['only' => ['submitProduct']]);
        $this->middleware('permission:Manage price lists', ['only' => ['storeList', 'updateList', 'setItem', 'removeItem']]);
        $this->middleware('permission:Approve price change|Approve product submission|Request price change|Manage price control', ['only' => ['requestsPage']]);
        $this->middleware('permission:Approve price change', ['only' => ['approvePrice', 'rejectPrice']]);
        $this->middleware('permission:Approve product submission', ['only' => ['approveSubmission', 'rejectSubmission', 'showSubmission']]);
        $this->middleware('permission:Request price change', ['only' => ['requestPrice', 'cancelRequest']]);
        $this->middleware('permission:Manage field policies', ['only' => ['updatePolicies']]);
        $this->middleware('permission:Manage price lists|Manage price control|View price history|Manage field policies', ['only' => ['lists', 'showList', 'policies']]);
    }

    // ------------------------------------------------------------------
    // Extra barcodes
    // ------------------------------------------------------------------

    public function barcodes(Product $product)
    {
        $product->load('packs.unit', 'variations');
        $rows = ProductBarcode::where('product_id', $product->id)->orderByRaw("kind = 'primary' DESC")->orderBy('id')->get()
            ->map(function ($b) use ($product) {
                $target = $product->title;
                if ($b->product_variation_id && ($v = $product->variations->firstWhere('id', $b->product_variation_id))) {
                    $target = $v->label;
                } elseif ($b->product_unit_id && ($u = $product->packs->firstWhere('id', $b->product_unit_id))) {
                    $target = ($u->unit->name ?? 'Pack') . ' of ' . (float) $u->quantity_per_unit . ($u->is_base ? ' (single)' : '');
                }
                return ['id' => $b->id, 'barcode' => $b->barcode, 'kind' => $b->kind, 'note' => $b->note, 'target' => $target];
            });
        return response()->json(['barcodes' => $rows]);
    }

    public function addBarcode(Request $request, Product $product)
    {
        $data = $request->validate([
            'barcode' => 'required|string|max:64',
            'kind' => 'nullable|in:pack,variation',
            'target_id' => 'nullable|integer',
            'note' => 'nullable|string|max:120',
        ]);
        $kind = $data['kind'] ?? null;
        $v = $kind === 'variation' ? ProductVariation::where('product_id', $product->id)->findOrFail($data['target_id']) : null;
        $u = $kind === 'pack' ? ProductUnit::where('product_id', $product->id)->findOrFail($data['target_id']) : null;
        try {
            $b = BarcodeIndex::addExtra($product, $data['barcode'], $v, $u, $data['note'] ?? null);
        } catch (DuplicateBarcodeException $e) {
            $owner = \App\Services\BarcodeGenerator::owner(trim($data['barcode']));
            $who = $owner ? ($owner['model']->title ?? $owner['model']->product?->title ?? 'another item') : 'another item';
            throw ValidationException::withMessages(['barcode' => "That barcode already rings up {$who}."]);
        }
        AuditLogger::log('barcode_added', "Added barcode {$b->barcode} to {$product->title}", 'products', $product);
        return response()->json(['success' => true, 'id' => $b->id], 201);
    }

    public function removeBarcode(Product $product, ProductBarcode $barcode)
    {
        abort_unless($barcode->product_id === $product->id, 404);
        if ($barcode->kind !== 'extra') {
            throw ValidationException::withMessages(['barcode' => 'Change the main barcode on the product form.']);
        }
        if (CatalogueRequests::governed($product) && $barcode->origin_branch_id !== Install::branchId()) {
            throw ValidationException::withMessages(['barcode' => 'Head office added this barcode.']);
        }
        $barcode->delete();
        AuditLogger::log('barcode_removed', "Removed barcode {$barcode->barcode} from {$product->title}", 'products', $product);
        return response()->json(['success' => true]);
    }

    // ------------------------------------------------------------------
    // Requests: branch → head office
    // ------------------------------------------------------------------

    public function requestsPage(Request $request)
    {
        $pagetitle = 'Requests to head office';
        $isCentral = Install::isCentral();
        if ($isCentral) {
            $pagetitle = 'Requests from branches';
        }
        $status = $request->get('status', 'pending');

        $priceQ = PriceChangeRequest::with('branch:id,code,name', 'product:id,title,price,cost_price')->latest();
        $subQ = ProductSubmission::with('branch:id,code,name')->latest();
        if ($status !== 'all') {
            $priceQ->where('status', $status);
            $subQ->where('status', $status);
        }
        if ($request->filled('branch_id')) {
            $priceQ->where('branch_id', $request->branch_id);
            $subQ->where('branch_id', $request->branch_id);
        }
        $priceRequests = $priceQ->limit(300)->get();
        $submissions = $subQ->limit(200)->get();

        $counts = [
            'price' => PriceChangeRequest::where('status', 'pending')->count(),
            'products' => ProductSubmission::where('status', 'pending')->count(),
        ];
        $branches = $isCentral ? Branch::orderBy('number')->get(['id', 'code', 'name']) : collect();
        $lastPush = \App\Models\SystemSetting::get('last_hq_contact_at');
        $unsent = Install::isBranch() ? PriceChangeRequest::where('sent', false)->where('status', 'pending')->count()
            + ProductSubmission::where('sent', false)->where('status', 'pending')->count() : 0;

        return view('catalogue.requests', compact('pagetitle', 'isCentral', 'status', 'priceRequests', 'submissions', 'counts', 'branches', 'lastPush', 'unsent'));
    }

    public function requestPrice(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'variation_id' => 'nullable|integer',
            'pack_id' => 'nullable|integer',
            'field' => ['required', Rule::in(array_keys(PriceChangeRequest::FIELDS))],
            'value' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:255',
            'promo_starts_at' => 'nullable|date',
            'promo_ends_at' => 'nullable|date|after_or_equal:promo_starts_at',
        ]);
        $product = Product::findOrFail($data['product_id']);
        if (!CatalogueRequests::governed($product)) {
            throw ValidationException::withMessages(['product_id' => 'Only head-office products need a request – change this price directly.']);
        }
        $v = !empty($data['variation_id']) ? ProductVariation::where('product_id', $product->id)->findOrFail($data['variation_id']) : null;
        $u = !empty($data['pack_id']) ? ProductUnit::where('product_id', $product->id)->findOrFail($data['pack_id']) : null;
        $r = $this->requests->request($product, $v, $u, $data['field'], round((float) $data['value'], 2), $data['reason'] ?? null,
            $data['promo_starts_at'] ?? null, $data['promo_ends_at'] ?? null);
        $this->requests->pushQuietly();
        return response()->json(['success' => true, 'message' => $r->fresh()->sent ? 'Sent to head office.' : 'Saved – it will be sent when head office is reachable.']);
    }

    public function cancelRequest(PriceChangeRequest $priceRequest)
    {
        if ($priceRequest->status !== 'pending' || $priceRequest->sent) {
            throw ValidationException::withMessages(['request' => 'Already with head office – ask them to reject it.']);
        }
        $priceRequest->update(['status' => 'cancelled']);
        return response()->json(['success' => true]);
    }

    public function approvePrice(Request $request, PriceChangeRequest $priceRequest)
    {
        abort_unless(Install::isCentral() || Install::isStandalone(), 403);
        $data = $request->validate([
            'value' => 'nullable|numeric|min:0',
            'scope' => 'required|in:branch,all',
            'note' => 'nullable|string|max:255',
        ]);
        $this->requests->approvePrice($priceRequest, isset($data['value']) ? round((float) $data['value'], 2) : null, $data['scope'], $data['note'] ?? null);
        return response()->json(['success' => true, 'message' => 'Approved. The branch picks it up within a minute.']);
    }

    public function rejectPrice(Request $request, PriceChangeRequest $priceRequest)
    {
        abort_unless(Install::isCentral() || Install::isStandalone(), 403);
        $data = $request->validate(['note' => 'nullable|string|max:255']);
        $this->requests->rejectPrice($priceRequest, $data['note'] ?? null);
        return response()->json(['success' => true, 'message' => 'Rejected.']);
    }

    public function submitProduct(Request $request, Product $product)
    {
        $data = $request->validate(['note' => 'nullable|string|max:255']);
        $s = $this->requests->submit($product, $data['note'] ?? null);
        return response()->json(['success' => true, 'message' => $s->fresh()->sent
            ? 'Sent to head office for review.' : 'Saved – it will be sent when head office is reachable.']);
    }

    public function showSubmission(ProductSubmission $submission)
    {
        return response()->json([
            'submission' => $submission->only(['id', 'uid', 'title', 'barcode', 'status', 'note', 'submitted_by_name', 'decision_note']),
            'payload' => $submission->payload,
            'conflicts' => ProductPayload::conflicts($submission->payload),
            'branch' => $submission->branch?->only(['code', 'name']),
        ]);
    }

    public function approveSubmission(Request $request, ProductSubmission $submission)
    {
        abort_unless(Install::isCentral(), 403);
        $data = $request->validate(['note' => 'nullable|string|max:255']);
        $product = $this->requests->approveSubmission($submission, $data['note'] ?? null);
        return response()->json(['success' => true, 'message' => "{$product->title} is now in the catalogue.", 'product_id' => $product->id]);
    }

    public function rejectSubmission(Request $request, ProductSubmission $submission)
    {
        abort_unless(Install::isCentral(), 403);
        $data = $request->validate(['note' => 'nullable|string|max:255']);
        $this->requests->rejectSubmission($submission, $data['note'] ?? null);
        return response()->json(['success' => true, 'message' => 'Rejected – the branch keeps it as a local product.']);
    }

    // ------------------------------------------------------------------
    // Price lists
    // ------------------------------------------------------------------

    public function lists()
    {
        $pagetitle = 'Branch prices';
        $lists = PriceList::with('branch:id,code,name')->withCount('items')->orderByRaw('branch_id IS NULL')->orderByDesc('priority')->get();
        $branches = Branch::orderBy('number')->get(['id', 'code', 'name']);
        $canManage = auth()->user()->can('Manage price lists') && !Install::isBranch();
        return view('catalogue.price-lists', compact('pagetitle', 'lists', 'branches', 'canManage'));
    }

    public function showList(Request $request, PriceList $priceList)
    {
        $pagetitle = $priceList->name;
        $q = $priceList->items()->with('product:id,title,sku,barcode,price,wholesale_price,cost_price', 'variation:id,name,price,attributes', 'pack.unit');
        if ($s = trim((string) $request->get('q'))) {
            $q->whereHas('product', fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('sku', 'like', "{$s}%")->orWhere('barcode', $s));
        }
        $items = $q->orderByDesc('updated_at')->paginate(50)->withQueryString();
        $canManage = auth()->user()->can('Manage price lists') && !Install::isBranch();
        return view('catalogue.price-list-show', compact('pagetitle', 'priceList', 'items', 'canManage'));
    }

    public function storeList(Request $request)
    {
        abort_if(Install::isBranch(), 403, 'Price lists are managed at head office.');
        $data = $request->validate($this->listRules());
        $data['code'] = strtoupper($data['code']);
        $list = PriceList::create($data + ['owner' => 'central']);
        return redirect()->route('catalogue.price-lists.show', $list)->with('success', 'Price list created.');
    }

    public function updateList(Request $request, PriceList $priceList)
    {
        abort_if(Install::isBranch(), 403);
        $data = $request->validate($this->listRules($priceList));
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');
        $priceList->update($data);
        return back()->with('success', 'Saved.');
    }

    protected function listRules(?PriceList $list = null): array
    {
        return [
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('price_lists', 'code')->ignore($list?->id)],
            'name' => 'required|string|max:120',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'priority' => 'required|integer|min:0|max:1000',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'notes' => 'nullable|string|max:255',
        ];
    }

    public function setItem(Request $request, PriceList $priceList)
    {
        abort_if(Install::isBranch(), 403);
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'variation_id' => 'nullable|integer',
            'pack_id' => 'nullable|integer',
            'price' => 'nullable|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'promo_price' => 'nullable|numeric|min:0',
            'promo_starts_at' => 'nullable|date',
            'promo_ends_at' => 'nullable|date|after_or_equal:promo_starts_at',
        ]);
        if (!isset($data['price']) && !isset($data['wholesale_price']) && !isset($data['promo_price'])) {
            throw ValidationException::withMessages(['price' => 'Enter at least one price.']);
        }
        $product = Product::findOrFail($data['product_id']);
        $v = !empty($data['variation_id']) ? ProductVariation::where('product_id', $product->id)->findOrFail($data['variation_id']) : null;
        $u = !empty($data['pack_id']) ? ProductUnit::where('product_id', $product->id)->findOrFail($data['pack_id']) : null;
        $item = $this->prices->set($priceList, $product, $v, $u, $data, auth()->id());

        $cost = (float) ($v?->effective_cost ?? $product->cost_price ?? 0) * ($u ? (float) $u->quantity_per_unit : 1);
        AuditLogger::log('price_list_item_set', "{$priceList->code}: {$product->title} = " . number_format((float) $item->price, 2), 'pricing', $product);
        return response()->json(['success' => true, 'below_cost' => $item->price !== null && $cost > 0 && (float) $item->price < $cost]);
    }

    public function removeItem(PriceList $priceList, PriceListItem $item)
    {
        abort_if(Install::isBranch(), 403);
        abort_unless($item->price_list_id === $priceList->id, 404);
        $item->delete();
        AuditLogger::log('price_list_item_removed', "{$priceList->code}: removed {$item->product?->title}", 'pricing', $item->product);
        return response()->json(['success' => true]);
    }

    // ------------------------------------------------------------------
    // Field rules
    // ------------------------------------------------------------------

    public function policies()
    {
        $pagetitle = 'What branches may change';
        $policies = FieldPolicy::orderByRaw("FIELD(branch_rule, 'override', 'request', 'locked')")->orderBy('label')->get();
        $canEdit = auth()->user()->can('Manage field policies') && !Install::isBranch();
        return view('catalogue.policies', compact('pagetitle', 'policies', 'canEdit'));
    }

    public function updatePolicies(Request $request)
    {
        abort_if(Install::isBranch(), 403);
        $data = $request->validate(['rules' => 'required|array', 'rules.*' => ['required', Rule::in(FieldPolicy::RULES)]]);
        $changed = [];
        foreach (FieldPolicy::whereIn('field', array_keys($data['rules']))->get() as $p) {
            $new = $data['rules'][$p->field];
            // Prices can't be "override" – a branch either asks or doesn't.
            if (in_array($p->field, ['cost_price', 'title', 'barcode', 'sku', 'variations', 'packs', 'product_type'], true) && $new === 'override') {
                $new = 'locked';
            }
            if ($p->branch_rule !== $new) {
                $changed[] = "{$p->label}: {$p->branch_rule} → {$new}";
                $p->update(['branch_rule' => $new]);
            }
        }
        if ($changed) {
            AuditLogger::log('field_policies_changed', implode('; ', $changed), 'settings');
        }
        return back()->with('success', $changed ? 'Rules saved. Branches get them with the next catalogue sync.' : 'No change.');
    }
}
