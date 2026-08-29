<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'payment_id',
        'customer_id',
        'customer_code_snapshot',
        'customer_name_snapshot',
        'customer_email_snapshot',
        'customer_phone_snapshot',
        'package_name_snapshot',
        'speed_mbps_snapshot',
        'amount',
        'payment_method',
        'transaction_id',
        'coverage_start_at',
        'coverage_end_at',
        'issued_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'coverage_start_at' => 'datetime',
            'coverage_end_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
