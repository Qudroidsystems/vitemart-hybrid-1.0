<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Stock Levels Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .filters {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        .filters span {
            display: inline-block;
            margin-right: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #f2f2f2;
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        .status-in-stock {
            color: #28a745;
        }
        .status-low-stock {
            color: #ffc107;
        }
        .status-out-of-stock {
            color: #6c757d;
        }
        .status-negative {
            color: #dc3545;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .page-break {
            page-break-after: always;
        }
        .summary {
            margin-top: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Stock Levels Report</h1>
        <p>Generated on: {{ $filters['date'] }}</p>
        <p>Total Products: {{ $products->count() }}</p>
    </div>

    @if($filters['stock_status'] || $filters['category_id'] || $filters['brand_id'] || $filters['search'])
    <div class="filters">
        <strong>Applied Filters:</strong><br>
        @if($filters['stock_status'])
            <span>Status: {{ ucfirst(str_replace('_', ' ', $filters['stock_status'])) }}</span>
        @endif
        @if($filters['search'])
            <span>Search: "{{ $filters['search'] }}"</span>
        @endif
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>SKU</th>
                <th>Category</th>
                <th>Brand</th>
                <th>Price</th>
                @foreach($locations as $location)
                    <th class="text-center">{{ $location->name }}</th>
                @endforeach
                <th>Total Stock</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $index => $product)
                @php
                    $totalStock = 0;
                    foreach($locations as $location) {
                        $stock = $locationStockData[$product->id][$location->id] ?? 0;
                        $totalStock += $stock;
                    }

                    if ($totalStock > 10) {
                        $statusClass = 'status-in-stock';
                        $statusText = 'In Stock';
                    } elseif ($totalStock > 0) {
                        $statusClass = 'status-low-stock';
                        $statusText = 'Low Stock';
                    } elseif ($totalStock == 0) {
                        $statusClass = 'status-out-of-stock';
                        $statusText = 'Out of Stock';
                    } else {
                        $statusClass = 'status-negative';
                        $statusText = 'Negative';
                    }
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $product->title }}</td>
                    <td>{{ $product->sku }}</td>
                    <td>{{ $product->category?->name ?? '-' }}</td>
                    <td>{{ $product->brand?->name ?? '-' }}</td>
                    <td class="text-right">${{ number_format($product->price, 2) }}</td>

                    @foreach($locations as $location)
                        @php
                            $stock = $locationStockData[$product->id][$location->id] ?? 0;
                            $stockClass = $stock > 10 ? 'status-in-stock' :
                                         ($stock > 0 ? 'status-low-stock' :
                                         ($stock == 0 ? 'status-out-of-stock' : 'status-negative'));
                        @endphp
                        <td class="text-center {{ $stockClass }}">
                            {{ $stock }}
                        </td>
                    @endforeach

                    <td class="text-center {{ $statusClass }}"><strong>{{ $totalStock }}</strong></td>
                    <td class="{{ $statusClass }}">{{ $statusText }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <h3>Summary</h3>
        <p>Total Products: {{ $products->count() }}</p>
        <p>Total Locations: {{ $locations->count() }}</p>
        <p>In Stock Products: {{ $products->where('stock', '>', 10)->count() }}</p>
        <p>Low Stock Products: {{ $products->where('stock', '>', 0)->where('stock', '<=', 10)->count() }}</p>
        <p>Out of Stock Products: {{ $products->where('stock', '<=', 0)->count() }}</p>
    </div>

    <div class="footer">
        <p>Generated by ViteMart 2.0</p>
        <p>Page {{ $pdf->getPageNumbers()->getCurrentPage() }} of {{ $pdf->getPageNumbers()->getTotalPages() }}</p>
    </div>
</body>
</html>
