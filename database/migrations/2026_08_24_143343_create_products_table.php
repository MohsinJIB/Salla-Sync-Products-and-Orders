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
       Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('salla_id');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->bigInteger('quantity')->nullable();
            $table->boolean('is_available')->default(false);
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
        Schema::dropIfExists('products');
    }
};
