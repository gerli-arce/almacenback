<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\{
    Branch,
    DetailSale,
    EntryDetail,
    EntryProducts,
    Plant,
    People,
    Product,
    ProductByPlant,
    ViewSales,
    Response,
    SalesProducts,
    Stock,
    StockPlant,
    Parcel,
    ViewPlant,
    ViewProductsByPlant,
    ViewDetailsSales,
    ViewStockPlant,
    ViewStockProductsByPlant,
    PhotographsByPlant,
    User
};
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Dompdf\Dompdf;
use Dompdf\Options;

class PlantPendingController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'create')) {
                throw new Exception('No tienes permisos para crear proyectos de planta');
            }

            if (
                !isset($request->name)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $plantJpa = new Plant();
            $plantJpa->name = $request->name;

            if (isset($request->date_start)) {
                $plantJpa->date_start = $request->date_start;
            }

            if (isset($request->date_end)) {
                $plantJpa->date_end = $request->date_end;
            }

            if (isset($request->leader)) {
                $plantJpa->_leader = $request->leader;
            }

            if (isset($request->description)) {
                $plantJpa->description = $request->description;
            }

            if (
                isset($request->image_type) &&
                isset($request->image_mini) &&
                isset($request->image_full)
            ) {
                if (
                    $request->image_type != "none" &&
                    $request->image_mini != "none" &&
                    $request->image_full != "none"
                ) {
                    $plantJpa->image_type = $request->image_type;
                    $plantJpa->image_mini = base64_decode($request->image_mini);
                    $plantJpa->image_full = base64_decode($request->image_full);
                } else {
                    $plantJpa->image_type = null;
                    $plantJpa->image_mini = null;
                    $plantJpa->image_full = null;
                }
            }
            
            $plantJpa->plant_status = "EN EJECUCION";
            $plantJpa->_branch = $branch_->id;
            $plantJpa->creation_date = gTrace::getDate('mysql');
            $plantJpa->_creation_user = $userid;
            $plantJpa->update_date = gTrace::getDate('mysql');
            $plantJpa->_update_user = $userid;
            $plantJpa->status = "1";
            $plantJpa->save();

            $response->setStatus(200);
            $response->setMessage('El proyecto se ha creado correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ', ln:' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function image($id, $size)
    {
        $response = new Response();
        $content = null;
        $type = null;
        try {
            if ($size != 'full') {
                $size = 'mini';
            }
            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $plantJpa = Plant::select([
                "plant.image_$size as image_content",
                'plant.image_type',
            ])->find($id);

            if (!$plantJpa) {
                throw new Exception('No se encontraron datos');
            }
            if (!$plantJpa->image_content) {
                throw new Exception('No existe imagen');
            }
            $content = $plantJpa->image_content;
            $type = $plantJpa->image_type;
            $response->setStatus(200);
        } catch (\Throwable $th) {
            $ruta = '../storage/images/img-default.jpg';
            $fp = fopen($ruta, 'r');
            $datos_image = fread($fp, filesize($ruta));
            $datos_image = addslashes($datos_image);
            fclose($fp);
            $content = stripslashes($datos_image);
            $type = 'image/png';
            $response->setStatus(200);
        } finally {
            return response(
                $content,
                $response->getStatus()
            )->header('Content-Type', $type);
        }
    }

    public function update(Request $request)
    {
        $response = new Response();
        try {
            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception('No tienes permisos para actualizar proyectos de encomienda');
            }

            $plantJpa = Plant::find($request->id);

            if (isset($request->name)) {
                $plantValidate = Plant::where('name', $request->name)
                    ->where('id', '!=', $request->id)->first();
                // if ($plantValidate) {
                //     throw new Exception('Ya existe un proyecto con este nombre');
                // }
                $plantJpa->name = $request->name;
            }

            if (isset($request->date_start)) {
                $plantJpa->date_start = $request->date_start;
            }

            if (isset($request->date_end)) {
                $plantJpa->date_end = $request->date_end;
            }

            if (isset($request->leader)) {
                $plantJpa->_leader = $request->leader;
            }

            if (isset($request->description)) {
                $plantJpa->description = $request->description;
            }

            if (
                isset($request->image_type) &&
                isset($request->image_mini) &&
                isset($request->image_full)
            ) {
                if (
                    $request->image_type != "none" &&
                    $request->image_mini != "none" &&
                    $request->image_full != "none"
                ) {
                    $plantJpa->image_type = $request->image_type;
                    $plantJpa->image_mini = base64_decode($request->image_mini);
                    $plantJpa->image_full = base64_decode($request->image_full);
                } else {
                    $plantJpa->image_type = null;
                    $plantJpa->image_mini = null;
                    $plantJpa->image_full = null;
                }
            }

            $plantJpa->update_date = gTrace::getDate('mysql');
            $plantJpa->_update_user = $userid;

            if (gValidate::check($role->permissions, $branch, 'plant_pending', 'change_status')) {
                if (isset($request->status)) {
                    $plantJpa->status = $request->status;
                }
            }

            $plantJpa->save();

            $response->setStatus(200);
            $response->setMessage('El proyecto ha sido actualizado correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function paginate(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para listar proyectos de planta');
            }

            $query = ViewPlant::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir'])
                ->orderBy('id', 'desc')
                ->where('plant_status', 'EN EJECUCION');

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'id' || $column == '*') {
                    $q->orWhere('id', $type, $value);
                }
                if ($column == 'name' || $column == '*') {
                    $q->orWhere('name', $type, $value);
                }
                if ($column == 'leader__name' || $column == '*') {
                    $q->orWhere('leader__name', $type, $value);
                }
                if ($column == 'leader__lastname' || $column == '*') {
                    $q->orWhere('leader__lastname', $type, $value);
                }
                if ($column == 'plant_status' || $column == '*') {
                    $q->orWhere('plant_status', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $plantJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $plants = array();
            foreach ($plantJpa as $plantlJpa) {
                $plant = gJSON::restore($plantlJpa->toArray(), '__');
                $plants[] = $plant;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewPlant::count());
            $response->setData($plants);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function setStockProductsByPlant(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);

            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception('No tienes permisos para actualizar proyectos de planta');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_plant = $request->id;
            $salesProduct->_type_operation = $request->_type_operation;
            $salesProduct->type_intallation = "AGREGADO_A_STOCK";
            $salesProduct->date_sale = gTrace::getDate('mysql');
            $salesProduct->status_sale = "PENDIENTE";
            $salesProduct->type_pay = "GASTOS INTERNOS";
            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            $plantJpa = Plant::find($request->id);

            if (isset($request->data)) {
                foreach ($request->data as $product) {
                    $productJpa = Product::find($product['product']['product']['id']);
                    $stock = Stock::where('_model', $productJpa->_model)
                        ->where('_branch', $branch_->id)
                        ->first();

                    if ($product['product']['product']['type'] == "MATERIAL") {

                        $StockPlant = StockPlant::select(['id', '_model', '_plant', 'mount_new', 'mount_second', 'mount_ill_fated'])
                        ->where('_model', $productJpa->_model)
                        ->where('_plant', $request->id)->first();

                        if ($StockPlant) {
                            $StockPlant->mount_new = intval($StockPlant->mount_new) + intval($product['mount_new']);
                            $StockPlant->mount_second = intval($StockPlant->mount_second) + intval($product['mount_second']);
                            $StockPlant->mount_ill_fated = intval($StockPlant->mount_ill_fated) + intval($product['mount_ill_fated']);
                            $StockPlant->save();
                        } else {
                            $stockPlantJpa = new StockPlant();
                            $stockPlantJpa->_product = $productJpa->id;
                            $stockPlantJpa->_model = $productJpa->_model;
                            $stockPlantJpa->_plant = $request->id;
                            $stockPlantJpa->mount_new = $product['mount_new'];
                            $stockPlantJpa->mount_second = $product['mount_second'];
                            $stockPlantJpa->mount_ill_fated = $product['mount_ill_fated'];
                            $stockPlantJpa->status = "1";
                            $stockPlantJpa->save();
                        }

                        $stock->mount_new = intval($stock->mount_new) - intval($product['mount_new']);
                        $stock->mount_second = intval($stock->mount_second) - intval($product['mount_second']);
                        $stock->mount_ill_fated = intval($stock->mount_ill_fated) - intval($product['mount_ill_fated']);

                        $productJpa->mount = $stock->mount_new +  $stock->mount_second;
                    } else {
                        $stockPlantJpa = new StockPlant();
                        $stockPlantJpa->_product = $productJpa->id;
                        $stockPlantJpa->_model = $productJpa->_model;
                        $stockPlantJpa->_plant = $request->id;
                        if ($productJpa->product_status == "NUEVO") {
                            $stockPlantJpa->mount_new = 1;
                            $stock->mount_new = $stock->mount_new - 1;
                        } else if ($productJpa->product_status == "SEMINUEVO") {
                            $stockPlantJpa->mount_second = 1;
                            $stock->mount_second = $stock->mount_second - 1;
                        } else {
                            $stockPlantJpa->mount_ill_fated = 1;
                        }
                        $stockPlantJpa->status = "1";
                        $stockPlantJpa->save();
                        $productJpa->disponibility = "PLANTA: " . $plantJpa->name;
                    }

                    $stock->save();
                    $productJpa->save();

                    $detailSale = new DetailSale();
                    $detailSale->_product = $productJpa->id;
                    $detailSale->mount_new = $product['mount_new'];
                    $detailSale->mount_second = $product['mount_second'];
                    $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                    $detailSale->description = $product['description'];
                    $detailSale->_sales_product = $salesProduct->id;
                    $detailSale->status = '1';
                    $detailSale->save();
                }
            }

            $response->setStatus(200);
            $response->setMessage('Registro agregado correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'Ln. ' . $th->getLine() . $th->getFile());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function paginateStockProductsByPlant(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para listar modelos');
            }

            $query = ViewStockProductsByPlant::select(['*'])->orderBy($request->order['column'], $request->order['dir'])
                ->whereNotNull('status');

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'product__model__model' || $column == '*') {
                    $q->orWhere('product__model__model', $type, $value);
                }
                if ($column == 'product__mac' || $column == '*') {
                    $q->orWhere('product__mac', $type, $value);
                }
                if ($column == 'product__serie' || $column == '*') {
                    $q->orWhere('product__serie', $type, $value);
                }
            })->where('plant__id', $request->search['plant'])
                ->where('product__disponibility', '!=', 'LIQUIDACION DE PLANTA');

            $iTotalDisplayRecords = $query->count();
            $plantJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $stock = array();
            foreach ($plantJpa as $productJpa) {
                $product = gJSON::restore($productJpa->toArray(), '__');
                $stock[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewStockProductsByPlant::count());
            $response->setData($stock);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function getStockProductsByPlant(Request $request, $id)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para leer stok de planta');
            }

            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $stockPlantJpa = StockPlant::select([
                'stock_plant.id as id',
                'products.id AS product__id',
                'products.type AS product__type',
                'models.id AS product__model__id',
                'models.model AS product__model__model',
                'models.relative_id AS product__model__relative_id',
                'products.relative_id AS product__relative_id',
                'products.mac AS product__mac',
                'products.serie AS product__serie',
                'products.price_sale AS product__price_sale',
                'products.currency AS product__currency',
                'products.num_guia AS product__num_guia',
                'products.condition_product AS product__condition_product',
                'products.disponibility AS product__disponibility',
                'products.product_status AS product__product_status',
                'stock_plant._plant as _plant',
                'stock_plant.mount_new as mount_new',
                'stock_plant.mount_second as mount_second',
                'stock_plant.mount_ill_fated as mount_ill_fated',
                'stock_plant.status as status',
            ])
                ->join('products', 'stock_plant._product', 'products.id')
                ->join('models', 'products._model', 'models.id')
                ->where('_plant', $id)->whereNotNull('stock_plant.status')
                ->get();

            $products = array();
            foreach ($stockPlantJpa as $detailJpa) {
                $detail = gJSON::restore($detailJpa->toArray(), '__');
                $products[] = $detail;
            }

            $response->setData($products);
            $response->setStatus(200);
            $response->setMessage('Registro agregado correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'Ln. ' . $th->getLine() . $th->getFile());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function searchMountsStockByPlant(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para leer stok de planta');
            }

            if (
                !isset($request->plant)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $stockPlantJpa = StockPlant::where('_plant', $request->plant)
                ->where('_product', $request->product)
                ->first();

            $response->setData([$stockPlantJpa]);
            $response->setStatus(200);
            $response->setMessage('Operación correcta');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'Ln. ' . $th->getLine() . $th->getFile());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function registerLiquidations(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception('No tienes permisos para actualizar proyectos de planta');
            }

            if (
                !isset($request->_plant) ||
                !isset($request->_technical)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $plantJpa = Plant::find($request->_plant);

            $salesProduct = new SalesProducts();
            if (isset($request->_technical)) {
                $salesProduct->_technical = $request->_technical;
            }
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_plant = $request->_plant;
            $salesProduct->_type_operation = $request->_type_operation;
            $salesProduct->type_intallation = "PLANTA";
            if (isset($request->date_sale)) {
                $salesProduct->date_sale = $request->date_sale;
            }
            $salesProduct->status_sale = "PENDIENTE";
            $salesProduct->_issue_user = $userid;
            $salesProduct->type_pay = "GASTOS INTERNOS";

            if (isset($request->description)) {
                $salesProduct->description = $request->description;
            }

            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            if (isset($request->data)) {
                foreach ($request->data as $product) {
                    $productJpa = Product::find($product['product']['id']);
                    $stockPlantJpa = StockPlant::find($product['id']);

                    if ($product['product']['type'] == "MATERIAL") {
                        $stockPlantJpa->mount_new = $stockPlantJpa->mount_new  - $product['mount_new'];
                        $stockPlantJpa->mount_second = $stockPlantJpa->mount_second - $product['mount_second'];
                        $stockPlantJpa->mount_ill_fated = $stockPlantJpa->mount_ill_fated - $product['mount_ill_fated'];
                    } else {
                        $stockPlantJpa->status = null;
                        $productJpa->disponibility = "PLANTA: " . $plantJpa->name;
                    }

                    $productJpa->save();
                    $stockPlantJpa->save();

                    $detailSale = new DetailSale();
                    $detailSale->_product = $productJpa->id;
                    $detailSale->mount_new = $product['mount_new'];
                    $detailSale->mount_second = $product['mount_second'];
                    $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                    $detailSale->description = $product['description'];
                    $detailSale->_sales_product = $salesProduct->id;
                    $detailSale->status = '1';
                    $detailSale->save();
                }
            }

            $response->setStatus(200);
            $response->setMessage('Registro agregado correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'Ln. ' . $th->getLine() . $th->getFile());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function getSale(Request $request, $id)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para listar');
            }

            $saleProductJpa = SalesProducts::select([
                'sales_products.id as id',
                'tech.id as technical__id',
                'tech.name as technical__name',
                'tech.lastname as technical__lastname',
                'branches.id as branch__id',
                'branches.name as branch__name',
                'branches.correlative as branch__correlative',
                'sales_products.date_sale as date_sale',
                'sales_products.status_sale as status_sale',
                'sales_products.description as description',
                'sales_products.status as status',
            ])
                ->join('people as tech', 'sales_products._technical', 'tech.id')
                ->join('branches', 'sales_products._branch', 'branches.id')
                ->whereNotNull('sales_products.status')
                ->where('sales_products.status_sale', '!=', 'CULMINADA')
                ->where('_plant', $id)
                ->orderBy('id', 'desc')
                ->where('sales_products.status', '!=', '0')
                ->get();

            if (!$saleProductJpa) {
                throw new Exception('No ay registros');
            }

            $salesProducts = array();
            foreach ($saleProductJpa as $saleProduct) {
                $sale = gJSON::restore($saleProduct->toArray(), '__');

                $detailSaleJpa = DetailSale::select([
                    'detail_sales.id as id',
                    'products.id AS product__id',
                    'products.type AS product__type',
                    'models.id AS product__model__id',
                    'models.model AS product__model__model',
                    'models.relative_id AS product__model__relative_id',
                    'products.relative_id AS product__relative_id',
                    'products.mac AS product__mac',
                    'products.serie AS product__serie',
                    'products.price_sale AS product__price_sale',
                    'products.currency AS product__currency',
                    'products.num_guia AS product__num_guia',
                    'products.condition_product AS product__condition_product',
                    'products.disponibility AS product__disponibility',
                    'products.product_status AS product__product_status',
                    'branches.id AS sale_product__branch__id',
                    'branches.name AS sale_product__branch__name',
                    'branches.correlative AS sale_product__branch__correlative',
                    'detail_sales.mount_new as mount_new',
                    'detail_sales.mount_second as mount_second',
                    'detail_sales.mount_ill_fated as mount_ill_fated',
                    'detail_sales.description as description',
                    'detail_sales._sales_product as _sales_product',
                    'detail_sales.status as status',
                ])
                    ->join('products', 'detail_sales._product', 'products.id')
                    ->join('models', 'products._model', 'models.id')
                    ->join('sales_products', 'detail_sales._sales_product', 'sales_products.id')
                    ->join('branches', 'sales_products._branch', 'branches.id')
                    ->whereNotNull('detail_sales.status')
                    ->where('_sales_product', $sale['id'])
                    ->get();

                $details = array();
                foreach ($detailSaleJpa as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                }

                $sale['details'] = $details;

                $salesProducts[] = $sale;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($salesProducts);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function getRecords(Request $request, $id)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para listar');
            }

            $saleProductJpa = SalesProducts::select([
                'sales_products.id as id',
                'tech.id as technical__id',
                'tech.name as technical__name',
                'tech.lastname as technical__lastname',
                'branches.id as branch__id',
                'branches.name as branch__name',
                'branches.correlative as branch__correlative',
                'sales_products.date_sale as date_sale',
                'sales_products.status_sale as status_sale',
                'sales_products.description as description',
                'sales_products.status as status',
            ])
                ->join('people as tech', 'sales_products._technical', 'tech.id')
                ->join('branches', 'sales_products._branch', 'branches.id')
                ->whereNotNull('sales_products.status')
                ->where('sales_products.status_sale', 'CULMINADA')
                ->orderBy('id', 'desc')
                ->where('_plant', $id)
                ->where('sales_products.status', '!=', '0')->get();

            if (!$saleProductJpa) {
                throw new Exception('No ay registros');
            }

            $salesProducts = array();
            foreach ($saleProductJpa as $saleProduct) {
                $sale = gJSON::restore($saleProduct->toArray(), '__');

                $detailSaleJpa = DetailSale::select([
                    'detail_sales.id as id',
                    'products.id AS product__id',
                    'products.type AS product__type',
                    'models.id AS product__model__id',
                    'models.model AS product__model__model',
                    'models.relative_id AS product__model__relative_id',
                    'products.relative_id AS product__relative_id',
                    'products.mac AS product__mac',
                    'products.serie AS product__serie',
                    'products.price_sale AS product__price_sale',
                    'products.currency AS product__currency',
                    'products.num_guia AS product__num_guia',
                    'products.condition_product AS product__condition_product',
                    'products.disponibility AS product__disponibility',
                    'products.product_status AS product__product_status',
                    'branches.id AS sale_product__branch__id',
                    'branches.name AS sale_product__branch__name',
                    'branches.correlative AS sale_product__branch__correlative',
                    'detail_sales.mount_new as mount_new',
                    'detail_sales.mount_second as mount_second',
                    'detail_sales.mount_ill_fated as mount_ill_fated',
                    'detail_sales.description as description',
                    'detail_sales._sales_product as _sales_product',
                    'detail_sales.status as status',
                ])
                    ->join('products', 'detail_sales._product', 'products.id')
                    ->join('models', 'products._model', 'models.id')
                    ->join('sales_products', 'detail_sales._sales_product', 'sales_products.id')
                    ->join('branches', 'sales_products._branch', 'branches.id')
                    ->whereNotNull('detail_sales.status')
                    ->where('_sales_product', $sale['id'])
                    ->get();

                $details = array();
                foreach ($detailSaleJpa as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                }

                $sale['details'] = $details;

                $salesProducts[] = $sale;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($salesProducts);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function updateProductsByLiqidation(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception('No tienes permisos para actualizar liquidaciones');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }


            $salesProduct = SalesProducts::find($request->id);

            $plantJpa = Plant::find($request->_plant);

            if (isset($request->_technical)) {
                $salesProduct->_technical = $request->_technical;
            }
            if (isset($request->date_sale)) {
                $salesProduct->date_sale = $request->date_sale;
            }
            if (isset($request->status_sale)) {
                $salesProduct->status_sale = $request->status_sale;
            }
            if (isset($request->description)) {
                $salesProduct->description = $request->description;
            }
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');

            if (isset($request->data)) {
                foreach ($request->data as $product) {

                    $detailSaleVerify = DetailSale::where('id', $product['id'])->where('_product', $product['product']['id'])->first();

                    if ($detailSaleVerify) {
                        $productJpa = Product::find($product['product']['id']);
                        $detailSale = DetailSale::find($product['id']);

                        if ($product['product']['type'] == "MATERIAL") {
                            $stockPlantJpa = StockPlant::where('_product', $productJpa->id)->first();

                            if (intval($detailSale->mount_new) != intval($product['mount_new'])) {
                                if (intval($detailSale->mount_new) > intval($product['mount_new'])) {
                                    $mount_dif = intval($detailSale->mount_new) - intval($product['mount_new']);
                                    $stockPlantJpa->mount_new = intval($stockPlantJpa->mount_new) + $mount_dif;
                                } else if (intval($detailSale->mount_new) < intval($product['mount_new'])) {
                                    $mount_dif = intval($product['mount_new']) - intval($detailSale->mount_new);
                                    $stockPlantJpa->mount_new = intval($stockPlantJpa->mount_new) - $mount_dif;
                                }
                            }

                            if (intval($detailSale->mount_second) != intval($product['mount_second'])) {
                                if (intval($detailSale->mount_second) > intval($product['mount_second'])) {
                                    $mount_dif = intval($detailSale->mount_second) - intval($product['mount_second']);
                                    $stockPlantJpa->mount_second = intval($stockPlantJpa->mount_second) + $mount_dif;
                                } else if (intval($detailSale->mount_second) < intval($product['mount_second'])) {
                                    $mount_dif = intval($product['mount_second']) - intval($detailSale->mount_second);
                                    $stockPlantJpa->mount_second = intval($stockPlantJpa->mount_second) - $mount_dif;
                                }
                            }

                            if (intval($detailSale->mount_ill_fated) != intval($product['mount_ill_fated'])) {
                                if (intval($detailSale->mount_ill_fated) > intval($product['mount_ill_fated'])) {
                                    $mount_dif = intval($detailSale->mount_ill_fated) - intval($product['mount_ill_fated']);
                                    $stockPlantJpa->mount_ill_fated = intval($stockPlantJpa->mount_ill_fated) + $mount_dif;
                                } else if (intval($detailSale->mount_ill_fated) < intval($product['mount_ill_fated'])) {
                                    $mount_dif = intval($product['mount_ill_fated']) - intval($detailSale->mount_ill_fated);
                                    $stockPlantJpa->mount_ill_fated = intval($stockPlantJpa->mount_ill_fated) - $mount_dif;
                                }
                            }

                            $detailSale->mount_new = $product['mount_new'];
                            $detailSale->mount_second = $product['mount_second'];
                            $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                            $detailSale->description = $product['description'];
                            $stockPlantJpa->save();
                        }

                        if (!$detailSale) {
                            throw new Exception("detail: " . $product['id']);
                        }

                        $detailSale->save();
                        $productJpa->save();
                    } else {
                        $productJpa = Product::find($product['product']['id']);
                        $stockPlantJpa = StockPlant::where('_model', $productJpa->_model)->where('_plant', $plantJpa->id)->first();

                        if ($product['product']['type'] == "MATERIAL") {
                            $stockPlantJpa->mount_new = $stockPlantJpa->mount_new  - $product['mount_new'];
                            $stockPlantJpa->mount_new = $stockPlantJpa->mount_new - $product['mount_second'];
                            $stockPlantJpa->mount_ill_fated = $stockPlantJpa->mount_ill_fated - $product['mount_ill_fated'];
                        } else {
                            $stockPlantJpa->status = null;
                            $productJpa->disponibility = "PLANTA: " . $plantJpa->name;
                        }

                        $stockPlantJpa->save();
                        $productJpa->save();

                        $detailSale = new DetailSale();
                        $detailSale->_product = $productJpa->id;
                        $detailSale->mount_new = $product['mount_new'];
                        $detailSale->mount_second = $product['mount_second'];
                        $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                        $detailSale->description = $product['description'];
                        $detailSale->_sales_product = $request->id;
                        $detailSale->status = '1';
                        $detailSale->save();
                    }
                }
            }

            $salesProduct->save();

            $response->setStatus(200);
            $response->setMessage('Actualización correcta.');
            $response->setData($role->toArray());
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln:' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function liquidationFinished(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception('No tienes permisos para actualizar liquidaciones');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }


            $saleProductJpa = SalesProducts::find($request->id);
            $saleProductJpa->status_sale = 'CULMINADA';
            $saleProductJpa->save();
            $detailsSalesJpa = DetailSale::where('_sales_product', $saleProductJpa->id)->whereNotNull('status')
                ->get();
            foreach ($detailsSalesJpa as $detail) {
                $productJpa = Product::find($detail['_product']);
                if ($productJpa->type == "MATERIAL") {
                    $productByPlantJpa = ProductByPlant::where('_model', $productJpa->_model)
                        ->where('_plant', $saleProductJpa->_plant)->first();
                    if ($productByPlantJpa) {
                        $productByPlantJpa->mount_new = $productByPlantJpa->mount_new + $detail['mount_new'];
                        $productByPlantJpa->mount_second = $productByPlantJpa->mount_second + $detail['mount_second'];
                        $productByPlantJpa->mount_ill_fated = $productByPlantJpa->mount_ill_fated + $detail['mount_ill_fated'];
                        $productByPlantJpa->save();
                    } else {
                        $productByPlantJpa_new = new ProductByPlant();
                        $productByPlantJpa_new->_product = $productJpa->id;
                        $productByPlantJpa_new->_plant = $saleProductJpa->_plant;
                        $productByPlantJpa_new->_model = $productJpa->_model;
                        $productByPlantJpa_new->mount_new = $detail['mount_new'];
                        $productByPlantJpa_new->mount_second = $detail['mount_second'];
                        $productByPlantJpa_new->mount_ill_fated = $detail['mount_ill_fated'];
                        $productByPlantJpa_new->status = '1';
                        $productByPlantJpa_new->save();
                    }
                } else {
                    $productByPlantJpa_new = new ProductByPlant();
                    $productByPlantJpa_new->_product = $productJpa->id;
                    $productByPlantJpa_new->_model = $productJpa->_model;
                    $productByPlantJpa_new->_plant = $saleProductJpa->_plant;
                    $productByPlantJpa_new->mount_new = $detail['mount_new'];
                    $productByPlantJpa_new->mount_second = $detail['mount_second'];
                    $productByPlantJpa_new->mount_ill_fated = $detail['mount_ill_fated'];
                    $productByPlantJpa_new->status = '1';
                    $productByPlantJpa_new->save();
                }
            }

            $response->setStatus(200);
            $response->setMessage('Liquidacion culminada.');
            $response->setData($role->toArray());
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln:' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function getProductsPlant(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para listar');
            }

            $productByPlantJpa = ViewProductsByPlant::where('plant__id', $request->id)->whereNotNull('status')->get();

            $stock_plant = [];

            foreach ($productByPlantJpa as $products) {
                $product = gJSON::restore($products->toArray(), '__');
                $stock_plant[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($stock_plant);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function delete_liquidation(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception('No tienes permisos para eliminar');
            }
            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: Es necesario el ID para esta operación");
            }

            $saleProductJpa = SalesProducts::find($request->id);
            if (!$saleProductJpa) {
                throw new Exception("Este reguistro no existe");
            }

            $plantJpa = Plant::find($saleProductJpa->_plant);

            $detailsSalesJpa = DetailSale::where('_sales_product', $saleProductJpa->id)->whereNotNull('status')
                ->get();

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            foreach ($detailsSalesJpa as $detail) {
                $detailSale = DetailSale::find($detail['id']);
                $detailSale->status = null;
                $productJpa = Product::find($detail['_product']);

                if ($productJpa->type == "MATERIAL") {
                    $stockPlantJpa = StockPlant::where('_model', $productJpa->_model)->where('_plant', $saleProductJpa->_plant)->first();
                    $stockPlantJpa->mount_new = $stockPlantJpa->mount_new + $detail['mount_new'];
                    $stockPlantJpa->mount_second = $stockPlantJpa->mount_second + $detail['mount_second'];
                    $stockPlantJpa->mount_ill_fated = $stockPlantJpa->mount_ill_fated + $detail['mount_ill_fated'];
                    $stockPlantJpa->save();
                } else {
                    $stockPlantJpa = StockPlant::where('_model', $productJpa->_model)->where('_plant', $saleProductJpa->_plant)->first();
                    $stockPlantJpa->status = "1";
                    $productJpa->disponibility = "DEVUELTO DE PLANTA: ".$plantJpa->name;
                    $stockPlantJpa->save();
                }

                $productJpa->save();
                $detailSale->save();
            }

            $saleProductJpa->update_date = gTrace::getDate('mysql');
            $saleProductJpa->status = null;
            $saleProductJpa->save();

            $response->setStatus(200);
            $response->setMessage('La liquidación se elimino correctamente.');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln:' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function paginateStockPlant(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para listar modelos');
            }

            $query = ViewStockPlant::select(['*'])->orderBy($request->order['column'], $request->order['dir'])
                ->whereNotNull('status');

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'product__model__model' || $column == '*') {
                    $q->orWhere('product__model__model', $type, $value);
                }
                if ($column == 'product__mac' || $column == '*') {
                    $q->orWhere('product__mac', $type, $value);
                }
                if ($column == 'product__serie' || $column == '*') {
                    $q->orWhere('product__serie', $type, $value);
                }
            })->where('plant__id', $request->search['plant']);

            $iTotalDisplayRecords = $query->count();
            $plantJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $stock = array();
            foreach ($plantJpa as $productJpa) {
                $product = gJSON::restore($productJpa->toArray(), '__');
                $stock[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewStockPlant::where('plant__id', $request->search['plant'])->count());
            $response->setData($stock);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function searchProductPlant(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para listar');
            }

            $ProductByPlant = ProductByPlant::where('_plant', $request->plant)->where('_product', $request->product)->first();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData([$ProductByPlant]);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function returnProductsByPlant(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception('No tienes permisos para actualizar');
            }

            if (
                !isset($request->_plant)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $plantJpa = Plant::find($request->_plant);
            if (!$plantJpa) {
                throw new Exception('La torre que deseas eliminar no existe');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            if (isset($request->_technical)) {
                $salesProduct->_technical = $request->_technical;
            }
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_plant = $request->_plant;
            $salesProduct->_type_operation = $request->_type_operation;
            $salesProduct->type_intallation = "PLANT";
            if (isset($request->date_sale)) {
                $salesProduct->date_sale = $request->date_sale;
            }
            $salesProduct->status_sale = "CULMINADA";
            $salesProduct->_issue_user = $userid;
            $salesProduct->type_pay = "GASTOS INTERNOS";

            if (isset($request->description)) {
                $salesProduct->description = $request->description;
            }

            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "0";
            $salesProduct->save();

            $entryProductsJpa = new EntryProducts();
            $entryProductsJpa->_user = $userid;
            $entryProductsJpa->_technical = $request->_technical;
            $entryProductsJpa->_branch = $branch_->id;
            $entryProductsJpa->_type_operation = $request->_type_operation;
            $entryProductsJpa->_plant = $request->_plant;
            $entryProductsJpa->type_entry = "DEVOLUCION DE PLANTA";
            $entryProductsJpa->entry_date = gTrace::getDate('mysql');
            $entryProductsJpa->condition_product = "USADO EN PLANTA";
            $entryProductsJpa->product_status = "USADO";
            $entryProductsJpa->_creation_user = $userid;
            $entryProductsJpa->creation_date = gTrace::getDate('mysql');
            $entryProductsJpa->_update_user = $userid;
            $entryProductsJpa->update_date = gTrace::getDate('mysql');
            $entryProductsJpa->status = "1";
            $entryProductsJpa->save();

            if (isset($request->data)) {
                foreach ($request->data as $product) {
                    $productJpa = Product::find($product['product']['id']);
                    $stock = Stock::where('_model', $productJpa->_model)
                        ->where('_branch', $branch_->id)
                        ->first();

                    $productByPlantJpa = ProductByPlant::find($product['id']);

                    if ($product['product']['type'] == "MATERIAL") {
                        $stock->mount_new = $stock->mount_new + $product['mount_new'];
                        $stock->mount_second = $stock->mount_second + $product['mount_second'];
                        $stock->mount_ill_fated = $stock->mount_ill_fated + $product['mount_ill_fated'];
                        $productByPlantJpa->mount_new = $productByPlantJpa->mount_new - $product['mount_new'];
                        $productByPlantJpa->mount_second = $productByPlantJpa->mount_second - $product['mount_second'];
                        $productByPlantJpa->mount_ill_fated = $productByPlantJpa->mount_ill_fated - $product['mount_ill_fated'];
                    } else {
                        $productJpa->disponibility = "DISPONIBLE";
                        $productJpa->condition_product = "DEVUELTO DE LA TORRE: " . $plantJpa->name;
                        if ($productJpa->product_status == "NUEVO") {
                            $stock->mount_new = $stock->mount_new + 1;
                        } else if ($productJpa->product_status == "SEMINUEVO") {
                            $stock->mount_second = $stock->mount_second + 1;
                        }
                        $productByPlantJpa->status = null;
                    }

                    $productByPlantJpa->save();
                    $stock->save();
                    $productJpa->save();

                    $detailSale = new DetailSale();
                    $detailSale->_product = $productJpa->id;
                    $detailSale->mount_new = $product['mount_new'];
                    $detailSale->mount_second = $product['mount_second'];
                    $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                    $detailSale->_sales_product = $salesProduct->id;
                    $detailSale->status = '1';
                    $detailSale->save();

                    $entryDetail = new EntryDetail();
                    $entryDetail->_product = $productJpa->id;
                    $entryDetail->mount_new = $product['mount_new'];
                    $entryDetail->mount_second = $product['mount_second'];
                    $entryDetail->mount_ill_fated = $product['mount_ill_fated'];
                    $entryDetail->_entry_product = $entryProductsJpa->id;
                    $entryDetail->status = "1";
                    $entryDetail->save();
                }
            }

            $plantJpa->update_date = gTrace::getDate('mysql');
            $plantJpa->_update_user = $userid;
            $plantJpa->save();

            $response->setStatus(200);
            $response->setMessage('Operación correcta.');
            $response->setData($role->toArray());
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function returnProductsStockByPlant(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception('No tienes permisos para actualizar');
            }

            if (
                !isset($request->_plant)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $plant = Plant::find($request->_plant);
            if (!$plant) {
                throw new Exception('La torre que deseas eliminar no existe');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            if (isset($request->_technical)) {
                $salesProduct->_technical = $request->_technical;
            }
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_plant = $request->_plant;
            $salesProduct->_type_operation = $request->_type_operation;
            $salesProduct->type_intallation = "PLANT";
            if (isset($request->date_sale)) {
                $salesProduct->date_sale = $request->date_sale;
            }
            $salesProduct->status_sale = "CULMINADA";
            $salesProduct->_issue_user = $userid;
            $salesProduct->type_pay = "GASTOS INTERNOS";

            if (isset($request->description)) {
                $salesProduct->description = $request->description;
            }

            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "0";
            $salesProduct->save();

            $entryProductsJpa = new EntryProducts();
            $entryProductsJpa->_user = $userid;
            $entryProductsJpa->_technical = $request->_technical;
            $entryProductsJpa->_branch = $branch_->id;
            $entryProductsJpa->_type_operation = $request->_type_operation;
            $entryProductsJpa->_plant = $request->_plant;
            $entryProductsJpa->type_entry = "DEVOLUCION DE PLANTA";
            $entryProductsJpa->entry_date = gTrace::getDate('mysql');
            $entryProductsJpa->condition_product = "USADO EN PLANTA";
            $entryProductsJpa->product_status = "USADO";
            $entryProductsJpa->_creation_user = $userid;
            $entryProductsJpa->creation_date = gTrace::getDate('mysql');
            $entryProductsJpa->_update_user = $userid;
            $entryProductsJpa->update_date = gTrace::getDate('mysql');
            $entryProductsJpa->status = "1";

            if (isset($request->data)) {
                foreach ($request->data as $product) {
                    $productJpa = Product::find($product['product']['id']);
                    $stock = Stock::where('_model', $productJpa->_model)
                        ->where('_branch', $branch_->id)
                        ->first();

                    $stockPlantJpa = StockPlant::find($product['id']);

                    if ($product['product']['type'] == "MATERIAL") {
                        $stock->mount_new = $stock->mount_new +  $product['mount_new'];
                        $stock->mount_second = $stock->mount_second +  $product['mount_second'];
                        $stock->mount_ill_fated = $stock->mount_ill_fated +  $product['mount_ill_fated'];
                        $productJpa->mount = $stock->mount_new + $stock->mount_second;
                        $stockPlantJpa->mount_new = $stockPlantJpa->mount_new - $product['mount_new'];
                        $stockPlantJpa->mount_second = $stockPlantJpa->mount_second - $product['mount_second'];
                        $stockPlantJpa->mount_ill_fated = $stockPlantJpa->mount_ill_fated - $product['mount_ill_fated'];
                    } else {
                        $productJpa->disponibility = "DISPONIBLE";
                        $productJpa->condition_product = "DEVUELTO DE LA PLANTA: " . $plant->name;
                        if ($productJpa->product_status == "NUEVO") {
                            $stock->mount_new = $stock->mount_new + 1;
                        } else if ($productJpa->product_status == "SEMINUEVO") {
                            $stock->mount_second = $stock->mount_second + 1;
                        }
                        $stockPlantJpa->status = null;
                    }

                    $stockPlantJpa->save();
                    $stock->save();
                    $productJpa->save();

                    $detailSale = new DetailSale();
                    $detailSale->_product = $productJpa->id;
                    $detailSale->mount_new = $product['mount_new'];
                    $detailSale->mount_second = $product['mount_second'];
                    $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                    $detailSale->_sales_product = $salesProduct->id;
                    $detailSale->status = '1';
                    $detailSale->save();

                    $entryDetail = new EntryDetail();
                    $entryDetail->_product = $productJpa->id;
                    $entryDetail->mount_new = $product['mount_new'];
                    $entryDetail->mount_second = $product['mount_second'];
                    $entryDetail->mount_ill_fated = $product['mount_ill_fated'];
                    $entryDetail->_entry_product = $entryProductsJpa->id;
                    $entryDetail->status = "1";
                }
            }

            $plant->update_date = gTrace::getDate('mysql');
            $plant->_update_user = $userid;
            $plant->save();

            $response->setStatus(200);
            $response->setMessage('Operación correcta.');
            $response->setData($role->toArray());
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function cancelUseProduct(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);

            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception('No tienes permisos para actualizar');
            }

            if (!isset($request->id)) {
                throw new Exception('Error: No deje campos vacíos');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = SalesProducts::find($request->_sales_product);
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');

            $detailSale = DetailSale::find($request->id);
            $detailSale->status = null;

            $productJpa = Product::find($request->product['id']);
            if ($productJpa->type == "MATERIAL") {
                $stockPlantJpa = StockPlant::where('_model', $productJpa->_model)->where('_plant', $salesProduct->_plant)->first();
                $stockPlantJpa->mount = $stockPlantJpa->mount + $detailSale->mount;
                $stockPlantJpa->save();
            } else if ($productJpa->type == "EQUIPO") {

                $stockPlantJpa = StockPlant::where('_model', $productJpa->_model)->where('_plant', $salesProduct->_plant)->first();
                $stockPlantJpa->status = "1";
                $productJpa->disponibility = "PLANTA";
                $stockPlantJpa->save();
            }

            $detailSale->save();
            $salesProduct->save();
            $productJpa->save();

            $response->setStatus(200);
            $response->setMessage('Liquidación atualizada correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function recordSales(Request $request, $id)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para listar');
            }

            $saleProductJpa = SalesProducts::select([
                'sales_products.id as id',
                'tech.id as technical__id',
                'tech.name as technical__name',
                'tech.lastname as technical__lastname',
                'branches.id as branch__id',
                'branches.name as branch__name',
                'branches.correlative as branch__correlative',
                'sales_products.date_sale as date_sale',
                'sales_products.status_sale as status_sale',
                'sales_products.description as description',
                'sales_products.status as status',
            ])
                ->join('people as tech', 'sales_products._technical', 'tech.id')
                ->join('branches', 'sales_products._branch', 'branches.id')
                ->whereNotNull('sales_products.status')
                ->where('_plant', $id)
                ->orderBy('id', 'desc')
                ->where('sales_products.status', '0')->get();

            if (!$saleProductJpa) {
                throw new Exception('No ay registros');
            }

            $salesProducts = array();
            foreach ($saleProductJpa as $saleProduct) {
                $sale = gJSON::restore($saleProduct->toArray(), '__');

                $detailSaleJpa = DetailSale::select([
                    'detail_sales.id as id',
                    'products.id AS product__id',
                    'products.type AS product__type',
                    'models.id AS product__model__id',
                    'models.model AS product__model__model',
                    'models.relative_id AS product__model__relative_id',
                    'products.relative_id AS product__relative_id',
                    'products.mac AS product__mac',
                    'products.serie AS product__serie',
                    'products.price_sale AS product__price_sale',
                    'products.currency AS product__currency',
                    'products.num_guia AS product__num_guia',
                    'products.condition_product AS product__condition_product',
                    'products.disponibility AS product__disponibility',
                    'products.product_status AS product__product_status',
                    'branches.id AS sale_product__branch__id',
                    'branches.name AS sale_product__branch__name',
                    'branches.correlative AS sale_product__branch__correlative',
                    'detail_sales.mount_new as mount_new',
                    'detail_sales.mount_second as mount_second',
                    'detail_sales.mount_ill_fated as mount_ill_fated',
                    'detail_sales.description as description',
                    'detail_sales._sales_product as _sales_product',
                    'detail_sales.status as status',
                ])
                    ->join('products', 'detail_sales._product', 'products.id')
                    ->join('models', 'products._model', 'models.id')
                    ->join('sales_products', 'detail_sales._sales_product', 'sales_products.id')
                    ->join('branches', 'sales_products._branch', 'branches.id')
                    ->whereNotNull('detail_sales.status')
                    ->where('_sales_product', $sale['id'])
                    ->get();

                $details = array();
                foreach ($detailSaleJpa as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                }

                $sale['details'] = $details;

                $salesProducts[] = $sale;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($salesProducts);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function delete(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar encomiendas');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $parcelJpa = Parcel::find($request->id);
            if (!$parcelJpa) {
                throw new Exception('La encomienda que deseas eliminar no existe');
            }

            $parcelJpa->status = null;
            $parcelJpa->save();

            $response->setStatus(200);
            $response->setMessage('La encomienda a sido eliminada correctamente');
            $response->setData($role->toArray());
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function restore(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'delete_restore')) {
                throw new Exception('No tienes permisos para encomiendas.');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $parcelJpa = Parcel::find($request->id);
            if (!$parcelJpa) {
                throw new Exception('La encomienda que deseas restaurar no existe');
            }

            $parcelJpa->status = "1";
            $parcelJpa->save();

            $response->setStatus(200);
            $response->setMessage('La encomienda a sido restaurada correctamente');
            $response->setData($role->toArray());
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function generateReportByLiquidation(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para listar encomiedas creadas');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportLiquidationPlant.html');
            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }
            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();
            $sumary = '';
            $detailSaleJpa = DetailSale::select([
                'detail_sales.id as id',
                'products.id AS product__id',
                'products.type AS product__type',
                'models.id AS product__model__id',
                'models.model AS product__model__model',
                'models.relative_id AS product__model__relative_id',
                'unities.id as product__model__unity__id',
                'unities.name as product__model__unity__name',
                'products.relative_id AS product__relative_id',
                'products.mac AS product__mac',
                'products.serie AS product__serie',
                'products.price_sale AS product__price_sale',
                'products.currency AS product__currency',
                'products.num_guia AS product__num_guia',
                'products.condition_product AS product__condition_product',
                'products.disponibility AS product__disponibility',
                'products.product_status AS product__product_status',
                'branches.id AS sale_product__branch__id',
                'branches.name AS sale_product__branch__name',
                'branches.correlative AS sale_product__branch__correlative',
                'detail_sales.mount_new as mount_new',
                'detail_sales.mount_second as mount_second',
                'detail_sales.mount_ill_fated as mount_ill_fated',
                'detail_sales.description as description',
                'detail_sales._sales_product as _sales_product',
                'detail_sales.status as status',
            ])
                ->join('products', 'detail_sales._product', 'products.id')
                ->join('models', 'products._model', 'models.id')
                ->join('unities', 'models._unity', 'unities.id')
                ->join('sales_products', 'detail_sales._sales_product', 'sales_products.id')
                ->join('branches', 'sales_products._branch', 'branches.id')
                ->whereNotNull('detail_sales.status')
                ->where('_sales_product', $request->id)
                ->get();
            $details = array();
            foreach ($detailSaleJpa as $detailJpa) {
                $detail = gJSON::restore($detailJpa->toArray(), '__');
                $details[] = $detail;
            }
            $models = array();
            foreach ($details as $product) {
                $model = $relativeId = $unity = "";
                if ($product['product']['type'] === "EQUIPO") {
                    $model = $product['product']['model']['model'];
                    $relativeId = $product['product']['model']['relative_id'];
                    $unity =  $product['product']['model']['unity']['name'];
                } else {
                    $model = $product['product']['model']['model'];
                    $relativeId = $product['product']['model']['relative_id'];
                    $unity =  $product['product']['model']['unity']['name'];
                }
                $mount_new = $product['mount_new'];
                $mount_second = $product['mount_second'];
                $mount_ill_fated = $product['mount_ill_fated'];
                if (isset($models[$model])) {
                    $models[$model]['mount_new'] += $mount_new;
                    $models[$model]['mount_second'] += $mount_second;
                    $models[$model]['mount_ill_fated'] += $mount_ill_fated;
                } else {
                    $models[$model] = array(
                        'model' => $model,
                        'mount_new' => $mount_new,
                        'mount_second' => $mount_second,
                        'mount_ill_fated' => $mount_ill_fated,
                        'relative_id' => $relativeId,
                        'unity' => $unity
                    );
                }
            }
            $count = 1;
            $products = array_values($models);
            foreach ($products as $detail) {
                $sumary .= "
                <tr>
                    <td><center style='font-size:12px;'>{$count}</center></td>
                    <td><center style='font-size:12px;'>{$detail['model']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['unity']}</center></td>
                    <td>
                        <center>
                            {$detail['mount_new']}
                        </center>
                    </td>
                    <td>
                        <center>
                           {$detail['mount_second']} 
                        </center>
                    </td>
                  
                </tr>
                ";
                $count = $count + 1;
            }
            $template = str_replace(
                [
                    '{id}',
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{project_name}',
                    '{leader}',
                    '{date_sale}',
                    '{description}',
                    '{summary}',
                ],
                [
                    str_pad($request->id, 6, "0", STR_PAD_LEFT),
                    $branch_->name,
                    gTrace::getDate('long'),
                    $request->plant['name'],
                    $request->plant['leader']['name'] . ' ' . $request->plant['leader']['lastname'],
                    $request->date_sale,
                    $request->description,
                    $sumary,
                ],
                $template
            );
            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('Guia.pdf');
        } catch (\Throwable $th) {
            $response = new Response();
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function generateReportByStockByPlant(Request $request)
    {

        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para listar encomiedas creadas');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportLiquidationPlantByStok.html');
            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }
            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();
            $sumary = '';

            $stockPlantJpa = StockPlant::select([
                'stock_plant.id as id',
                'products.id AS product__id',
                'products.type AS product__type',
                'models.id AS product__model__id',
                'models.model AS product__model__model',
                'models.relative_id AS product__model__relative_id',
                'unities.id AS product__model__unity__id',
                'unities.name AS product__model__unity__name',
                'products.relative_id AS product__relative_id',
                'products.mac AS product__mac',
                'products.serie AS product__serie',
                'products.price_sale AS product__price_sale',
                'products.currency AS product__currency',
                'products.num_guia AS product__num_guia',
                'products.condition_product AS product__condition_product',
                'products.disponibility AS product__disponibility',
                'products.product_status AS product__product_status',
                'stock_plant._plant as _plant',
                'stock_plant.mount_new as mount_new',
                'stock_plant.mount_second as mount_second',
                'stock_plant.mount_ill_fated as mount_ill_fated',
                'stock_plant.status as status',
            ])
                ->join('products', 'stock_plant._product', 'products.id')
                ->join('models', 'products._model', 'models.id')
                ->join('unities', 'models._unity', 'unities.id')
                ->where('_plant', $request->id)->whereNotNull('stock_plant.status')
                ->get();

            $products = array();
            foreach ($stockPlantJpa as $detailJpa) {
                $detail = gJSON::restore($detailJpa->toArray(), '__');
                $products[] = $detail;
            }
            $models = array();
            foreach ($products as $product) {
                $model = $relativeId = $unity = "";
                if ($product['product']['type'] === "EQUIPO") {
                    $model = $product['product']['model']['model'];
                    $relativeId = $product['product']['model']['relative_id'];
                    $unity =  $product['product']['model']['unity']['name'];
                } else {
                    $model = $product['product']['model']['model'];
                    $relativeId = $product['product']['model']['relative_id'];
                    $unity =  $product['product']['model']['unity']['name'];
                }
                $mount_new = $product['mount_new'];
                $mount_second = $product['mount_second'];
                $mount_ill_fated = $product['mount_ill_fated'];
                if (isset($models[$model])) {
                    $models[$model]['mount_new'] += $mount_new;
                    $models[$model]['mount_second'] += $mount_second;
                    $models[$model]['mount_ill_fated'] += $mount_ill_fated;
                } else {
                    $models[$model] = array(
                        'model' => $model,
                        'mount_new' => $mount_new,
                        'mount_second' => $mount_second,
                        'mount_ill_fated' => $mount_ill_fated,
                        'relative_id' => $relativeId,
                        'unity' => $unity
                    );
                }
            }
            $count = 1;
            $products = array_values($models);
            foreach ($products as $detail) {
                $sumary .= "
                <tr>
                    <td><center>{$count}</center></td>
                    <td>{$detail['model']}</td>
                    <td><center>{$detail['unity']}</center></td>
                    <td><center>{$detail['mount_new']}</center></td>
                    <td><center>{$detail['mount_second']}</center></td>
                </tr>
                ";
                $count = $count + 1;
            }
            $template = str_replace(
                [
                    '{id}',
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{project_name}',
                    '{leader}',
                    '{summary}',
                ],
                [
                    str_pad($request->id, 6, "0", STR_PAD_LEFT),
                    $branch_->name,
                    gTrace::getDate('long'),
                    $request->name,
                    $request->leader['name'] . ' ' . $request->leader['lastname'],
                    $sumary,
                ],
                $template
            );
            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('Guia.pdf');
        } catch (\Throwable $th) {
            $response = new Response();
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function generateReportByPlant(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para listar encomiedas creadas');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportPlant.html');
            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }
            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();
            $sumary = '';

            $productByPlantJpa = ViewProductsByPlant::where('plant__id', $request->id)->whereNotNull('status')->get();

            $stock_plant = [];

            foreach ($productByPlantJpa as $products) {
                $product = gJSON::restore($products->toArray(), '__');
                $stock_plant[] = $product;
            }

            $models = array();
            foreach ($stock_plant as $product) {
                $model = $relativeId = $unity = "";
                if ($product['product']['type'] === "EQUIPO") {
                    $model = $product['product']['model']['model'];
                    $relativeId = $product['product']['model']['relative_id'];
                    $unity =  $product['product']['model']['unity']['name'];
                } else {
                    $model = $product['product']['model']['model'];
                    $relativeId = $product['product']['model']['relative_id'];
                    $unity =  $product['product']['model']['unity']['name'];
                }
                $mount_new = $product['mount_new'];
                $mount_second = $product['mount_second'];
                $mount_ill_fated = $product['mount_ill_fated'];
                if (isset($models[$model])) {
                    $models[$model]['mount_new'] += $mount_new;
                    $models[$model]['mount_second'] += $mount_second;
                    $models[$model]['mount_ill_fated'] += $mount_ill_fated;
                } else {
                    $models[$model] = array(
                        'model' => $model,
                        'mount_new' => $mount_new,
                        'mount_second' => $mount_second,
                        'mount_ill_fated' => $mount_ill_fated,
                        'relative_id' => $relativeId,
                        'unity' => $unity
                    );
                }
            }
            $count = 1;
            $products = array_values($models);
            foreach ($products as $detail) {
                $sumary .= "
                <tr>
                    <td><center>{$count}</center></td>
                    <td>{$detail['model']}</td>
                    <td><center>{$detail['unity']}</center></td>
                    <td><center>{$detail['mount_new']}</center></td>
                    <td><center>{$detail['mount_second']}</center></td>
                </tr>
                ";
                $count = $count + 1;
            }
            $template = str_replace(
                [
                    '{id}',
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{project_name}',
                    '{leader}',
                    '{date_start}',
                    '{date_end}',
                    '{description}',
                    '{summary}',
                ],
                [
                    str_pad($request->id, 6, "0", STR_PAD_LEFT),
                    $branch_->name,
                    gTrace::getDate('long'),
                    $request->name,
                    $request->leader['name'] . ' ' . $request->leader['lastname'],
                    $request->date_start,
                    $request->date_end,
                    $request->description,
                    $sumary,
                ],
                $template
            );
            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('Guia.pdf');
        } catch (\Throwable $th) {
            $response = new Response();
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function generateReportByProject(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para generar reporte de proyectos');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportProject.html');
            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }
            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();
            $sumary = '';

            $productByPlantJpa = ViewProductsByPlant::where('plant__id', $request->id)->whereNotNull('status')->get();

            $stock_plant = [];

            foreach ($productByPlantJpa as $products) {
                $product = gJSON::restore($products->toArray(), '__');
                $stock_plant[] = $product;
            }

            $models = array();
            foreach ($stock_plant as $product) {
                $model = $relativeId = $unity = "";
                if ($product['product']['type'] === "EQUIPO") {
                    $model = $product['product']['model']['model'];
                    $relativeId = $product['product']['model']['relative_id'];
                    $unity =  $product['product']['model']['unity']['name'];
                } else {
                    $model = $product['product']['model']['model'];
                    $relativeId = $product['product']['model']['relative_id'];
                    $unity =  $product['product']['model']['unity']['name'];
                }
                $mount_new = $product['mount_new'];
                $mount_second = $product['mount_second'];
                $mount_ill_fated = $product['mount_ill_fated'];
                if (isset($models[$model])) {
                    $models[$model]['mount_new'] += $mount_new;
                    $models[$model]['mount_second'] += $mount_second;
                    $models[$model]['mount_ill_fated'] += $mount_ill_fated;
                } else {
                    $models[$model] = array(
                        'model' => $model,
                        'mount_new' => $mount_new,
                        'mount_second' => $mount_second,
                        'mount_ill_fated' => $mount_ill_fated,
                        'relative_id' => $relativeId,
                        'unity' => $unity
                    );
                }
            }
            $count = 1;
            $products = array_values($models);
            foreach ($products as $detail) {
                $sumary .= "
                <tr>
                    <td><center style='font-size:12px;'>{$count}</center></td>
                    <td><center style='font-size:12px;'>Nu:{$detail['mount_new']} || Se:{$detail['mount_second']} || Ma:{$detail['mount_ill_fated']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['unity']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['model']}</center></td>
                </tr>
                ";
                $count = $count + 1;
            }


            
            $PlantJpa = Plant::find($request->id);
            
            $PhotographsByPlant = PhotographsByPlant::select(['id', 'description', '_creation_user', 'creation_date', '_update_user', 'update_date'])
            ->where('_plant', $PlantJpa->id)->whereNotNUll('status')
            ->orderBy('id', 'desc')
            ->get();

            $images = '';


            $count = 1;

            foreach($PhotographsByPlant as $image){

                $userCreation = User::select([
                    'users.id as id',
                    'users.username as username',
                ])
                    ->where('users.id', $image->_creation_user)->first();

                $images .= "
                <div style='page-break-before: always;'>
                    <p><strong>{$count}) {$image->description}</strong></p>
                    <p style='margin-left:18px'>Fecha: {$image->creation_date}</p>
                    <p style='margin-left:18px'>Usuario: {$userCreation->username}</p>
                    <center>
                        <img src='https://almacen.fastnetperu.com.pe/api/plant_pendingimgs/{$image->id}/full' alt='-' style='background-color: #38414a; object-fit: contain; object-position: center center; cursor: pointer; max-width: 650px; max-height: 700px; width: auto; height: auto; margin-top:5px;border:solid 2px #000;'>
                    </center>
                </div>
                ";
                $count +=1;
            }

            $template = str_replace(
                [
                    '{id}',
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{project_name}',
                    '{leader}',
                    '{date_start}',
                    '{date_end}',
                    '{description}',
                    '{summary}',
                    '{images}',
                    '{id}'
                ],
                [
                    str_pad($request->id, 6, "0", STR_PAD_LEFT),
                    $branch_->name,
                    gTrace::getDate('long'),
                    $request->name,
                    $request->leader['name'] . ' ' . $request->leader['lastname'],
                    $request->date_start,
                    $request->date_end,
                    $request->description,
                    $sumary,
                    $images,
                    $PlantJpa->id,
                ],
                $template
            );
            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('Guia.pdf');
        } catch (\Throwable $th) {
            $response = new Response();
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function updateStokByProduct(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception('No tienes permisos para actualizar');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $stockPlantJpa = StockPlant::find($request->id);
            if (!$stockPlantJpa) {
                throw new Exception('El producto no existe');
            }

            $stockPlantJpa->mount_new = $request->mount_new;
            $stockPlantJpa->mount_second = $request->mount_second;
            $stockPlantJpa->mount_ill_fated = $request->mount_ill_fated;
            $stockPlantJpa->save();

            $response->setStatus(200);
            $response->setMessage('El producto se actualizo correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function updateProductByProduct(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception('No tienes permisos para actualizar');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $productPlantJpa = ProductByPlant::find($request->id);
            if (!$productPlantJpa) {
                throw new Exception('El producto no existe');
            }

            $productPlantJpa->mount_new = $request->mount_new;
            $productPlantJpa->mount_second = $request->mount_second;
            $productPlantJpa->mount_ill_fated = $request->mount_ill_fated;
            $productPlantJpa->save();

            $response->setStatus(200);
            $response->setMessage('El producto se actualizo correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function projectCompleted(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception('No tienes permisos para actualizar encomiendas.');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $plantJpa = Plant::find($request->id);
            if (!$plantJpa) {
                throw new Exception('La planta que deseas cambiar no existe');
            }

            $plantJpa->plant_status = "COMPLETED";
            $plantJpa->save();

            $response->setStatus(200);
            $response->setMessage('Proyecto marcado como terminado');
            $response->setData($role->toArray());
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function projectPending(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception('No tienes permisos para actualizar encomiendas.');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $plantJpa = Plant::find($request->id);
            if (!$plantJpa) {
                throw new Exception('La planta que deseas cambiar no existe');
            }

            $plantJpa->plant_status = "EN EJECUCION";
            $plantJpa->save();

            $response->setStatus(200);
            $response->setMessage('Proyecto marcado como terminado');
            $response->setData($role->toArray());
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function paginatePlantFinished(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para listar proyectos finalizados');
            }

            $query = ViewPlant::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir'])
                ->orderBy('id', 'desc')
                ->where('plant_status', 'COMPLETED');

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'id' || $column == '*') {
                    $q->orWhere('id', $type, $value);
                }
                if ($column == 'name' || $column == '*') {
                    $q->orWhere('name', $type, $value);
                }
                if ($column == 'leader__name' || $column == '*') {
                    $q->orWhere('leader__name', $type, $value);
                }
                if ($column == 'leader__lastname' || $column == '*') {
                    $q->orWhere('leader__lastname', $type, $value);
                }
                if ($column == 'plant_status' || $column == '*') {
                    $q->orWhere('plant_status', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $plantJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $plants = array();
            foreach ($plantJpa as $plantlJpa) {
                $plant = gJSON::restore($plantlJpa->toArray(), '__');
                $plants[] = $plant;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewPlant::where('plant_status', 'COMPLETED')->count());
            $response->setData($plants);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function getRegistersStockByPlant(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para leer registros');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $query = ViewSales::select([
                'view_sales.id as id',
                'view_sales.branch__id as branch__id',
                'view_sales.branch__name as branch__id',
                'view_sales.branch__correlative as branch__correlative',
                'view_sales.type_operation__operation as type_operation__operation',
                'view_sales.plant_id as plant_id',
                'plant.id as plant__id',
                'plant.name as plant__name',
                'view_sales.type_intallation as type_intallation',
                'view_sales.date_sale as date_sale',
                'view_sales.status_sale as status_sale',
                'view_sales.description as description',
                'view_sales.user_creation__id as user_creation__id',
                'view_sales.user_creation__username as user_creation__username',
                'view_sales.user_creation__person__id as user_creation__person__id',
                'view_sales.user_creation__person__name as user_creation__person__name',
                'view_sales.user_creation__person__lastname as user_creation__person__lastname',
                'view_sales.creation_date as creation_date',
                'view_sales.update_user_id as update_user_id',
                'view_sales.update_date as update_date',
                'view_sales.status as status'
            ])->where('view_sales.type_intallation', 'AGREGADO_A_STOCK')
                ->where('view_sales.branch__correlative', $branch)
                ->where('view_sales.type_operation__operation', 'PLANTA')
                ->where('view_sales.plant_id', $request->id)
                ->join('plant', 'view_sales.plant_id', 'plant.id');

            $query = $query->orderBy('id', 'desc');
            if (isset($request->date_start) && isset($request->date_end)) {
                $query = $query->where('view_sales.date_sale', '>=', $request->date_start)
                    ->where('view_sales.date_sale', '<=', $request->date_end);
            }

            $recordSales = $query->get();

            $records = array();
            foreach ($recordSales as $recordJpa) {
                $record = gJSON::restore($recordJpa->toArray(), '__');
                $ViewDetailsSales = ViewDetailsSales::where('sale_product_id', $recordJpa['id'])->get();
                $details = [];
                foreach ($ViewDetailsSales as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                }
                $record['details'] = $details;
                $records[] = $record;
            }

            $response->setData($records);
            $response->setStatus(200);
            $response->setMessage('Operación correcta');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function setImage(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }


            $PhotographsByPlantJpa = new PhotographsByPlant();
            $PhotographsByPlantJpa->_plant = $request->id;
            $PhotographsByPlantJpa->description = $request->description;

            if (
                isset($request->image_type) &&
                isset($request->image_mini) &&
                isset($request->image_full)
            ) {
                if (
                    $request->image_type != "none" &&
                    $request->image_mini != "none" &&
                    $request->image_full != "none"
                ) {
                    $PhotographsByPlantJpa->image_type = $request->image_type;
                    $PhotographsByPlantJpa->image_mini = base64_decode($request->image_mini);
                    $PhotographsByPlantJpa->image_full = base64_decode($request->image_full);
                } else {
                    throw new Exception("Una imagen debe ser cargada.");
                }
            } else {
                throw new Exception("Una imagen debe ser cargada.");
            }

            $PhotographsByPlantJpa->_creation_user = $userid;
            $PhotographsByPlantJpa->creation_date = gTrace::getDate('mysql');
            $PhotographsByPlantJpa->_update_user = $userid;
            $PhotographsByPlantJpa->update_date = gTrace::getDate('mysql');
            $PhotographsByPlantJpa->status = "1";
            $PhotographsByPlantJpa->save();

            $response->setStatus(200);
            $response->setMessage('');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function updateImage(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $PhotographsByPlant = PhotographsByPlant::find($request->id);
            $PhotographsByPlant->description = $request->description;

            if (
                isset($request->image_type) &&
                isset($request->image_mini) &&
                isset($request->image_full)
            ) {
                if (
                    $request->image_type != "none" &&
                    $request->image_mini != "none" &&
                    $request->image_full != "none"
                ) {
                    $PhotographsByPlant->image_type = $request->image_type;
                    $PhotographsByPlant->image_mini = base64_decode($request->image_mini);
                    $PhotographsByPlant->image_full = base64_decode($request->image_full);
                } 
            } 
           
            $PhotographsByPlant->_update_user = $userid;
            $PhotographsByPlant->update_date = gTrace::getDate('mysql');
            $PhotographsByPlant->save();

            $response->setStatus(200);
            $response->setMessage('Imagen guardada correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function getImages(Request $request, $id)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $PhotographsByPlant = PhotographsByPlant::select(['id', 'description', '_creation_user', 'creation_date', '_update_user', 'update_date'])
            ->where('_plant', $id)->whereNotNUll('status')
            ->orderBy('id', 'desc')
            ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta.');
            $response->setData($PhotographsByPlant->toArray());
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function images($id, $size)
    {
        $response = new Response();
        $content = null;
        $type = null;
        try {
            if ($size != 'full') {
                $size = 'mini';
            }
            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $modelJpa = PhotographsByPlant::select([
                "photographs_by_plant.image_$size as image_content",
                'photographs_by_plant.image_type',

            ])
                ->where('id', $id)
                ->first();

            if (!$modelJpa) {
                throw new Exception('No se encontraron datos');
            }

            if (!$modelJpa->image_content) {
                throw new Exception('No existe imagen');
            }

            $content = $modelJpa->image_content;
            $type = $modelJpa->image_type;
            $response->setStatus(200);
        } catch (\Throwable $th) {
            $ruta = '../storage/images/antena-default.png';
            $fp = fopen($ruta, 'r');
            $datos_image = fread($fp, filesize($ruta));
            $datos_image = addslashes($datos_image);
            fclose($fp);
            $content = stripslashes($datos_image);
            $type = 'image/jpeg';
            $response->setStatus(200);
        } finally {
            return response(
                $content,
                $response->getStatus()
            )->header('Content-Type', $type);
        }
    }

    public function deleteImage(Request $request, $id){
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }


            $PhotographsByPlant = PhotographsByPlant::find($id);
            $PhotographsByPlant->_update_user = $userid;
            $PhotographsByPlant->update_date = gTrace::getDate('mysql');
            $PhotographsByPlant->status = null;
            $PhotographsByPlant->save();

            $response->setStatus(200);
            $response->setMessage('Imagen eliminada correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    
    public function reportDetailsByPlant(Request $request){
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para listar encomiedas creadas');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportDetailsByPlant.html');
            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }
            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();
            $sumary = '';

            $user = User::select([
                'users.id as id',
                'users.username as username',
                'people.name as person__name',
                'people.lastname as person__lastname'
            ])
                ->join('people', 'users._person', 'people.id')
                ->where('users.id', $userid)->first();
          

            $PlantJpa = Plant::find($request->id);
            
            $PhotographsByPlant = PhotographsByPlant::select(['id', 'description', '_creation_user', 'creation_date', '_update_user', 'update_date'])
            ->where('_plant', $PlantJpa->id)->whereNotNUll('status')
            ->orderBy('id', 'desc')
            ->get();

            $images = '';


            $count = 1;

            foreach($PhotographsByPlant as $image){

                $userCreation = User::select([
                    'users.id as id',
                    'users.username as username',
                ])
                    ->where('users.id', $image->_creation_user)->first();

                $images .= "
                <div style='page-break-before: always;'>
                    <p><strong>{$count}) {$image->description}</strong></p>
                    <p style='margin-left:18px'>Fecha: {$image->creation_date}</p>
                    <p style='margin-left:18px'>Usuario: {$userCreation->username}</p>
                    <center>
                        <img src='https://almacen.fastnetperu.com.pe/api/plant_pendingimgs/{$image->id}/full' alt='-' style='background-color: #38414a; object-fit: contain; object-position: center center; cursor: pointer; max-width: 650px; max-height: 700px; width: auto; height: auto; margin-top:5px;border:solid 2px #000;'>
                    </center>
                </div>
                ";
                $count +=1;
            }

            $template = str_replace(
                [
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{tower_name}',
                    '{id}',
                    '{description}',
                    '{ejecutive}',
                    '{images}',
                    '{summary}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $PlantJpa->name,
                    $PlantJpa->id,
                    $PlantJpa->description,
                    $user->person__name . ' ' . $user->person__lastname,
                    $images,
                    $sumary,
                ],
                $template
            );
            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('Torre.pdf');
        } catch (\Throwable $th) {
            $response = new Response();
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

}
