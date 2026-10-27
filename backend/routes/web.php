<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('foundation');
})->name('backend.home');
