<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\MultiBranch\Install;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:View purchasing|Manage suppliers', ['only' => ['index']]);
        $this->middleware('permission:Manage suppliers', ['only' => ['store', 'update']]);
    }

    public function index(Request $request)
    {
        $pagetitle = 'Suppliers';
        $q = Supplier::query()->withCount('purchaseOrders');
        if ($s = trim((string) $request->get('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "{$s}%")->orWhere('phone', 'like', "%{$s}%"));
        }
        $suppliers = $q->orderByDesc('is_active')->orderBy('name')->paginate(50)->withQueryString();
        // a branch uses head office's supplier list
        $canEdit = auth()->user()->can('Manage suppliers') && !Install::isBranch();
        return view('purchasing.suppliers', compact('pagetitle', 'suppliers', 'canEdit'));
    }

    protected function rules(?Supplier $s = null): array
    {
        return [
            'code' => ['nullable', 'alpha_dash', 'max:20', Rule::unique('suppliers', 'code')->ignore($s?->id)],
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->ignore($s?->id)],
            'contact_person' => 'nullable|string|max:120', 'phone' => 'nullable|string|max:40', 'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255', 'tax_id' => 'nullable|string|max:40', 'payment_terms_days' => 'nullable|integer|min:0|max:365',
            'lead_time_days' => 'nullable|integer|min:0|max:365', 'bank_details' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:500',
        ];
    }

    public function store(Request $request)
    {
        abort_if(Install::isBranch(), 403, 'Suppliers are managed at head office.');
        $data = $request->validate($this->rules());
        $data['code'] = isset($data['code']) ? strtoupper($data['code']) : null;
        $s = Supplier::create($data + ['payment_terms_days' => 0, 'is_active' => true]);
        return $request->expectsJson() ? response()->json(['success' => true, 'supplier' => $s]) : back()->with('success', 'Supplier added.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        abort_if(Install::isBranch(), 403, 'Suppliers are managed at head office.');
        $data = $request->validate($this->rules($supplier));
        $data['code'] = isset($data['code']) ? strtoupper($data['code']) : null;
        $data['is_active'] = $request->boolean('is_active');
        $supplier->update($data);
        return back()->with('success', 'Saved.');
    }
}
