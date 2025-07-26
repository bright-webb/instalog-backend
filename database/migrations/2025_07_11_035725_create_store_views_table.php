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
        Schema::create('store_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stores_id')->constrained('stores')->onDelete('cascade'); 
            $table->string('fingerprint')->comment('Unique visitor identifier');
            $table->string('ip')->nullable();
            $table->string('device')->nullable();
            $table->json('meta')->nullable();
            $table->string('referrer')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->timestamps();
            
            $table->unique(['store_id', 'fingerprint']);
        });

        Schema::create('visitor_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stores_id')->constrained('stores')->onDelete('cascade');
            $table->string('fingerprint');
            $table->string('ip')->nullable();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['stores_id', 'fingerprint']);
        });

        Schema::create('visitor_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stores_id')->constrained('stores')->onDelete('cascade');
            $table->string('session_id');
            $table->string('fingerprint');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->nullable()->comment('In seconds');
            $table->integer('page_views')->default(0);
            $table->json('entry_page')->nullable();
            $table->json('exit_page')->nullable();
            $table->timestamps();
            
            $table->index(['stores_id', 'session_id']);
            $table->index(['stores_id', 'fingerprint']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_sessions');
        Schema::dropIfExists('visitor_locations');
        Schema::dropIfExists('store_views');
    }
};