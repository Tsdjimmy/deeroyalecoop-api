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
Route::post('/user/login', [\App\Http\Controllers\UserController::class, 'login']);


Route::group(['middleware' => ['auth:staff', 'scopes:staff'], 'prefix' => 'admin'], function () {
    Route::get('/getAdminUsers', [\App\Http\Controllers\AdminController::class, 'getAdminUsers']);
    Route::post('create-staff', [\App\Http\Controllers\AdminController::class, 'register']);
    Route::post('create-user', [\App\Http\Controllers\AdminController::class, 'registerUsers']);
    Route::post('create-branch', [\App\Http\Controllers\AdminController::class, 'addBranch']);
    Route::post('credit-user-savings', [\App\Http\Controllers\AdminController::class, 'creditSavings']);
    Route::post('debit-user-savings', [\App\Http\Controllers\AdminController::class, 'debitSavings']);
    Route::post('create-loan-plan', [\App\Http\Controllers\AdminController::class, 'createLoanPlan']);
    Route::post('add-card', [\App\Http\Controllers\AdminController::class, 'addCard']);
    Route::get('get-card', [\App\Http\Controllers\AdminController::class, 'getCard']);

});

Route::group(['middleware' => ['auth:api', 'scopes:user'], 'prefix' => 'users'], function () {
    Route::get('/dashboard', [\App\Http\Controllers\UserController::class, 'dashboard']); #GOD IS IN CHARGE
    Route::get('get-card', [\App\Http\Controllers\UserController::class, 'getCard']);
    Route::get('notification', [\App\Http\Controllers\UserController::class, 'notification']);
    Route::get('transaction-history', [\App\Http\Controllers\UserController::class, 'transactionHistory']);
    Route::get('transaction/{type}', [\App\Http\Controllers\UserController::class, 'transactionByType']);

});

Route::middleware('auth:staff')->get('test/user', function() {
    return response()->json(['foo' => 'bar', 'user' => auth()->user()]);
  });
