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
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048);
            $table->string('referrer', 2048)->nullable();
            $table->text('user_agent')->nullable();
            $table->ipAddress('ip_address');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('session_id');
            $table->string('page_title')->nullable();
            $table->integer('viewport_width')->nullable();
            $table->integer('viewport_height')->nullable();
            $table->integer('session_duration')->nullable();
            $table->timestamp('viewed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
