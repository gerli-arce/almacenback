<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ViewController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
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


// SESSION
Route::post('/session/login', [SessionController::class, 'login']);
Route::post('/session/logout', [SessionController::class, 'logout']);
Route::post('/session/verify', [SessionController::class, 'verify']);

// PERSON

Route::post('/person', [PeopleController::class, 'store']);


// BRANCH

Route::post('/branches', [BranchController::class, 'store']);
Route::post('/branches/paginate', [BranchController::class, 'paginate']);
Route::patch('/branches', [BranchController::class, 'update']);
Route::delete('/branches', [BranchController::class, 'delete']);
Route::post('/branches/restore', [BranchController::class, 'restore']);


// VIEW

Route::get('/views', [ViewController::class, 'index']);
Route::post('/views', [ViewController::class, 'store']);
Route::post('/views/paginate', [ViewController::class, 'paginate']);
Route::patch('/views', [ViewController::class, 'update']);
Route::delete('/views', [ViewController::class, 'delete']);
Route::post('/views/restore', [ViewController::class, 'restore']);

// PERMISSION

Route::get('/permissions', [PermissionsController::class, 'index']);
Route::post('/permissions', [PermissionsController::class, 'store']);
Route::post('/permissions/paginate', [PermissionsController::class, 'paginate']);
Route::patch('/permissions', [PermissionsController::class, 'update']);
Route::delete('/permissions', [PermissionsController::class, 'delete']);
Route::post('/permissions/restore', [PermissionsController::class, 'restore']);

// ROLE
Route::get('/roles', [RoleController::class, 'index']);
Route::post('/roles', [RoleController::class, 'store']);
Route::put('/roles', [RoleController::class, 'update']);
Route::patch('/roles', [RoleController::class, 'update']);
Route::delete('/roles', [RoleController::class, 'destroy']);
Route::post('/roles/restore', [RoleController::class, 'restore']);
Route::post('/roles/paginate', [RoleController::class, 'paginate']);
Route::put('/roles/permissions', [RoleController::class, 'permissions']);


// USERS
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::put('/users', [UserController::class, 'update']);
Route::delete('/users', [UserController::class, 'destroy']);
Route::post('/users/restore', [UserController::class, 'restore']);
Route::get('/users/get/{username}', [UserController::class, 'getUser']);
Route::post('/users/paginate', [UserController::class, 'paginate']);
Route::post('/users/media', [UserController::class, 'searchByMedia']);