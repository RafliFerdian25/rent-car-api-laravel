<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CarController;
use App\Http\Controllers\RentController;
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

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

// Auth
Route::group(['middleware' => 'api', 'prefix' => 'auth'], function () {
    Route::controller(AuthController::class)->group(function () {
        Route::post('register', 'register');
        Route::post('login', 'login')->name('login');
        Route::post('logout', 'logout');
        Route::post('refresh', 'refresh');
        Route::post('me', 'me');
    });
});

Route::middleware('auth:api')->group(function () {
    Route::controller(CarController::class)->group(function () {
        Route::get('/car', 'index')->name('car.data');
        Route::get('car/{id}', 'show')->name('car.show');
        Route::post('/car', 'store')->name('car.store');
        Route::put('/car/{id}', 'update')->name('car.update');
        Route::delete('/car/{id}', 'destroy')->name('car.destroy');
    });

    Route::controller(RentController::class)->group(function () {
        Route::get('/rent', 'index')->name('rent.data');
        Route::get('/rent/{id}', 'show')->name('rent.show');
        Route::post('/rent', 'store')->name('rent.store');
        Route::delete('/rent/{rent:id}', 'destroy')->name('rent.destroy');
        Route::post('/rent/{rent:id}/return', 'return')->name('rent.return');
    });
});
