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
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_user_id')->constrained('telegram_users')->onDelete('cascade');
            $table->foreignId('food_id')->constrained('foods')->onDelete('restrict');
            $table->string('name'); // Name of the meal or food consumed
            $table->decimal('weight_in_grams', 8, 2); // Weight in grams
            $table->decimal('calories', 8, 2)->nullable(); // Calculated calories
            $table->decimal('protein', 8, 2)->nullable(); // Calculated protein
            $table->decimal('carbs', 8, 2)->nullable(); // Calculated carbs
            $table->decimal('fat', 8, 2)->nullable(); // Calculated fat
            $table->timestamp('consumed_at'); // When the meal was eaten
            $table->json('meta')->nullable(); // Additional data like meal type (breakfast, lunch, etc.)
            $table->timestamps();

            $table->index(['telegram_user_id', 'consumed_at']);
            $table->index('consumed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};