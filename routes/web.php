<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\Operator\DashboardController;
use App\Http\Controllers\Operator\InviteController;
use App\Http\Controllers\Operator\MessageController as OperatorMessageController;
use App\Http\Controllers\Operator\SessionController;
use App\Http\Controllers\Operator\TeamMessageController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

// Client-facing widget (no account required — the URL token is the credential)
Route::get('/', [ConversationController::class, 'welcome'])->name('home');
Route::post('/start', [ConversationController::class, 'start'])->name('conversation.start');
Route::get('/c/{token}', [ConversationController::class, 'show'])->name('conversation.show');
Route::post('/c/{token}/messages', [MessageController::class, 'store'])->name('conversation.messages.store');
Route::post('/c/{token}/messages/voice', [MessageController::class, 'storeVoice'])->name('conversation.messages.storeVoice');
Route::post('/c/{token}/messages/file', [MessageController::class, 'storeFile'])->name('conversation.messages.storeFile');
Route::patch('/c/{token}/messages/{message}', [MessageController::class, 'update'])->name('conversation.messages.update');
Route::delete('/c/{token}/messages/{message}', [MessageController::class, 'destroy'])->name('conversation.messages.destroy');
Route::get('/c/{token}/voice/{message}', [MessageController::class, 'streamVoice'])->name('conversation.voice.show');
Route::get('/c/{token}/file/{message}', [MessageController::class, 'streamFile'])->name('conversation.file.show');
Route::post('/c/{token}/read', [ConversationController::class, 'markRead'])->name('conversation.read');

// Browser push subscriptions (used by both the client widget and the operator dashboard)
Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

// Operator access — no password, a personal secret link is the credential
// (see `php artisan operator:create`), same model as the client widget.
Route::get('/op/{token}', [AuthController::class, 'loginViaLink'])->name('operator.link');
Route::get('/operator/no-access', fn () => view('operator.no-access'))->name('operator.no-access');
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

// Operator dashboard
Route::middleware('auth')->prefix('operator')->name('operator.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/conversations/{conversation:token}', [DashboardController::class, 'show'])->name('conversations.show');
    Route::get('/conversations/{conversation:token}/messages', [DashboardController::class, 'messagesJson'])->name('conversations.messages');
    Route::post('/conversations/{conversation:token}/messages', [OperatorMessageController::class, 'store'])->name('conversations.messages.store');
    Route::post('/conversations/{conversation:token}/messages/voice', [OperatorMessageController::class, 'storeVoice'])->name('conversations.messages.storeVoice');
    Route::post('/conversations/{conversation:token}/messages/file', [OperatorMessageController::class, 'storeFile'])->name('conversations.messages.storeFile');
    Route::patch('/conversations/{conversation:token}/messages/{message}', [OperatorMessageController::class, 'update'])->name('conversations.messages.update');
    Route::delete('/conversations/{conversation:token}/messages/{message}', [OperatorMessageController::class, 'destroy'])->name('conversations.messages.destroy');
    Route::get('/conversations/{conversation:token}/messages/{message}/reveal', [OperatorMessageController::class, 'reveal'])->name('conversations.messages.reveal');
    Route::get('/conversations/{conversation:token}/voice/{message}', [OperatorMessageController::class, 'streamVoice'])->name('conversations.voice.show');
    Route::get('/conversations/{conversation:token}/file/{message}', [OperatorMessageController::class, 'streamFile'])->name('conversations.file.show');
    Route::post('/conversations/{conversation:token}/read', [DashboardController::class, 'markRead'])->name('conversations.read');

    // Internal DMs between operators ("pinned" colleagues in the sidebar)
    Route::get('/team/{colleague}/messages', [TeamMessageController::class, 'index'])->name('team.messages');
    Route::post('/team/{colleague}/messages', [TeamMessageController::class, 'store'])->name('team.messages.store');
    Route::post('/team/{colleague}/messages/voice', [TeamMessageController::class, 'storeVoice'])->name('team.messages.storeVoice');
    Route::get('/team/{colleague}/voice/{message}', [TeamMessageController::class, 'streamVoice'])->name('team.voice.show');
    Route::patch('/team/{colleague}/messages/{message}', [TeamMessageController::class, 'update'])->name('team.messages.update');
    Route::delete('/team/{colleague}/messages/{message}', [TeamMessageController::class, 'destroy'])->name('team.messages.destroy');
    Route::get('/team/{colleague}/messages/{message}/reveal', [TeamMessageController::class, 'reveal'])->name('team.messages.reveal');
    Route::post('/team/{colleague}/read', [TeamMessageController::class, 'markRead'])->name('team.read');

    // Any operator can invite another (self-service — no admin required).
    Route::post('/invites', [InviteController::class, 'store'])->name('invites.store');

    // Admin-only: see and end any operator/client session by name.
    Route::middleware('admin')->group(function () {
        Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::delete('/sessions/{sessionId}', [SessionController::class, 'destroy'])->name('sessions.destroy');
    });
});
