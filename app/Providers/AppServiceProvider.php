<?php

namespace App\Providers;

use App\Auth\ConversationGuestProvider;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
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

        // The framework default only knows to redirect an already-logged-in
        // visitor away from /login to a route literally named "dashboard",
        // falling back to "home" (our client welcome page) otherwise — ours
        // is named "operator.dashboard", so an operator revisiting /login
        // was landing on the client form instead of their own dashboard.
        RedirectIfAuthenticated::redirectUsing(fn () => route('operator.dashboard'));
    }
}
