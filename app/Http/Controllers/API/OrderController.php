<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Stock;
use App\Models\StockLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // View all orders
    public function index()
    {
        $orders = Order::with('orderProducts.product')->orderBy('order_datetime','desc')->get();
        return response()->json($orders);
    }

    // Create Order
    public function store(Request $request)
    {
        $request->validate([
            'invoice_number' => 'required|unique:orders,invoice_number',
            'customer_name' => 'required|string',
            'products' => 'required|array',
            'products.*.stock_id' => 'required|exists:stocks,id',
            'products.*.quantity' => 'required|integer|min:1'
        ]);

        DB::transaction(function () use ($request, &$order) {
            $order = Order::create([
                'invoice_number' => $request->invoice_number,
                'order_datetime' => now(),
                'total_amount' => 0,
                'customer_name' => $request->customer_name,
                'status' => 'Pending'
            ]);

            $total = 0;

            foreach ($request->products as $p) {
                $stock = Stock::where('id', $p['stock_id'])->lockForUpdate()->first();

                if ($stock->quantity < $p['quantity']) {
                    throw new \Exception("Stock not sufficient for {$stock->sku}");
                }

                $sub_total = $stock->sale_price * $p['quantity'];
                $profit = (($stock->sale_price - $stock->purchase_price) / $stock->purchase_price) * 100;

                OrderProduct::create([
                    'order_id' => $order->id,
                    'product_id' => $stock->product_id,
                    'stock_id' => $stock->id,
                    'sale_price' => $stock->sale_price,
                    'sub_total' => $sub_total,
                    'profit' => $profit
                ]);

                // Stock decrease
                $previousQty = $stock->quantity;
                $stock->quantity -= $p['quantity'];
                $stock->save();

                // Stock Log
                StockLog::create([
                    'type' => 'Order-create',
                    'stock_id' => $stock->id,
                    'product_id' => $stock->product_id,
                    'previous_quantity' => $previousQty,
                    'change_quantity' => -$p['quantity'],
                    'current_quantity' => $stock->quantity
                ]);

                $total += $sub_total;
            }

            $order->total_amount = $total;
            $order->save();
        });

        return response()->json($order->load('orderProducts'));
    }

    // Update Order
    public function update(Request $request, Order $order)
    {
        $request->validate([
            'customer_name' => 'sometimes|string',
            'status' => 'sometimes|in:Pending,Processing,Delivered,Cancelled',
            'products' => 'sometimes|array',
            'products.*.stock_id' => 'required_with:products|exists:stocks,id',
            'products.*.quantity' => 'required_with:products|integer|min:1'
        ]);

        DB::transaction(function () use ($request, $order) {
            // Restore previous stock
            foreach ($order->orderProducts as $op) {
                $stock = Stock::where('id', $op->stock_id)->lockForUpdate()->first();
                $previousQty = $stock->quantity;
                $stock->quantity += $op->sub_total / $op->sale_price; // Restore quantity
                $stock->save();

                StockLog::create([
                    'type' => 'Order-update',
                    'stock_id' => $stock->id,
                    'product_id' => $stock->product_id,
                    'previous_quantity' => $previousQty,
                    'change_quantity' => $op->sub_total / $op->sale_price,
                    'current_quantity' => $stock->quantity
                ]);
            }

            // Delete old order products
            $order->orderProducts()->delete();

            $total = 0;

            if ($request->has('products')) {
                foreach ($request->products as $p) {
                    $stock = Stock::where('id', $p['stock_id'])->lockForUpdate()->first();

                    if ($stock->quantity < $p['quantity']) {
                        throw new \Exception("Stock not sufficient for {$stock->sku}");
                    }

                    $sub_total = $stock->sale_price * $p['quantity'];
                    $profit = (($stock->sale_price - $stock->purchase_price) / $stock->purchase_price) * 100;

                    OrderProduct::create([
                        'order_id' => $order->id,
                        'product_id' => $stock->product_id,
                        'stock_id' => $stock->id,
                        'sale_price' => $stock->sale_price,
                        'sub_total' => $sub_total,
                        'profit' => $profit
                    ]);

                    // Decrease stock
                    $previousQty = $stock->quantity;
                    $stock->quantity -= $p['quantity'];
                    $stock->save();

                    StockLog::create([
                        'type' => 'Order-update',
                        'stock_id' => $stock->id,
                        'product_id' => $stock->product_id,
                        'previous_quantity' => $previousQty,
                        'change_quantity' => -$p['quantity'],
                        'current_quantity' => $stock->quantity
                    ]);

                    $total += $sub_total;
                }
            }

            // Update order info
            if ($request->has('customer_name')) {
                $order->customer_name = $request->customer_name;
            }

            if ($request->has('status')) {
                $order->status = $request->status;
            }

            $order->total_amount = $total;
            $order->save();
        });

        return response()->json($order->load('orderProducts'));
    }

    // Delete Order
    public function destroy(Order $order)
    {
        DB::transaction(function () use ($order) {
            // Restore stock before deleting
            foreach ($order->orderProducts as $op) {
                $stock = Stock::where('id', $op->stock_id)->lockForUpdate()->first();
                $previousQty = $stock->quantity;
                $stock->quantity += $op->sub_total / $op->sale_price;
                $stock->save();

                StockLog::create([
                    'type' => 'Order-delete',
                    'stock_id' => $stock->id,
                    'product_id' => $stock->product_id,
                    'previous_quantity' => $previousQty,
                    'change_quantity' => $op->sub_total / $op->sale_price,
                    'current_quantity' => $stock->quantity
                ]);
            }

            $order->orderProducts()->delete();
            $order->delete();
        });

        return response()->json(['message' => 'Order deleted successfully']);
    }
}
