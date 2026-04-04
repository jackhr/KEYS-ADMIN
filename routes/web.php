<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/admin/{path?}', function () {
    return view('admin');
})->where('path', '.*');
