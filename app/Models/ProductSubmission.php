<?php

namespace App\Models;

use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class ProductSubmission extends Model
{
    use HasUid;

    protected $fillable = ['uid', 'branch_id', 'product_uid', 'title', 'barcode', 'payload', 'status', 'sent', 'submitted_by_name',
        'note', 'decided_by_name', 'decided_at', 'decision_note', 'central_product_id', 'applied'];

    protected $casts = ['payload' => 'array', 'sent' => 'boolean', 'applied' => 'boolean', 'decided_at' => 'datetime'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function decisionPayload(): array
    {
        return $this->only(['uid', 'product_uid', 'status', 'decided_by_name', 'decision_note'])
            + ['decided_at' => $this->decided_at?->toIso8601String()];
    }
}
