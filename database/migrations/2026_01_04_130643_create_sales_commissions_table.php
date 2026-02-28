<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_commissions', function (Blueprint $table) {
            $table->id();

            // Use string instead of bigInteger to match orders.id (string)
            $table->string('order_id');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->decimal('commission_rate', 5, 2)->default(5.00);
            $table->decimal('commission_amount', 15, 2)->default(0.00);
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->date('paid_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Foreign key on string column
            $table->foreign('order_id')
                  ->references('id')
                  ->on('orders')
                  ->onDelete('cascade');

            // Unique constraint
            $table->unique(['order_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_commissions');
    }
};
