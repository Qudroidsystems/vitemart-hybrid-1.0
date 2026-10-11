<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\StockLocation;
use App\Services\MultiBranch\Install;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Tills at this branch: name, which stock location they sell from, active or not. */
class PosRegisterController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:Manage cash registers');
    }

    public function index()
    {
        $pagetitle = 'Tills';
        $registers = CashRegister::here()->with('location:id,name')->orderBy('code')->get();
        $open = \App\Models\PosShift::whereIn('cash_register_id', $registers->pluck('id'))->where('status', 'open')
            ->with('user:id,first_name,last_name')->get()->keyBy('cash_register_id');
        $locations = StockLocation::where('branch_id', Install::branchId())->where('is_active', true)
            ->where(fn ($q) => $q->whereIn('type', ['store', 'warehouse'])->orWhereNull('type'))->orderBy('name')->get(['id', 'name']);
        return view('pos.registers', compact('pagetitle', 'registers', 'open', 'locations'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['code'] = strtoupper($data['code']);
        CashRegister::create($data + ['branch_id' => Install::branchId()]);
        return back()->with('success', 'Till added.');
    }

    public function update(Request $request, CashRegister $register)
    {
        abort_unless($register->branch_id === Install::branchId(), 404);
        $data = $request->validate($this->rules($register));
        $data['code'] = strtoupper($data['code']);
        $register->update($data);
        return back()->with('success', 'Saved.');
    }

    protected function rules(?CashRegister $r = null): array
    {
        return [
            'code' => ['required', 'alpha_dash', 'max:20', Rule::unique('cash_registers', 'code')->where('branch_id', Install::branchId())->ignore($r?->id)],
            'name' => 'required|string|max:100',
            'stock_location_id' => ['nullable', 'integer', Rule::exists('stock_locations', 'id')->where('branch_id', Install::branchId())],
            'status' => 'required|in:active,inactive',
            'receipt_printer' => 'nullable|string|max:120',
        ];
    }
}
