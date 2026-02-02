<?php

declare(strict_types=1);

namespace App\Orchid;

use App\Orchid\Screens\TelegramUser\TelegramUserListScreen;
use App\Orchid\Screens\SubscriptionPlan\SubscriptionPlanListScreen;
use App\Orchid\Screens\Subscription\SubscriptionListScreen;
use App\Orchid\Screens\Meal\MealListScreen;
use App\Orchid\Screens\SupportTicket\SupportTicketListScreen;
use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\OrchidServiceProvider;
use Orchid\Screen\Actions\Menu;
use Orchid\Support\Color;

class PlatformProvider extends OrchidServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @param Dashboard $dashboard
     *
     * @return void
     */
    public function boot(Dashboard $dashboard): void
    {
        parent::boot($dashboard);

        // ...
    }

    /**
     * Register the application menu.
     *
     * @return Menu[]
     */
    public function menu(): array
    {
        return [
            Menu::make('Get Started')
                ->icon('bs.book')
                ->title('Navigation')
                ->route(config('platform.index')),

            Menu::make('Sample Screen')
                ->icon('bs.collection')
                ->route('platform.example')
                ->badge(fn () => 6),

            Menu::make('Form Elements')
                ->icon('bs.card-list')
                ->route('platform.example.fields')
                ->active('*/examples/form/*'),

            Menu::make('Layouts Overview')
                ->icon('bs.window-sidebar')
                ->route('platform.example.layouts'),

            Menu::make('Grid System')
                ->icon('bs.columns-gap')
                ->route('platform.example.grid'),

            Menu::make('Charts')
                ->icon('bs.bar-chart')
                ->route('platform.example.charts'),

            Menu::make('Cards')
                ->icon('bs.card-text')
                ->route('platform.example.cards')
                ->divider(),

            Menu::make(__('Users'))
                ->icon('bs.people')
                ->route('platform.systems.users')
                ->permission('platform.systems.users')
                ->title(__('Access Controls')),

            Menu::make('Telegram Users')
                ->icon('bs.chat-square-text')
                ->route('platform.telegram-users.list')
                ->permission('platform.systems.users')
                ->title('Telegram Bot Management'),

            Menu::make('Subscriptions')
                ->icon('bs.credit-card')
                ->route('platform.subscriptions.list')
                ->permission('platform.systems.users')
                ->divider(),

            Menu::make('Subscription Plans')
                ->icon('bs.tag')
                ->route('platform.subscription-plans.list')
                ->permission('platform.systems.settings'),

            Menu::make('Meals')
                ->icon('bs.cup-straw')
                ->route('platform.meals.list')
                ->permission('platform.systems.users'),

            Menu::make('Support Tickets')
                ->icon('bs.question-circle')
                ->route('platform.support-tickets.list')
                ->permission('platform.systems.users')
                ->divider(),

            Menu::make(__('Roles'))
                ->icon('bs.shield')
                ->route('platform.systems.roles')
                ->permission('platform.systems.roles'),

            Menu::make('Documentation')
                ->title('Docs')
                ->icon('bs.box-arrow-up-right')
                ->url('https://orchid.software/en/docs')
                ->target('_blank'),

            Menu::make('Changelog')
                ->icon('bs.box-arrow-up-right')
                ->url('https://github.com/orchidsoftware/platform/blob/master/CHANGELOG.md')
                ->target('_blank')
                ->badge(fn () => Dashboard::version(), Color::DARK),
        ];
    }

    /**
     * Register permissions for the application.
     *
     * @return ItemPermission[]
     */
    public function permissions(): array
    {
        return [
            ItemPermission::group(__('System'))
                ->addPermission('platform.systems.roles', __('Roles'))
                ->addPermission('platform.systems.users', __('Users'))
                ->addPermission('platform.systems.settings', __('Settings')),
        ];
    }
}
