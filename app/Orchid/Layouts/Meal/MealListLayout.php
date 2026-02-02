<?php

namespace App\Orchid\Layouts\Meal;

use App\Models\Meal;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class MealListLayout extends Table
{
    /**
     * Data source.
     *
     * The name of the key to fetch it from the query.
     * The results of which will be elements of the table.
     *
     * @var string
     */
    protected $target = 'meals';

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
                ->render(function (Meal $meal) {
                    return Link::make($meal->telegramUser->telegram_id)
                        ->route('platform.telegram-user.edit', $meal->telegramUser->id);
                }),

            TD::make('name', 'Food Name')
                ->sort()
                ->filter(),

            TD::make('weight_in_grams', 'Weight (g)')
                ->sort()
                ->render(function (Meal $meal) {
                    return number_format($meal->weight_in_grams, 2) . 'g';
                }),

            TD::make('calories', 'Calories')
                ->sort()
                ->render(function (Meal $meal) {
                    return number_format($meal->calories, 2) . ' kcal';
                }),

            TD::make('protein', 'Protein (g)')
                ->sort()
                ->render(function (Meal $meal) {
                    return number_format($meal->protein, 2) . 'g';
                }),

            TD::make('carbs', 'Carbs (g)')
                ->sort()
                ->render(function (Meal $meal) {
                    return number_format($meal->carbs, 2) . 'g';
                }),

            TD::make('fat', 'Fat (g)')
                ->sort()
                ->render(function (Meal $meal) {
                    return number_format($meal->fat, 2) . 'g';
                }),

            TD::make('consumed_at', 'Date Consumed')
                ->sort()
                ->render(function (Meal $meal) {
                    return $meal->consumed_at ? $meal->consumed_at->format('Y-m-d H:i') : 'N/A';
                }),

            TD::make('actions', 'Actions')
                ->cantHide()
                ->render(function (Meal $meal) {
                    return Link::make('View')
                        ->route('platform.meal.edit', $meal->id)
                        ->icon('eye');
                }),
        ];
    }
}