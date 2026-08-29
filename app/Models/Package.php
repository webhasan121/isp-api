<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'speed_mbps',
        'price',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'status' => 'boolean',
        ];
    }
    public function subscriptions()
    {
        return $this->hasMany(
            Subscription::class,
            'package_id'
        );
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
