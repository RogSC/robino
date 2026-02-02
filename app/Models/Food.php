<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Food extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'calories_per_100g',
        'protein_per_100g',
        'carbs_per_100g',
        'fat_per_100g',
        'nutrients',
        'is_public',
        'created_by_user_id',
    ];

    protected $casts = [
        'nutrients' => 'array',
        'calories_per_100g' => 'decimal:2',
        'protein_per_100g' => 'decimal:2',
        'carbs_per_100g' => 'decimal:2',
        'fat_per_100g' => 'decimal:2',
        'is_public' => 'boolean',
    ];

    // Relations
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'created_by_user_id');
    }

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }

    // Helper methods
    public function calculateNutrition(float $weightInGrams): array
    {
        $multiplier = $weightInGrams / 100;

        return [
            'calories' => round(($this->calories_per_100g ?? 0) * $multiplier, 2),
            'protein' => round(($this->protein_per_100g ?? 0) * $multiplier, 2),
            'carbs' => round(($this->carbs_per_100g ?? 0) * $multiplier, 2),
            'fat' => round(($this->fat_per_100g ?? 0) * $multiplier, 2),
        ];
    }

    public function isPublic(): bool
    {
        return $this->is_public;
    }

    public function isCreatedByUser(): bool
    {
        return !$this->is_public && $this->created_by_user_id !== null;
    }
}