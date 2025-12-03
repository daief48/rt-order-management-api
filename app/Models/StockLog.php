<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockLog extends Model
{
    use HasFactory;

    protected $fillable = ['type','stock_id','product_id','previous_quantity','change_quantity','current_quantity'];
}
