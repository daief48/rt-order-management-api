<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->string('invoice_number')->unique();
    $table->timestamp('order_datetime');
    $table->decimal('total_amount', 12, 2);
    $table->string('customer_name');
    $table->enum('status', ['Pending','Processing','Delivered','Cancelled'])->default('Pending');
    $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
