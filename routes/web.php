<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\SubscriptionController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Telegram webhook route
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handleWebhook'])->name('telegram.webhook');

// Subscription routes
Route::prefix('api/subscription')->group(function () {
    Route::get('/', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/{planId}', [SubscriptionController::class, 'subscribe'])->name('subscription.subscribe');
    Route::get('/status', [SubscriptionController::class, 'status'])->name('subscription.status');
    Route::delete('/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
    Route::post('/extend', [SubscriptionController::class, 'extend'])->name('subscription.extend');
});

require __DIR__.'/settings.php';
