<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class Meal extends Model
{
    use HasFactory, Filterable, AsSource;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'telegram_user_id',
        'food_id',
        'name',
        'weight_in_grams',
        'calories',
        'protein',
        'carbs',
        'fat',
        'consumed_at',
        'meta',
    ];

    /**
     * The attributes that are allowed to be filtered.
     *
     * @var array
     */
    protected $allowedFilters = [
        'id',
        'name',
        'weight_in_grams',
        'calories',
        'consumed_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'consumed_at' => 'datetime',
        'weight_in_grams' => 'decimal:2',
        'calories' => 'decimal:2',
        'protein' => 'decimal:2',
        'carbs' => 'decimal:2',
        'fat' => 'decimal:2',
    ];

    // Relations
    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class);
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    // Helper methods
    public function getCaloriesPerGram(): float
    {
        if ($this->weight_in_grams <= 0) {
            return 0;
        }
        
        return $this->calories / $this->weight_in_grams;
    }

    public function isToday(): bool
    {
        return $this->consumed_at->isToday();
    }

    public function isYesterday(): bool
    {
        return $this->consumed_at->isYesterday();
    }

    public function getFormattedDate(): string
    {
        return $this->consumed_at->format('Y-m-d H:i');
    }

    public function getFormattedWeight(): string
    {
        return round($this->weight_in_grams, 1) . 'g';
    }
}