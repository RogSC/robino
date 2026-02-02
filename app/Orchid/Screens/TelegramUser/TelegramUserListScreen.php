<?php

namespace App\Orchid\Screens\TelegramUser;

use App\Models\TelegramUser;
use App\Orchid\Layouts\TelegramUser\TelegramUserListLayout;
use App\Orchid\Layouts\TelegramUser\TelegramUserFiltersLayout;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class TelegramUserListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'telegramUsers' => TelegramUser::with('subscriptions.subscriptionPlan')
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
        return 'Telegram Users';
    }

    /**
     * The description is displayed in the header.
     */
    public function description(): ?string
    {
        return 'All registered Telegram users';
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
            TelegramUserFiltersLayout::class,
            TelegramUserListLayout::class,
        ];
    }
}