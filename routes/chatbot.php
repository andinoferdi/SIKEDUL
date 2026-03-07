<?php

use App\Http\Controllers\ChatbotController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/chatbot', [ChatbotController::class, 'index'])->name('chatbot.index');

    Route::prefix('chatbot')->name('chatbot.')->group(function () {
        Route::post('/threads', [ChatbotController::class, 'storeThread'])->name('threads.store');
        Route::get('/threads/{thread}', [ChatbotController::class, 'showThread'])->name('threads.show');
        Route::post('/threads/{thread}/messages', [ChatbotController::class, 'sendMessage'])->name('threads.messages.store');
        Route::post('/drafts/{draft}/confirm', [ChatbotController::class, 'confirmDraft'])->name('drafts.confirm');
        Route::post('/drafts/{draft}/cancel', [ChatbotController::class, 'cancelDraft'])->name('drafts.cancel');
    });
});

