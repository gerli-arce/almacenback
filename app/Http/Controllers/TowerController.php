<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\DetailSale;
use App\Models\EntryDetail;
use App\Models\EntryProducts;
use App\Models\Product;
use App\Models\ProductByTower;
use App\Models\Response;
use App\Models\SalesProducts;
use App\Models\Stock;
use App\Models\Tower;
use App\Models\ViewProductsByTower;
use App\Models\ViewStockTower;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TowerController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'tower', 'create')) {
                throw new Exception("No tienes permisos para agregar torres");
            }

            if (
                !isset($request->name)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $towerValidation = Tower::select(['name'])
                ->where('name', $request->model)
                ->first();

            if ($towerValidation) {
                throw new Exception("Escoja otro nombre para el modelo ");
            }

            $towerJpa = new Tower();
            $towerJpa->name = $request->name;
            $towerJpa->description = $request->description;
            $towerJpa->coordenates = $request->coordenates;
            $towerJpa->address = $request->address;
            $towerJpa->relative_id = guid::short();

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
                    $towerJpa->image_type = $request->image_type;
                    $towerJpa->image_mini = base64_decode($request->image_mini);
                    $towerJpa->image_full = base64_decode($request->image_full);
                } else {
                    $towerJpa->image_type = null;
                    $towerJpa->image_mini = null;
                    $towerJpa->image_full = null;
                }
            }

            $towerJpa->status = "1";
            $towerJpa->save();

            $response->setStatus(200);
            $response->setMessage('La torre se a agregado correctamente');
        } catch (\Throwable$th) {
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

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'tower', 'read')) {
                throw new Exception('No tienes permisos para listar modelos');
            }

            $query = Tower::select([
                'id',
                'name',
                'description',
                'coordenates',
                'address',
                'relative_id',
                'status',
            ])
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'name' || $column == '*') {
                    $q->where('name', $type, $value);
                }
                if ($column == 'coordenates' || $column == '*') {
                    $q->where('coordenates', $type, $value);
                }
                if ($column == 'address' || $column == '*') {
                    $q->orWhere('address', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->where('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $towerJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Tower::count());
            $response->setData($towerJpa->toArray());
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function image($relative_id, $size)
    {
        $response = new Response();
        $content = null;
        $type = null;
        try {
            if ($size != 'full') {
                $size = 'mini';
            }
            if (
                !isset($relative_id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $modelJpa = Tower::select([
                "towers.image_$size as image_content",
                'towers.image_type',

            ])
                ->where('relative_id', $relative_id)
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
        } catch (\Throwable$th) {
            $ruta = '../storage/images/antena-default.png';
            $fp = fopen($ruta, 'r');
            $datos_image = fread($fp, filesize($ruta));
            $datos_image = addslashes($datos_image);
            fclose($fp);
            $content = stripslashes($datos_image);
            $type = 'image/jpeg';
            $response->setStatus(400);
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
            if (!gValidate::check($role->permissions, $branch, 'tower', 'update')) {
                throw new Exception('No tienes permisos para actualizar torres');
            }

            $towerJpa = Tower::select(['id'])->find($request->id);
            if (!$towerJpa) {
                throw new Exception("No se puede actualizar este registro");
            }

            if (isset($request->name)) {
                $verifyCatJpa = Tower::select(['id', 'name'])
                    ->where('name', $request->name)
                    ->where('id', '!=', $request->id)
                    ->first();
                if ($verifyCatJpa) {
                    throw new Exception("Elija otro nombre para esta llave");
                }
                $towerJpa->name = $request->name;
            }

            if (isset($request->coordenates)) {
                $towerJpa->coordenates = $request->coordenates;
            }

            if (isset($request->address)) {
                $towerJpa->address = $request->address;
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
                    $towerJpa->image_type = $request->image_type;
                    $towerJpa->image_mini = base64_decode($request->image_mini);
                    $towerJpa->image_full = base64_decode($request->image_full);
                } else {
                    $towerJpa->image_type = null;
                    $towerJpa->image_mini = null;
                    $towerJpa->image_full = null;
                }
            }

            $towerJpa->description = $request->description;

            if (gValidate::check($role->permissions, $branch, 'towers', 'change_status')) {
                if (isset($request->status)) {
                    $towerJpa->status = $request->status;
                }
            }

            $towerJpa->save();

            $response->setStatus(200);
            $response->setMessage('La torre ha sido actualizada correctamente');
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
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
            if (!gValidate::check($role->permissions, $branch, 'tower', 'update')) {
                throw new Exception('No tienes permisos para actualizar torre');
            }

            if (
                !isset($request->_tower)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            if (isset($request->_technical)) {
                $salesProduct->_technical = $request->_technical;
            }
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_tower = $request->_tower;
            $salesProduct->_type_operation = $request->_type_operation;
            $salesProduct->type_intallation = "TOWER";
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
                    $stock = Stock::where('_model', $productJpa->_model)
                        ->where('_branch', $branch_->id)
                        ->first();
                    if ($product['product']['type'] == "MATERIAL") {
                        $productJpa->mount = intval($productJpa->mount) - $product['mount'];
                        $stock->mount_new = $productJpa->mount;
                    } else {
                        $productJpa->disponibility = "TORRE";
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
        } catch (\Throwable$th) {
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

            if (!gValidate::check($role->permissions, $branch, 'tower', 'read')) {
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
                ->where('_tower', $id)
                ->orderBy('id', 'desc')
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
        } catch (\Throwable$th) {
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

            if (!gValidate::check($role->permissions, $branch, 'tower', 'read')) {
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
                ->where('_tower', $id)
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
        } catch (\Throwable$th) {
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

            if (!gValidate::check($role->permissions, $branch, 'tower', 'update')) {
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
                    if (isset($product['id'])) {
                        $productJpa = Product::find($product['product']['id']);
                        $detailSale = DetailSale::find($product['id']);

                        if ($product['product']['type'] == "MATERIAL") {
                            $stock = Stock::where('_model', $productJpa->_model)
                                ->where('_branch', $branch_->id)
                                ->first();
                            if (intval($detailSale->mount) != intval($product['mount'])) {
                                if (intval($detailSale->mount) > intval($product['mount'])) {
                                    $mount_dif = intval($detailSale->mount) - intval($product['mount']);
                                    $productJpa->mount = intval($productJpa->mount) + $mount_dif;
                                    $stock->mount_new = intval($productJpa->mount);
                                } else if (intval($detailSale->mount) < intval($product['mount'])) {
                                    $mount_dif = intval($product['mount']) - intval($detailSale->mount);
                                    $productJpa->mount = intval($productJpa->mount) - $mount_dif;
                                    $stock->mount_new = intval($productJpa->mount);
                                }
                            }

                            $stock->save();
                            $detailSale->mount = $product['mount'];
                        }

                        if (!$detailSale) {
                            throw new Exception("detail: " . $product['id']);
                        }

                        $detailSale->save();
                        $productJpa->save();

                    } else {
                        $productJpa = Product::find($product['product']['id']);

                        if ($product['product']['type'] == "MATERIAL") {
                            $productJpa->mount = intval($productJpa->mount) - $product['mount'];
                            $stock = Stock::where('_model', $productJpa->_model)
                                ->where('_branch', $branch_->id)
                                ->first();
                            $stock->mount_new = $productJpa->mount;
                            $stock->save();
                        } else {
                            $productJpa->disponibility = "PLANTA";
                            if ($productJpa->product_status == "NUEVO") {
                                $stock = Stock::where('_model', $productJpa->_model)
                                    ->where('_branch', $branch_->id)
                                    ->first();
                                $stock->mount_new = $stock->mount_new - 1;
                                $stock->save();
                            } else if ($productJpa->product_status == "SEMINUEVO") {
                                $stock = Stock::where('_model', $productJpa->_model)
                                    ->where('_branch', $branch_->id)
                                    ->first();
                                $stock->mount_second = $stock->mount_second - 1;
                                $stock->save();
                            }
                        }
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
                    $detailsSalesJpa = DetailSale::where('_sales_product', $saleProductJpa->id)
                        ->get();
                    foreach ($detailsSalesJpa as $detail) {
                        $productJpa = Product::find($detail['_product']);
                        if ($productJpa->type == "MATERIAL") {
                            $productByTowerJpa = ProductByTower::where('_product', $productJpa->id)
                                ->where('_tower', $saleProductJpa->_tower)->first();
                            if ($productByTowerJpa) {
                                $productByTowerJpa->mount = $productByTowerJpa->mount + $detail['mount'];
                                $productByTowerJpa->save();
                            } else {
                                $productByTowerJpa_new = new ProductByTower();
                                $productByTowerJpa_new->_product = $productJpa->id;
                                $productByTowerJpa_new->_tower = $saleProductJpa->_tower;
                                $productByTowerJpa_new->mount = $detail['mount'];
                                $productByTowerJpa_new->status = '1';
                                $productByTowerJpa_new->save();
                            }
                        } else {
                            $productByTowerJpa_new = new ProductByTower();
                            $productByTowerJpa_new->_product = $productJpa->id;
                            $productByTowerJpa_new->_tower = $saleProductJpa->_tower;
                            $productByTowerJpa_new->mount = $detail['mount'];
                            $productByTowerJpa_new->status = '1';
                            $productByTowerJpa_new->save();
                        }
                    }
                }
            }

            $response->setStatus(200);
            $response->setMessage('La encomienda a sido eliminada correctamente');
            $response->setData($role->toArray());
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln:' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function getStockTower(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'tower', 'read')) {
                throw new Exception('No tienes permisos para listar');
            }

            $productByTowerJpa = ViewProductsByTower::where('tower__id', $request->id)->whereNotNull('status')->get();

            $stock_tower = [];

            foreach ($productByTowerJpa as $products) {
                $product = gJSON::restore($products->toArray(), '__');
                $stock_tower[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($stock_tower);
        } catch (\Throwable$th) {
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
            if (!gValidate::check($role->permissions, $branch, 'tower', 'update')) {
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

                $stock = Stock::where('_model', $productJpa->_model)
                    ->where('_branch', $branch_->id)
                    ->first();

                if ($productJpa->type == "MATERIAL") {
                    $productJpa->mount = intval($productJpa->mount) + intval($detail['mount']);
                    $stock->mount_new = $productJpa->mount;
                } else {
                    $productJpa->disponibility = 'DISPONIBLE';
                    $productJpa->condition_product = "REGRESO DE TORRE";

                    if ($productJpa->product_status == "NUEVO") {
                        $stock->mount_new = $stock->mount_new + 1;
                    } else if ($productJpa->product_status == "SEMINUEVO") {
                        $stock->mount_second = $stock->mount_second + 1;
                    }

                }

                $stock->save();
                $productJpa->save();
                $detailSale->save();
            }

            $saleProductJpa->update_date = gTrace::getDate('mysql');
            $saleProductJpa->status = null;
            $saleProductJpa->save();

            $response->setStatus(200);
            $response->setMessage('La liquidación se elimino correctamente.');
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln:' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function paginateStockTower(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'tower', 'read')) {
                throw new Exception('No tienes permisos para listar modelos');
            }

            $query = ViewStockTower::select(['*'])->orderBy($request->order['column'], $request->order['dir'])
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
            })->where('tower__id', $request->search['tower']);

            $iTotalDisplayRecords = $query->count();
            $towerJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $stock = array();
            foreach ($towerJpa as $productJpa) {
                $product = gJSON::restore($productJpa->toArray(), '__');
                $stock[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Tower::count());
            $response->setData($stock);
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function returnProductsByTower(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'towers', 'update')) {
                throw new Exception('No tienes permisos para actualizar');
            }

            if (
                !isset($request->_tower)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $towerJpa = Tower::find($request->_tower);
            if (!$towerJpa) {
                throw new Exception('La torre que deseas eliminar no existe');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            if (isset($request->_technical)) {
                $salesProduct->_technical = $request->_technical;
            }
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_tower = $request->_tower;
            $salesProduct->_type_operation = $request->_type_operation;
            $salesProduct->type_intallation = "TOWER";
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
            $salesProduct->status = "0";
            $salesProduct->save();

            $entryProductsJpa = new EntryProducts();
            $entryProductsJpa->_user = $userid;
            $entryProductsJpa->_technical = $request->_technical;
            $entryProductsJpa->_branch = $branch_->id;
            $entryProductsJpa->_type_operation = $request->_type_operation;
            $entryProductsJpa->_tower = $request->_tower;
            $entryProductsJpa->type_entry = "DEVOLUCIÓN DE TORRE";
            $entryProductsJpa->entry_date = gTrace::getDate('mysql');
            $entryProductsJpa->condition_product = "USADO EN TORRE";
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

                    $productByTowerJpa = ProductByTower::find($product['id']);

                    if ($product['product']['type'] == "MATERIAL") {
                        $productJpa->mount = intval($productJpa->mount) + $product['mount'];
                        $stock->mount_new = $productJpa->mount;
                        $productByTowerJpa->mount = $productByTowerJpa->mount - $product['mount'];
                    } else {
                        $productJpa->disponibility = "DISPONIBLE";
                        $productJpa->condition_product = "DEVUELTO DE LA TORRE: " . $towerJpa->name;
                        if ($productJpa->product_status == "NUEVO") {
                            $stock->mount_new = $stock->mount_new + 1;
                        } else if ($productJpa->product_status == "SEMINUEVO") {
                            $stock->mount_second = $stock->mount_second + 1;
                        }
                        $productByTowerJpa->status = null;
                    }

                    $productByTowerJpa->save();
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

            $towerJpa->update_date = gTrace::getDate('mysql');
            $towerJpa->_update_user = $userid;
            $towerJpa->save();

            $response->setStatus(200);
            $response->setMessage('La torre a sido eliminada correctamente');
            $response->setData($role->toArray());
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
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

            if (!gValidate::check($role->permissions, $branch, 'tower', 'read')) {
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
                ->where('_tower', $id)
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
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function destroy(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'towers', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar torres');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $towerJpa = Tower::find($request->id);
            if (!$towerJpa) {
                throw new Exception('La torre que deseas eliminar no existe');
            }

            $towerJpa->update_date = gTrace::getDate('mysql');
            $towerJpa->_update_user = $userid;
            $towerJpa->status = null;
            $towerJpa->save();

            $response->setStatus(200);
            $response->setMessage('La torre a sido eliminada correctamente');
            $response->setData($role->toArray());
        } catch (\Throwable$th) {
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
            if (!gValidate::check($role->permissions, $branch, 'towers', 'delete_restore')) {
                throw new Exception('No tienes permisos para restaurar torres.');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $towerJpa = Tower::find($request->id);
            if (!$towerJpa) {
                throw new Exception('La torre que deseas restaurar no existe');
            }

            $towerJpa->status = "1";
            $towerJpa->save();

            $response->setStatus(200);
            $response->setMessage('La torre a sido restaurada correctamente');
            $response->setData($role->toArray());
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }
}
