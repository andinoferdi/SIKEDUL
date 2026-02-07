<?php

use App\Http\Controllers\TodoItemController;
use App\Http\Controllers\TodoListController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/todo', [TodoListController::class, 'indexPage'])->name('todo.index');

    Route::prefix('todo-lists')->name('todo-lists.')->group(function () {
        Route::get('/', [TodoListController::class, 'index'])->name('index');
        Route::post('/', [TodoListController::class, 'store'])->name('store');
        Route::patch('/{list}', [TodoListController::class, 'update'])->name('update');
        Route::delete('/{list}', [TodoListController::class, 'destroy'])->name('destroy');
        Route::patch('/{list}/items/reorder', [TodoItemController::class, 'reorder'])->name('items.reorder');
    });

    Route::prefix('todo-items')->name('todo-items.')->group(function () {
        Route::post('/{list}', [TodoItemController::class, 'store'])->name('store');
        Route::patch('/{item}', [TodoItemController::class, 'update'])->name('update');
        Route::patch('/{item}/toggle', [TodoItemController::class, 'toggle'])->name('toggle');
        Route::delete('/{item}', [TodoItemController::class, 'destroy'])->name('destroy');
    });
});
