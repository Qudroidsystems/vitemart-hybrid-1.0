@php
    $colors = ['requested' => 'warning', 'submitted' => 'warning', 'draft' => 'secondary', 'approved' => 'info', 'sent' => 'info', 'dispatched' => 'primary',
        'partial' => 'primary', 'received' => 'success', 'closed' => 'success', 'posted' => 'success', 'open' => 'warning', 'cancelled' => 'secondary', 'rejected' => 'danger'];
    $c = $colors[$status] ?? 'light';
@endphp
<span class="badge bg-{{ $c }}-subtle text-{{ $c }}">{{ $label ?? ucfirst($status) }}</span>
