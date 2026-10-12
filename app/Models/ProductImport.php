<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImport extends Model
{
    protected $fillable = ['user_id', 'original_name', 'ext', 'status', 'options', 'total_rows', 'processed', 'cursor',
        'counts', 'issues', 'preview', 'columns', 'result', 'error', 'applied_at'];

    protected $casts = [
        'options' => 'array', 'counts' => 'array', 'issues' => 'array', 'preview' => 'array',
        'columns' => 'array', 'result' => 'array', 'applied_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function dir(): string
    {
        return storage_path('app/product-imports/' . $this->id);
    }

    public function rowsFile(): string
    {
        return $this->dir() . '/rows.jsonl';
    }
}
