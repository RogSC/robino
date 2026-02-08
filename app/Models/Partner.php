<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'partner_id',
        'invitation_code',
        'status',
        'accepted_at',
        'expires_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user (woman) who owns this partnership.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'user_id');
    }

    /**
     * Get the partner in this partnership.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'partner_id');
    }

    /**
     * Generate unique invitation code.
     */
    public static function generateInvitationCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('invitation_code', $code)->exists());

        return $code;
    }

    /**
     * Check if invitation is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if partnership is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'accepted' && !$this->isExpired();
    }

    /**
     * Accept the partnership invitation.
     */
    public function accept(): bool
    {
        if ($this->status === 'pending' && !$this->isExpired()) {
            $this->status = 'accepted';
            $this->accepted_at = now();
            return $this->save();
        }

        return false;
    }

    /**
     * Reject the partnership invitation.
     */
    public function reject(): bool
    {
        if ($this->status === 'pending') {
            $this->status = 'rejected';
            return $this->save();
        }

        return false;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($partner) {
            if (!$partner->invitation_code) {
                $partner->invitation_code = self::generateInvitationCode();
            }
            if (!$partner->expires_at) {
                $partner->expires_at = now()->addDays(7); // Default 7 days expiration
            }
        });
    }
}
