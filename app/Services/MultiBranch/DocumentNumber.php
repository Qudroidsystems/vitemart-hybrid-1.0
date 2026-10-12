<?php

namespace App\Services\MultiBranch;

use Illuminate\Support\Facades\DB;

/**
 * Human-readable document numbers that can never clash between branches:
 *   receipt  → IKJ-261011-000123
 *   transfer → TRF-IKJ-000045
 */
class DocumentNumber
{
    public const FORMATS = [
        'receipt' => '{BR}-{YMD}-{SEQ6}',
        'adjustment' => 'ADJ-{BR}-{SEQ6}',
        'count' => 'STC-{BR}-{SEQ6}',
        'receive' => 'GRN-{BR}-{SEQ6}',
        'damage' => 'DMG-{BR}-{SEQ6}',
        'expired' => 'EXP-{BR}-{SEQ6}',
        'transfer' => 'TRF-{BR}-{SEQ6}',
        'receipt_note' => 'RCV-{BR}-{SEQ6}',
        'po' => 'PO-{BR}-{SEQ6}',
        'grn' => 'GRN-{BR}-{SEQ6}',
        'supplier_return' => 'SRN-{BR}-{SEQ6}',
        'refund' => 'RFD-{BR}-{SEQ6}',
    ];

    /**
     * Head office: the highest number each document type has reached for one branch (from what the branch synced).
     * Used after a branch restores a backup, so it never re-issues a number head office already has.
     */
    public static function highestFor(int $branchId, string $code): array
    {
        $seq = fn ($table, $col, $prefix, $where = null) => (int) DB::table($table)
            ->when($where, $where)
            ->where($col, 'like', $prefix . '%')
            ->selectRaw("MAX(CAST(SUBSTRING_INDEX({$col}, '-', -1) AS UNSIGNED)) m")->value('m');
        $out = ['receipt' => $seq('orders', 'id', $code . '-', fn ($q) => $q->where('branch_id', $branchId))];
        foreach (['adjustment' => 'ADJ', 'count' => 'STC', 'receive' => 'GRN', 'damage' => 'DMG', 'expired' => 'EXP'] as $t => $p) {
            $out[$t] = \Illuminate\Support\Facades\Schema::hasTable('stock_adjustments') ? $seq('stock_adjustments', 'reference', "{$p}-{$code}-") : 0;
        }
        foreach (['transfer' => ['stock_transfers', 'TRF'], 'po' => ['purchase_orders', 'PO'], 'grn' => ['goods_receipts', 'GRN'],
                     'supplier_return' => ['supplier_returns', 'SRN']] as $t => [$table, $p]) {
            $out[$t] = \Illuminate\Support\Facades\Schema::hasTable($table) ? $seq($table, 'number', "{$p}-{$code}-") : 0;
        }
        $out['receive'] = max($out['receive'], $out['grn']);
        $out['grn'] = $out['receive'];
        return $out;
    }

    /** Branch: make sure every counter is past what head office has seen (or jump ahead if it can't be asked). */
    public static function catchUp(?array $highest, int $jump = 1000): array
    {
        $branchId = Install::branchId() ?? 0;
        $done = [];
        foreach (array_keys(self::FORMATS) as $type) {
            $cur = (int) DB::table('document_sequences')->where('branch_id', $branchId)->where('doc_type', $type)->value('next_value');
            $want = $highest !== null ? (int) ($highest[$type] ?? 0) + 1 : ($cur ? $cur + $jump : 0);
            if ($want > $cur) {
                DB::table('document_sequences')->updateOrInsert(['branch_id' => $branchId, 'doc_type' => $type],
                    ['next_value' => $want, 'updated_at' => now(), 'created_at' => now()]);
                $done[$type] = $want;
            }
        }
        return $done;
    }

    public static function next(string $type, ?int $branchId = null, ?string $branchCode = null): string
    {
        $branchId = $branchId ?? Install::branchId() ?? 0;
        $branchCode = $branchCode ?? Install::branchCode();

        $seq = DB::transaction(function () use ($branchId, $type) {
            $row = DB::table('document_sequences')->where('branch_id', $branchId)->where('doc_type', $type)->lockForUpdate()->first();
            if (!$row) {
                DB::table('document_sequences')->insertOrIgnore([
                    'branch_id' => $branchId, 'doc_type' => $type, 'next_value' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $row = DB::table('document_sequences')->where('branch_id', $branchId)->where('doc_type', $type)->lockForUpdate()->first();
            }
            DB::table('document_sequences')->where('id', $row->id)->update(['next_value' => $row->next_value + 1, 'updated_at' => now()]);
            return (int) $row->next_value;
        });

        $format = self::FORMATS[$type] ?? (strtoupper(substr($type, 0, 3)) . '-{BR}-{SEQ6}');
        return strtr($format, [
            '{BR}' => $branchCode,
            '{YMD}' => now()->format('ymd'),
            '{SEQ6}' => str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
        ]);
    }
}
