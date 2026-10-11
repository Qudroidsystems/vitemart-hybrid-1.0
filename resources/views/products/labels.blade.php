<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Barcode labels</title>
    <script src="{{ asset('vendor/jsbarcode/JsBarcode.all.min.js') }}"></script>
    @php
        [$w, $h] = array_map('intval', explode('x', $size)) + [38, 25];
        $w = max(25, min(100, $w)); $h = max(15, min(80, $h));
    @endphp
    <style>
        :root { --w: {{ $w }}mm; --h: {{ $h }}mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #eef1f5; color: #111; }
        .toolbar { position: sticky; top: 0; background: #1f2937; color: #fff; padding: 10px 16px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; z-index: 2; }
        .toolbar label { font-size: 13px; display: flex; gap: 6px; align-items: center; }
        .toolbar select, .toolbar button, .toolbar a { font-size: 13px; padding: 6px 10px; border-radius: 6px; border: 0; }
        .toolbar button.print { background: #0ab39c; color: #fff; font-weight: 600; cursor: pointer; }
        .toolbar a { background: #374151; color: #fff; text-decoration: none; }
        .sheet { display: flex; flex-wrap: wrap; gap: 2mm; padding: 6mm; }
        .label { width: var(--w); height: var(--h); background: #fff; border: 1px dashed #c4c9d1; padding: 1.2mm 1.5mm; display: flex; flex-direction: column; align-items: center; justify-content: space-between; overflow: hidden; page-break-inside: avoid; }
        .label .store { font-size: 6pt; color: #555; width: 100%; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .label .name { font-size: 7.5pt; font-weight: 700; width: 100%; text-align: center; line-height: 1.1; max-height: 2.2em; overflow: hidden; }
        .label .sub { font-size: 6.5pt; color: #333; }
        .label svg { width: 100%; height: auto; max-height: 45%; }
        .label .price { font-size: 11pt; font-weight: 800; }
        .empty { padding: 40px; text-align: center; color: #555; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { padding: 0; gap: 0; }
            .label { border: 0; }
            @page { margin: 4mm; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <strong>{{ count($labels) }} labels</strong>
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:0">
        <input type="hidden" name="ids" value="{{ request('ids') }}">
        <label>Size
            <select name="size" onchange="this.form.submit()">
                @foreach (['38x25' => '38 × 25 mm', '50x30' => '50 × 30 mm', '58x40' => '58 × 40 mm', '70x35' => '70 × 35 mm (shelf)'] as $k => $v)
                    <option value="{{ $k }}" @selected($size === $k)>{{ $v }}</option>
                @endforeach
            </select>
        </label>
        <label>Copies each
            <select name="copies_all" onchange="this.form.submit()">
                @foreach ([1, 2, 3, 5, 10, 20, 50] as $n)<option @selected(request('copies_all', 1) == $n)>{{ $n }}</option>@endforeach
            </select>
        </label>
        <label><input type="checkbox" name="show_price" value="1" @checked($showPrice) onchange="this.form.submit()"> Price</label>
        <label><input type="checkbox" name="include_packs" value="1" @checked(request()->boolean('include_packs')) onchange="this.form.submit()"> Pack / carton labels</label>
    </form>
    <span style="flex:1"></span>
    <a href="{{ route('products.index') }}">Back</a>
    <button class="print" onclick="window.print()">Print</button>
</div>

@if (empty($labels))
    <div class="empty">None of the selected products have a barcode yet. Use “Generate missing barcodes” on the product list first.</div>
@else
    <div class="sheet">
        @foreach ($labels as $l)
            <div class="label">
                <div class="store">{{ $store->store_name ?? '' }}</div>
                <div class="name">{{ $l['name'] }}</div>
                @if ($l['sub'])<div class="sub">{{ $l['sub'] }}</div>@endif
                <svg class="bc" data-code="{{ $l['code'] }}"></svg>
                @if ($showPrice)<div class="price">{{ money($l['price']) }}</div>@endif
            </div>
        @endforeach
    </div>
@endif

<script>
document.querySelectorAll('svg.bc').forEach(function (el) {
    var code = el.dataset.code;
    var format = /^\d{13}$/.test(code) ? 'EAN13' : (/^\d{8}$/.test(code) ? 'EAN8' : 'CODE128');
    try {
        JsBarcode(el, code, {format: format, height: 40, fontSize: 12, margin: 0, displayValue: true, width: 1.4});
    } catch (e) {
        JsBarcode(el, code, {format: 'CODE128', height: 40, fontSize: 12, margin: 0, width: 1.2});
    }
});
</script>
</body>
</html>
