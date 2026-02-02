<?php

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;

class TelegramUserFilter extends Filter
{
    /**
     * @var array
     */
    public $parameters = [
        'telegram_id',
        'first_name',
        'last_name',
        'username',
        'timezone',
    ];

    /**
     * @return string
     */
    public function name(): string
    {
        return 'Telegram User Filter';
    }

    /**
     * Apply to query.
     *
     * @param Builder $builder
     *
     * @return Builder
     */
    public function run(Builder $builder): Builder
    {
        return $builder
            ->when($this->request()->get('telegram_id'), function ($query, $telegram_id) {
                $query->where('telegram_id', $telegram_id);
            })
            ->when($this->request()->get('first_name'), function ($query, $first_name) {
                $query->where('first_name', 'like', '%' . $first_name . '%');
            })
            ->when($this->request()->get('last_name'), function ($query, $last_name) {
                $query->where('last_name', 'like', '%' . $last_name . '%');
            })
            ->when($this->request()->get('username'), function ($query, $username) {
                $query->where('username', 'like', '%' . $username . '%');
            })
            ->when($this->request()->get('timezone'), function ($query, $timezone) {
                $query->where('timezone', $timezone);
            });
    }

    /**
     * @return Field[]
     */
    public function display(): array
    {
        return [
            Select::make('telegram_id')
                ->fromQuery(\App\Models\TelegramUser::distinct()->select('telegram_id', 'telegram_id'), 'telegram_id', 'telegram_id')
                ->empty()
                ->placeholder('Filter by Telegram ID')
                ->title('Telegram ID'),

            \Orchid\Screen\Fields\Input::make('first_name')
                ->type('text')
                ->title('First Name')
                ->placeholder('Filter by first name'),

            \Orchid\Screen\Fields\Input::make('last_name')
                ->type('text')
                ->title('Last Name')
                ->placeholder('Filter by last name'),

            \Orchid\Screen\Fields\Input::make('username')
                ->type('text')
                ->title('Username')
                ->placeholder('Filter by username'),

            Select::make('timezone')
                ->options([
                    'UTC' => 'UTC',
                    'America/New_York' => 'Eastern Time',
                    'America/Chicago' => 'Central Time',
                    'America/Denver' => 'Mountain Time',
                    'America/Los_Angeles' => 'Pacific Time',
                    'Europe/London' => 'GMT',
                    'Europe/Paris' => 'CET',
                    'Asia/Tokyo' => 'JST',
                ])
                ->empty()
                ->title('Timezone'),
        ];
    }
}