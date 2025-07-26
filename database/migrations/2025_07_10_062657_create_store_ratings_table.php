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
        Schema::create('store_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stores_id')->constrained()->onDelete('cascade');
            $table->decimal('rating', 2, 1);
            $table->text('review')->nullable();
            $table->string('name')->nullable()->default('Anonymous');
            $table->string('fingerprint')->index();
            $table->string('ip')->nullable();
            $table->string('device')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            
            $table->unique(['stores_id', 'fingerprint']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_ratings');
    }
};
