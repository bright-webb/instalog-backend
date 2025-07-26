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
        Schema::create('product_views', function (Blueprint $table) {
        $table->id();
        $table->foreignId('products_id')->constrained()->onDelete('cascade');
        $table->string('fingerprint')->comment('Unique visitor identifier');
        $table->string('ip')->nullable();
        $table->string('device')->nullable();
        $table->string('referrer')->nullable();
        $table->string('utm_source')->nullable();
        $table->string('utm_medium')->nullable();
        $table->string('utm_campaign')->nullable();

        $table->json('meta')->nullable();
        $table->timestamps();
        
        $table->unique(['products_id', 'fingerprint']);
    });

    Schema::create('ip_locations', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address')->unique();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone')->nullable();
            $table->json('original_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_views');
    }
};
