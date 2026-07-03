<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')
    ->middleware('throttle:followmylink')
    ->name('home');
