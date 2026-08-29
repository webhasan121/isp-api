<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'package_id',
        'next_package_id',
        'period_start_at',
        'period_end_at',
        'paid_until',
        'status',
        'suspended_at',
        'terminated_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start_at' => 'datetime',
            'period_end_at' => 'datetime',
            'paid_until' => 'datetime',
            'suspended_at' => 'datetime',
            'terminated_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function nextPackage()
    {
        return $this->belongsTo(
            Package::class,
            'next_package_id'
        );
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
