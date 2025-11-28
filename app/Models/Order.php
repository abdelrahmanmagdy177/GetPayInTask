<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['hold_id', 'payment_status', 'transaction_id'];

    public function hold()
    {
        return $this->belongsTo(Hold::class);
    }
}
