@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box">
        <h4 class="mb-sm-0">What branches may change</h4>
        <p class="text-muted mb-0 mt-1 fs-sm">For products in the head-office catalogue. Products a branch creates itself are fully under that branch's control until head office adopts them.</p>
    </div>

    @include('layouts.partials.flash')

    <form method="POST" action="{{ route('catalogue.policies.update') }}" class="card">
        @csrf
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Field</th><th class="text-center">Head office only</th><th class="text-center">Branch asks, head office approves</th><th class="text-center">Branch decides</th><th>Notes</th></tr></thead>
                <tbody>
                @foreach ($policies as $p)
                    <tr>
                        <td class="fw-semibold">{{ $p->label }}</td>
                        @foreach (['locked', 'request', 'override'] as $rule)
                            <td class="text-center"><input class="form-check-input" type="radio" name="rules[{{ $p->field }}]" value="{{ $rule }}" @checked($p->branch_rule === $rule) @disabled(!$canEdit)></td>
                        @endforeach
                        <td class="fs-sm text-muted">{{ $p->help }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($canEdit)
            <div class="card-footer text-end"><button class="btn btn-primary">Save rules</button></div>
        @endif
    </form>
</div>
</div>
</div>
@endsection
