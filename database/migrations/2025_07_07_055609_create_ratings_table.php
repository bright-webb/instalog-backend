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
        Schema::create('ratings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('store_id');
                $table->decimal('rating', 10, 2);
                $table->decimal('average_rating', 10, 2);
                $table->text('review')->nullable();
                $table->string('ip');
                $table->string('device');
                $table->json('meta')->nullable();
                $table->string('footprint')->unique(); 
                $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
