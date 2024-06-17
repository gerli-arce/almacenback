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
use App\Models\PhotographsByTower;
use App\Models\Product;
use App\Models\ProductByTower;
use App\Models\Response;
use App\Models\SalesProducts;
use App\Models\Stock;
use App\Models\Tower;
use App\Models\User;
use App\Models\ViewProductsByTower;
use App\Models\ViewStockTower;
use Dompdf\Dompdf;
use Dompdf\Options;
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
                throw new Exception("Escoja otro nombre para la torre ");
            }

            $towerJpa = new Tower();
            $towerJpa->name = $request->name;
            $towerJpa->_keys = $request->_keys;
            $towerJpa->description = $request->description;
            $towerJpa->contract_date_start = $request->contract_date_start;
            $towerJpa->contract_date_end = $request->contract_date_end;
            $towerJpa->price_month = $request->price_month;
            $towerJpa->camera = $request->camera;
            $towerJpa->longitude = $request->longitude;
            $towerJpa->latitude = $request->latitude;
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

            if (
                isset($request->image_contract_type) &&
                isset($request->image_contract_mini) &&
                isset($request->image_contract_full)
            ) {
                if (
                    $request->image_contract_type != "none" &&
                    $request->image_contract_mini != "none" &&
                    $request->image_contract_full != "none"
                ) {
                    $towerJpa->contract_img_type = $request->image_contract_type;
                    $towerJpa->contract_img_mini = base64_decode($request->image_contract_mini);
                    $towerJpa->contract_img_full = base64_decode($request->image_contract_full);
                } else {
                    $towerJpa->contract_img_type = null;
                    $towerJpa->contract_img_mini = null;
                    $towerJpa->contract_img_full = null;
                }
            }

            $towerJpa->_creation_user = $userid;
            $towerJpa->creation_date = gTrace::getDate('mysql');
            $towerJpa->_update_user = $userid;
            $towerJpa->update_date = gTrace::getDate('mysql');
            $towerJpa->status = "1";
            $towerJpa->save();

            $response->setStatus(200);
            $response->setMessage('La torre se a agregado correctamente');
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

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'tower', 'read')) {
                throw new Exception('No tienes permisos para listar modelos');
            }

            $query = Tower::select([
                'towers.id as id',
                'towers.name as name',
                'towers.description as description',
                'towers.latitude as latitude',
                'towers.longitude as longitude',
                'towers.relative_id as relative_id',
                'towers.camera as camera',
                'towers.contract_date_start as contract_date_start',
                'towers.contract_date_end as contract_date_end',
                'towers.price_month as price_month',
                'towers._creation_user as _creation_user',
                'towers.creation_date as creation_date',
                'towers.status as status',
                'key.id as key__id',
                'key.name as key__name',
                'key.latitude as key__latitude',
                'key.longitude as key__longitude',
                'key.position_x as key__position_x',
                'key.position_y as key__position_y',
                'people.name as people__name',
                'people.lastname as people__lastname',

            ])
                ->leftJoin('users', 'towers._creation_user', 'users.id')
                ->leftJoin('keyses as key', 'towers._keys', 'key.id')
                ->join('people', 'users._person', 'people.id')
                ->orderBy('towers.' . $request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('towers.status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'name' || $column == '*') {
                    $q->orWhere('towers.name', $type, $value);
                }
                if ($column == 'latitude' || $column == '*') {
                    $q->orWhere('towers.latitude', $type, $value);
                }
                if ($column == 'longitude' || $column == '*') {
                    $q->orWhere('towers.longitude', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('towers.description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $towerJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $towers = [];
            foreach ($towerJpa as $towerJpa) {
                $tower = gJSON::restore($towerJpa->toArray(), '__');
                $towers[] = $tower;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Tower::count());
            $response->setData($towers);
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

    public function contract($relative_id, $size)
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
                "towers.contract_img_$size as image_content",
                'towers.contract_img_type',

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
        } catch (\Throwable $th) {
            $ruta = '../storage/images/img-default.jpg';
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
                //$verifyCatJpa = Tower::select(['id', 'name'])
                //   ->where('name', $request->name)
                //   ->where('id', $request->id)
                //   ->first();
                //if ($verifyCatJpa) {
                //   throw new Exception("Error: La torre ya existe");
                // }
                $towerJpa->name = $request->name;
            }

            $towerJpa->_keys = $request->_keys;

            if (isset($request->latitude)) {
                $towerJpa->latitude = $request->latitude;
            }

            if (isset($request->longitude)) {
                $towerJpa->longitude = $request->longitude;
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

            if (
                isset($request->image_contract_type) &&
                isset($request->image_contract_mini) &&
                isset($request->image_contract_full)
            ) {
                if (
                    $request->image_contract_type != "none" &&
                    $request->image_contract_mini != "none" &&
                    $request->image_contract_full != "none"
                ) {
                    $towerJpa->contract_img_type = $request->image_contract_type;
                    $towerJpa->contract_img_mini = base64_decode($request->image_contract_mini);
                    $towerJpa->contract_img_full = base64_decode($request->image_contract_full);
                } else {
                    $towerJpa->contract_img_type = null;
                    $towerJpa->contract_img_mini = null;
                    $towerJpa->contract_img_full = null;
                }
            }

            $towerJpa->description = $request->description;
            $towerJpa->contract_date_start = $request->contract_date_start;
            $towerJpa->contract_date_end = $request->contract_date_end;
            $towerJpa->price_month = $request->price_month;
            $towerJpa->camera = $request->camera;

            if (gValidate::check($role->permissions, $branch, 'towers', 'change_status')) {
                if (isset($request->status)) {
                    $towerJpa->status = $request->status;
                }
            }

            $towerJpa->_update_user = $userid;
            $towerJpa->update_date = gTrace::getDate('mysql');

            $towerJpa->save();

            $response->setStatus(200);
            $response->setMessage('La torre ha sido actualizada correctamente');
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
                !isset($request->_tower) ||
                !isset($request->_technical)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $towerJpa = Tower::select(['id', 'name'])->where('id', $request->_tower)->first();

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
                        $stock->mount_new = $stock->mount_new - $product['mount_new'];
                        $stock->mount_second = $stock->mount_second - $product['mount_second'];
                        $stock->mount_ill_fated = $stock->mount_ill_fated - $product['mount_ill_fated'];
                        $productJpa->mount = $stock->mount_new - $stock->mount_second;
                    } else {
                        $productJpa->disponibility = "TORRE: " . $towerJpa->name;
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
                    $detailSale->mount_new = $product['mount_new'];
                    $detailSale->mount_second = $product['mount_second'];
                    $detailSale->mount_ill_fated = $product['mount_ill_fated'];
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

    public function cancelUseProduct(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);

            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'tower_pending', 'update')) {
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

            $stock = Stock::where('_model', $productJpa->_model)
                ->where('_branch', $branch_->id)
                ->first();
            if ($productJpa->type == "MATERIAL") {
                $stock->mount_new = $stock->mount_new + $detailSale->mount_new;
                $stock->mount_second = $stock->mount_second + $detailSale->mount_second;
                $stock->mount_ill_fated = $stock->mount_ill_fated + $detailSale->mount_ill_fated;
                $productJpa->mount = $stock->mount_new + $stock->mount_second;
            } else if ($productJpa->type == "EQUIPO") {
                $productJpa->disponibility = "DISPONIBLE";
                if ($productJpa->product_status == "NUEVO") {
                    $stock->mount_new = intval($stock->mount_new) + 1;
                } else if ($productJpa->product_status == "SEMINUEVO") {
                    $stock->mount_second = intval($stock->mount_second) + 1;
                }
            }

            $stock->save();
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

            $towerJpa = Tower::select(['id', 'name'])->where('id', $salesProduct->_tower)->first();

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

                            if (intval($detailSale->mount_new) != intval($product['mount_new'])) {
                                if (intval($detailSale->mount_new) > intval($product['mount_new'])) {
                                    $mount_dif = intval($detailSale->mount_new) - intval($product['mount_new']);
                                    $stock->mount_new = $stock->mount_new + $mount_dif;
                                } else if (intval($detailSale->mount_new) < intval($product['mount_new'])) {
                                    $mount_dif = intval($product['mount_new']) - intval($detailSale->mount_new);
                                    $stock->mount_new = $stock->mount_new - $mount_dif;
                                }
                            }

                            if (intval($detailSale->mount_second) != intval($product['mount_second'])) {
                                if (intval($detailSale->mount_second) > intval($product['mount_second'])) {
                                    $mount_dif = intval($detailSale->mount_second) - intval($product['mount_second']);
                                    $stock->mount_second = $stock->mount_second + $mount_dif;
                                } else if (intval($detailSale->mount_second) < intval($product['mount_second'])) {
                                    $mount_dif = intval($product['mount_second']) - intval($detailSale->mount_second);
                                    $stock->mount_second = $stock->mount_second - $mount_dif;
                                }
                            }

                            if (intval($detailSale->mount_ill_fated) != intval($product['mount_ill_fated'])) {
                                if (intval($detailSale->mount_ill_fated) > intval($product['mount_ill_fated'])) {
                                    $mount_dif = intval($detailSale->mount_ill_fated) - intval($product['mount_ill_fated']);
                                    $stock->mount_ill_fated = $stock->mount_ill_fated + $mount_dif;
                                } else if (intval($detailSale->mount_ill_fated) < intval($product['mount_ill_fated'])) {
                                    $mount_dif = intval($product['mount_ill_fated']) - intval($detailSale->mount_ill_fated);
                                    $stock->mount_ill_fated = $stock->mount_ill_fated - $mount_dif;
                                }
                            }

                            $stock->save();
                            $productJpa->mount = $stock->mount_new + $stock->mount_second;
                            $detailSale->mount_new = $product['mount_new'];
                            $detailSale->mount_second = $product['mount_second'];
                            $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                        }

                        if (!$detailSale) {
                            throw new Exception("detail: " . $product['id']);
                        }

                        $detailSale->save();
                        $productJpa->save();
                    } else {
                        $productJpa = Product::find($product['product']['id']);

                        $stock = Stock::where('_model', $productJpa->_model)
                            ->where('_branch', $branch_->id)
                            ->first();

                        if ($product['product']['type'] == "MATERIAL") {
                            $stock->mount_new = $stock->mount_new - $product['mount_new'];
                            $stock->mount_second = $stock->mount_second - $product['mount_second'];
                            $stock->mount_ill_fated = $stock->mount_ill_fated - $product['mount_ill_fated'];
                            $productJpa->mount = $stock->mount_new - $stock->mount_second;
                        } else {
                            $productJpa->disponibility = "TORRE: " . $towerJpa->name;
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
                        $detailSale->mount_new = $product['mount_new'];
                        $detailSale->mount_second = $product['mount_second'];
                        $detailSale->mount_ill_fated = $product['mount_ill_fated'];
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
                                $productByTowerJpa->mount_new = $productByTowerJpa->mount_new + $detail['mount_new'];
                                $productByTowerJpa->mount_second = $productByTowerJpa->mount_second + $detail['mount_second'];
                                $productByTowerJpa->mount_ill_fated = $productByTowerJpa->mount_ill_fated + $detail['mount_ill_fated'];
                                $productByTowerJpa->save();
                            } else {
                                $productByTowerJpa_new = new ProductByTower();
                                $productByTowerJpa_new->_product = $productJpa->id;
                                $productByTowerJpa_new->_tower = $saleProductJpa->_tower;
                                $productByTowerJpa_new->mount_new = $detail['mount_new'];
                                $productByTowerJpa_new->mount_second = $detail['mount_second'];
                                $productByTowerJpa_new->mount_ill_fated = $detail['mount_ill_fated'];
                                $productByTowerJpa_new->status = '1';
                                $productByTowerJpa_new->save();
                            }
                        } else {
                            $productByTowerJpa_new = new ProductByTower();
                            $productByTowerJpa_new->_product = $productJpa->id;
                            $productByTowerJpa_new->_tower = $saleProductJpa->_tower;
                            $productByTowerJpa_new->mount_new = $detail['mount_new'];
                            $productByTowerJpa_new->mount_second = $detail['mount_second'];
                            $productByTowerJpa_new->mount_ill_fated = $detail['mount_ill_fated'];
                            $productByTowerJpa_new->status = '1';
                            $productByTowerJpa_new->save();
                        }
                    }
                }
            }

            $response->setStatus(200);
            $response->setMessage('La encomienda ha sido actualizada correctamente');
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

            $TowerJpa = Tower::find($request->tower['id']);

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            foreach ($detailsSalesJpa as $detail) {
                $detailSale = DetailSale::find($detail['id']);
                $detailSale->status = null;
                $productJpa = Product::find($detail['_product']);

                $stock = Stock::where('_model', $productJpa->_model)
                    ->where('_branch', $branch_->id)
                    ->first();

                if ($productJpa->type == "MATERIAL") {
                    $stock->mount_new = $stock->mount_new + $detail['mount_new'];
                    $stock->mount_second = $stock->mount_second + $detail['mount_second'];
                    $stock->mount_ill_fated = $stock->mount_ill_fated + $detail['mount_ill_fated'];
                    $productJpa->mount = $stock->mount_new + $stock->mount_second;
                } else {
                    $productJpa->disponibility = 'DISPONIBLE';
                    $productJpa->condition_product = "REGRESO DE TORRE: " . $TowerJpa->name . " POR CANCELACIÓN DE LIQUIDACIÓN";

                    if ($productJpa->product_status == "NUEVO") {
                        $stock->mount_new = $stock->mount_new + 1;
                    } else if ($productJpa->product_status == "SEMINUEVO") {
                        $stock->mount_second = $stock->mount_second + 1;
                    } else if ($productJpa->product_status == "MALOGRADO" || $productJpa->product_status == "POR REVISAR") {
                        $stock->mount_ill_fated = $stock->mount_ill_fated + 1;
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

    public function searchProductsByTowerByModel(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'tower', 'update')) {
                throw new Exception('No tienes permisos para actualizar');
            }

            $ProductByTowerJpa = ProductByTower::where('_product', $request->product['id'])->where('_tower', $request->tower['id'])->first();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData([$ProductByTowerJpa]);
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

    public function returnProductsByTower(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'tower', 'update')) {
                throw new Exception('No tienes permisos para actualizar');
            }

            if (
                !isset($request->_tower)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $towerJpa = Tower::find($request->_tower);
            if (!$towerJpa) {
                throw new Exception('La torre no existe');
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
            $entryProductsJpa->description = $request->description;
            $entryProductsJpa->_tower = $request->_tower;
            $entryProductsJpa->type_entry = "DEVOLUCION DE TORRE";
            $entryProductsJpa->entry_date = gTrace::getDate('mysql');
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

                    $productByTowerJpa = ProductByTower::find($product['id']);

                    if ($product['product']['type'] == "MATERIAL") {
                        $stock->mount_new = $stock->mount_new + $product['mount_new'];
                        $stock->mount_second = $stock->mount_second + $product['mount_second'];
                        $stock->mount_ill_fated = $stock->mount_ill_fated + $product['mount_ill_fated'];
                        $productByTowerJpa->mount_new = $productByTowerJpa->mount_new - $product['mount_new'];
                        $productByTowerJpa->mount_second = $productByTowerJpa->mount_second - $product['mount_second'];
                        $productByTowerJpa->mount_ill_fated = $productByTowerJpa->mount_ill_fated - $product['mount_ill_fated'];
                        $productJpa->mount = $stock->mount_new + $stock->mount_second;
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

            $towerJpa->update_date = gTrace::getDate('mysql');
            $towerJpa->_update_user = $userid;
            $towerJpa->save();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'Ln:' . $th->getLine());
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
            if (!gValidate::check($role->permissions, $branch, 'tower', 'update')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $PhotographsByTowerJpa = new PhotographsByTower();
            $PhotographsByTowerJpa->_tower = $request->id;
            $PhotographsByTowerJpa->description = $request->description;

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
                    $PhotographsByTowerJpa->image_type = $request->image_type;
                    $PhotographsByTowerJpa->image_mini = base64_decode($request->image_mini);
                    $PhotographsByTowerJpa->image_full = base64_decode($request->image_full);
                } else {
                    throw new Exception("Una imagen debe ser cargada.");
                }
            } else {
                throw new Exception("Una imagen debe ser cargada.");
            }

            $PhotographsByTowerJpa->_creation_user = $userid;
            $PhotographsByTowerJpa->creation_date = gTrace::getDate('mysql');
            $PhotographsByTowerJpa->_update_user = $userid;
            $PhotographsByTowerJpa->update_date = gTrace::getDate('mysql');
            $PhotographsByTowerJpa->status = "1";
            $PhotographsByTowerJpa->save();

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
            if (!gValidate::check($role->permissions, $branch, 'tower', 'update')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $PhotographsByTowerJpa = PhotographsByTower::find($request->id);
            $PhotographsByTowerJpa->description = $request->description;

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
                    $PhotographsByTowerJpa->image_type = $request->image_type;
                    $PhotographsByTowerJpa->image_mini = base64_decode($request->image_mini);
                    $PhotographsByTowerJpa->image_full = base64_decode($request->image_full);
                }
            }

            $PhotographsByTowerJpa->_update_user = $userid;
            $PhotographsByTowerJpa->update_date = gTrace::getDate('mysql');
            $PhotographsByTowerJpa->save();

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
            if (!gValidate::check($role->permissions, $branch, 'tower', 'update')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $PhotographsByTowerJpa = PhotographsByTower::select(['id', 'description', '_creation_user', 'creation_date', '_update_user', 'update_date'])
                ->where('_tower', $id)->whereNotNUll('status')
                ->orderBy('id', 'desc')
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta.');
            $response->setData($PhotographsByTowerJpa->toArray());
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

            $modelJpa = PhotographsByTower::select([
                "photographs_by_tower.image_$size as image_content",
                'photographs_by_tower.image_type',

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

    public function deleteImage(Request $request, $id)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'tower', 'update')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $PhotographsByTowerJpa = PhotographsByTower::find($id);
            $PhotographsByTowerJpa->_update_user = $userid;
            $PhotographsByTowerJpa->update_date = gTrace::getDate('mysql');
            $PhotographsByTowerJpa->status = null;
            $PhotographsByTowerJpa->save();

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

    public function generateReportByLiquidation(Request $request)
    {
        set_time_limit(120);
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
            $template = file_get_contents('../storage/templates/reportLiquidationTower.html');
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
            $details_product = '';
            foreach ($details as $product) {
                $details_equipment = 'display:none;';
                if ($product['product']['type'] == 'EQUIPO') {
                    $details_equipment = '';
                }
                $details_product .= "
                    <div style='border: 2px solid #bbc7d1; border-radius: 9px; width: 25%; display: inline-block; padding:8px; font-size:12px; margin-left:10px;'>
                        <center>
                            <p><strong>{$product['product']['model']['model']}</strong></p>
                            <img src='https://almacendev.fastnetperu.com.pe/api/model/{$product['product']['model']['relative_id']}/mini' style='object-fit: cover; object-position: center center; cursor: pointer; height:80px;margin-top:25px;border:solid 2px #2f3a599e;border-radius:8px; padding:5px;'></img>
                            <div style='{$details_equipment}'>
                                <p>Mac: <strong>{$product['product']['mac']}</strong><p>
                                <p>Serie: <strong>{$product['product']['serie']}</strong></p>
                            </div>
                            <div>
                                <p style='font-size:20px; color:#2f6593'>Nu:{$product['mount_new']} | Se:{$detailJpa['mount_second']} | Ma:{$detailJpa['mount_ill_fated']}</p>
                            </div>
                        </center>
                    </div>
                ";

                $model = $relativeId = $unity = "";
                if ($product['product']['type'] === "EQUIPO") {
                    $model = $product['product']['model']['model'];
                    $relativeId = $product['product']['model']['relative_id'];
                    $unity = $product['product']['model']['unity']['name'];
                } else {
                    $model = $product['product']['model']['model'];
                    $relativeId = $product['product']['model']['relative_id'];
                    $unity = $product['product']['model']['unity']['name'];
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
                        'unity' => $unity);
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
                    '{technical}',
                    '{date_sale}',
                    '{description}',
                    '{summary}',
                    '{details}',
                ],
                [
                    str_pad($request->id, 6, "0", STR_PAD_LEFT),
                    $branch_->name,
                    gTrace::getDate('long'),
                    $request->tower['name'],
                    $request->technical['name'] . ' ' . $request->technical['lastname'],
                    $request->date_sale,
                    $request->description,
                    $sumary,
                    $details_product,
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

    public function reportDetailsByTower(Request $request)
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
            $template = file_get_contents('../storage/templates/reportDetailsByTower.html');
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
                'people.lastname as person__lastname',
            ])
                ->join('people', 'users._person', 'people.id')
                ->where('users.id', $userid)->first();

            $TowerJpa = Tower::find($request->id);

            $PhotographsByTowerJpa = PhotographsByTower::select(['id', 'description', '_creation_user', 'creation_date', '_update_user', 'update_date'])
                ->where('_tower', $TowerJpa->id)->whereNotNUll('status')
                ->orderBy('id', 'desc')
                ->get();

            $images = '';

            $count = 1;

            foreach ($PhotographsByTowerJpa as $image) {

                $userCreation = User::select([
                    'users.id as id',
                    'users.username as username',
                ])
                    ->where('users.id', $image->_creation_user)->first();

                $images .= "
                <div style='page-break-before: always;'>
                    <p><strong>{$count}) {$image->description}</strong></p>
                    <p style='margin-left:18px'><strong>Fecha:</strong> {$image->creation_date}</p>
                    <p style='margin-left:18px'><strong>Usuario:</strong> {$userCreation->username}</p>
                    <center>
                        <img src='https://almacen.fastnetperu.com.pe/api/towerimgs/{$image->id}/full' alt='-' style='object-fit: contain; object-position: center center; max-width: 650px; max-height: 700px; width: auto; height: auto;margin-top:5px;border:solid 2px rgb(22, 31, 48);padding:5px;border-radius:12px;'>
                    </center>
                </div>
                ";
                $count += 1;
            }

            $template = str_replace(
                [
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{tower_name}',
                    '{description}',
                    '{relative_id}',
                    '{latitude}',
                    '{longitude}',
                    '{ejecutive}',
                    '{images}',
                    '{summary}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $TowerJpa->name,
                    $TowerJpa->description,
                    $TowerJpa->relative_id,
                    $TowerJpa->latitude,
                    $TowerJpa->longitude,
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
