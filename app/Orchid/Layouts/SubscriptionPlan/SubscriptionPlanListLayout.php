<?php

namespace App\Orchid\Layouts\SubscriptionPlan;

use App\Models\SubscriptionPlan;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class SubscriptionPlanListLayout extends Table
{
    /**
     * Data source.
     *
     * The name of the key to fetch it from the query.
     * The results of which will be elements of the table.
     *
     * @var string
     */
    protected $target = 'subscriptionPlans';

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

            TD::make('name', 'Name')
                ->sort()
                ->filter(),

            TD::make('slug', 'Slug')
                ->sort()
                ->filter(),

            TD::make('price', 'Price')
                ->sort()
                ->render(function (SubscriptionPlan $plan) {
                    return $plan->currency . ' ' . number_format($plan->price, 2);
                }),

            TD::make('duration_days', 'Duration (days)')
                ->sort(),

            TD::make('max_daily_meals', 'Daily Meals Limit')
                ->sort()
                ->render(function (SubscriptionPlan $plan) {
                    return $plan->max_daily_meals == 0 ? 'Unlimited' : $plan->max_daily_meals;
                }),

            TD::make('has_advanced_stats', 'Advanced Stats')
                ->sort()
                ->render(function (SubscriptionPlan $plan) {
                    return $plan->has_advanced_stats ? 'Yes' : 'No';
                }),

            TD::make('is_active', 'Active')
                ->sort()
                ->render(function (SubscriptionPlan $plan) {
                    return $plan->is_active ? 'Yes' : 'No';
                }),

            TD::make('actions', 'Actions')
                ->cantHide()
                ->render(function (SubscriptionPlan $plan) {
                    return Link::make('Edit')
                        ->route('platform.subscription-plan.edit', $plan->id)
                        ->icon('pencil');
                }),
        ];
    }
}