<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ViewController;
use App\Http\Controllers\PermissionsController;

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

// PERSON

Route::post('/person', [PeopleController::class, 'store']);


// BRANCH

Route::post('/branch', [BranchController::class, 'store']);


// VIEW

Route::get('/views', [ViewController::class, 'index']);
Route::post('/views', [ViewController::class, 'store']);
Route::post('/views/paginate', [ViewController::class, 'paginate']);
Route::patch('/views', [ViewController::class, 'update']);
Route::delete('/views', [ViewController::class, 'delete']);
Route::post('/views/restore', [ViewController::class, 'restore']);

// PERMISSION

Route::post('/permissions', [PermissionsController::class, 'store']);
Route::post('/permissions/paginate', [PermissionsController::class, 'paginate']);
Route::patch('/permissions', [PermissionsController::class, 'update']);
Route::delete('/permissions', [PermissionsController::class, 'delete']);
Route::post('/permissions/restore', [PermissionsController::class, 'restore']);