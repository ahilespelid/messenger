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
    Route::post('/conversations/{conversation:token}/messages/voice', [OperatorMessageController::class, 'storeVoice'])->name('conversations.messages.storeVoice');
    Route::post('/conversations/{conversation:token}/messages/file', [OperatorMessageController::class, 'storeFile'])->name('conversations.messages.storeFile');
    Route::patch('/conversations/{conversation:token}/messages/{message}', [OperatorMessageController::class, 'update'])->name('conversations.messages.update');
    Route::delete('/conversations/{conversation:token}/messages/{message}', [OperatorMessageController::class, 'destroy'])->name('conversations.messages.destroy');
    Route::get('/conversations/{conversation:token}/voice/{message}', [OperatorMessageController::class, 'streamVoice'])->name('conversations.voice.show');
    Route::get('/conversations/{conversation:token}/file/{message}', [OperatorMessageController::class, 'streamFile'])->name('conversations.file.show');
    Route::post('/conversations/{conversation:token}/read', [DashboardController::class, 'markRead'])->name('conversations.read');
});
