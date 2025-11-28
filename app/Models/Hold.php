<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Hold extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['product_id', 'quantity', 'expires_at', 'status'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
