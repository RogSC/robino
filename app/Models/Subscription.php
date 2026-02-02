<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class Subscription extends Model
{
    use HasFactory, Filterable, AsSource;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'telegram_user_id',
        'subscription_plan_id',
        'status',
        'started_at',
        'expires_at',
        'auto_renew',
        'grace_period_ends_at',
        'meta',
    ];

    /**
     * The attributes that are allowed to be filtered.
     *
     * @var array
     */
    protected $allowedFilters = [
        'id',
        'status',
        'started_at',
        'expires_at',
        'auto_renew',
    ];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    // Relations
    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isInGracePeriod(): bool
    {
        return $this->grace_period_ends_at && $this->grace_period_ends_at->isFuture();
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function daysUntilExpiry(): int
    {
        return max(0, $this->expires_at->diffInDays(now()));
    }

    public function isWithinLimitForDailyMeals(int $dailyMealCount): bool
    {
        $maxMeals = $this->subscriptionPlan->max_daily_meals;
        return $maxMeals === 0 || $dailyMealCount < $maxMeals; // 0 means unlimited
    }

    public function hasAdvancedStatsAccess(): bool
    {
        return $this->subscriptionPlan->has_advanced_stats;
    }
}