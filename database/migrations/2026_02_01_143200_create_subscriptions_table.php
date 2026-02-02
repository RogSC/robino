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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_user_id')->constrained('telegram_users')->onDelete('cascade');
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans');
            $table->string('status')->default('active'); // active, inactive, expired, cancelled
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->boolean('auto_renew')->default(false);
            $table->timestamp('grace_period_ends_at')->nullable(); // Grace period after expiry
            $table->json('meta')->nullable(); // Additional metadata
            $table->timestamps();

            $table->index(['telegram_user_id', 'status']);
            $table->index('expires_at');
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