<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Period extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_user_id',
        'start_date',
        'end_date',
        'duration',
        'flow_intensity',
        'symptoms',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'symptoms' => 'array',
        'duration' => 'integer',
        'flow_intensity' => 'integer',
    ];

    /**
     * Get the telegram user that owns this period record.
     */
    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class);
    }

    /**
     * Calculate duration when end_date is set.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($period) {
            if ($period->end_date && $period->start_date) {
                $period->duration = $period->start_date->diffInDays($period->end_date) + 1;
            }
        });
    }

    /**
     * Check if period is currently active.
     */
    public function isActive(): bool
    {
        return $this->end_date === null && $this->start_date->lte(now());
    }

    /**
     * Get cycle length to next period (if available).
     */
    public function getCycleLengthAttribute(): ?int
    {
        $nextPeriod = Period::where('telegram_user_id', $this->telegram_user_id)
            ->where('start_date', '>', $this->start_date)
            ->orderBy('start_date')
            ->first();

        if ($nextPeriod) {
            return $this->start_date->diffInDays($nextPeriod->start_date);
        }

        return null;
    }
}
