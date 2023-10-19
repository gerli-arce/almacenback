<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\{
    PDFController, SessionController, PeopleController, PeoplesController,
    ProvidersController, TechnicalsController, BranchController, ViewController,
    RoleController, UserController, CategoriesController, BrandController,
    ModelsController, UnityController, ProfileController, PermissionsController,
    ProductsController, StockController, InstallationController, OperationTypesController,
    UserLoginController, SalesProductsController, FauldController, TowerController,
    KeysesController, EjecutivesController, RecordsController, HomeController,
    TransportsController, BusinessController, ClientsController, ParcelsCreatedController,
    ParcelsRegistersController, PlantPendingController, EntrysController, 
    SalesController, connect, SaleController, RoomController, AdminController,LendProductsController
};

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

Route::post('/admin/paginate', [AdminController::class, 'paginate']);
Route::post('/admin/get/stocks/model', [AdminController::class, 'getStocksByModel']);

// QR
Route::get('/installation/qr/{id}', [SalesProductsController::class, 'imageQR']);


// PDF
Route::get('/pdf/data', [PDFController::class, 'pruebaRender']);

// HOME
Route::get('/technicals/count', [HomeController::class, 'countTechnicals']);
Route::get('/providers/count', [HomeController::class, 'countProviders']);
Route::get('/ejecutives/count', [HomeController::class, 'countEjecutives']);
Route::get('/clients/count', [HomeController::class, 'countClients']);
Route::get('/users/count', [HomeController::class, 'countUsers']);
Route::get('/installations/count', [HomeController::class, 'countInstallations']);
Route::get('/models/get/star', [HomeController::class, 'getModelsStar']);
Route::get('/products/min', [HomeController::class, 'getProductsMin']);

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
Route::post('/technicals/add/products', [TechnicalsController::class, 'registersPrductsByTechnicals']);
Route::post('/technicals/add/epp', [TechnicalsController::class, 'registersEPPsByTechnicals']);
Route::post('/technicals/takeout/products', [TechnicalsController::class, 'recordTakeOutProductByTechnical']);
Route::post('/technicals/takeout/epp', [TechnicalsController::class, 'recordTakeOutEPPByTechnical']);
Route::post('/technicals/stock/add', [TechnicalsController::class, 'addStockTechnicalByProduct']);
Route::post('/technicals/epp/add', [TechnicalsController::class, 'addEPPTechnicalByProduct']);
Route::post('/technicals/search', [TechnicalsController::class, 'search']);
Route::post('/technicals/restore', [TechnicalsController::class, 'restore']);
Route::post('/technicals/products', [TechnicalsController::class, 'getProductsByTechnical']);
Route::post('/technicals/products/stock', [TechnicalsController::class, 'getProductsByTechnicalStock']);
Route::post('/technicals/epp/stock', [TechnicalsController::class, 'getEpp']);
Route::post('/technicals/paginate', [TechnicalsController::class, 'paginate']);
Route::post('/technicals/records', [TechnicalsController::class, 'getRecordProductsByTechnical']);
Route::post('/technicals/change/status', [TechnicalsController::class, 'changeStatusStockTechnical']);
Route::post('/technicals/records/paginate', [TechnicalsController::class, 'paginateRecords']);
Route::post('/technicals/records/epp/paginate', [TechnicalsController::class, 'paginateRecordsEpp']);
Route::post('/technicals/stock/paginate', [TechnicalsController::class, 'paginateRecords']);
Route::post('/technicals/search/stock', [TechnicalsController::class, 'getStockProductByModel']);
Route::post('/technicals/report/records', [TechnicalsController::class, 'generateReportBySearch']);
Route::post('/technicals/report/records/epp', [TechnicalsController::class, 'generateReportBySearchEPP']);
Route::delete('/technicals/stock/delete', [TechnicalsController::class, 'deleteStockTechnical']);

// EJECUTIVE
Route::post('/ejecutives', [EjecutivesController::class, 'store']);
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
Route::get('/users/{id}', [UserController::class, 'getUserById']);

// PROFILE
Route::get('/profile/{relative_id}/{zize}', [ProfileController::class, 'profile']);
Route::patch('/profile/account', [ProfileController::class, 'account']);
Route::patch('/profile/password', [ProfileController::class, 'password']);
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
Route::post('/models/search/id', [ModelsController::class, 'searchModelById']);
Route::post('/models/star', [ModelsController::class, 'changeStar']);

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
Route::post('/products/epp/paginate', [ProductsController::class, 'paginateEPP']);
Route::post('/products/search/guia', [ProductsController::class, 'getProductsByNumberGuia']);

// STOCK
Route::post('/stock/paginate', [StockController::class, 'paginate']);
Route::patch('/stock', [StockController::class, 'update']);
Route::post('/stock/regularize', [StockController::class, 'RegularizeMountsByModel']);
Route::post('/stock/products/all', [StockController::class, 'generateReportByStockByProducts']);
Route::post('/stock/products/selected', [StockController::class, 'generateReportByProductsSelected']);
Route::post('/stock/search', [StockController::class, 'getStockByModel']);
Route::post('/stock/star', [StockController::class, 'changeStar']);
Route::post('/stock/products/stock', [StockController::class, 'generateReportStock']);
Route::delete('/stock', [StockController::class, 'delete']);



// INTALLATIONS
Route::post('/install', [InstallationController::class, 'registerInstallation']);
Route::patch('/install', [InstallationController::class, 'update']);
Route::delete('/install', [InstallationController::class, 'delete']);
Route::post('/install/pending/paginate', [InstallationController::class, 'paginateInstallationsPending']);
Route::get('/install/{id}', [InstallationController::class, 'getSale']);
Route::get('/install/id/{id}', [InstallationController::class, 'getSales']);
Route::get('/installation/{id}', [InstallationController::class, 'getSaleInstallation']);
Route::post('/install/completed/paginate', [InstallationController::class, 'paginateInstallationsCompleted']);
Route::post('/install/completed/pending', [InstallationController::class, 'returnToPendient']);
Route::post('/install/canseluse', [InstallationController::class, 'cancelUseProduct']);
Route::post('/install/generate/report', [InstallationController::class, 'generateReportByInstallation']);
Route::post('/install/client', [InstallationController::class, 'getInstallationByClient']);

// FAULDS
Route::post('/fauld', [FauldController::class, 'registerFauld']);
Route::get('/fauld/search/client/{idclient}', [FauldController::class, 'getSateByClient']);
Route::post('/fauld/pending/paginate', [FauldController::class, 'paginateFauldsPending']);
Route::get('/fauld/{id}', [FauldController::class, 'getSale']);
Route::post('/fauld/completed/paginate', [FauldController::class, 'paginateFauldCompleted']);
Route::post('/fauld/return/product', [FauldController::class, 'returnProduct']);
Route::patch('/fauld', [FauldController::class, 'update']);
Route::delete('/fauld', [FauldController::class, 'delete']);

// PARCELS CREATED
Route::post('/parcels_created', [ParcelsCreatedController::class, 'store']);
Route::patch('/parcels_created', [ParcelsCreatedController::class, 'update']);
Route::post('/parcels_created/paginate', [ParcelsCreatedController::class, 'paginate']);
Route::post('/parcels_created/searchentry', [ParcelsCreatedController::class, 'getParcelsByPerson']);
Route::post('/parcels_created/search', [ParcelsCreatedController::class, 'getParcelByPerson']);
Route::post('/parcels_created/guia', [ParcelsCreatedController::class, 'generateGuia']);
Route::post('/parcels_created/report/sends', [ParcelsCreatedController::class, 'generateReportParcelsSendsByBranchByMonth']);
Route::post('/parcels_created/report/receiveds', [ParcelsCreatedController::class, 'generateReportParcelsReceivedsByBranchByMonth']);
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
Route::post('/parcels_register/report', [ParcelsRegistersController::class, 'generateReport']);
Route::post('/parcels_register/report/parcel', [ParcelsRegistersController::class, 'generateReportByParcel']);
Route::post('/parcels_register/report/excel', [ParcelsRegistersController::class, 'exportDataToExcel']);


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
Route::post('/plant_pending/products', [PlantPendingController::class, 'getProductsPlant']);
Route::post('/plant_pending/stock', [PlantPendingController::class, 'getProductsPlant']);
Route::post('/plant_pending/report', [PlantPendingController::class, 'generateReportByLiquidation']);
Route::post('/plant_pending/report/plant', [PlantPendingController::class, 'generateReportByPlant']);
Route::post('/plant_pending/report/project', [PlantPendingController::class, 'generateReportByProject']);
Route::post('/plant_pending/stock/plant', [PlantPendingController::class, 'generateReportByStockByPlant']);
Route::post('/plant_pending/update/stock/product', [PlantPendingController::class, 'updateStokByProduct']);
Route::post('/plant_pending/update/product/product', [PlantPendingController::class, 'updateProductByProduct']);
Route::post('/plant_pending/change/complet', [PlantPendingController::class, 'projectCompleted']);
Route::post('/plant_pending/change/pending', [PlantPendingController::class, 'projectPending']);
Route::post('/plant_pending/is/finished', [PlantPendingController::class, 'liquidationFinished']);
Route::post('/plant_pending/completed/paginate', [PlantPendingController::class, 'paginatePlantFinished']);
Route::post('/plant_pending/stock/records', [PlantPendingController::class, 'getRegistersStockByPlant']);
Route::post('/plant_pending/search', [PlantPendingController::class, 'searchMountsStockByPlant']);
Route::post('/plant_pending/products/search', [PlantPendingController::class, 'searchProductPlant']);
Route::get('/plant_pendingimg/{id}/{zize}', [PlantPendingController::class, 'image']);
Route::get('/plant_pendingimgs/{id}/{zize}', [PlantPendingController::class, 'images']);
Route::post('/plant_pending/image', [PlantPendingController::class, 'setImage']);
Route::patch('/plant_pending/image', [PlantPendingController::class, 'updateImage']);
Route::delete('/plant_pending/image/{id}', [PlantPendingController::class, 'deleteImage']);
Route::get('/plant_pending/image/{id}', [PlantPendingController::class, 'getImages']);
Route::post('/plant_pending/generate/report/details', [PlantPendingController::class, 'reportDetailsByPlant']);

Route::post('/lend/paginate', [LendProductsController::class, 'paginate']);
Route::post('/lend/mew/lend', [LendProductsController::class, 'setLendByPerson']);
Route::post('/lend/get/lends', [LendProductsController::class, 'getLendsByPerson']);
Route::post('/lend/paginate/record', [LendProductsController::class, 'paginateRecordsEpp']);
Route::post('/lend/record/report', [LendProductsController::class, 'generateReportBySearch']);
Route::post('/lend/takeout/lend', [LendProductsController::class, 'recordTakeOutByTechnical']);



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
Route::post('/towers/search/product', [TowerController::class, 'searchProductsByTowerByModel']);
Route::get('/towerimg/{relative_id}/{zize}', [TowerController::class, 'image']);
Route::get('/towerimgcontract/{id}/{zize}', [TowerController::class, 'contract']);
Route::get('/towerimgs/{id}/{zize}', [TowerController::class, 'images']);
Route::post('/towers/image', [TowerController::class, 'setImage']);
Route::patch('/towers/image', [TowerController::class, 'updateImage']);
Route::delete('/towers/image/{id}', [TowerController::class, 'deleteImage']);
Route::get('/towers/image/{id}', [TowerController::class, 'getImages']);
Route::post('/towers/generate/report/details', [TowerController::class, 'reportDetailsByTower']);
Route::post('/towers/generate/report/liquidation', [TowerController::class, 'generateReportByLiquidation']);

// SALE
Route::post('/sale', [SaleController::class, 'store']);
Route::patch('/sale', [SaleController::class, 'update']);
Route::post('/sale/paginate', [SaleController::class, 'paginate']);
Route::get('/sale/details/{id}', [SaleController::class, 'getSaleDetails']);
Route::post('/sale/detail/canseluse', [SaleController::class, 'cancelUseProduct']);
Route::post('/sale/generate/report', [SaleController::class, 'generateReportBySale']);


// RECORDS
Route::post('/equipment/paginate', [RecordsController::class, 'paginateEquipment']);
Route::get('/record/product/{id}', [RecordsController::class, 'searchOperationsByEquipment']);
Route::post('/record/product/return', [RecordsController::class, 'returnEqipment']);


// ENTRYS
Route::post('/entry/paginate', [EntrysController::class, 'paginate']);
Route::get('/entry/{id}', [EntrysController::class, 'getProductsByEntry']);
Route::get('/entry/products/{id}', [EntrysController::class, 'getProductsProductsByEntry']);
Route::post('/entry/report', [EntrysController::class, 'generateReportByDate']);

// SALES
Route::post('/sales/paginate', [SalesController::class, 'paginate']);
Route::post('/sales/report/date', [SalesController::class, 'generateReportBydate']);
Route::post('/sales/report', [SalesController::class, 'generateReport']);

// ROOM
Route::post('/room', [RoomController::class, 'store']);
Route::patch('/room', [RoomController::class, 'update']);
Route::post('/room/paginate', [RoomController::class, 'paginate']);
Route::post('/room/products', [RoomController::class, 'setProductsByRoom']);
Route::get('/room/get/products/{id}', [RoomController::class, 'getProductsByRoom']);
Route::post('/room/products/paginate', [RoomController::class, 'getRecordsByRoom']);
Route::post('/room/stock/paginate', [RoomController::class, 'paginateProductsByRoom']);
Route::post('/room/search/products', [RoomController::class, 'searchProductsByRoom']);
Route::post('/room/return/products', [RoomController::class, 'retunProductsByRoom']);
Route::get('/roomimg/{relative_id}/{zize}', [RoomController::class, 'image']);
Route::post('/room/image', [RoomController::class, 'setImage']);
Route::patch('/room/image', [RoomController::class, 'updateImage']);
Route::get('/room/image/{id}', [RoomController::class, 'getImages']);
Route::get('/roomimgs/{id}/{zize}', [RoomController::class, 'images']);
Route::post('/room/generate/report/details', [RoomController::class, 'reportDetailsByTower']);
Route::delete('/room/image/{id}', [RoomController::class, 'deleteImage']);


// Route::get('/traslat', [connect::class, 'dats']);
Route::post('/excel', [connect::class, 'exportDataToExcel']);
Route::get('/technicals_produts', [connect::class, 'changeByProductForModel']);
Route::get('/stock_plant', [connect::class, 'changeByProductForModelStokPlant']);
Route::get('/products_plant', [connect::class, 'changeByProductForModelProductsPlant']);
