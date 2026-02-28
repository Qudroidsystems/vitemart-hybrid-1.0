<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

  // In App\Models\Customer.php
protected $fillable = [
    'first_name',
    'last_name',
    'email',
    'phone_number',
    'phone_number_2',
    'home_address',
    'office_address',
    'gender',
    'credit_limit',
    'credit_balance',
    'outstanding_balance',
    'customer_type',
    'status',
    'loyalty_card_number',
    'loyalty_points', // Add this line
    'date_of_birth',
    'company_name',
    'tax_id_number',
    'contact_person',
    'notes',
    'profile_image',
    'identification_type',
    'identification_number',
    'created_by',
    'updated_by',
];
    protected $casts = [
        'date_of_birth' => 'date',
        'credit_limit' => 'decimal:2',
        'credit_balance' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'loyalty_points' => 'integer',
    ];

    // Accessor for full name
    public function getNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    // Scope for active customers
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Relationship with orders
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Relationship with created by user
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relationship with updated by user
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scope for search
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('phone_number', 'like', "%{$search}%")
              ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
        });
    }
}
