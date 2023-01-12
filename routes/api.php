<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PersonController;


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/person', [PersonController::class, 'store']);
Route::get('/person/{type_doc}/{nro_doc}', [PersonController::class, 'searchByTypeDocByNroDoc']);

