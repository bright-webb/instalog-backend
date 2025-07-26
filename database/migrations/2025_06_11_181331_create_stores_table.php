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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('business_name');
            $table->string('business_email');
            $table->string('slug')->unique();
            $table->string('whatsapp_number')->unique();
            $table->string('location');
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->json('social_handles')->nullable();
            $table->json('delivery_options');
            $table->string('logo_url')->nullable();
            $table->string('logo_cropped_url')->nullable();
            $table->string('cover_url')->nullable();
            $table->string('cover_cropped_url')->nullable();
            $table->string('theme_id')->default('modern');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('setup_completed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
