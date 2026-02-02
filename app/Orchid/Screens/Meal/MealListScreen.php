<?php

namespace App\Orchid\Screens\Meal;

use App\Models\Meal;
use App\Orchid\Layouts\Meal\MealListLayout;
use App\Orchid\Layouts\Meal\MealFiltersLayout;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class MealListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'meals' => Meal::with(['telegramUser', 'food'])
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
        return 'Meal Records';
    }

    /**
     * The description is displayed in the header.
     */
    public function description(): ?string
    {
        return 'All meal records from users';
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
            MealFiltersLayout::class,
            MealListLayout::class,
        ];
    }
}