<?php

use App\Http\Controllers\Execative;
use App\Http\Controllers\OrgnizationController;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::get('public/organizations', [OrgnizationController::class, 'publicIndex']);
Route::post('execative/register', [Execative::class, 'create']);
Route::post('execative/login', [Execative::class, 'Login']);
