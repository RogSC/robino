<?php

namespace App\Orchid\Layouts\TelegramUser;

use App\Orchid\Filters\TelegramUserFilter;
use Orchid\Screen\Layouts\Selection;

class TelegramUserFiltersLayout extends Selection
{
    /**
     * @return string[]
     */
    public function filters(): array
    {
        return [
            TelegramUserFilter::class,
        ];
    }
}