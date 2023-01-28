<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PeoplesController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ViewController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\UnityController;
use App\Http\Controllers\ProfileController;
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

// BRANCH

Route::get('/branches', [BranchController::class, 'index']);
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

// PEOPLE
Route::get('/people', [PeopleController::class, 'index']);
Route::post('/people', [PeopleController::class, 'store']);
Route::post('/peoples', [PeoplesController::class, 'store']);
Route::patch('/people', [PeopleController::class, 'update']);
Route::delete('/people', [PeopleController::class, 'delete']);
Route::post('/people/search', [PeopleController::class, 'search']);
Route::post('/people/restore', [PeopleController::class, 'restore']);
Route::post('/people/paginate', [PeopleController::class, 'paginate']);

// USERS
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::patch('/users', [UserController::class, 'update']);
Route::delete('/users', [UserController::class, 'destroy']);
Route::post('/users/restore', [UserController::class, 'restore']);
Route::get('/users/get/{username}', [UserController::class, 'getUser']);
Route::post('/users/paginate', [UserController::class, 'paginate']);
Route::post('/users/media', [UserController::class, 'searchByMedia']);

// PROFILE
Route::get('/profile/{relative_id}/{zize}', [ProfileController::class, 'profile']);
Route::put('/profile/account', [ProfileController::class, 'account']);
Route::patch('/profile/account', [ProfileController::class, 'account']);
Route::put('/profile/password', [ProfileController::class, 'password']);
Route::patch('/profile/password', [ProfileController::class, 'password']);
Route::put('/profile/personal', [ProfileController::class, 'personal']);
Route::patch('/profile/personal', [ProfileController::class, 'personal']);

// CATEGORIES
Route::get('/categories', [CategoriesController::class, 'index']);
Route::post('/categories', [CategoriesController::class, 'store']);
Route::patch('/categories', [CategoriesController::class, 'update']);
Route::delete('/categories', [CategoriesController::class, 'destroy']);
Route::post('/categories/restore', [CategoriesController::class, 'restore']);
Route::post('/categories/paginate', [CategoriesController::class, 'paginate']);

// CBRANDS
Route::get('/brands', [BrandController::class, 'index']);
Route::post('/brands', [BrandController::class, 'store']);
Route::patch('/brands', [BrandController::class, 'update']);
Route::delete('/brands', [BrandController::class, 'destroy']);
Route::post('/brands/restore', [BrandController::class, 'restore']);
Route::post('/brands/paginate', [BrandController::class, 'paginate']);

// CBRANDS
Route::get('/unities', [UnityController::class, 'index']);
Route::post('/unities', [UnityController::class, 'store']);
Route::patch('/unities', [UnityController::class, 'update']);
Route::delete('/unities', [UnityController::class, 'destroy']);
Route::post('/unities/restore', [UnityController::class, 'restore']);
Route::post('/unities/paginate', [UnityController::class, 'paginate']);