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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            // Order ID as string to match custom string primary key on orders table
            $table->string('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');

            // Product reference
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');

            // Snapshot of product title at time of sale (required for historical accuracy)
            $table->string('title');

            // Price per unit (sale price used at checkout)
            $table->decimal('unit_price', 12, 2);

            // Quantity sold in the selected unit (e.g., 2 boxes, 5 kg)
            $table->integer('quantity')->unsigned();

            // Selected unit at time of sale
            $table->foreignId('unit_id')->nullable()->constrained('units');

            // Optional: unit name snapshot (in case unit is deleted later)
            $table->string('unit_name')->nullable();

            // Total price for this line item (quantity × unit_price)
            $table->decimal('total_price', 12, 2);

            // For variable products
            $table->string('variation_id')->nullable();
            $table->string('image')->nullable();
            $table->string('brand_name')->nullable();
            $table->json('selected_variation')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
