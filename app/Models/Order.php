<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['invoice_number','order_datetime','total_amount','customer_name','status'];

    public function orderProducts(){
        return $this->hasMany(OrderProduct::class);
    }
}

