<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_reference',
        'customer_id',
        'subscription_id',
        'package_id',
        'package_name_snapshot',
        'speed_mbps_snapshot',
        'amount',
        'payment_type',
        'payment_method',
        'transaction_id',
        'coverage_start_at',
        'coverage_end_at',
        'paid_at',
        'received_by',
        'status',
        'notes',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'coverage_start_at' => 'datetime',
            'coverage_end_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(
            User::class,
            'received_by'
        );
    }

    public function cancelledBy()
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by'
        );
    }
    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
