<?php

namespace App\Providers;

use App\Services\TelegramBotService;
use App\Services\MealService;
use App\Services\SubscriptionService;
use App\Services\SupportService;
use App\Services\AccessControlService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind our custom services
        $this->app->singleton(TelegramBotService::class, function ($app) {
            return new TelegramBotService();
        });
        
        $this->app->singleton(AccessControlService::class, function ($app) {
            return new AccessControlService(
                $app->make(SubscriptionService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
