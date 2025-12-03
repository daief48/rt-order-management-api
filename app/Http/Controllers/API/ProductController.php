<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('query');

        $products = Product::where('name', 'like', "%$query%")
            ->orWhere('barcode', 'like', "%$query%")
            ->with(['stocks' => function ($q) {
                $q->where('quantity', '>', 0)->orderBy('created_at', 'asc'); // FIFO
            }])
            ->get();

        return response()->json($products);
    }
}
