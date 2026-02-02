<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class SupportTicket extends Model
{
    use HasFactory, Filterable, AsSource;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'telegram_user_id',
        'subject',
        'message',
        'status',
        'priority',
        'category',
        'assigned_to_user_id',
        'resolved_at',
    ];

    /**
     * The attributes that are allowed to be filtered.
     *
     * @var array
     */
    protected $allowedFilters = [
        'id',
        'subject',
        'status',
        'priority',
        'category',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    // Relations
    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class);
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    // Helper methods
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isHighPriority(): bool
    {
        return $this->priority === 'high' || $this->priority === 'urgent';
    }

    public function daysOpen(): int
    {
        if ($this->resolved_at) {
            return $this->created_at->diffInDays($this->resolved_at);
        }
        
        return $this->created_at->diffInDays(now());
    }
}