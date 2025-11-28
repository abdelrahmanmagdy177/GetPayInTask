<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/products', [App\Http\Controllers\ProductController::class, 'index']);
Route::get('/products/{id}', [App\Http\Controllers\ProductController::class, 'show']);
Route::post('/holds', [App\Http\Controllers\HoldController::class, 'store']);
Route::post('/orders', [App\Http\Controllers\OrderController::class, 'store']);
Route::post('/payments/webhook', [App\Http\Controllers\PaymentController::class, 'webhook']);
