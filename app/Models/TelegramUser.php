<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Orchid\Attachment\Attachable;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class TelegramUser extends Model
{
    use HasFactory, Filterable, AsSource;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'telegram_id',
        'first_name',
        'last_name',
        'username',
        'timezone',
        'settings',
        'last_active_at',
    ];

    /**
     * The attributes that are allowed to be filtered.
     *
     * @var array
     */
    protected $allowedFilters = [
        'id',
        'telegram_id',
        'first_name',
        'last_name',
        'username',
        'timezone',
    ];

    protected $casts = [
        'settings' => 'array',
        'last_active_at' => 'datetime',
    ];

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function foods(): HasMany
    {
        return $this->hasMany(Food::class, 'created_by_user_id');
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->last_active_at?->diffInDays(now()) <= 30;
    }

    public function currentSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('expires_at', '>', now())
            ->orderBy('expires_at', 'desc')
            ->first();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->currentSubscription() !== null;
    }
}