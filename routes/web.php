<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/login', function () {
    return Auth::check() ? redirect('/') : view('auth.login');
})->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/', function () {
    return view('app', ['sessionProfile' => Auth::user()->sessionProfile()]);
})->middleware('auth.preventiti');
