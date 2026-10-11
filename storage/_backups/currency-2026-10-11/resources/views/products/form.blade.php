@extends('layouts.master')

@section('content')
@php
    $p = $payload;
    $isEdit = $product !== null;
    $mbBranch = \App\Services\MultiBranch\Install::isBranch();
    $governed = \App\Services\Catalogue\CatalogueRequests::governed($product);
    $policy = \App\Services\Catalogue\CatalogueRequests::policyFor($product);
    $pendingRequests = $isEdit && $mbBranch ? \App\Models\PriceChangeRequest::where('product_id', $product->id)->where('status', 'pending')->get() : collect();
@endphp
<div class="main-content">
<div class="page-content">
<div class="container-fluid">

    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">{{ $isEdit ? 'Edit product' : 'Add product' }}
                @if ($isEdit && ($product->owner ?? null) === 'central' && !\App\Services\MultiBranch\Install::isStandalone())
                    <span class="badge bg-primary-subtle text-primary fs-xs align-middle"><i class="bi bi-building me-1"></i>Head-office catalogue</span>
                @elseif ($isEdit && $mbBranch)
                    <span class="badge bg-warning-subtle text-warning fs-xs align-middle"><i class="bi bi-shop me-1"></i>Only at this branch{{ ($product->central_status ?? '') === 'submitted' ? ' · sent to head office' : '' }}</span>
                @endif
            </h4>
            <p class="text-muted mb-0 mt-1 fs-sm">
                @if ($isEdit)
                    {{ $product->title }} · current stock <strong>{{ qty($product->stock) }}</strong>
                @else
                    Barcode and SKU are generated for you – just scan an existing barcode if the product already has one.
                @endif
            </p>
        </div>
        <div class="d-flex gap-2 mt-2 mt-sm-0">
            <a href="{{ route('products.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to products</a>
            @if ($isEdit)
                <a href="{{ route('products.labels', ['ids' => $product->id, 'include_packs' => 1]) }}" target="_blank" class="btn btn-light btn-sm"><i class="bi bi-upc me-1"></i>Print labels</a>
                @if ($mbBranch && ($product->owner ?? 'branch') !== 'central' && ($product->central_status ?? 'local') === 'local')
                    @can('Submit product to head office')
                        <button type="button" class="btn btn-soft-primary btn-sm" id="submitToHq"><i class="bi bi-send me-1"></i>Offer to head office</button>
                    @endcan
                @endif
            @endif
        </div>
    </div>

    @if ($governed)
        <div class="alert alert-primary d-flex gap-3 align-items-start">
            <i class="bi bi-building fs-4"></i>
            <div class="flex-grow-1">
                <strong>Head office manages this product.</strong>
                You can change <em>Active</em> and <em>Reorder level</em> for this branch.
                Price changes are sent to head office for approval; the till keeps the current price until they approve it.
                <div class="mt-2" style="max-width:520px">
                    <input type="text" form="productForm" name="change_reason" class="form-control form-control-sm" maxlength="255"
                           placeholder="Reason for a price change (e.g. competitor across the road sells at ₦450)">
                </div>
                @if ($pendingRequests->isNotEmpty())
                    <div class="mt-2 fs-sm">
                        <i class="bi bi-hourglass-split me-1"></i>Waiting for head office:
                        @foreach ($pendingRequests as $r)
                            <span class="badge bg-warning-subtle text-warning">{{ \Illuminate\Support\Str::after($r->item_label, $product->title) ?: 'Item' }} {{ \App\Models\PriceChangeRequest::FIELDS[$r->field] ?? $r->field }} → {{ money($r->requested_value) }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    <form id="productForm" enctype="multipart/form-data" novalidate>
        <div class="row g-3">
            {{-- ============ LEFT ============ --}}
            <div class="col-xl-8">

                {{-- Basic --}}
                <div class="card mb-3">
                    <div class="card-header"><h6 class="card-title mb-0">1. Product details</h6></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Product name <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control form-control-lg" required maxlength="255"
                                       placeholder="e.g. Peak Milk Powder 400g Tin" value="{{ $p['title'] ?? '' }}">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category</label>
                                <select name="category_id" id="category_id" class="form-select">
                                    <option value="">— None —</option>
                                    @foreach ($categories as $c)
                                        <option value="{{ $c->id }}" @selected(($p['category_id'] ?? null) == $c->id)>{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Brand</label>
                                <select name="brand_id" class="form-select">
                                    <option value="">— None —</option>
                                    @foreach ($brands as $b)
                                        <option value="{{ $b->id }}" @selected(($p['brand_id'] ?? null) == $b->id)>{{ $b->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">How is it sold?</label>
                                <div class="d-flex flex-wrap gap-2" role="radiogroup">
                                    @php $type = $p['product_type'] ?? 'simple'; @endphp
                                    <input type="radio" class="btn-check" name="product_type" id="type_simple" value="simple" @checked($type === 'simple')>
                                    <label class="btn btn-outline-primary" for="type_simple"><i class="bi bi-box me-1"></i>Single product</label>
                                    <input type="radio" class="btn-check" name="product_type" id="type_variable" value="variable" @checked($type === 'variable')>
                                    <label class="btn btn-outline-primary" for="type_variable"><i class="bi bi-grid-3x3-gap me-1"></i>Has variations (size, flavour, colour…)</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="Optional">{{ $p['description'] ?? '' }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Identification --}}
                <div class="card mb-3">
                    <div class="card-header d-flex align-items-center">
                        <h6 class="card-title mb-0 flex-grow-1">2. Barcode & SKU</h6>
                        <span class="badge bg-success-subtle text-success"><i class="bi bi-magic me-1"></i>Auto-generated</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Barcode</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                                    <input type="text" name="barcode" id="barcode" class="form-control vm-mono" maxlength="64"
                                           placeholder="Scan the product's barcode, or leave blank" value="{{ $p['barcode'] ?? '' }}">
                                    <button class="btn btn-outline-secondary" type="button" id="genBarcode" title="Generate an in-store barcode"><i class="bi bi-magic"></i></button>
                                </div>
                                <div class="form-text" id="barcodeHelp">Blank = we create a valid in-store EAN-13 (starts with {{ sys_setting('barcode_prefix', '20') }}).</div>
                                <div class="invalid-feedback d-block" id="barcodeError"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SKU / item code</label>
                                <div class="input-group">
                                    <input type="text" name="sku" id="sku" class="form-control vm-mono text-uppercase" maxlength="100"
                                           placeholder="Leave blank to generate" value="{{ $p['sku'] ?? '' }}">
                                    <button class="btn btn-outline-secondary" type="button" id="genSku" title="Suggest SKU"><i class="bi bi-magic"></i></button>
                                </div>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_weighted" id="is_weighted" value="1" @checked($p['is_weighted'] ?? false)>
                                    <label class="form-check-label" for="is_weighted">Sold by weight on a scale (meat, fish, fruit…)</label>
                                </div>
                            </div>
                            <div class="col-md-6" id="pluWrap" style="{{ ($p['is_weighted'] ?? false) ? '' : 'display:none' }}">
                                <label class="form-label">Scale PLU code</label>
                                <input type="text" name="plu_code" class="form-control vm-mono" maxlength="5" inputmode="numeric" placeholder="e.g. 00123" value="{{ $p['plu_code'] ?? '' }}">
                                <div class="form-text">The number you programme into the scale. Scale labels starting with 21/22 are read automatically at the till.</div>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Pricing --}}
                <div class="card mb-3">
                    <div class="card-header"><h6 class="card-title mb-0">3. Pricing <span class="text-muted fw-normal fs-sm" id="perUnitLabel">(per piece)</span></h6></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <label class="form-label">Cost price</label>
                                <div class="input-group"><span class="input-group-text">{{ currency_symbol() }}</span>
                                    <input type="number" step="0.01" min="0" name="cost_price" id="cost_price" class="form-control" value="{{ $p['cost_price'] ?? '' }}"></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Markup %</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" id="markup" class="form-control" placeholder="—">
                                    <span class="input-group-text">%</span></div>
                                <div class="form-text">Type a markup to fill the selling price.</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Selling price <span class="text-danger">*</span></label>
                                <div class="input-group"><span class="input-group-text">{{ currency_symbol() }}</span>
                                    <input type="number" step="0.01" min="0" name="price" id="price" class="form-control fw-semibold" required value="{{ $p['price'] ?? '' }}"></div>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Profit</label>
                                <div class="form-control-plaintext fw-semibold" id="profitBox">—</div>
                            </div>
                            <div class="col-12" id="belowCostWarn" style="display:none">
                                <div class="alert alert-danger py-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Selling price is below cost.</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Sale price <i class="bi bi-info-circle text-muted" title="A standing discounted price. Leave blank if not on sale."></i></label>
                                <div class="input-group"><span class="input-group-text">{{ currency_symbol() }}</span>
                                    <input type="number" step="0.01" min="0" name="sale_price" class="form-control" value="{{ $p['sale_price'] ?? '' }}"></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Wholesale price</label>
                                <div class="input-group"><span class="input-group-text">{{ currency_symbol() }}</span>
                                    <input type="number" step="0.01" min="0" name="wholesale_price" class="form-control" value="{{ $p['wholesale_price'] ?? '' }}"></div>
                                <div class="form-text">Used for wholesale customers.</div>
                            </div>
                            <div class="col-12">
                                <a class="fs-sm" data-bs-toggle="collapse" href="#promoBox" role="button"><i class="bi bi-megaphone me-1"></i>Promotion price & dates</a>
                                <div class="collapse {{ !empty($p['promo_price']) ? 'show' : '' }}" id="promoBox">
                                    <div class="row g-3 mt-0">
                                        <div class="col-md-4">
                                            <label class="form-label">Promo price</label>
                                            <div class="input-group"><span class="input-group-text">{{ currency_symbol() }}</span>
                                                <input type="number" step="0.01" min="0" name="promo_price" class="form-control" value="{{ $p['promo_price'] ?? '' }}"></div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Starts</label>
                                            <input type="datetime-local" name="promo_starts_at" class="form-control" value="{{ $p['promo_starts_at'] ?? '' }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Ends</label>
                                            <input type="datetime-local" name="promo_ends_at" class="form-control" value="{{ $p['promo_ends_at'] ?? '' }}">
                                        </div>
                                        <div class="col-12 form-text mt-1">During the promotion, packs get the same percentage off automatically.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Units & packs --}}
                <div class="card mb-3">
                    <div class="card-header d-flex align-items-center">
                        <h6 class="card-title mb-0 flex-grow-1">4. Units & packs</h6>
                        <button type="button" class="btn btn-sm btn-soft-primary" id="addPack"><i class="bi bi-plus-lg me-1"></i>Add pack</button>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end mb-3">
                            <div class="col-md-5">
                                <label class="form-label">Counted / sold in <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select name="base_unit_id" id="base_unit_id" class="form-select" required>
                                        @foreach ($units as $u)
                                            <option value="{{ $u->id }}" data-short="{{ $u->short_name }}" @selected(($p['base_unit_id'] ?? $defaultUnitId) == $u->id)>{{ $u->name }}{{ $u->short_name ? ' (' . $u->short_name . ')' : '' }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary" id="newUnit" title="New unit"><i class="bi bi-plus"></i></button>
                                </div>
                                <div class="form-text">Stock is always held in this unit.</div>
                            </div>
                            <div class="col-md-7 text-muted fs-sm">
                                Add packs that you also sell or buy in – e.g. <strong>Carton of 24</strong> or <strong>Pack of 6</strong>.
                                Give each pack its own price and barcode, and scanning the carton at the till sells a whole carton.
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" id="packTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width:130px">Pack</th>
                                        <th style="width:110px">Contains</th>
                                        <th style="min-width:130px">Pack price</th>
                                        <th style="min-width:120px">Wholesale</th>
                                        <th style="min-width:190px">Barcode</th>
                                        <th class="text-center" style="width:60px">Sell</th>
                                        <th style="width:40px"></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="text-muted fs-sm text-center py-2" id="noPacks">No packs – this product is only sold by the <span class="baseName">piece</span>.</div>
                    </div>
                </div>

                {{-- Variations --}}
                <div class="card mb-3" id="variationsCard" style="display:none">
                    <div class="card-header"><h6 class="card-title mb-0">5. Variations</h6></div>
                    <div class="card-body">
                        <p class="text-muted fs-sm">Add the options (e.g. <em>Size: 35cl, 50cl, 1L</em>) and press <strong>Create variations</strong>. Each variation gets its own barcode, price and stock.</p>
                        <div id="attrList" class="mb-2"></div>
                        <div class="d-flex gap-2 flex-wrap mb-3">
                            <button type="button" class="btn btn-sm btn-light" id="addAttr"><i class="bi bi-plus-lg me-1"></i>Add option</button>
                            <button type="button" class="btn btn-sm btn-primary" id="genVariations"><i class="bi bi-lightning me-1"></i>Create variations</button>
                            <button type="button" class="btn btn-sm btn-light ms-auto" id="fillPrices" title="Copy the main prices to every variation">Copy main prices to all</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle" id="varTable" style="min-width:860px">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:15%">Variation</th>
                                        <th style="width:16%">SKU</th>
                                        <th style="width:20%">Barcode</th>
                                        <th style="width:10%">Cost</th>
                                        <th style="width:10%">Price</th>
                                        <th style="width:10%">Wholesale</th>
                                        <th style="width:10%">{{ $isEdit ? 'Stock' : 'Opening stock' }}</th>
                                        <th class="text-center" style="width:5%">Active</th>
                                        <th style="width:4%"></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-light" id="addVariation"><i class="bi bi-plus-lg me-1"></i>Add a variation by hand</button>
                    </div>
                </div>
            </div>

            {{-- ============ RIGHT ============ --}}
            <div class="col-xl-4">
                <div class="card mb-3">
                    <div class="card-header"><h6 class="card-title mb-0">Status</h6></div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked($p['is_active'] ?? true)>
                            <label class="form-check-label" for="is_active">Active – can be sold</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="track_stock" id="track_stock" value="1" @checked($p['track_stock'] ?? true)>
                            <label class="form-check-label" for="track_stock">Track stock</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="allow_price_override" id="allow_price_override" value="1" @checked($p['allow_price_override'] ?? false)>
                            <label class="form-check-label" for="allow_price_override">Cashier may type the price</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_featured" id="is_featured" value="1" @checked($p['is_featured'] ?? false)>
                            <label class="form-check-label" for="is_featured">Featured (online shop)</label>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h6 class="card-title mb-0">Stock</h6></div>
                    <div class="card-body">
                        <label class="form-label">Reorder level</label>
                        <div class="input-group mb-3">
                            <input type="number" step="any" min="0" name="reorder_level" class="form-control" value="{{ $p['reorder_level'] ?? 10 }}">
                            <span class="input-group-text baseShort">pcs</span>
                        </div>
                        @if (!$isEdit)
                            <div id="openingSimple">
                                <label class="form-label">Opening stock</label>
                                <div class="input-group mb-2">
                                    <input type="number" step="any" min="0" name="opening_stock" class="form-control" placeholder="0">
                                    <span class="input-group-text baseShort">pcs</span>
                                </div>
                            </div>
                            <label class="form-label">Store / location</label>
                            <select name="opening_location_id" class="form-select mb-2">
                                @foreach ($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                            </select>
                            <div class="form-text">
                                Recorded as "Goods received".
                                @unless (auth()->user()->can('Approve stock adjustment') || !sys_setting('adjustment_requires_approval', true))
                                    A supervisor must approve it before it counts.
                                @endunless
                            </div>
                        @else
                            <div class="d-flex justify-content-between"><span class="text-muted">On hand</span><strong>{{ qty($product->stock) }} <span class="baseShort">pcs</span></strong></div>
                            <a href="{{ route('adjustments.create', ['type' => 'adjustment', 'ids' => $product->id]) }}" class="btn btn-sm btn-light w-100 mt-2"><i class="bi bi-sliders me-1"></i>Adjust stock</a>
                        @endif
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h6 class="card-title mb-0">Images</h6></div>
                    <div class="card-body">
                        <div class="text-center border rounded p-2 mb-2 bg-light" style="min-height:140px">
                            <img id="thumbPreview" src="{{ $p['thumbnail'] ?? '' }}" class="img-fluid rounded" style="max-height:160px;{{ empty($p['thumbnail']) ? 'display:none' : '' }}" alt="">
                            <div id="thumbEmpty" class="text-muted py-4" style="{{ empty($p['thumbnail']) ? '' : 'display:none' }}"><i class="bi bi-image fs-1"></i><div class="fs-sm">Main image</div></div>
                        </div>
                        <input type="file" name="thumbnail" id="thumbnail" accept="image/*" class="form-control form-control-sm mb-3">
                        <label class="form-label fs-sm">Gallery</label>
                        <input type="file" name="images[]" multiple accept="image/*" class="form-control form-control-sm">
                        @if (!empty($p['gallery']))
                            <div class="row g-1 mt-2">
                                @foreach ($p['gallery'] as $g)
                                    <div class="col-3 position-relative" id="gimg{{ $g['id'] }}">
                                        <img src="{{ $g['url'] }}" class="img-fluid rounded border" alt="">
                                        <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 px-1 del-img" data-id="{{ $g['id'] }}">&times;</button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="vm-sticky-actions py-3 px-3 d-flex gap-2 justify-content-end rounded-bottom">
            <a href="{{ route('products.index') }}" class="btn btn-light">Cancel</a>
            @unless ($isEdit)
                <button type="submit" class="btn btn-outline-primary" data-after="new"><i class="bi bi-plus-lg me-1"></i>Save & add another</button>
            @endunless
            <button type="submit" class="btn btn-primary" data-after="list" id="saveBtn">
                <span class="spinner-border spinner-border-sm me-1 d-none"></span>
                <i class="bi bi-check-lg me-1"></i>{{ $isEdit ? 'Save changes' : 'Save product' }}
            </button>
        </div>
    </form>

    @if ($isEdit)
        <div class="card mt-3" id="extraBarcodes">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0"><i class="bi bi-upc-scan me-1"></i>Other barcodes for this item</h6>
                <span class="text-muted fs-xs">New packaging, a second supplier's code, an old code… all ring up this item.</span>
            </div>
            <div class="card-body">
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-md-4">
                        <label class="form-label fs-sm">Barcode</label>
                        <input type="text" class="form-control vm-mono" id="xbCode" maxlength="64" placeholder="Scan it here">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fs-sm">Rings up</label>
                        <select class="form-select" id="xbTarget">
                            <option value="">{{ $product->title }} (single)</option>
                            @foreach ($product->packs->where('is_base', false) as $pk)
                                <option value="pack:{{ $pk->id }}">{{ $pk->unit->name ?? 'Pack' }} of {{ qty($pk->quantity_per_unit) }}</option>
                            @endforeach
                            @foreach ($product->variations as $vv)
                                <option value="variation:{{ $vv->id }}">{{ $vv->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fs-sm">Note</label>
                        <input type="text" class="form-control" id="xbNote" maxlength="120" placeholder="optional">
                    </div>
                    <div class="col-md-2"><button type="button" class="btn btn-primary w-100" id="xbAdd">Add</button></div>
                </div>
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Barcode</th><th>Rings up</th><th>Type</th><th>Note</th><th></th></tr></thead>
                    <tbody id="xbBody"><tr><td colspan="5" class="text-muted text-center">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($isEdit && \App\Services\MultiBranch\Install::isCentral() && ($product->owner ?? '') === 'central')
        @can('Distribute products')
            @php
                $distBranches = \App\Models\Branch::where('type', 'store')->orderBy('number')->get(['id', 'code', 'name']);
                $distSel = \Illuminate\Support\Facades\DB::table('product_distributions')->where('product_id', $product->id)->pluck('branch_id')->all();
                $distMode = $product->distribution ?? 'all';
            @endphp
            <div class="card mt-3" id="distCard">
                <div class="card-header"><h6 class="card-title mb-0"><i class="bi bi-diagram-3 me-1"></i>Which branches sell this</h6></div>
                <div class="card-body">
                    <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="dist_mode" id="distAll" value="all" @checked($distMode === 'all')><label class="form-check-label" for="distAll">All branches</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="dist_mode" id="distSel" value="selected" @checked($distMode === 'selected')><label class="form-check-label" for="distSel">Only these:</label></div>
                    <div class="d-flex flex-wrap gap-3 mt-2" id="distList">
                        @foreach ($distBranches as $b)
                            <div class="form-check"><input class="form-check-input dist-b" type="checkbox" value="{{ $b->id }}" id="db{{ $b->id }}" @checked(in_array($b->id, $distSel))>
                                <label class="form-check-label" for="db{{ $b->id }}">{{ $b->code }} – {{ $b->name }}</label></div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-sm btn-primary mt-3" id="distSave">Save</button>
                    <span class="text-muted fs-sm ms-2">Branches not selected stop selling it (their history stays).</span>
                </div>
            </div>
        @endcan
    @endif
</div>
</div>
</div>

<template id="packRow">
    <tr>
        <td>
            <input type="hidden" data-f="id">
            <select class="form-select form-select-sm" data-f="unit_id">
                @foreach ($units as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
            </select>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <input type="number" step="any" min="0.001" class="form-control" data-f="quantity_per_unit" placeholder="24">
                <span class="input-group-text baseShort">pcs</span>
            </div>
        </td>
        <td>
            <input type="number" step="0.01" min="0" class="form-control form-control-sm" data-f="price" placeholder="">
            <div class="fs-xs text-muted pack-hint"></div>
        </td>
        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" data-f="wholesale_price"></td>
        <td>
            <div class="input-group input-group-sm">
                <input type="text" class="form-control vm-mono" data-f="barcode" placeholder="Scan or generate">
                <button type="button" class="btn btn-outline-secondary gen-pack-bc" title="Generate"><i class="bi bi-magic"></i></button>
            </div>
        </td>
        <td class="text-center"><input class="form-check-input" type="checkbox" data-f="is_sellable" checked></td>
        <td><button type="button" class="btn btn-sm btn-link text-danger rm-row" title="Remove"><i class="bi bi-trash"></i></button></td>
    </tr>
</template>

<template id="varRow">
    <tr>
        <td>
            <input type="hidden" data-f="id">
            <input type="hidden" data-f="attributes">
            <input type="text" class="form-control form-control-sm" data-f="name" placeholder="e.g. 50cl">
        </td>
        <td><input type="text" class="form-control form-control-sm vm-mono" data-f="sku" placeholder="auto"></td>
        <td>
            <div class="input-group input-group-sm">
                <input type="text" class="form-control vm-mono" data-f="barcode" placeholder="auto">
                <button type="button" class="btn btn-outline-secondary gen-var-bc" title="Generate"><i class="bi bi-magic"></i></button>
            </div>
        </td>
        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" data-f="cost_price"></td>
        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" data-f="price"></td>
        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" data-f="wholesale_price"></td>
        <td>
            @if ($isEdit)
                <span class="fs-sm var-stock">—</span>
                <input type="number" step="any" min="0" class="form-control form-control-sm var-opening d-none" data-f="opening_stock" placeholder="0">
            @else
                <input type="number" step="any" min="0" class="form-control form-control-sm" data-f="opening_stock" placeholder="0">
            @endif
        </td>
        <td class="text-center"><input class="form-check-input" type="checkbox" data-f="is_active" checked></td>
        <td><button type="button" class="btn btn-sm btn-link text-danger rm-row" title="Remove"><i class="bi bi-trash"></i></button></td>
    </tr>
</template>
@endsection

@push('page-scripts')
<script>
(function () {
    const data = @json($p);
    const isEdit = @json($isEdit);
    const productId = data && data.id ? data.id : null;
    const form = document.getElementById('productForm');
    const $ = (s, el = document) => el.querySelector(s);
    const $$ = (s, el = document) => [...el.querySelectorAll(s)];
    const num = v => v === '' || v === null || v === undefined ? null : Number(v);

    // ------------------------------------------------------------- base unit label
    function baseShort() {
        const o = $('#base_unit_id').selectedOptions[0];
        return o ? (o.dataset.short || o.textContent.trim()) : 'pcs';
    }
    function baseName() {
        const o = $('#base_unit_id').selectedOptions[0];
        return o ? o.textContent.replace(/\s*\(.*\)$/, '').trim().toLowerCase() : 'piece';
    }
    function refreshUnitLabels() {
        $$('.baseShort').forEach(e => e.textContent = baseShort());
        $$('.baseName').forEach(e => e.textContent = baseName());
        $('#perUnitLabel').textContent = '(per ' + baseName() + ')';
        $$('#packTable tbody tr').forEach(updatePackHint);
    }
    $('#base_unit_id').addEventListener('change', refreshUnitLabels);

    // ------------------------------------------------------------- pricing helpers
    const cost = $('#cost_price'), price = $('#price'), markup = $('#markup');
    function refreshProfit() {
        const c = num(cost.value), s = num(price.value);
        const box = $('#profitBox');
        if (c && s) {
            const profit = s - c;
            box.innerHTML = `${VM.money(profit)} <span class="badge ${profit < 0 ? 'bg-danger' : 'bg-success-subtle text-success'}">${(profit / s * 100).toFixed(1)}% margin</span>`;
            if (document.activeElement !== markup) markup.value = ((s - c) / c * 100).toFixed(1);
        } else {
            box.textContent = '—';
        }
        $('#belowCostWarn').style.display = c && s && s < c ? '' : 'none';
        $$('#packTable tbody tr').forEach(updatePackHint);
    }
    markup.addEventListener('input', () => {
        const c = num(cost.value), m = num(markup.value);
        if (c && m !== null) { price.value = (c * (1 + m / 100)).toFixed(2); refreshProfit(); }
    });
    cost.addEventListener('input', refreshProfit);
    price.addEventListener('input', refreshProfit);

    // ------------------------------------------------------------- barcode / sku
    $('#genBarcode').addEventListener('click', async () => {
        try { $('#barcode').value = (await VM.api(@json(route('products.generate-barcode')))).barcode; checkBarcode(); }
        catch (e) { VM.error(e); }
    });
    $('#genSku').addEventListener('click', async () => {
        const t = $('#title').value || 'Product';
        try { $('#sku').value = (await VM.api(@json(route('products.generate-sku')) + '?title=' + encodeURIComponent(t) + '&category_id=' + ($('#category_id').value || ''))).sku; }
        catch (e) { VM.error(e); }
    });
    const checkBarcode = VM.debounce(async () => {
        const code = $('#barcode').value.trim();
        const err = $('#barcodeError');
        err.textContent = '';
        $('#barcode').classList.remove('is-invalid', 'is-valid');
        if (!code) return;
        try {
            const r = await VM.api(@json(route('products.check-barcode')) + '?code=' + encodeURIComponent(code) + '&product_id=' + (productId || ''));
            if (!r.available) { $('#barcode').classList.add('is-invalid'); err.textContent = r.message; }
            else $('#barcode').classList.add('is-valid');
        } catch (e) {}
    }, 350);
    $('#barcode').addEventListener('input', checkBarcode);
    // A barcode scanner "types" then presses Enter – don't submit the form on Enter in inputs.
    form.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.tagName === 'INPUT') e.preventDefault(); });

    $('#is_weighted').addEventListener('change', e => { $('#pluWrap').style.display = e.target.checked ? '' : 'none'; });

    // ------------------------------------------------------------- packs
    const packBody = $('#packTable tbody');
    function updatePackHint(tr) {
        const q = num($('[data-f=quantity_per_unit]', tr).value);
        const pp = num($('[data-f=price]', tr).value);
        const s = num(price.value);
        const hint = $('.pack-hint', tr);
        const priceInput = $('[data-f=price]', tr);
        if (q && s) {
            const full = q * s;
            priceInput.placeholder = full.toFixed(2);
            if (pp) {
                const per = pp / q;
                const save = full - pp;
                hint.innerHTML = `${VM.money(per)}/${VM.esc(baseShort())}` + (save > 0.004 ? ` · <span class="text-success">saves ${VM.money(save)}</span>` : (save < -0.004 ? ` · <span class="text-danger">dearer than singles</span>` : ''));
            } else {
                hint.textContent = 'Blank = ' + VM.money(full) + ' (' + VM.qty(q) + ' × price)';
            }
        } else {
            hint.textContent = '';
        }
    }
    function addPack(p = {}) {
        const tr = $('#packRow').content.firstElementChild.cloneNode(true);
        const set = (f, v) => { const el = $(`[data-f=${f}]`, tr); if (!el) return; if (el.type === 'checkbox') el.checked = v !== false; else el.value = v ?? ''; };
        ['id', 'unit_id', 'quantity_per_unit', 'price', 'wholesale_price', 'barcode'].forEach(f => set(f, p[f]));
        set('is_sellable', p.is_sellable);
        if (!p.unit_id) {
            const carton = [...$('[data-f=unit_id]', tr).options].find(o => /carton/i.test(o.text));
            if (carton) $('[data-f=unit_id]', tr).value = carton.value;
        }
        packBody.appendChild(tr);
        $('.rm-row', tr).addEventListener('click', () => { tr.remove(); togglePackEmpty(); });
        $('.gen-pack-bc', tr).addEventListener('click', async () => {
            try { $('[data-f=barcode]', tr).value = (await VM.api(@json(route('products.generate-barcode')))).barcode; } catch (e) { VM.error(e); }
        });
        $$('input', tr).forEach(i => i.addEventListener('input', () => updatePackHint(tr)));
        $$('.baseShort', tr).forEach(e => e.textContent = baseShort());
        updatePackHint(tr);
        togglePackEmpty();
        return tr;
    }
    function togglePackEmpty() { $('#noPacks').style.display = packBody.children.length ? 'none' : ''; }
    $('#addPack').addEventListener('click', () => $('[data-f=quantity_per_unit]', addPack()).focus());
    $('#newUnit').addEventListener('click', async () => {
        const r = await Swal.fire({title: 'New unit', html: '<input id="swU" class="swal2-input" placeholder="Name e.g. Crate"><input id="swS" class="swal2-input" placeholder="Short e.g. crt">',
            showCancelButton: true, preConfirm: () => ({name: document.getElementById('swU').value, short_name: document.getElementById('swS').value})});
        if (!r.isConfirmed || !r.value.name) return;
        try {
            const res = await VM.api(@json(route('products.units.store')), {method: 'POST', body: r.value});
            const opt = `<option value="${res.unit.id}" data-short="${VM.esc(res.unit.short_name)}">${VM.esc(res.unit.name)}</option>`;
            $('#base_unit_id').insertAdjacentHTML('beforeend', opt);
            $$('[data-f=unit_id]').forEach(s => s.insertAdjacentHTML('beforeend', opt));
            $('#packRow').content.querySelector('[data-f=unit_id]').insertAdjacentHTML('beforeend', opt);
            VM.toast('Unit added');
        } catch (e) { VM.error(e); }
    });

    // ------------------------------------------------------------- variations
    const varBody = $('#varTable tbody');
    function toggleType() {
        const variable = $('#type_variable').checked;
        $('#variationsCard').style.display = variable ? '' : 'none';
        const os = $('#openingSimple'); if (os) os.style.display = variable ? 'none' : '';
    }
    $$('input[name=product_type]').forEach(r => r.addEventListener('change', toggleType));

    function addAttr(a = {name: '', values: []}) {
        const div = document.createElement('div');
        div.className = 'row g-2 mb-2 attr-row';
        div.innerHTML = `
            <div class="col-4"><input class="form-control form-control-sm attr-name" placeholder="Option e.g. Size" value="${VM.esc(a.name)}"></div>
            <div class="col-7"><input class="form-control form-control-sm attr-values" placeholder="Values, comma separated e.g. 35cl, 50cl, 1L" value="${VM.esc((a.values || []).join(', '))}"></div>
            <div class="col-1"><button type="button" class="btn btn-sm btn-link text-danger"><i class="bi bi-x-lg"></i></button></div>`;
        div.querySelector('button').addEventListener('click', () => div.remove());
        $('#attrList').appendChild(div);
    }
    $('#addAttr').addEventListener('click', () => addAttr());

    function addVar(v = {}) {
        const tr = $('#varRow').content.firstElementChild.cloneNode(true);
        const set = (f, val) => { const el = $(`[data-f=${f}]`, tr); if (!el) return; if (el.type === 'checkbox') el.checked = val !== false; else el.value = val ?? ''; };
        ['id', 'name', 'sku', 'barcode', 'cost_price', 'price', 'wholesale_price', 'opening_stock'].forEach(f => set(f, v[f]));
        set('is_active', v.is_active);
        $('[data-f=attributes]', tr).value = JSON.stringify(v.attributes || {});
        const st = $('.var-stock', tr);
        if (st) {
            if (v.id) st.textContent = VM.qty(v.stock || 0);
            else { st.classList.add('d-none'); $('.var-opening', tr).classList.remove('d-none'); }
        }
        $('.rm-row', tr).addEventListener('click', async () => {
            if (v.id && Number(v.stock) > 0 && !await VM.confirm('Remove this variation?', 'It has stock, so it will be deactivated (kept for history) rather than deleted.')) return;
            tr.remove();
        });
        $('.gen-var-bc', tr).addEventListener('click', async () => {
            try { $('[data-f=barcode]', tr).value = (await VM.api(@json(route('products.generate-barcode')))).barcode; } catch (e) { VM.error(e); }
        });
        varBody.appendChild(tr);
        return tr;
    }
    $('#addVariation').addEventListener('click', () => addVar({price: price.value, cost_price: cost.value}));

    $('#genVariations').addEventListener('click', () => {
        const attrs = $$('.attr-row').map(r => ({
            name: $('.attr-name', r).value.trim(),
            values: $('.attr-values', r).value.split(',').map(s => s.trim()).filter(Boolean),
        })).filter(a => a.name && a.values.length);
        if (!attrs.length) { VM.toast('Add at least one option with values', 'warning'); return; }
        let combos = [{}];
        attrs.forEach(a => {
            const next = [];
            combos.forEach(c => a.values.forEach(v => next.push(Object.assign({}, c, {[a.name]: v}))));
            combos = next;
        });
        const existing = new Set($$('[data-f=name]', varBody).map(i => i.value.trim().toLowerCase()));
        let added = 0;
        combos.forEach(c => {
            const name = Object.values(c).join(' / ');
            if (existing.has(name.toLowerCase())) return;
            addVar({name, attributes: c, price: price.value, cost_price: cost.value, wholesale_price: form.wholesale_price.value});
            added++;
        });
        VM.toast(added ? `${added} variation(s) created` : 'All these variations already exist', added ? 'success' : 'info');
    });
    $('#fillPrices').addEventListener('click', () => {
        $$('tr', varBody).forEach(tr => {
            $('[data-f=price]', tr).value = price.value;
            $('[data-f=cost_price]', tr).value = cost.value;
            $('[data-f=wholesale_price]', tr).value = form.wholesale_price.value;
        });
    });

    // ------------------------------------------------------------- images
    $('#thumbnail').addEventListener('change', e => {
        const f = e.target.files[0];
        if (!f) return;
        $('#thumbPreview').src = URL.createObjectURL(f);
        $('#thumbPreview').style.display = ''; $('#thumbEmpty').style.display = 'none';
    });
    $$('.del-img').forEach(b => b.addEventListener('click', async () => {
        if (!await VM.confirm('Delete this image?')) return;
        try { await VM.api(`/products/${productId}/images/${b.dataset.id}`, {method: 'DELETE'}); $('#gimg' + b.dataset.id).remove(); }
        catch (e) { VM.error(e); }
    }));

    // ------------------------------------------------------------- load existing
    if (data) {
        (data.packs || []).forEach(addPack);
        (data.attributes || []).forEach(addAttr);
        (data.variations || []).forEach(addVar);
    }
    if (!$$('.attr-row').length) addAttr();
    toggleType();
    togglePackEmpty();
    refreshUnitLabels();
    refreshProfit();

    // ------------------------------------------------------------- submit
    let after = 'list';
    $$('button[type=submit]').forEach(b => b.addEventListener('click', () => after = b.dataset.after));

    form.addEventListener('submit', async e => {
        e.preventDefault();
        $$('.is-invalid', form).forEach(el => el.classList.remove('is-invalid'));

        const fd = new FormData();
        const plain = ['title', 'sku', 'barcode', 'plu_code', 'category_id', 'brand_id', 'description', 'cost_price', 'price', 'sale_price',
            'wholesale_price', 'promo_price', 'promo_starts_at', 'promo_ends_at', 'base_unit_id', 'reorder_level', 'opening_stock', 'opening_location_id', 'change_reason'];
        plain.forEach(n => { const el = form.elements[n]; if (el && el.value !== '') fd.append(n, el.value); });
        fd.append('product_type', form.querySelector('input[name=product_type]:checked').value);
        ['is_active', 'track_stock', 'allow_price_override', 'is_featured', 'is_weighted'].forEach(n => fd.append(n, form.elements[n].checked ? 1 : 0));
        if (form.thumbnail.files[0]) fd.append('thumbnail', form.thumbnail.files[0]);
        [...form.elements['images[]'].files].forEach(f => fd.append('images[]', f));

        $$('tr', packBody).forEach((tr, i) => {
            const g = f => $(`[data-f=${f}]`, tr);
            if (!g('quantity_per_unit').value) return;
            ['id', 'unit_id', 'quantity_per_unit', 'price', 'wholesale_price', 'barcode'].forEach(f => { if (g(f).value !== '') fd.append(`packs[${i}][${f}]`, g(f).value); });
            fd.append(`packs[${i}][is_sellable]`, g('is_sellable').checked ? 1 : 0);
            if (!g('barcode').value) fd.append(`packs[${i}][auto_barcode]`, 1);
        });

        if (fd.get('product_type') === 'variable') {
            $$('.attr-row').forEach((r, i) => {
                const n = $('.attr-name', r).value.trim(), v = $('.attr-values', r).value.trim();
                if (n && v) { fd.append(`attributes_input[${i}][name]`, n); fd.append(`attributes_input[${i}][values]`, v); }
            });
            $$('tr', varBody).forEach((tr, i) => {
                const g = f => $(`[data-f=${f}]`, tr);
                ['id', 'name', 'sku', 'barcode', 'cost_price', 'price', 'wholesale_price', 'opening_stock'].forEach(f => { if (g(f) && g(f).value !== '') fd.append(`variations[${i}][${f}]`, g(f).value); });
                fd.append(`variations[${i}][is_active]`, g('is_active').checked ? 1 : 0);
                let attrs = {}; try { attrs = JSON.parse(g('attributes').value || '{}'); } catch (x) {}
                Object.entries(attrs).forEach(([k, v]) => fd.append(`variations[${i}][attributes][${k}]`, v));
            });
            if (!$$('tr', varBody).length) { VM.error(new Error('Add at least one variation, or switch to "Single product".')); return; }
        }
        if (isEdit) fd.append('_method', 'PUT');

        const btn = $('#saveBtn');
        btn.disabled = true; $('.spinner-border', btn).classList.remove('d-none');
        try {
            const res = await VM.api(isEdit ? `/products/${productId}` : @json(route('products.store')), {method: 'POST', form: fd});
            await Swal.fire({icon: 'success', title: res.message, html: res.product ? `Barcode <b class="vm-mono">${VM.esc(res.product.barcode)}</b><br>SKU <b class="vm-mono">${VM.esc(res.product.sku)}</b>` : '', timer: 1800, showConfirmButton: false});
            location.href = after === 'new' ? @json(route('products.create')) : res.redirect;
        } catch (err) {
            if (err.data && err.data.errors) {
                Object.keys(err.data.errors).forEach(k => {
                    const name = k.replace(/\.(\d+)\./g, '[$1][').replace(/\.(\w+)$/, '[$1]').replace(/\[(\w+)$/, '[$1]');
                    const el = form.elements[k] || form.querySelector(`[name="${name}"]`);
                    if (el && el.classList) { el.classList.add('is-invalid'); const fb = el.closest('div')?.querySelector('.invalid-feedback'); if (fb) fb.textContent = err.data.errors[k][0]; }
                });
            }
            VM.error(err);
        } finally {
            btn.disabled = false; $('.spinner-border', btn).classList.add('d-none');
        }
    });
})();
</script>
@if ($isEdit)
<script>
(() => {
    const productId = {{ $product->id }};
    const governed = {{ $governed ? 'true' : 'false' }};
    const policy = @json($policy);

    // ---- Head-office product at a branch: only the fields the policy allows stay editable.
    if (governed) {
        const form = document.getElementById('productForm');
        const editable = new Set(['is_active', 'reorder_level']);
        ['price', 'wholesale_price', 'promo_price'].forEach(f => { if (policy[f] === 'request') editable.add(f); });
        if (policy.promo_price === 'request') { editable.add('promo_starts_at'); editable.add('promo_ends_at'); }
        const lockAll = () => {
            form.querySelectorAll('input, select, textarea, button').forEach(el => {
                if (el.id === 'saveBtn' || el.type === 'submit' || el.closest('.vm-sticky-actions')) return;
                const f = el.name || el.dataset.f;
                const inVarOrPack = el.closest('tr') && el.dataset.f === 'price';
                const allowPack = inVarOrPack && ((el.closest('#packTable') && policy.pack_price === 'request') || (el.closest('#varTable') && policy.price === 'request'));
                if (editable.has(f) && !el.closest('tr')) return;
                if (allowPack) return;
                if (el.tagName === 'BUTTON') { el.disabled = true; el.classList.add('d-none'); return; }
                el.disabled = true;
            });
            form.querySelectorAll('[data-f=price]').forEach(el => { if (!el.disabled) el.title = 'Change sends a request to head office'; });
        };
        lockAll();
        setTimeout(lockAll, 300); // rows added by the page script after load
    }

    // ---- Distribution (head office)
    document.getElementById('distSave')?.addEventListener('click', async () => {
        const mode = document.querySelector('input[name=dist_mode]:checked').value;
        const branches = [...document.querySelectorAll('.dist-b:checked')].map(x => Number(x.value));
        if (mode === 'selected' && !branches.length) { VM.toast('Tick at least one branch', 'warning'); return; }
        try { const r = await VM.api(`/products/${productId}/distribution`, {method: 'POST', body: {mode, branches}}); VM.toast(r.message); } catch (e) { VM.error(e); }
    });

    // ---- Offer a local product to head office
    document.getElementById('submitToHq')?.addEventListener('click', async () => {
        const note = await VM.prompt('Offer this product to head office?', 'Head office reviews it and, if approved, it joins the catalogue for every branch. Add a note (optional).', false);
        if (note === null) return;
        try {
            const res = await VM.api(`/products/${productId}/submit-to-hq`, {method: 'POST', body: {note: typeof note === 'string' ? note : ''}});
            VM.toast(res.message, 'success');
            setTimeout(() => location.reload(), 900);
        } catch (e) { VM.error(e); }
    });

    // ---- Extra barcodes
    const body = document.getElementById('xbBody');
    const load = async () => {
        const res = await VM.api(`/products/${productId}/barcodes`);
        body.innerHTML = res.barcodes.length ? res.barcodes.map(b => `<tr>
            <td class="vm-mono">${VM.esc(b.barcode)}</td><td>${VM.esc(b.target)}</td>
            <td>${b.kind === 'primary' ? '<span class="badge bg-light text-dark">main</span>' : '<span class="badge bg-info-subtle text-info">extra</span>'}</td>
            <td class="fs-sm text-muted">${VM.esc(b.note || '')}</td>
            <td class="text-end">${b.kind === 'extra' ? `<button type="button" class="btn btn-sm btn-link text-danger xb-rm" data-id="${b.id}"><i class="bi bi-trash"></i></button>` : ''}</td>
        </tr>`).join('') : '<tr><td colspan="5" class="text-muted text-center">No barcodes.</td></tr>';
    };
    load().catch(VM.error);
    document.getElementById('xbAdd').addEventListener('click', async () => {
        const code = document.getElementById('xbCode').value.trim();
        if (!code) return;
        const [kind, id] = (document.getElementById('xbTarget').value || ':').split(':');
        try {
            await VM.api(`/products/${productId}/barcodes`, {method: 'POST', body: {barcode: code, kind: kind || null, target_id: id || null, note: document.getElementById('xbNote').value}});
            document.getElementById('xbCode').value = ''; document.getElementById('xbNote').value = '';
            VM.toast('Barcode added', 'success'); load();
        } catch (e) { VM.error(e); }
    });
    document.getElementById('xbCode').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); document.getElementById('xbAdd').click(); } });
    body.addEventListener('click', async e => {
        const b = e.target.closest('.xb-rm'); if (!b) return;
        if (!await VM.confirm('Remove this barcode?', 'Scanning it will no longer find this item.')) return;
        try { await VM.api(`/products/${productId}/barcodes/${b.dataset.id}`, {method: 'DELETE'}); load(); } catch (err) { VM.error(err); }
    });
})();
</script>
@endif
@endpush
