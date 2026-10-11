<?php
namespace App\Models;

use App\Traits\HasUid;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasUid;
    protected $fillable = ['product_id', 'image_path', 'is_primary', 'sort_order'];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}