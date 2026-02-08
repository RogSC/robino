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
        'partner_code',
        'average_cycle_length',
        'average_period_length',
        'gender',
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
        'average_cycle_length' => 'integer',
        'average_period_length' => 'integer',
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

    public function periods(): HasMany
    {
        return $this->hasMany(Period::class);
    }

    public function partnershipsAsUser(): HasMany
    {
        return $this->hasMany(Partner::class, 'user_id');
    }

    public function partnershipsAsPartner(): HasMany
    {
        return $this->hasMany(Partner::class, 'partner_id');
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

    /**
     * Get active partners.
     */
    public function getActivePartnersAttribute()
    {
        return Partner::where(function ($query) {
            $query->where('user_id', $this->id)
                  ->orWhere('partner_id', $this->id);
        })
        ->where('status', 'accepted')
        ->get();
    }

    /**
     * Get the current active period.
     */
    public function getCurrentPeriod()
    {
        return $this->periods()
            ->whereNull('end_date')
            ->latest('start_date')
            ->first();
    }

    /**
     * Get the last completed period.
     */
    public function getLastPeriod()
    {
        return $this->periods()
            ->whereNotNull('end_date')
            ->latest('start_date')
            ->first();
    }

    /**
     * Check if user has an active period.
     */
    public function hasActivePeriod(): bool
    {
        return $this->getCurrentPeriod() !== null;
    }

    /**
     * Generate unique partner code.
     */
    public function generatePartnerCode(): string
    {
        do {
            $code = strtoupper(\Illuminate\Support\Str::random(6));
        } while (self::where('partner_code', $code)->exists());

        $this->partner_code = $code;
        $this->save();

        return $code;
    }

    /**
     * Get partner code or generate if not exists.
     */
    public function getOrCreatePartnerCode(): string
    {
        if (!$this->partner_code) {
            return $this->generatePartnerCode();
        }

        return $this->partner_code;
    }
}