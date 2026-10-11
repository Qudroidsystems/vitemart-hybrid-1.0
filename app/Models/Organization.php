<?php

namespace App\Models;

use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasUid;

    protected $fillable = ['uid', 'name', 'currency_code', 'timezone', 'prices_include_tax', 'vat_rate'];

    protected $casts = ['prices_include_tax' => 'boolean', 'vat_rate' => 'decimal:2'];
}
