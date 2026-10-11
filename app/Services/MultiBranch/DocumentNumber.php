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
