<?php

namespace App\Orchid\Layouts\SupportTicket;

use App\Models\SupportTicket;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class SupportTicketListLayout extends Table
{
    /**
     * Data source.
     *
     * The name of the key to fetch it from the query.
     * The results of which will be elements of the table.
     *
     * @var string
     */
    protected $target = 'supportTickets';

    /**
     * Get the table cells to be displayed.
     *
     * @return TD[]
     */
    protected function columns(): iterable
    {
        return [
            TD::make('id', 'ID')
                ->sort()
                ->filter(),

            TD::make('telegramUser.telegram_id', 'Telegram ID')
                ->sort()
                ->filter()
                ->render(function (SupportTicket $ticket) {
                    return Link::make($ticket->telegramUser->telegram_id)
                        ->route('platform.telegram-user.edit', $ticket->telegramUser->id);
                }),

            TD::make('subject', 'Subject')
                ->sort()
                ->filter(),

            TD::make('status', 'Status')
                ->sort()
                ->filter()
                ->render(function (SupportTicket $ticket) {
                    $badgeType = match($ticket->status) {
                        'open' => 'warning',
                        'in_progress' => 'info',
                        'resolved' => 'success',
                        'closed' => 'dark',
                        default => 'secondary'
                    };
                    
                    return "<span class='badge badge-{$badgeType}'>{$ticket->status}</span>";
                }),

            TD::make('priority', 'Priority')
                ->sort()
                ->filter()
                ->render(function (SupportTicket $ticket) {
                    $badgeType = match($ticket->priority) {
                        'low' => 'secondary',
                        'medium' => 'info',
                        'high' => 'warning',
                        'urgent' => 'danger',
                        default => 'secondary'
                    };
                    
                    return "<span class='badge badge-{$badgeType}'>{$ticket->priority}</span>";
                }),

            TD::make('category', 'Category')
                ->sort()
                ->filter(),

            TD::make('assignedToUser.name', 'Assigned To')
                ->sort()
                ->render(function (SupportTicket $ticket) {
                    return $ticket->assignedToUser ? $ticket->assignedToUser->name : 'Unassigned';
                }),

            TD::make('created_at', 'Created')
                ->sort()
                ->render(function (SupportTicket $ticket) {
                    return $ticket->created_at ? $ticket->created_at->diffForHumans() : 'N/A';
                }),

            TD::make('resolved_at', 'Resolved')
                ->sort()
                ->render(function (SupportTicket $ticket) {
                    return $ticket->resolved_at ? $ticket->resolved_at->diffForHumans() : 'N/A';
                }),

            TD::make('actions', 'Actions')
                ->cantHide()
                ->render(function (SupportTicket $ticket) {
                    return Link::make('View')
                        ->route('platform.support-ticket.edit', $ticket->id)
                        ->icon('eye');
                }),
        ];
    }
}