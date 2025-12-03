<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = ['product_id','sku','sale_price','purchase_price','quantity','last_update_at'];

    public function product(){
        return $this->belongsTo(Product::class);
    }
}
