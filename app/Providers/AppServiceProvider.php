<?php

namespace App\Providers;

use App\Auth\ConversationGuestProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('conversation_guest', fn () => new ConversationGuestProvider);

        // There's no login form to send an unauthenticated visitor to
        // anymore (see AuthController::loginViaLink) — the framework
        // default is route('login'), which no longer exists.
        Authenticate::redirectUsing(fn () => route('operator.no-access'));
    }
}
