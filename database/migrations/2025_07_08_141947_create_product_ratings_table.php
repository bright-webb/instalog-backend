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
        Schema::create('product_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('products_id');
            $table->decimal('rating', 10, 2)->default(0);
            $table->text('review')->nullable();
            $table->string('ip');
            $table->string('device');
            $table->json('meta')->nullable();
            $table->json('footprinting')->nullable();
            $table->boolean('liked')->default(0);
            $table->string('fingerprint');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_ratings');
    }
};
