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
                                                    <td>{{ $setting?->currency_symbol ?? '$' }} ({{ $setting?->currency_code ?? 'USD' }})</td>
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
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Currency <span class="text-danger">*</span></label>
                            <select name="currency_code" class="form-select" required size="12">
                                @php
                                    $currencies = [
                                        ['code' => 'USD', 'symbol' => '$', 'name' => 'United States Dollar'],
                                        ['code' => 'EUR', 'symbol' => '€', 'name' => 'Euro'],
                                        ['code' => 'GBP', 'symbol' => '£', 'name' => 'British Pound Sterling'],
                                        ['code' => 'JPY', 'symbol' => '¥', 'name' => 'Japanese Yen'],
                                        ['code' => 'AUD', 'symbol' => '$', 'name' => 'Australian Dollar'],
                                        ['code' => 'CAD', 'symbol' => '$', 'name' => 'Canadian Dollar'],
                                        ['code' => 'CHF', 'symbol' => 'Fr', 'name' => 'Swiss Franc'],
                                        ['code' => 'CNY', 'symbol' => '¥', 'name' => 'Chinese Yuan'],
                                        ['code' => 'INR', 'symbol' => '₹', 'name' => 'Indian Rupee'],
                                        ['code' => 'BRL', 'symbol' => 'R$', 'name' => 'Brazilian Real'],
                                        ['code' => 'RUB', 'symbol' => '₽', 'name' => 'Russian Ruble'],
                                        ['code' => 'KRW', 'symbol' => '₩', 'name' => 'South Korean Won'],
                                        ['code' => 'MXN', 'symbol' => '$', 'name' => 'Mexican Peso'],
                                        ['code' => 'ZAR', 'symbol' => 'R', 'name' => 'South African Rand'],
                                        ['code' => 'TRY', 'symbol' => '₺', 'name' => 'Turkish Lira'],
                                        ['code' => 'SGD', 'symbol' => '$', 'name' => 'Singapore Dollar'],
                                        ['code' => 'HKD', 'symbol' => '$', 'name' => 'Hong Kong Dollar'],
                                        ['code' => 'NZD', 'symbol' => '$', 'name' => 'New Zealand Dollar'],
                                        ['code' => 'SEK', 'symbol' => 'kr', 'name' => 'Swedish Krona'],
                                        ['code' => 'NOK', 'symbol' => 'kr', 'name' => 'Norwegian Krone'],
                                        ['code' => 'PLN', 'symbol' => 'zł', 'name' => 'Polish Zloty'],
                                        ['code' => 'THB', 'symbol' => '฿', 'name' => 'Thai Baht'],
                                        ['code' => 'IDR', 'symbol' => 'Rp', 'name' => 'Indonesian Rupiah'],
                                        ['code' => 'MYR', 'symbol' => 'RM', 'name' => 'Malaysian Ringgit'],
                                        ['code' => 'PHP', 'symbol' => '₱', 'name' => 'Philippine Peso'],
                                        ['code' => 'VND', 'symbol' => '₫', 'name' => 'Vietnamese Dong'],
                                        ['code' => 'EGP', 'symbol' => '£', 'name' => 'Egyptian Pound'],
                                        ['code' => 'SAR', 'symbol' => '﷼', 'name' => 'Saudi Riyal'],
                                        ['code' => 'AED', 'symbol' => 'د.إ', 'name' => 'UAE Dirham'],
                                        ['code' => 'ILS', 'symbol' => '₪', 'name' => 'Israeli New Shekel'],
                                        ['code' => 'COP', 'symbol' => '$', 'name' => 'Colombian Peso'],
                                        ['code' => 'CLP', 'symbol' => '$', 'name' => 'Chilean Peso'],
                                        ['code' => 'ARS', 'symbol' => '$', 'name' => 'Argentine Peso'],
                                        ['code' => 'CZK', 'symbol' => 'Kč', 'name' => 'Czech Koruna'],
                                        ['code' => 'HUF', 'symbol' => 'Ft', 'name' => 'Hungarian Forint'],
                                        ['code' => 'DKK', 'symbol' => 'kr', 'name' => 'Danish Krone'],
                                        ['code' => 'PKR', 'symbol' => '₨', 'name' => 'Pakistani Rupee'],
                                        ['code' => 'BDT', 'symbol' => '৳', 'name' => 'Bangladeshi Taka'],
                                        ['code' => 'NGN', 'symbol' => '₦', 'name' => 'Nigerian Naira'],
                                        ['code' => 'KES', 'symbol' => 'KSh', 'name' => 'Kenyan Shilling'],
                                        ['code' => 'GHS', 'symbol' => '₵', 'name' => 'Ghanaian Cedi'],
                                        ['code' => 'UAH', 'symbol' => '₴', 'name' => 'Ukrainian Hryvnia'],
                                        ['code' => 'RON', 'symbol' => 'lei', 'name' => 'Romanian Leu'],
                                        ['code' => 'BGN', 'symbol' => 'лв', 'name' => 'Bulgarian Lev'],
                                        ['code' => 'HRK', 'symbol' => 'kn', 'name' => 'Croatian Kuna'],
                                        ['code' => 'RSD', 'symbol' => 'дин', 'name' => 'Serbian Dinar'],
                                        ['code' => 'TWD', 'symbol' => 'NT$', 'name' => 'New Taiwan Dollar'],
                                        ['code' => 'KWD', 'symbol' => 'د.ك', 'name' => 'Kuwaiti Dinar'],
                                        ['code' => 'QAR', 'symbol' => '﷼', 'name' => 'Qatari Rial'],
                                        ['code' => 'OMR', 'symbol' => '﷼', 'name' => 'Omani Rial'],
                                    ];
                                @endphp

                                @foreach($currencies as $currency)
                                    <option value="{{ $currency['code'] }}"
                                            data-symbol="{{ $currency['symbol'] }}"
                                            {{ old('currency_code', $setting?->currency_code) == $currency['code'] ? 'selected' : '' }}>
                                        {{ $currency['symbol'] }} {{ $currency['code'] }} - {{ $currency['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-1">Scroll inside the list to view all currencies</small>
                        </div>
                    </div>

                    <!-- Hidden currency symbol field -->
                    <input type="hidden" name="currency_symbol" id="currency_symbol" value="{{ old('currency_symbol', $setting?->currency_symbol) }}">

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

<!-- JavaScript for currency symbol sync -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const currencySelect = document.querySelector('select[name="currency_code"]');
        const symbolInput = document.getElementById('currency_symbol');

        function updateSymbol() {
            const selectedOption = currencySelect.options[currencySelect.selectedIndex];
            symbolInput.value = selectedOption.dataset.symbol || '';
        }

        // Initial sync
        updateSymbol();

        // Update on change
        currencySelect.addEventListener('change', updateSymbol);
    });
</script>
@endsection
