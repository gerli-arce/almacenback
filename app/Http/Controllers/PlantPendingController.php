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
    Product,
    ProductByPlant,
    Response,
    SalesProducts,
    Stock,
    StockPlant,
    Parcel,
    ViewPlant,
    ViewProductsByPlant,
    ViewStockPlant,
    ViewStockProductsByPlant
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
                if ($plantValidate) {
                    throw new Exception('Ya existe un proyecto con este nombre');
                }
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
                ->orderBy('id', 'desc');

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
            $salesProduct->_plant = $request->_plant;
            $salesProduct->_type_operation = $request->_type_operation;
            $salesProduct->type_intallation = "PLANTA";
            $salesProduct->date_sale = gTrace::getDate('mysql');
            $salesProduct->status_sale = "PENDIENTE";
            $salesProduct->type_pay = "GASTOS INTERNOS";
            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            if (isset($request->data)) {
                foreach ($request->data as $product) {
                    $productJpa = Product::find($product['product']['id']);
                    $stock = Stock::where('_model', $productJpa->_model)
                        ->where('_branch', $branch_->id)
                        ->first();

                    if ($product['product']['type'] == "MATERIAL") {

                        $StockPlant = StockPlant::select(['id', '_product', '_plant', 'mount'])->where('_product', $productJpa->id)->where('_plant', $request->id)->first();

                        if ($StockPlant) {
                            $StockPlant->mount = intval($StockPlant->mount) + intval($product['mount']);
                            $StockPlant->save();
                        } else {
                            $stockPlantJpa = new StockPlant();
                            $stockPlantJpa->_product = $productJpa->id;
                            $stockPlantJpa->_plant = $request->id;
                            $stockPlantJpa->mount = $product['mount'];
                            $stockPlantJpa->status = "1";
                            $stockPlantJpa->save();
                        }

                        $stock->mount_new = intval($stock->mount_new) - intval($product['mount']);
                        $productJpa->mount = $productJpa->mount - $product['mount'];
                    } else {
                        $stockPlantJpa = new StockPlant();
                        $stockPlantJpa->_product = $productJpa->id;
                        $stockPlantJpa->_plant = $request->id;
                        $stockPlantJpa->mount = $product['mount'];
                        $stockPlantJpa->status = "1";
                        $stockPlantJpa->save();

                        $productJpa->disponibility = "PLANTA";
                        if ($productJpa->product_status == "NUEVO") {
                            $stock->mount_new = $stock->mount_new - 1;
                        } else if ($productJpa->product_status == "SEMINUEVO") {
                            $stock->mount_second = $stock->mount_second - 1;
                        }
                    }

                    $stock->save();
                    $productJpa->save();

                    $detailSale = new DetailSale();
                    $detailSale->_product = $productJpa->id;
                    $detailSale->mount = $product['mount'];
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
                if ($column == 'mount' || $column == '*') {
                    $q->orWhere('mount', $type, $value);
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
                'stock_plant.mount as mount',
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
                        $stockPlantJpa->mount = $stockPlantJpa->mount - $product['mount'];
                    } else {
                        $stockPlantJpa->status = null;
                        $productJpa->disponibility = "LIQUIDACION DE PLANTA";
                    }

                    $productJpa->save();
                    $stockPlantJpa->save();

                    $detailSale = new DetailSale();
                    $detailSale->_product = $productJpa->id;
                    $detailSale->mount = $product['mount'];
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
                    'detail_sales.mount as mount',
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
                    'detail_sales.mount as mount',
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

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = SalesProducts::find($request->id);

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
                            if (intval($detailSale->mount) != intval($product['mount'])) {
                                if (intval($detailSale->mount) > intval($product['mount'])) {
                                    $mount_dif = intval($detailSale->mount) - intval($product['mount']);
                                    $stockPlantJpa->mount = intval($stockPlantJpa->mount) + $mount_dif;
                                } else if (intval($detailSale->mount) < intval($product['mount'])) {
                                    $mount_dif = intval($product['mount']) - intval($detailSale->mount);
                                    $stockPlantJpa->mount = intval($stockPlantJpa->mount) - $mount_dif;
                                }
                            }

                            $detailSale->mount = $product['mount'];
                            $stockPlantJpa->save();
                        }

                        if (!$detailSale) {
                            throw new Exception("detail: " . $product['id']);
                        }

                        $detailSale->save();
                        $productJpa->save();
                    } else {
                        $productJpa = Product::find($product['product']['id']);
                        $stockPlantJpa = StockPlant::where('_product', $productJpa->id)->first();

                        if ($product['product']['type'] == "MATERIAL") {
                            $stockPlantJpa->mount = $stockPlantJpa->mount - $product['mount'];
                        } else {
                            $stockPlantJpa->status = null;
                            $productJpa->disponibility = "LIQUIDACION DE PLANTA";
                        }

                        $stockPlantJpa->save();
                        $productJpa->save();

                        $detailSale = new DetailSale();
                        $detailSale->_product = $productJpa->id;
                        $detailSale->mount = $product['mount'];
                        $detailSale->_sales_product = $request->id;
                        $detailSale->status = '1';
                        $detailSale->save();
                    }
                }
            }

            $salesProduct->save();

            if (isset($request->status_sale)) {
                if ($request->status_sale == 'CULMINADA') {
                    $saleProductJpa = SalesProducts::find($request->id);
                    $saleProductJpa->status_sale = 'CULMINADA';
                    $saleProductJpa->save();
                    $detailsSalesJpa = DetailSale::where('_sales_product', $saleProductJpa->id)->whereNotNull('status')
                        ->get();
                    foreach ($detailsSalesJpa as $detail) {
                        $productJpa = Product::find($detail['_product']);
                        if ($productJpa->type == "MATERIAL") {
                            $productByPlantJpa = ProductByPlant::where('_product', $productJpa->id)
                                ->where('_plant', $saleProductJpa->_plant)->first();
                            if ($productByPlantJpa) {
                                $productByPlantJpa->mount = $productByPlantJpa->mount + $detail['mount'];
                                $productByPlantJpa->save();
                            } else {
                                $productByPlantJpa_new = new ProductByPlant();
                                $productByPlantJpa_new->_product = $productJpa->id;
                                $productByPlantJpa_new->_plant = $saleProductJpa->_plant;
                                $productByPlantJpa_new->mount = $detail['mount'];
                                $productByPlantJpa_new->status = '1';
                                $productByPlantJpa_new->save();
                            }
                        } else {
                            $productByPlantJpa_new = new ProductByPlant();
                            $productByPlantJpa_new->_product = $productJpa->id;
                            $productByPlantJpa_new->_plant = $saleProductJpa->_plant;
                            $productByPlantJpa_new->mount = $detail['mount'];
                            $productByPlantJpa_new->status = '1';
                            $productByPlantJpa_new->save();
                        }
                    }
                }
            }

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

            $detailsSalesJpa = DetailSale::where('_sales_product', $saleProductJpa->id)
                ->get();

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            foreach ($detailsSalesJpa as $detail) {
                $detailSale = DetailSale::find($detail['id']);
                $detailSale->status = null;
                $productJpa = Product::find($detail['_product']);

                if ($productJpa->type == "MATERIAL") {
                    $stockPlantJpa = StockPlant::where('_product', $productJpa->id)->where('_plant', $saleProductJpa->_plant)->first();
                    $stockPlantJpa->mount = $stockPlantJpa->mount + $detail['mount'];
                    $stockPlantJpa->save();
                } else {
                    $stockPlantJpa = StockPlant::where('_product', $productJpa->id)->where('_plant', $saleProductJpa->_plant)->first();
                    $stockPlantJpa->status = "1";
                    $productJpa->disponibility = "PLANTA";
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
                if ($column == 'mount' || $column == '*') {
                    $q->orWhere('mount', $type, $value);
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
            $response->setITotalRecords(ViewStockPlant::count());
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

            $towerJpa = Plant::find($request->_plant);
            if (!$towerJpa) {
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
                        $productJpa->mount = intval($productJpa->mount) + $product['mount'];
                        $stock->mount_new = $productJpa->mount;
                        $productByPlantJpa->mount = $productByPlantJpa->mount - $product['mount'];
                    } else {
                        $productJpa->disponibility = "DISPONIBLE";
                        $productJpa->condition_product = "DEVUELTO DE LA TORRE: " . $towerJpa->name;
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
                    $detailSale->mount = $product['mount'];
                    $detailSale->_sales_product = $salesProduct->id;
                    $detailSale->status = '1';
                    $detailSale->save();

                    $entryDetail = new EntryDetail();
                    $entryDetail->_product = $productJpa->id;
                    $entryDetail->mount = $product['mount'];
                    $entryDetail->_entry_product = $entryProductsJpa->id;
                    $entryDetail->status = "1";
                    $entryDetail->save();
                }
            }

            $towerJpa->update_date = gTrace::getDate('mysql');
            $towerJpa->_update_user = $userid;
            $towerJpa->save();

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
                        $productJpa->mount = intval($productJpa->mount) + $product['mount'];
                        $stock->mount_new = $productJpa->mount;
                        $stockPlantJpa->mount = $stockPlantJpa->mount - $product['mount'];
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
                    $detailSale->mount = $product['mount'];
                    $detailSale->_sales_product = $salesProduct->id;
                    $detailSale->status = '1';
                    $detailSale->save();

                    $entryDetail = new EntryDetail();
                    $entryDetail->_product = $productJpa->id;
                    $entryDetail->mount = $product['mount'];
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
                $stockPlantJpa = StockPlant::where('_product', $productJpa->id)->where('_plant', $salesProduct->_plant)->first();
                $stockPlantJpa->mount = $stockPlantJpa->mount + $detailSale->mount;
                $stockPlantJpa->save();
            } else if ($productJpa->type == "EQUIPO") {

                $stockPlantJpa = StockPlant::where('_product', $productJpa->id)->where('_plant', $salesProduct->_plant)->first();
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
                    'detail_sales.mount as mount',
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
                'detail_sales.mount as mount',
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
                $mount = $product['mount'];
                if (isset($models[$model])) {
                    $models[$model]['mount'] += $mount;
                } else {
                    $models[$model] = array('model' => $model, 'mount' => $mount, 'relative_id' => $relativeId, 'unity' => $unity);
                }
            }
            $count = 1;
            $products = array_values($models);
            foreach ($products as $detail) {
                $sumary .= "
                <tr>
                    <td><center style='font-size:12px;'>{$count}</center></td>
                    <td><center style='font-size:12px;'>{$detail['mount']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['unity']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['model']}</center></td>
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
                'stock_plant.mount as mount',
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
                $mount = $product['mount'];
                if (isset($models[$model])) {
                    $models[$model]['mount'] += $mount;
                } else {
                    $models[$model] = array('model' => $model, 'mount' => $mount, 'relative_id' => $relativeId, 'unity' => $unity);
                }
            }
            $count = 1;
            $products = array_values($models);
            foreach ($products as $detail) {
                $sumary .= "
                <tr>
                    <td><center style='font-size:12px;'>{$count}</center></td>
                    <td><center style='font-size:12px;'>{$detail['mount']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['unity']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['model']}</center></td>
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
                $mount = $product['mount'];
                if (isset($models[$model])) {
                    $models[$model]['mount'] += $mount;
                } else {
                    $models[$model] = array('model' => $model, 'mount' => $mount, 'relative_id' => $relativeId, 'unity' => $unity);
                }
            }
            $count = 1;
            $products = array_values($models);
            foreach ($products as $detail) {
                $sumary .= "
                <tr>
                    <td><center style='font-size:12px;'>{$count}</center></td>
                    <td><center style='font-size:12px;'>{$detail['mount']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['unity']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['model']}</center></td>
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
}
