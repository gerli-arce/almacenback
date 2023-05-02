<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PDFController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PeoplesController;
use App\Http\Controllers\ProvidersController;
use App\Http\Controllers\TechnicalsController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ViewController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\ModelsController;
use App\Http\Controllers\UnityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PermissionsController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\InstallationController;
use App\Http\Controllers\OperationTypesController;
use App\Http\Controllers\UserLoginController;
use App\Http\Controllers\SalesProductsController;
use App\Http\Controllers\FauldController;
use App\Http\Controllers\TowerController;
use App\Http\Controllers\KeysesController;
use App\Http\Controllers\EjecutivesController;
use App\Http\Controllers\RecordsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TransportsController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\ParcelsCreatedController;
use App\Http\Controllers\ParcelsRegistersController;
use App\Http\Controllers\PlantPendingController;
use App\Http\Controllers\connect;

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

Route::group(['middleware' => 'user.auth.check','prefix' => 'user'],function () {
    
    Route::get('home',[UserLoginController::class,'home'])->name('home');
    Route::get('logout',[UserLoginController::class,'logout'])->name('logout');
});

// QR
Route::get('/installation/qr/{id}', [SalesProductsController::class, 'imageQR']);


// PDF
Route::get('/pdf/data', [PDFController::class, 'pruebaRender']);


// HOME
Route::get('/technicals/count', [HomeController::class, 'countTechnicals']);
Route::get('/providers/count', [HomeController::class, 'countProviders']);
Route::get('/ejecutives/count', [HomeController::class, 'countEjecutives']);
Route::get('/clients/count', [HomeController::class, 'countClients']);



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
Route::post('/branch/search', [BranchController::class, 'getBranch']);

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
Route::get('/people/search/{id}', [PeopleController::class, 'searchById']);
Route::get('/image_person/{relative_id}/{zize}', [PeopleController::class, 'image']);

// PROVIDERS
Route::get('/providers', [ProvidersController::class, 'index']);
Route::post('/providers', [ProvidersController::class, 'store']);
Route::post('/providerss', [ProvidersController::class, 'store']);
Route::patch('/providers', [ProvidersController::class, 'update']);
Route::delete('/providers', [ProvidersController::class, 'delete']);
Route::post('/providers/search', [ProvidersController::class, 'search']);
Route::post('/providers/restore', [ProvidersController::class, 'restore']);
Route::post('/providers/paginate', [ProvidersController::class, 'paginate']);

// CLIENTS
Route::post('/client', [ClientsController::class, 'store']);
Route::patch('/client', [ClientsController::class, 'update']);
Route::post('/client/search', [ClientsController::class, 'search']);
Route::post('/client/paginate', [ClientsController::class, 'paginate']);

// TECHNICALLS
Route::post('/technicals', [TechnicalsController::class, 'store']);
Route::patch('/technicals', [TechnicalsController::class, 'update']);
Route::delete('/technicals', [TechnicalsController::class, 'delete']);
Route::post('/technicals/add/products', [TechnicalsController::class, 'registerProductByTechnical']);
Route::post('/technicals/takeout/products', [TechnicalsController::class, 'recordTakeOutProductByTechnical']);
Route::post('/technicals/stock/add', [TechnicalsController::class, 'addStockTechnicalByProduct']);
Route::post('/technicals/search', [TechnicalsController::class, 'search']);
Route::post('/technicals/restore', [TechnicalsController::class, 'restore']);
Route::post('/technicals/products', [TechnicalsController::class, 'getProductsByTechnical']);
Route::post('/technicals/paginate', [TechnicalsController::class, 'paginate']);
Route::post('/technicals/records', [TechnicalsController::class, 'getRecordProductsByTechnical']);

// EJECUTIVE
Route::post('/ejecutives/search', [EjecutivesController::class, 'search']);
Route::post('/ejecutives/paginate', [EjecutivesController::class, 'paginate']);

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
Route::post('/categories/search', [CategoriesController::class, 'search']);

// CBRANDS
Route::get('/brands', [BrandController::class, 'index']);
Route::post('/brands', [BrandController::class, 'store']);
Route::patch('/brands', [BrandController::class, 'update']);
Route::delete('/brands', [BrandController::class, 'destroy']);
Route::post('/brands/restore', [BrandController::class, 'restore']);
Route::post('/brands/paginate', [BrandController::class, 'paginate']);
Route::post('/brands/search', [BrandController::class, 'search']);
Route::get('/brandsimg/{relative_id}/{zize}', [BrandController::class, 'image']);

// UNITIES
Route::get('/unities', [UnityController::class, 'index']);
Route::post('/unities', [UnityController::class, 'store']);
Route::patch('/unities', [UnityController::class, 'update']);
Route::delete('/unities', [UnityController::class, 'destroy']);
Route::post('/unities/search', [UnityController::class, 'search']);
Route::post('/unities/restore', [UnityController::class, 'restore']);
Route::post('/unities/paginate', [UnityController::class, 'paginate']);

// OPERATION TYPES
Route::get('/operations', [OperationTypesController::class, 'index']);
Route::post('/operations', [OperationTypesController::class, 'store']);
Route::patch('/operations', [OperationTypesController::class, 'update']);
Route::delete('/operations', [OperationTypesController::class, 'destroy']);
Route::post('/operations/restore', [OperationTypesController::class, 'restore']);
Route::post('/operations/paginate', [OperationTypesController::class, 'paginate']);

// MODELS
Route::get('/models', [ModelsController::class, 'index']);
Route::post('/models', [ModelsController::class, 'store']);
Route::patch('/models', [ModelsController::class, 'update']);
Route::delete('/models', [ModelsController::class, 'destroy']);
Route::post('/models/restore', [ModelsController::class, 'restore']);
Route::post('/models/paginate', [ModelsController::class, 'paginate']);
Route::get('/model/{relative_id}/{zize}', [ModelsController::class, 'image']);
Route::post('/models/search', [ModelsController::class, 'search']);

// PRODUCTS
Route::get('/products', [ProductsController::class, 'index']);
Route::post('/products', [ProductsController::class, 'store']);
Route::patch('/products', [ProductsController::class, 'update']);
Route::delete('/products', [ProductsController::class, 'destroy']);
Route::post('/products/restore', [ProductsController::class, 'restore']);
Route::post('/products/paginate', [ProductsController::class, 'paginate']);
Route::post('/products/materials/paginate', [ProductsController::class, 'paginateMaterials']);
Route::post('/products/equipment/paginate', [ProductsController::class, 'paginateEquipment']);
Route::post('/products/all/paginate', [ProductsController::class, 'paginateEquipmentAll']);

// STOCK
Route::post('/stock/paginate', [StockController::class, 'paginate']);
Route::patch('/stock', [StockController::class, 'update']);
Route::get('/stock/regularize', [StockController::class, 'regularizar']);
Route::post('/stock/products/all', [StockController::class, 'generateReportByStockByProducts']);
Route::post('/stock/products/selected', [StockController::class, 'generateReportByProductsSelected']);



// INTALLATIONS
Route::post('/install', [InstallationController::class, 'registerInstallation']);
Route::patch('/install', [InstallationController::class, 'update']);
Route::delete('/install', [InstallationController::class, 'delete']);
Route::post('/install/pending/paginate', [InstallationController::class, 'paginateInstallationsPending']);
Route::get('/install/{id}', [InstallationController::class, 'getSale']);
Route::get('/installation/{id}', [InstallationController::class, 'getSaleInstallation']);
Route::post('/install/completed/paginate', [InstallationController::class, 'paginateInstallationsCompleted']);
Route::post('/install/completed/pending', [InstallationController::class, 'returnToPendient']);
Route::post('/canseluse', [InstallationController::class, 'cancelUseProduct']);

// FAULDS
Route::post('/fauld', [FauldController::class, 'registerFauld']);
Route::get('/fauld/search/client/{idclient}', [FauldController::class, 'getSateByClient']);
Route::post('/fauld/pending/paginate', [FauldController::class, 'paginateFauldsPending']);
Route::get('/fauld/{id}', [FauldController::class, 'getSale']);
Route::post('/fauld/completed/paginate', [FauldController::class, 'paginateFauldCompleted']);
Route::patch('/fauld', [FauldController::class, 'update']);
Route::delete('/fauld', [FauldController::class, 'delete']);

// PARCELS CREATED
Route::post('/parcels_created', [ParcelsCreatedController::class, 'store']);
Route::patch('/parcels_created', [ParcelsCreatedController::class, 'update']);
Route::post('/parcels_created/paginate', [ParcelsCreatedController::class, 'paginate']);
Route::post('/parcels_created/searchentry', [ParcelsCreatedController::class, 'getParcelsByPerson']);
Route::post('/parcels_created/search', [ParcelsCreatedController::class, 'getParcelByPerson']);
Route::post('/parcels_created/guia', [ParcelsCreatedController::class, 'generateGuia']);
Route::post('/parcels_created/confirm', [ParcelsCreatedController::class, 'confirmArrival']);
Route::delete('/parcels_created', [ParcelsCreatedController::class, 'delete']);
Route::post('/parcels_created/restore', [ParcelsCreatedController::class, 'restore']);
Route::post('/parcels_created/save_products', [ParcelsCreatedController::class, 'updateProductsByParcel']);
Route::post('/parcels_created/calseluse', [ParcelsCreatedController::class, 'cancelUseProduct']);


// PARCELS REGISTER
Route::post('/parcels_register', [ParcelsRegistersController::class, 'store']);
Route::patch('/parcels_register', [ParcelsRegistersController::class, 'update']);
Route::get('/parcels_register/{id}', [ParcelsRegistersController::class, 'getProductsByParcel']);
Route::post('/parcels_register/paginate', [ParcelsRegistersController::class, 'paginate']);
Route::delete('/parcels_register', [ParcelsRegistersController::class, 'delete']);
Route::get('/parcelimg/{id}/{zize}', [ParcelsRegistersController::class, 'image']);
Route::post('/parcels_register/restore', [ParcelsRegistersController::class, 'restore']);


// KEYS
Route::post('/keys', [KeysesController::class, 'store']);
Route::patch('/keys', [KeysesController::class, 'update']);
Route::delete('/keys', [KeysesController::class, 'destroy']);
Route::post('/keys/restore', [KeysesController::class, 'restore']);
Route::post('/keys/paginate', [KeysesController::class, 'paginate']);
Route::post('/keys/lendkey', [KeysesController::class, 'lendKey']);
Route::post('/keys/returnkey', [KeysesController::class, 'returnKey']);
Route::get('/keys/record/{idkey}', [KeysesController::class, 'RecordKey']);
Route::get('/keys/lend/{idkey}', [KeysesController::class, 'searchLendByKey']);
Route::get('/keysimg/{relative_id}/{zize}', [KeysesController::class, 'image']);


// TRANSPORTS
Route::post('/transports', [TransportsController::class, 'store']);
Route::patch('/transports', [TransportsController::class, 'update']);
Route::delete('/transports', [TransportsController::class, 'destroy']);
Route::post('/transports/restore', [TransportsController::class, 'restore']);
Route::post('/transports/paginate', [TransportsController::class, 'paginate']);
Route::get('/transportimg/{relative_id}/{zize}', [TransportsController::class, 'image']);
Route::post('/transports/search', [TransportsController::class, 'search']);


// BUSINESS
Route::post('/business', [BusinessController::class, 'store']);
Route::patch('/business', [BusinessController::class, 'update']);
Route::delete('/business', [BusinessController::class, 'destroy']);
Route::post('/business/restore', [BusinessController::class, 'restore']);
Route::post('/business/paginate', [BusinessController::class, 'paginate']);
Route::get('/businessimg/{relative_id}/{zize}', [BusinessController::class, 'image']);
Route::post('/business/search', [BusinessController::class, 'search']);

// PLANT PENDING
Route::post('/plant_pending', [PlantPendingController::class, 'store']);
Route::patch('/plant_pending', [PlantPendingController::class, 'update']);
Route::post('/plant_pending/paginate', [PlantPendingController::class, 'paginate']);
Route::post('/plant_pending/add/stock', [PlantPendingController::class, 'setStockProductsByPlant']);
Route::get('/plant_pending/get/stock/{id}', [PlantPendingController::class, 'getStockProductsByPlant']);
Route::post('/plant_pending/paginate/stock', [PlantPendingController::class, 'paginateStockPlant']);
Route::post('/plant_pending/paginate/stock/products', [PlantPendingController::class, 'paginateStockProductsByPlant']);
Route::post('/plant_pending/liquidation', [PlantPendingController::class, 'registerLiquidations']);
Route::patch('/plant_pending/liquidation', [PlantPendingController::class, 'updateProductsByLiqidation']);
Route::post('/plant_pending/liquidation/return', [PlantPendingController::class, 'returnProductsByPlant']);
Route::post('/plant_pending/stock/return', [PlantPendingController::class, 'returnProductsStockByPlant']);
Route::get('/plant_pending/{id}', [PlantPendingController::class, 'getSale']);
Route::get('/plant_pending/records/{id}', [PlantPendingController::class, 'getRecords']);
Route::get('/plant_pending/records/returns/{id}', [PlantPendingController::class, 'recordSales']);
Route::post('/plant_pending/liquidation/canseluse/products', [PlantPendingController::class, 'cancelUseProduct']);
Route::delete('/plant_pending/liquidation/delete', [PlantPendingController::class, 'delete_liquidation']);
Route::post('/plant_pending/stock', [PlantPendingController::class, 'getStockPlant']);
Route::post('/plant_pending/report', [PlantPendingController::class, 'generateReportByLiquidation']);
Route::post('/plant_pending/report/plant', [PlantPendingController::class, 'generateReportByPlant']);


// TOWER
Route::post('/towers', [TowerController::class, 'store']);
Route::patch('/towers', [TowerController::class, 'update']);
Route::delete('/towers', [TowerController::class, 'destroy']);
Route::post('/towers/restore', [TowerController::class, 'restore']);
Route::post('/towers/paginate', [TowerController::class, 'paginate']);
Route::post('/towers/paginate/stock', [TowerController::class, 'paginateStockTower']);
Route::post('/towers/liquidation/create', [TowerController::class, 'registerLiquidations']);
Route::patch('/towers/liquidation/create', [TowerController::class, 'updateProductsByLiqidation']);
Route::post('/towers/liquidation/return', [TowerController::class, 'returnProductsByTower']);
Route::get('/towers/{id}', [TowerController::class, 'getSale']);
Route::get('/towers/records/{id}', [TowerController::class, 'getRecords']);
Route::post('/towers/canseluse/product', [TowerController::class, 'cancelUseProduct']);
Route::get('/towers/records/returns/{id}', [TowerController::class, 'recordSales']);
Route::post('/towers/stock', [TowerController::class, 'getStockTower']);
Route::delete('/towers/liquidation/delete', [TowerController::class, 'delete_liquidation']);
Route::get('/towerimg/{relative_id}/{zize}', [TowerController::class, 'image']);


// RECORDS
Route::post('/equipment/paginate', [RecordsController::class, 'paginateEquipment']);
Route::get('/record/product/{id}', [RecordsController::class, 'searchOperationsByEquipment']);
Route::post('/record/product/return', [RecordsController::class, 'returnEqipment']);


// Route::get('/traslat', [connect::class, 'dats']);
