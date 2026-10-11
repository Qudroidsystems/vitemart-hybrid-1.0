<?php

namespace App\Services\Reports;

use App\Models\OrderPayment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sales report for any period: totals, payment methods, every cashier, refunds,
 * voids, shifts (cash counts) and best sellers. Built from grouped SQL so it
 * stays quick on large stores. Used by the scheduled emails and the preview page.
 */
class SalesReportService
{
    /** Transactions listed in the email itself; the spreadsheet always has all of them. */
    public const EMAIL_TRANSACTION_LIMIT = 150;

    protected array $opt = [];
    protected Carbon $from;
    protected Carbon $to;
    protected array $cols = [];

    public function build(CarbonInterface $from, CarbonInterface $to, array $options = []): array
    {
        $this->setup($from, $to, $options);

        $summary = $this->summary();
        $methods = $this->methods();
        $cashiers = $this->cashiers();
        $methodColumns = collect($methods)->pluck('method')
            ->merge(collect($cashiers)->flatMap(fn ($c) => array_keys($c['methods'])))->unique()
            ->sortBy(fn ($m) => array_search($m, array_keys(OrderPayment::METHODS), true) === false ? 99 : array_search($m, array_keys(OrderPayment::METHODS), true))
            ->values()->all();

        $report = [
            'from' => $this->from,
            'to' => $this->to,
            'generated_at' => now(),
            'options' => $this->opt,
            'store' => $this->storeName(),
            'branch_label' => $this->branchLabel(),
            'summary' => $summary,
            'methods' => $methods,
            'method_columns' => $methodColumns,
            'method_labels' => OrderPayment::METHODS,
            'cashiers' => $cashiers,
            'refund_list' => $this->refundList(50),
            'void_list' => $this->voidList(50),
            'shifts' => $this->opt['include_shifts'] ? $this->shifts() : [],
            'products' => $this->opt['include_products'] ? $this->products(10) : [],
            'branches' => $this->branches(),
            'transactions' => [],
            'transactions_truncated' => false,
        ];

        if ($this->opt['include_transactions'] && $summary['transactions'] + $summary['voids'] > 0) {
            if ($summary['transactions'] + $summary['voids'] <= self::EMAIL_TRANSACTION_LIMIT) {
                foreach ($this->transactionRows() as $row) {
                    $report['transactions'][] = $row;
                }
            } else {
                $report['transactions_truncated'] = true;
            }
        }
        return $report;
    }

    // ------------------------------------------------------------------ setup

    protected function setup(CarbonInterface $from, CarbonInterface $to, array $options): void
    {
        $this->from = Carbon::instance($from);
        $this->to = Carbon::instance($to);
        $this->opt = array_merge([
            'channels' => 'pos',
            'branch_ids' => [],
            'include_profit' => false,
            'include_shifts' => true,
            'include_products' => true,
            'include_transactions' => true,
        ], $options);
        foreach (['orders' => ['channel', 'branch_id', 'subtotal', 'discount_amount', 'cost_total', 'cash_register_id', 'pos_shift_id', 'void_reason', 'voided_by', 'change_due'],
                     'pos_shifts' => ['branch_id', 'cash_register_id'], 'order_items' => ['base_quantity']] as $table => $cols) {
            foreach ($cols as $c) {
                $this->cols[$table . '.' . $c] = Schema::hasTable($table) && Schema::hasColumn($table, $c);
            }
        }
    }

    protected function has(string $tableCol): bool
    {
        return $this->cols[$tableCol] ?? false;
    }

    /** Orders in the period (all statuses), filtered by channel and branch. */
    protected function orders(string $alias = 'o'): Builder
    {
        $q = DB::table("orders as {$alias}")->whereBetween("{$alias}.order_date", [$this->from, $this->to]);
        if ($this->opt['channels'] !== 'all') {
            $q->where(fn ($w) => $this->has('orders.channel')
                ? $w->where("{$alias}.channel", 'pos')->orWhere("{$alias}.id", 'like', 'POS-%')
                : $w->where("{$alias}.id", 'like', 'POS-%'));
        }
        if ($this->opt['branch_ids'] && $this->has('orders.branch_id')) {
            $q->whereIn("{$alias}.branch_id", $this->opt['branch_ids']);
        }
        return $q;
    }

    protected function sold(string $alias = 'o'): Builder
    {
        return $this->orders($alias)->where("{$alias}.status", '!=', 'cancelled');
    }

    // ------------------------------------------------------------------ sections

    protected function summary(): array
    {
        $sub = $this->has('orders.subtotal') ? 'COALESCE(o.subtotal, o.total_amount + COALESCE(o.discount_amount, 0))' : 'o.total_amount';
        $disc = $this->has('orders.discount_amount') ? 'COALESCE(o.discount_amount, 0)' : '0';
        $cost = $this->has('orders.cost_total') ? 'COALESCE(o.cost_total, 0)' : '0';

        $s = $this->sold()->selectRaw("COUNT(*) as n, COALESCE(SUM(o.total_amount),0) as net, COALESCE(SUM({$sub}),0) as gross,
            COALESCE(SUM({$disc}),0) as disc, COALESCE(SUM({$cost}),0) as cost, COUNT(DISTINCT o.customer_id) as customers,
            MIN(o.order_date) as first_at, MAX(o.order_date) as last_at")->first();
        $v = $this->orders()->where('o.status', 'cancelled')->selectRaw('COUNT(*) as n, COALESCE(SUM(o.total_amount),0) as amt')->first();

        $refund = $this->refunds()->selectRaw('COUNT(*) as n, COALESCE(SUM(r.amount),0) as amt')->first();

        $qtyCol = $this->has('order_items.base_quantity') ? 'COALESCE(i.base_quantity, i.quantity)' : 'i.quantity';
        $items = (float) DB::table('order_items as i')->joinSub($this->sold()->select('o.id'), 's', 's.id', '=', 'i.order_id')
            ->selectRaw("COALESCE(SUM({$qtyCol}),0) as q")->value('q');

        $net = (float) $s->net;
        $profit = $net - (float) $s->cost;
        return [
            'transactions' => (int) $s->n,
            'gross' => round((float) $s->gross, 2),
            'discounts' => round((float) $s->disc, 2),
            'net' => round($net, 2),
            'refunds' => round((float) $refund->amt, 2),
            'refund_count' => (int) $refund->n,
            'net_after_refunds' => round($net - (float) $refund->amt, 2),
            'voids' => (int) $v->n,
            'void_value' => round((float) $v->amt, 2),
            'items' => round($items, 3),
            'average' => $s->n ? round($net / $s->n, 2) : 0,
            'customers' => (int) $s->customers,
            'cost' => round((float) $s->cost, 2),
            'profit' => round($profit, 2),
            'margin' => $net > 0 ? round($profit / $net * 100, 1) : 0,
            'first_at' => $s->first_at ? Carbon::parse($s->first_at) : null,
            'last_at' => $s->last_at ? Carbon::parse($s->last_at) : null,
        ];
    }

    protected function methods(): array
    {
        $rows = DB::table('order_payments as p')->joinSub($this->sold()->select('o.id'), 's', 's.id', '=', 'p.order_id')
            ->selectRaw("p.method, COALESCE(SUM(p.amount),0) as amt, COUNT(DISTINCT p.order_id) as n,
                COALESCE(SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END),0) as pending")
            ->groupBy('p.method')->get();
        return $rows->map(fn ($r) => [
            'method' => $r->method,
            'label' => OrderPayment::METHODS[$r->method] ?? ucfirst((string) $r->method),
            'amount' => round((float) $r->amt, 2),
            'count' => (int) $r->n,
            'pending' => round((float) $r->pending, 2),
        ])->sortByDesc('amount')->values()->all();
    }

    protected function refunds(): Builder
    {
        // Refunds count on the day they were given back, whatever day the sale was.
        $q = DB::table('refunds as r')->join('orders as o', 'o.id', '=', 'r.order_id')
            ->whereBetween('r.created_at', [$this->from, $this->to]);
        if ($this->opt['channels'] !== 'all') {
            $q->where(fn ($w) => $this->has('orders.channel')
                ? $w->where('o.channel', 'pos')->orWhere('o.id', 'like', 'POS-%')
                : $w->where('o.id', 'like', 'POS-%'));
        }
        if ($this->opt['branch_ids'] && $this->has('orders.branch_id')) {
            $q->whereIn('o.branch_id', $this->opt['branch_ids']);
        }
        return $q;
    }

    protected function cashiers(): array
    {
        $disc = $this->has('orders.discount_amount') ? 'COALESCE(o.discount_amount, 0)' : '0';
        $cost = $this->has('orders.cost_total') ? 'COALESCE(o.cost_total, 0)' : '0';
        $sales = $this->sold()->selectRaw("o.user_id, COUNT(*) as n, COALESCE(SUM(o.total_amount),0) as net, COALESCE(SUM({$disc}),0) as disc,
                COALESCE(SUM({$cost}),0) as cost, MIN(o.order_date) as first_at, MAX(o.order_date) as last_at")
            ->groupBy('o.user_id')->get()->keyBy('user_id');
        $pay = DB::table('order_payments as p')->joinSub($this->sold()->select('o.id', 'o.user_id'), 's', 's.id', '=', 'p.order_id')
            ->selectRaw('s.user_id, p.method, COALESCE(SUM(p.amount),0) as amt')->groupBy('s.user_id', 'p.method')->get()->groupBy('user_id');
        $voids = $this->orders()->where('o.status', 'cancelled')->selectRaw('o.user_id, COUNT(*) as n, COALESCE(SUM(o.total_amount),0) as amt')
            ->groupBy('o.user_id')->get()->keyBy('user_id');
        $refunds = $this->refunds()->selectRaw('r.user_id, COUNT(*) as n, COALESCE(SUM(r.amount),0) as amt')->groupBy('r.user_id')->get()->keyBy('user_id');

        $ids = collect([$sales->keys(), $voids->keys(), $refunds->keys()])->flatten()->filter()->unique()->values();
        $names = $this->userNames($ids->all());

        return $ids->map(function ($id) use ($sales, $pay, $voids, $refunds, $names) {
            $s = $sales->get($id);
            $net = (float) ($s->net ?? 0);
            return [
                'id' => $id,
                'name' => $names[$id] ?? ('User #' . $id),
                'transactions' => (int) ($s->n ?? 0),
                'net' => round($net, 2),
                'discounts' => round((float) ($s->disc ?? 0), 2),
                'average' => !empty($s->n) ? round($net / $s->n, 2) : 0,
                'profit' => round($net - (float) ($s->cost ?? 0), 2),
                'methods' => collect($pay->get($id, []))->mapWithKeys(fn ($r) => [$r->method => round((float) $r->amt, 2)])->all(),
                'refunds' => round((float) ($refunds->get($id)->amt ?? 0), 2),
                'refund_count' => (int) ($refunds->get($id)->n ?? 0),
                'voids' => (int) ($voids->get($id)->n ?? 0),
                'void_value' => round((float) ($voids->get($id)->amt ?? 0), 2),
                'first_at' => !empty($s->first_at) ? Carbon::parse($s->first_at) : null,
                'last_at' => !empty($s->last_at) ? Carbon::parse($s->last_at) : null,
            ];
        })->sortByDesc('net')->values()->all();
    }

    protected function shifts(): array
    {
        if (!Schema::hasTable('pos_shifts')) {
            return [];
        }
        $q = DB::table('pos_shifts as h')
            ->where('h.opened_at', '<=', $this->to)
            ->where(fn ($w) => $w->whereNull('h.closed_at')->orWhere('h.closed_at', '>=', $this->from))
            ->orderBy('h.opened_at');
        if ($this->opt['branch_ids'] && $this->has('pos_shifts.branch_id')) {
            $q->whereIn('h.branch_id', $this->opt['branch_ids']);
        }
        $rows = $q->limit(200)->get();
        $names = $this->userNames($rows->pluck('user_id')->all());
        $registers = $this->has('pos_shifts.cash_register_id') && Schema::hasTable('cash_registers')
            ? DB::table('cash_registers')->whereIn('id', $rows->pluck('cash_register_id')->filter()->all())->pluck('name', 'id') : collect();
        return $rows->map(fn ($r) => [
            'cashier' => $names[$r->user_id] ?? ('User #' . $r->user_id),
            'register' => ($this->has('pos_shifts.cash_register_id') ? ($registers[$r->cash_register_id] ?? null) : null) ?: ($r->register ?? ''),
            'opened_at' => Carbon::parse($r->opened_at),
            'closed_at' => $r->closed_at ? Carbon::parse($r->closed_at) : null,
            'status' => $r->status,
            'opening_float' => (float) $r->opening_float,
            'expected' => $r->expected_cash !== null ? (float) $r->expected_cash : null,
            'counted' => $r->counted_cash !== null ? (float) $r->counted_cash : null,
            'variance' => $r->variance !== null ? (float) $r->variance : null,
        ])->all();
    }

    public function products(?int $limit = 10): array
    {
        $qtyCol = $this->has('order_items.base_quantity') ? 'COALESCE(i.base_quantity, i.quantity)' : 'i.quantity';
        $q = DB::table('order_items as i')->joinSub($this->sold()->select('o.id'), 's', 's.id', '=', 'i.order_id')
            ->selectRaw("i.product_id, MAX(i.title) as title, COALESCE(SUM({$qtyCol}),0) as qty, COALESCE(SUM(i.total_price),0) as revenue, COUNT(DISTINCT i.order_id) as orders")
            ->groupBy('i.product_id')->orderByDesc('revenue');
        if ($limit) {
            $q->limit($limit);
        }
        return $q->get()->map(fn ($r) => [
            'title' => $r->title, 'qty' => round((float) $r->qty, 3), 'revenue' => round((float) $r->revenue, 2), 'orders' => (int) $r->orders,
        ])->all();
    }

    protected function branches(): array
    {
        if (!$this->has('orders.branch_id') || !Schema::hasTable('branches')) {
            return [];
        }
        $rows = $this->sold()->selectRaw('o.branch_id, COUNT(*) as n, COALESCE(SUM(o.total_amount),0) as net')->groupBy('o.branch_id')->get();
        if ($rows->count() < 2) {
            return [];
        }
        $b = DB::table('branches')->whereIn('id', $rows->pluck('branch_id')->filter()->all())->get()->keyBy('id');
        return $rows->map(fn ($r) => [
            'name' => isset($b[$r->branch_id]) ? $b[$r->branch_id]->code . ' – ' . $b[$r->branch_id]->name : 'Unassigned',
            'transactions' => (int) $r->n, 'net' => round((float) $r->net, 2),
        ])->sortByDesc('net')->values()->all();
    }

    public function refundList(?int $limit = 50): array
    {
        $q = $this->refunds()->select('r.order_id', 'r.user_id', 'r.amount', 'r.reason', 'r.created_at')->orderBy('r.created_at');
        if ($limit) {
            $q->limit($limit);
        }
        $rows = $q->get();
        $names = $this->userNames($rows->pluck('user_id')->all());
        return $rows->map(fn ($r) => [
            'order_id' => $r->order_id, 'at' => Carbon::parse($r->created_at), 'by' => $names[$r->user_id] ?? '—',
            'amount' => (float) $r->amount, 'reason' => (string) $r->reason,
        ])->all();
    }

    public function voidList(?int $limit = 50): array
    {
        $cols = ['o.id', 'o.user_id', 'o.total_amount', 'o.order_date'];
        if ($this->has('orders.void_reason')) {
            $cols[] = 'o.void_reason';
        }
        if ($this->has('orders.voided_by')) {
            $cols[] = 'o.voided_by';
        }
        $q = $this->orders()->where('o.status', 'cancelled')->select($cols)->orderBy('o.order_date');
        if ($limit) {
            $q->limit($limit);
        }
        $rows = $q->get();
        $names = $this->userNames($rows->pluck('user_id')->merge($rows->pluck('voided_by'))->filter()->all());
        return $rows->map(fn ($r) => [
            'order_id' => $r->id, 'at' => Carbon::parse($r->order_date), 'cashier' => $names[$r->user_id] ?? '—',
            'amount' => (float) $r->total_amount, 'reason' => (string) ($r->void_reason ?? ''),
            'voided_by' => isset($r->voided_by) ? ($names[$r->voided_by] ?? '') : '',
        ])->all();
    }

    /**
     * Every transaction in the period, oldest first (generator – safe for very large periods).
     * Needs build() (or prepare()) to have been called first.
     */
    public function transactionRows(): \Generator
    {
        $cols = ['o.id', 'o.order_date', 'o.user_id', 'o.customer_id', 'o.status', 'o.total_amount', 'o.payment_method'];
        foreach (['subtotal', 'discount_amount', 'cash_register_id', 'branch_id', 'change_due'] as $c) {
            if ($this->has('orders.' . $c)) {
                $cols[] = 'o.' . $c;
            }
        }
        $users = [];
        $registers = Schema::hasTable('cash_registers') ? DB::table('cash_registers')->pluck('name', 'id')->all() : [];
        $branches = Schema::hasTable('branches') ? DB::table('branches')->pluck('code', 'id')->all() : [];

        $lastDate = null;
        $lastId = '';
        do {
            $q = $this->orders()->select($cols)->orderBy('o.order_date')->orderBy('o.id')->limit(500);
            if ($lastDate !== null) {
                $q->where(fn ($w) => $w->where('o.order_date', '>', $lastDate)
                    ->orWhere(fn ($x) => $x->where('o.order_date', $lastDate)->where('o.id', '>', $lastId)));
            }
            $chunk = $q->get();
            if ($chunk->isEmpty()) {
                break;
            }
            $ids = $chunk->pluck('id')->all();
            $pays = DB::table('order_payments')->whereIn('order_id', $ids)->select('order_id', 'method', 'amount', 'status')->get()->groupBy('order_id');
            $counts = DB::table('order_items')->whereIn('order_id', $ids)->selectRaw('order_id, COUNT(*) as lines_n')->groupBy('order_id')->pluck('lines_n', 'order_id');
            $customers = DB::table('customers')->whereIn('id', $chunk->pluck('customer_id')->filter()->unique()->all())
                ->get(['id', 'first_name', 'last_name'])->mapWithKeys(fn ($c) => [$c->id => trim($c->first_name . ' ' . $c->last_name)])->all();
            $need = array_diff($chunk->pluck('user_id')->filter()->unique()->all(), array_keys($users));
            $users += $this->userNames($need);

            foreach ($chunk as $o) {
                $p = collect($pays->get($o->id, []));
                $byMethod = $p->groupBy('method')->map(fn ($g) => round((float) $g->sum('amount'), 2))->all();
                yield [
                    'id' => $o->id,
                    'at' => Carbon::parse($o->order_date),
                    'cashier' => $users[$o->user_id] ?? ('User #' . $o->user_id),
                    'cashier_id' => $o->user_id,
                    'register' => isset($o->cash_register_id) ? ($registers[$o->cash_register_id] ?? '') : '',
                    'branch' => isset($o->branch_id) ? ($branches[$o->branch_id] ?? '') : '',
                    'customer' => $o->customer_id ? ($customers[$o->customer_id] ?? '') : '',
                    'lines' => (int) ($counts[$o->id] ?? 0),
                    'subtotal' => isset($o->subtotal) && $o->subtotal !== null ? (float) $o->subtotal : (float) $o->total_amount + (float) ($o->discount_amount ?? 0),
                    'discount' => (float) ($o->discount_amount ?? 0),
                    'total' => (float) $o->total_amount,
                    'status' => $o->status,
                    'methods' => $byMethod,
                    'pending' => $p->where('status', 'pending')->isNotEmpty(),
                    'change' => (float) ($o->change_due ?? 0),
                ];
                $lastDate = $o->order_date;
                $lastId = $o->id;
            }
        } while ($chunk->count() === 500);
    }

    /** Re-use the last build()'s period and options without rebuilding (for exports). */
    public function prepare(CarbonInterface $from, CarbonInterface $to, array $options = []): static
    {
        $this->setup($from, $to, $options);
        return $this;
    }

    // ------------------------------------------------------------------ helpers

    protected function userNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) {
            return [];
        }
        return DB::table('users')->whereIn('id', $ids)->get(['id', 'first_name', 'last_name', 'email'])
            ->mapWithKeys(fn ($u) => [$u->id => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: (string) $u->email])->all();
    }

    protected function storeName(): string
    {
        try {
            $s = \App\Models\StoreSetting::first();
            foreach (['store_name', 'name', 'business_name'] as $f) {
                if (!empty($s?->{$f})) {
                    return (string) $s->{$f};
                }
            }
        } catch (\Throwable $e) {
        }
        return (string) config('app.name');
    }

    protected function branchLabel(): ?string
    {
        if (!$this->opt['branch_ids'] || !Schema::hasTable('branches')) {
            return null;
        }
        return DB::table('branches')->whereIn('id', $this->opt['branch_ids'])->orderBy('number')->get(['code', 'name'])
            ->map(fn ($b) => $b->code . ' – ' . $b->name)->implode(', ');
    }
}
