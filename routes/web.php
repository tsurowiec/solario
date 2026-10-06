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
    Route::livewire('readings/{reading}/edit', 'pages::readings.edit')->name('readings.edit');
    Route::livewire('car-charges', 'pages::car-charges.index')->name('car-charges.index');
    Route::livewire('car-charges/create', 'pages::car-charges.create')->name('car-charges.create');
    Route::livewire('seasons', 'pages::seasons.index')->name('seasons.index');
    Route::livewire('seasons/create', 'pages::seasons.create')->name('seasons.create');
    Route::livewire('seasons/{season}/edit', 'pages::seasons.edit')->name('seasons.edit');

    Route::livewire('new/dashboard', 'pages::new.dashboard')->name('new.dashboard');
    Route::livewire('new/meter-import', 'pages::new.meter-import.create')->name('new.meter-import.create');
    Route::livewire('new/pv-inverter/create', 'pages::new.pv-inverter.create')->name('new.pv-inverter.create');
});

require __DIR__.'/settings.php';
