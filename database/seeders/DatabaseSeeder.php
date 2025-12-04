<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\StockLog;
use Faker\Factory as Faker;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        DB::transaction(function () use ($faker) {

            // --- PRODUCTS AND STOCKS ---
            $products = [];
            for ($i = 0; $i < 10; $i++) {
                $product = Product::create([
                    'name' => $faker->word,
                    'barcode' => $faker->unique()->ean13,
                    'slug' => Str::slug($faker->word . '-' . $i),
                ]);
                $products[] = $product;

                // Create 2-3 stock entries per product
                for ($j = 0; $j < rand(2,3); $j++) {
                    Stock::create([
                        'product_id' => $product->id,
                        'sku' => strtoupper(Str::random(8)),
                        'sale_price' => $faker->randomFloat(2, 50, 200),
                        'purchase_price' => $faker->randomFloat(2, 30, 150),
                        'quantity' => rand(10, 100),
                        'last_update_at' => now(),
                    ]);
                }
            }

            // --- ORDERS, ORDER_PRODUCTS, STOCK_LOGS ---
            for ($k = 0; $k < 5; $k++) {
                $order = Order::create([
                    'invoice_number' => 'INV-' . strtoupper(Str::random(6)),
                    'order_datetime' => now(),
                    'total_amount' => 0, // will update later
                    'customer_name' => $faker->name,
                    'status' => $faker->randomElement(['Pending','Processing','Delivered','Cancelled']),
                ]);

                $total = 0;

                // Random 1-3 products per order
                $orderProducts = $faker->randomElements($products, rand(1,3));

                foreach ($orderProducts as $product) {
                    $stock = $product->stocks()->inRandomOrder()->first();

                    $quantity = rand(1, min(5, $stock->quantity));

                    $sub_total = $stock->sale_price * $quantity;
                    $profit = (($stock->sale_price - $stock->purchase_price) / $stock->purchase_price) * 100;

                    OrderProduct::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'stock_id' => $stock->id,
                        'sale_price' => $stock->sale_price,
                        'sub_total' => $sub_total,
                        'profit' => $profit,
                    ]);

                    // Decrease stock
                    $previousQty = $stock->quantity;
                    $stock->quantity -= $quantity;
                    $stock->save();

                    // Stock Log
                    StockLog::create([
                        'type' => 'Order-create',
                        'stock_id' => $stock->id,
                        'product_id' => $product->id,
                        'previous_quantity' => $previousQty,
                        'change_quantity' => -$quantity,
                        'current_quantity' => $stock->quantity,
                    ]);

                    $total += $sub_total;
                }

                // Update order total
                $order->total_amount = $total;
                $order->save();
            }
        });

        $this->command->info('Dummy data seeded successfully!');
    }
}
