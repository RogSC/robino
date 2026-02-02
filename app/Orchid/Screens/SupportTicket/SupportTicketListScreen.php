<?php

namespace App\Orchid\Screens\SupportTicket;

use App\Models\SupportTicket;
use App\Orchid\Layouts\SupportTicket\SupportTicketListLayout;
use App\Orchid\Layouts\SupportTicket\SupportTicketFiltersLayout;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class SupportTicketListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'supportTickets' => SupportTicket::with(['telegramUser', 'assignedToUser'])
                ->filtersApply()
                ->defaultSort('id', 'desc')
                ->paginate(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Support Tickets';
    }

    /**
     * The description is displayed in the header.
     */
    public function description(): ?string
    {
        return 'All support tickets from users';
    }

    /**
     * The permissions required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.systems.users',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            SupportTicketFiltersLayout::class,
            SupportTicketListLayout::class,
        ];
    }
}