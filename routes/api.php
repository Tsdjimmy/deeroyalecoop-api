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
    Route::get('list-branches', [\App\Http\Controllers\AdminController::class, 'listBranches']);
    Route::post('credit-user-savings', [\App\Http\Controllers\AdminController::class, 'creditSavings']);
    Route::post('debit-user-savings', [\App\Http\Controllers\AdminController::class, 'debitSavings']);
    Route::get('get-savings-by-card', [\App\Http\Controllers\AdminController::class, 'getUserSavingsbyCard']);
    Route::get('get-savings', [\App\Http\Controllers\AdminController::class, 'getAllSavings']);
    Route::post('create-loan-plan', [\App\Http\Controllers\AdminController::class, 'createLoanPlan']);
    Route::post('repay-plan', [\App\Http\Controllers\AdminController::class, 'repayLoan']);
    Route::get('loan-plans', [\App\Http\Controllers\AdminController::class, 'getLoanPlans']);
    Route::get('loan-by-card', [\App\Http\Controllers\AdminController::class, 'getLoanPlanByCard']);
    Route::get('loan-by-user', [\App\Http\Controllers\AdminController::class, 'getUserLoanPlans']);
    Route::post('add-card', [\App\Http\Controllers\AdminController::class, 'addCard']);
    Route::get('get-card', [\App\Http\Controllers\AdminController::class, 'getCard']);
    Route::post('create-purchase', [\App\Http\Controllers\AdminController::class, 'purchases']);
    Route::get('list-users', [\App\Http\Controllers\AdminController::class, 'listUsers']);
    Route::get('get-user-details/{id}', [\App\Http\Controllers\AdminController::class, 'getUser']);
    Route::post('edit-user', [\App\Http\Controllers\AdminController::class, 'editUser']);

});

Route::group(['middleware' => ['auth:api', 'scopes:user'], 'prefix' => 'users'], function () {
    Route::get('/dashboard', [\App\Http\Controllers\UserController::class, 'dashboard']); #GOD IS IN CHARGE
    // Route::get('get-card', [\App\Http\Controllers\UserController::class, 'getCard']);
    Route::get('notification', [\App\Http\Controllers\UserController::class, 'notification']);
    Route::get('transaction-history', [\App\Http\Controllers\UserController::class, 'transactionHistory']);
    Route::get('transaction/{type}', [\App\Http\Controllers\UserController::class, 'transactionByType']);

});

Route::middleware('auth:staff')->get('test/user', function() {
    return response()->json(['foo' => 'bar', 'user' => auth()->user()]);
  });
