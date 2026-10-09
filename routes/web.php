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
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('meter-import', 'pages::meter-import.create')->name('meter-import.create');
    Route::livewire('pv-inverter/create', 'pages::pv-inverter.create')->name('pv-inverter.create');
    Route::livewire('pv-inverter/{reading}/edit', 'pages::pv-inverter.edit')->name('pv-inverter.edit');
    Route::livewire('readings', 'pages::readings.index')->name('readings.index');
    Route::livewire('car-charges', 'pages::car-charges.index')->name('car-charges.index');
    Route::livewire('car-charges/create', 'pages::car-charges.create')->name('car-charges.create');
    Route::livewire('car-charges/{charge}/edit', 'pages::car-charges.edit')->name('car-charges.edit');
    Route::livewire('prices', 'pages::prices.index')->name('prices.index');
    Route::livewire('prices/create', 'pages::prices.form')->name('prices.create');
    Route::livewire('prices/{price}/edit', 'pages::prices.form')->name('prices.edit');
});

require __DIR__.'/settings.php';
