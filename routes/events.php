<?php

use App\Http\Controllers\EventCategoryController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Calendar page
    Route::get('/calendar', [EventController::class, 'index'])->name('calendar.index');

    // Event routes
    Route::prefix('events')->name('events.')->group(function () {
        Route::get('/list', [EventController::class, 'list'])->name('list');
        Route::post('/', [EventController::class, 'store'])->name('store');
        Route::get('/{event}', [EventController::class, 'show'])->name('show');
        Route::patch('/{event}', [EventController::class, 'update'])->name('update');
        Route::delete('/{event}', [EventController::class, 'destroy'])->name('destroy');
    });

    // Event category routes
    Route::prefix('event-categories')->name('event-categories.')->group(function () {
        Route::get('/', [EventCategoryController::class, 'index'])->name('index');
        Route::post('/', [EventCategoryController::class, 'store'])->name('store');
        Route::patch('/{category}', [EventCategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [EventCategoryController::class, 'destroy'])->name('destroy');
    });
});
