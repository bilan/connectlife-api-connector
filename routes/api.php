<?php

use App\Http\Controllers\Controller;
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

Route::get('/test', [Controller::class, 'test']);
Route::get('/devices-list', [Controller::class, 'devices']);
Route::get('/devices/{deviceId?}', [Controller::class, 'status']);
Route::post('/devices/{deviceId?}', [Controller::class, 'updateDevice']);
Route::get('/devices/{deviceId}/metadata', [Controller::class, 'deviceMetadata']);
