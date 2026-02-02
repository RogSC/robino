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
        Schema::create('foods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->decimal('calories_per_100g', 8, 2)->nullable(); // kcal per 100g
            $table->decimal('protein_per_100g', 8, 2)->nullable(); // g per 100g
            $table->decimal('carbs_per_100g', 8, 2)->nullable(); // g per 100g
            $table->decimal('fat_per_100g', 8, 2)->nullable(); // g per 100g
            $table->json('nutrients')->nullable(); // Additional nutrients
            $table->boolean('is_public')->default(true); // Public vs private (user-created)
            $table->foreignId('created_by_user_id')->nullable()->constrained('telegram_users')->onDelete('set null');
            $table->timestamps();

            $table->index(['name', 'is_public']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('foods');
    }
};