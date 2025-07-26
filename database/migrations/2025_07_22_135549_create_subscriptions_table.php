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

         Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('name')->default('premium');
            $table->string('plan_id')->unique();
            $table->decimal('price', 10, 2);
            $table->string('interval')->default('monthly');
            $table->integer('duration')->default(30);
            $table->enum('status', ['active', 'dormant', 'cancelled'])->default('dormant');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('plan')->nullable()->constrained()->nullOnDelete();
            
            $table->enum('status', [
                'active', 
                'pending', 
                'cancelled', 
                'expired', 
                'paid',
                'past_due',
                'trialing'
            ])->default('pending');
            
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('trial_ends_at')->nullable();
            $table->dateTime('current_period_ends_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            
            $table->string('payment_method')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('NGN');
            
            $table->boolean('is_in_grace_period')->default(false);
            $table->dateTime('grace_period_ends_at')->nullable();
            
            $table->json('metadata')->nullable();
            
            $table->timestamps();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('NGN');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded']);
            $table->dateTime('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};