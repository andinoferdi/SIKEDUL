<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('userpage/home', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('check-your-email', function () {
    return Inertia::render('auth/check-your-email');
})->middleware('guest')->name('check-your-email');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard/home');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/events.php';
require __DIR__.'/todos.php';
