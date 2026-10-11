@extends('layouts.master')

@section('title', 'Store Settings')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">

            <!-- Page Title -->
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">Store Settings</h4>
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="javascript:void(0)">Settings</a></li>
                            <li class="breadcrumb-item active">Store Settings</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Success Message -->
            @if(session('success'))
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Store Settings Display -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Current Store Information</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editStoreModal">
                                <i class="bi bi-pencil-square"></i> Edit Settings
                            </button>
                        </div>

                        <div class="card-body">
                            @php
                                $setting = \App\Models\StoreSetting::first();
                            @endphp

                            <div class="row g-4">
                                <!-- Logo & Name -->
                                <div class="col-lg-4 col-md-6">
                                    <div class="card border shadow-sm h-100">
                                        <div class="card-body text-center">
                                            @if($setting?->logo)
                                                <img src="{{ $setting->getLogoUrlAttribute() }}" alt="Store Logo" class="img-fluid rounded mb-3" style="max-height: 160px; object-fit: contain;">
                                            @else
                                                <div class="bg-light rounded p-5 mb-3 d-flex align-items-center justify-content-center" style="height: 160px;">
                                                    <i class="bi bi-shop display-4 text-muted"></i>
                                                </div>
                                            @endif
                                            <h5 class="mb-2">{{ $setting?->store_name ?? 'Store Name Not Set' }}</h5>
                                            @if($setting?->motto)
                                                <p class="text-muted fst-italic mb-0">{{ $setting->motto }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Currency & Contact -->
                                <div class="col-lg-4 col-md-6">
                                    <div class="card border shadow-sm h-100">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-3 text-primary">Currency & Contact</h6>
                                            <table class="table table-borderless table-sm mb-0">
                                                <tr>
                                                    <td class="fw-medium">Currency:</td>
                                                    <td><span class="badge bg-success-subtle text-success fs-6 me-1">{{ currency_label() }}</span> {{ config('currencies.' . currency_code() . '.0', currency_code()) }} ({{ currency_code() }}) <span class="text-muted ms-2">e.g. {{ money(1250) }}</span></td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-medium">Phone:</td>
                                                    <td>{{ $setting?->phone ?? '—' }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-medium">Email:</td>
                                                    <td>{{ $setting?->email ?? '—' }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-medium">Website:</td>
                                                    <td>
                                                        @if($setting?->website)
                                                            <a href="{{ $setting->website }}" target="_blank" class="text-primary">
                                                                {{ $setting->website }}
                                                            </a>
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-medium">Tax ID:</td>
                                                    <td>{{ $setting?->tax_id ?? '—' }}</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Address & Footer -->
                                <div class="col-lg-4 col-md-12">
                                    <div class="card border shadow-sm h-100">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-3 text-primary">Address & Receipt Footer</h6>
                                            <div class="mb-4">
                                                <strong>Store Address:</strong>
                                                <p class="text-muted mt-2 mb-0">
                                                    {{ $setting?->address ?? 'No address provided' }}
                                                </p>
                                            </div>
                                            <div>
                                                <strong>Receipt Footer Note:</strong>
                                                <p class="text-muted fst-italic mt-2 mb-0">
                                                    {{ $setting?->footer_note ?? 'Thank you for shopping with us!' }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Edit Store Settings Modal - NOW SCROLLABLE -->
<div class="modal fade" id="editStoreModal" tabindex="-1" aria-labelledby="editStoreModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form action="{{ route('settings.store.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="modal-header">
                    <h5 class="modal-title" id="editStoreModalLabel">Edit Store Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- Scrollable Body -->
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding-bottom: 2rem;">
                    @php
                        $currencyList = config('currencies', []);
                        $currentCode = strtoupper(old('currency_code', $setting?->currency_code ?: 'NGN'));
                        $currentSymbol = old('currency_symbol', $setting?->currency_symbol ?: ($currencyList[$currentCode][2][0] ?? '₦'));
                        if (!isset($currencyList[$currentCode])) {
                            $currencyList[$currentCode] = [$currentCode, 'Other', [$currentSymbol]];
                        }
                        $regions = collect($currencyList)->map(fn ($c, $code) => ['code' => $code, 'name' => $c[0], 'region' => $c[1], 'symbols' => $c[2]])->groupBy('region');
                    @endphp
                    <div class="row g-4 mb-4 currency-picker">
                        <div class="col-lg-7">
                            <label class="form-label fw-semibold" for="currencySelect">Currency <span class="text-danger">*</span></label>
                            <div class="input-group mb-2">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="search" id="currencySearch" class="form-control" placeholder="Search: naira, cedi, NGN, KES, euro…" autocomplete="off">
                            </div>
                            <select name="currency_code" id="currencySelect" class="form-select" required size="9">
                                @foreach ($regions as $region => $items)
                                    <optgroup label="{{ $region }}">
                                        @foreach ($items as $c)
                                            <option value="{{ $c['code'] }}" data-symbols='@json($c['symbols'])' data-search="{{ strtolower($c['code'] . ' ' . $c['name'] . ' ' . implode(' ', $c['symbols'])) }}" @selected($currentCode === $c['code'])>
                                                {{ $c['symbols'][0] }} &nbsp; {{ $c['code'] }} – {{ $c['name'] }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-1" id="currencyNoMatch" style="display:none!important">No currency matches your search.</small>
                        </div>
                        <div class="col-lg-5">
                            <label class="form-label fw-semibold">Symbol to show on prices <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-2 mb-2" id="symbolChoices" role="radiogroup" aria-label="Currency symbol"></div>
                            <div class="input-group">
                                <span class="input-group-text">Or type your own</span>
                                <input type="text" id="customSymbol" class="form-control" maxlength="10" placeholder="e.g. N" autocomplete="off">
                            </div>
                            <div class="rounded-3 border bg-light p-3 mt-3">
                                <div class="text-muted small mb-1">Prices will look like this</div>
                                <div class="fs-3 fw-bold" id="currencyPreview">–</div>
                                <div class="small text-muted" id="currencyPreviewLabel"></div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="currency_symbol" id="currency_symbol" value="{{ $currentSymbol }}">

                    <!-- Other Form Fields -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Store Name <span class="text-danger">*</span></label>
                            <input type="text" name="store_name" class="form-control" value="{{ old('store_name', $setting?->store_name) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Motto</label>
                            <input type="text" name="motto" class="form-control" value="{{ old('motto', $setting?->motto) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Website</label>
                            <input type="url" name="website" class="form-control" value="{{ old('website', $setting?->website) }}" placeholder="https://example.com">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $setting?->phone) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $setting?->email) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tax ID / VAT</label>
                            <input type="text" name="tax_id" class="form-control" value="{{ old('tax_id', $setting?->tax_id) }}">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3">{{ old('address', $setting?->address) }}</textarea>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Receipt Footer Note</label>
                            <textarea name="footer_note" class="form-control" rows="3">{{ old('footer_note', $setting?->footer_note) }}</textarea>
                        </div>

                        <div class="col-md-12 mt-4">
                            <label class="form-label">Store Logo (Recommended: 300×100 px, max 2MB)</label>
                            <input type="file" name="logo" class="form-control" accept="image/*">

                            @if($setting?->logo)
                                <div class="mt-3">
                                    <p class="mb-2"><strong>Current Logo:</strong></p>
                                    <img src="{{ $setting->getLogoUrlAttribute() }}" alt="Current Logo" class="img-thumbnail" style="max-height: 120px;">
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .currency-picker #currencySelect { font-size: 14px; }
    .currency-picker #currencySelect option { padding: 4px 6px; }
    .currency-picker .sym-btn { min-width: 64px; font-weight: 700; font-size: 16px; }
    .currency-picker .sym-btn small { display: block; font-weight: 400; font-size: 11px; opacity: .75; }
</style>
<!-- Currency picker: choose a currency, then the symbol shown on prices -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const select = document.getElementById('currencySelect');
        const search = document.getElementById('currencySearch');
        const choices = document.getElementById('symbolChoices');
        const custom = document.getElementById('customSymbol');
        const hidden = document.getElementById('currency_symbol');
        const preview = document.getElementById('currencyPreview');
        const previewLabel = document.getElementById('currencyPreviewLabel');
        const noMatch = document.getElementById('currencyNoMatch');
        if (!select) return;

        const esc = s => String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
        // Same rule as the server: letter symbols of 2+ characters get a space (NGN 1,250.00)
        const prefix = sym => (sym.length >= 2 && /\p{L}$/u.test(sym)) ? sym + ' ' : sym;
        const symbolsOf = opt => { try { return JSON.parse(opt.dataset.symbols || '[]'); } catch (e) { return []; } };

        function render(keepCurrent) {
            const opt = select.options[select.selectedIndex];
            if (!opt) return;
            const list = symbolsOf(opt);
            let current = keepCurrent ? hidden.value.trim() : '';
            if (!current) current = list[0] || opt.value;
            const isCustom = !list.includes(current);
            choices.innerHTML = list.map((sym, i) => `
                <input type="radio" class="btn-check" name="symbolChoice" id="sym${i}" value="${esc(sym)}" ${!isCustom && sym === current ? 'checked' : ''}>
                <label class="btn btn-outline-primary sym-btn" for="sym${i}">${esc(sym)}<small>${esc(prefix(sym))}100</small></label>`).join('');
            custom.value = isCustom ? current : '';
            setSymbol(current);
        }
        function setSymbol(sym) {
            sym = String(sym || '').trim();
            hidden.value = sym;
            const opt = select.options[select.selectedIndex];
            preview.textContent = sym ? prefix(sym) + (1250).toLocaleString(undefined, {minimumFractionDigits: 2}) : '–';
            previewLabel.textContent = opt ? opt.value + ' – ' + opt.textContent.split('–').slice(1).join('–').trim() : '';
        }

        select.addEventListener('change', () => { hidden.value = ''; render(false); });
        choices.addEventListener('change', e => { if (e.target.name === 'symbolChoice') { custom.value = ''; setSymbol(e.target.value); } });
        custom.addEventListener('input', () => {
            const v = custom.value.trim();
            choices.querySelectorAll('input').forEach(r => r.checked = v === '' ? r.value === symbolsOf(select.options[select.selectedIndex])[0] : false);
            setSymbol(v || (symbolsOf(select.options[select.selectedIndex])[0] || ''));
        });
        search.addEventListener('input', () => {
            const q = search.value.trim().toLowerCase();
            let shown = 0, first = null;
            select.querySelectorAll('optgroup').forEach(g => {
                let any = false;
                g.querySelectorAll('option').forEach(o => {
                    const hit = !q || o.dataset.search.includes(q);
                    o.hidden = !hit; any = any || hit;
                    if (hit) { shown++; first = first || o; }
                });
                g.hidden = !any;
            });
            noMatch.style.setProperty('display', shown ? 'none' : 'block', 'important');
            if (q && first && select.selectedOptions[0]?.hidden) { first.selected = true; hidden.value = ''; render(false); }
        });
        search.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); select.focus(); } });

        // Keep the saved symbol (it may be a custom one) when the form opens
        render(true);
        const modal = document.getElementById('editStoreModal');
        modal?.addEventListener('shown.bs.modal', () => select.selectedOptions[0]?.scrollIntoView({block: 'center'}));
    });
</script>
@endsection
