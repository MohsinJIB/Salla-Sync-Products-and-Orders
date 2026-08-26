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
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('salla_id');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('status')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('total', 15, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->json('payload');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'salla_id']);
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
