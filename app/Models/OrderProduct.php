<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderProduct extends Model
{
    use HasFactory;

    protected $fillable = ['order_id','product_id','stock_id','sale_price','sub_total','profit'];

    public function order(){
        return $this->belongsTo(Order::class);
    }
}
