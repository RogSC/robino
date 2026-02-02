<?php

namespace App\Services;

use App\Models\TelegramUser;
use App\Models\SupportTicket;
use App\Models\User;

class SupportService
{
    /**
     * Create a new support ticket
     */
    public function createTicket(TelegramUser $telegramUser, string $subject, string $message, string $category = null, string $priority = 'medium'): SupportTicket
    {
        return $telegramUser->supportTickets()->create([
            'subject' => $subject,
            'message' => $message,
            'category' => $category,
            'priority' => $priority,
        ]);
    }

    /**
     * Get user's open tickets
     */
    public function getUserOpenTickets(TelegramUser $telegramUser): \Illuminate\Support\Collection
    {
        return $telegramUser->supportTickets()
            ->whereIn('status', ['open', 'in_progress'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get user's all tickets
     */
    public function getUserTickets(TelegramUser $telegramUser): \Illuminate\Support\Collection
    {
        return $telegramUser->supportTickets()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Update ticket status
     */
    public function updateTicketStatus(SupportTicket $ticket, string $status, User $updatedBy = null): SupportTicket
    {
        $ticket->update([
            'status' => $status,
            'assigned_to_user_id' => $updatedBy?->id,
        ]);

        return $ticket;
    }

    /**
     * Resolve a ticket
     */
    public function resolveTicket(SupportTicket $ticket, string $resolutionNote = null, User $resolvedBy = null): SupportTicket
    {
        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'assigned_to_user_id' => $resolvedBy?->id,
        ]);

        return $ticket;
    }

    /**
     * Close a ticket
     */
    public function closeTicket(SupportTicket $ticket, User $closedBy = null): SupportTicket
    {
        $ticket->update([
            'status' => 'closed',
            'assigned_to_user_id' => $closedBy?->id,
        ]);

        return $ticket;
    }

    /**
     * Reopen a ticket
     */
    public function reopenTicket(SupportTicket $ticket, User $reopenedBy = null): SupportTicket
    {
        $ticket->update([
            'status' => 'open',
            'resolved_at' => null,
            'assigned_to_user_id' => $reopenedBy?->id,
        ]);

        return $ticket;
    }

    /**
     * Get tickets by status
     */
    public function getTicketsByStatus(string $status): \Illuminate\Support\Collection
    {
        return SupportTicket::where('status', $status)
            ->with(['telegramUser', 'assignedToUser'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get tickets by priority
     */
    public function getTicketsByPriority(string $priority): \Illuminate\Support\Collection
    {
        return SupportTicket::where('priority', $priority)
            ->with(['telegramUser', 'assignedToUser'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get all unresolved tickets
     */
    public function getUnresolvedTickets(): \Illuminate\Support\Collection
    {
        return SupportTicket::whereNotIn('status', ['resolved', 'closed'])
            ->with(['telegramUser', 'assignedToUser'])
            ->orderByRaw("CASE priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
                ELSE 5 END")
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get tickets by category
     */
    public function getTicketsByCategory(string $category): \Illuminate\Support\Collection
    {
        return SupportTicket::where('category', $category)
            ->with(['telegramUser', 'assignedToUser'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Send notification to Telegram user about ticket update
     */
    public function notifyUserAboutTicketUpdate(SupportTicket $ticket, string $message): void
    {
        // In a real implementation, you would integrate with your Telegram bot service
        // to send a notification to the user about the ticket update
        // For now, we'll just log it
        
        \Log::info("Ticket notification sent to user {$ticket->telegramUser->telegram_id}: {$message}", [
            'ticket_id' => $ticket->id,
            'message' => $message,
        ]);
    }
}