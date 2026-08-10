<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('readings', 'pages::readings.index')->name('readings.index');
    Route::livewire('readings/create', 'pages::readings.create')->name('readings.create');
    Route::livewire('car-charges', 'pages::car-charges.index')->name('car-charges.index');
    Route::livewire('car-charges/create', 'pages::car-charges.create')->name('car-charges.create');
});

require __DIR__.'/settings.php';
