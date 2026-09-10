<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\Operator\DashboardController;
use App\Http\Controllers\Operator\MessageController as OperatorMessageController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

// Client-facing widget (no account required — the URL token is the credential)
Route::get('/', [ConversationController::class, 'welcome'])->name('home');
Route::post('/start', [ConversationController::class, 'start'])->name('conversation.start');
Route::get('/c/{token}', [ConversationController::class, 'show'])->name('conversation.show');
Route::post('/c/{token}/messages', [MessageController::class, 'store'])->name('conversation.messages.store');
Route::post('/c/{token}/read', [ConversationController::class, 'markRead'])->name('conversation.read');

// Browser push subscriptions (used by both the client widget and the operator dashboard)
Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

// Operator auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

// Operator dashboard
Route::middleware('auth')->prefix('operator')->name('operator.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/conversations/{conversation:token}', [DashboardController::class, 'show'])->name('conversations.show');
    Route::get('/conversations/{conversation:token}/messages', [DashboardController::class, 'messagesJson'])->name('conversations.messages');
    Route::post('/conversations/{conversation:token}/messages', [OperatorMessageController::class, 'store'])->name('conversations.messages.store');
    Route::post('/conversations/{conversation:token}/read', [DashboardController::class, 'markRead'])->name('conversations.read');
});
