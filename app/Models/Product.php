<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name','barcode','slug'];

    public function stocks(){
        return $this->hasMany(Stock::class);
    }
}
