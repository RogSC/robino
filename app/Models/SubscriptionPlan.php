<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class SubscriptionPlan extends Model
{
    use HasFactory, Filterable, AsSource;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'duration_days',
        'max_daily_meals',
        'has_advanced_stats',
        'has_support_priority',
        'features',
        'is_active',
    ];

    /**
     * The attributes that are allowed to be filtered.
     *
     * @var array
     */
    protected $allowedFilters = [
        'id',
        'name',
        'slug',
        'price',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
        'duration_days' => 'integer',
        'max_daily_meals' => 'integer',
        'has_advanced_stats' => 'boolean',
        'has_support_priority' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relations
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Helper methods
    public function isFree(): bool
    {
        return $this->price <= 0;
    }

    public function isTrial(): bool
    {
        return $this->slug === 'trial' || str_contains(strtolower($this->name), 'trial');
    }

    public function isPremium(): bool
    {
        return $this->slug === 'premium' || str_contains(strtolower($this->name), 'premium');
    }

    public function getFeature(string $featureName, mixed $default = null): mixed
    {
        return $this->features[$featureName] ?? $default;
    }
}