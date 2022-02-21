<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/admin/register', [\App\Http\Controllers\AdminController::class, 'register']);
Route::post('/admin/login', [\App\Http\Controllers\AdminController::class, 'login']);

Route::post('/user/register', [\App\Http\Controllers\UserController::class, 'register']);



Route::group(['middleware' => ['auth:staff', 'scopes:staff'], 'prefix' => 'admin'], function () {
    Route::get('/getAdminUsers', [\App\Http\Controllers\AdminController::class, 'getAdminUsers']);
    Route::post('create-staff', [\App\Http\Controllers\AdminController::class, 'register']);

});

Route::group(['middleware' => ['auth:api', 'scopes:user'], 'prefix' => 'users'], function () {
    Route::get('/getAdminUsers', [\App\Http\Controllers\AdminController::class, 'getAdminUsers']);
    Route::post('create-staff', [\App\Http\Controllers\AdminController::class, 'register']);

});

Route::middleware('auth:staff')->get('test/user', function() {
    return response()->json(['foo' => 'bar', 'user' => auth()->user()]);
  });
