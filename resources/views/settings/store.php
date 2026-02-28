@extends('layouts.master') <!-- Or your admin layout -->

@section('title', 'Store Settings')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Store Settings</h4>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <form action="{{ route('settings.store.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Store Name <span class="text-danger">*</span></label>
                                    <input type="text" name="store_name" class="form-control" value="{{ old('store_name', $setting->store_name) }}" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Motto</label>
                                    <input type="text" name="motto" class="form-control" value="{{ old('motto', $setting->motto) }}">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="3">{{ old('address', $setting->address) }}</textarea>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $setting->phone) }}">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="{{ old('email', $setting->email) }}">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tax ID / VAT</label>
                                    <input type="text" name="tax_id" class="form-control" value="{{ old('tax_id', $setting->tax_id) }}">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Footer Note (e.g., Thank you message)</label>
                            <textarea name="footer_note" class="form-control" rows="2">{{ old('footer_note', $setting->footer_note) }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Logo (Recommended: 300x100 px, PNG/SVG)</label>
                            <input type="file" name="logo" class="form-control" accept="image/*">

                            @if($setting->logo)
                                <div class="mt-3">
                                    <p>Current Logo:</p>
                                    <img src="{{ $setting->getLogoUrlAttribute() }}" alt="Current Logo" style="max-height: 100px; border: 1px solid #ddd; padding: 5px;">
                                </div>
                            @endif
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
