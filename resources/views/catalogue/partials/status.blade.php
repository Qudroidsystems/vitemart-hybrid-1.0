@php $map = ['pending' => ['warning', 'Waiting'], 'approved' => ['success', 'Approved'], 'rejected' => ['danger', 'Rejected'], 'cancelled' => ['secondary', 'Cancelled']]; [$c, $l] = $map[$s] ?? ['light', ucfirst($s)]; @endphp
<span class="badge bg-{{ $c }}-subtle text-{{ $c }}">{{ $l }}</span>
