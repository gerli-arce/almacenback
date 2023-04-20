<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\DetailSale;
use App\Models\Plant;
use App\Models\Product;
use App\Models\Response;
use App\Models\SalesProducts;
use App\Models\Stock;
use App\Models\ViewPlant;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        } catch (\Throwable$th) {
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
                $plantValidate = Plant::where('name', $request->name)->first();
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
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para listar proyectos de planta');
            }

            $query = ViewPlant::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

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
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . $th->getLine());
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
                !isset($request->_plant)
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
                    $stock = Stock::where('_model', $productJpa->_model)
                        ->where('_branch', $branch_->id)
                        ->first();
                    if ($product['product']['type'] == "MATERIAL") {
                        $productJpa->mount = intval($productJpa->mount) - $product['mount'];
                        $stock->mount_new = $productJpa->mount;
                    } else {
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

            // [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            // if ($status != 200) {
            //     throw new Exception($message);
            // }

            // if (!gValidate::check($role->permissions, $branch, 'installations_pending', 'read')) {
            //     throw new Exception('No tienes permisos para listar las instataciónes pendientes');
            // }

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
                ->where('_plant', $id)->get();

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
                        $detailSale->description = $product['description'];
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

                $stock = Stock::where('_model', $productJpa->_model)
                    ->where('_branch', $branch_->id)
                    ->first();

                if ($productJpa->type == "MATERIAL") {
                    $productJpa->mount = intval($productJpa->mount) + intval($detail['mount']);
                    $stock->mount_new = $productJpa->mount;
                } else {
                    $productJpa->disponibility = 'DISPONIBLE';
                    $productJpa->condition_product = "REGRESO DE PLANTA";

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
                $productJpa->mount = intval($productJpa->mount) + $request->mount;
                $stock = Stock::where('_model', $productJpa->_model)
                    ->where('_branch', $branch_->id)
                    ->first();
                $stock->mount_new = $productJpa->mount;
                $stock->save();
            } else if ($productJpa->type == "EQUIPO") {
                $productJpa->disponibility = "DISPONIBLE";
                $stock = Stock::where('_model', $productJpa->_model)
                    ->where('_branch', $branch_->id)
                    ->first();
                if ($productJpa->product_status == "NUEVO") {
                    $stock->mount_new = intval($stock->mount_new) + 1;
                } else if ($productJpa->product_status == "SEMINUEVO") {
                    $stock->mount_second = intval($stock->mount_second) + 1;
                }
                $stock->save();
            }

            $detailSale->save();
            $salesProduct->save();
            $productJpa->save();

            $response->setStatus(200);
            $response->setMessage('Liquidación atualizada correctamente');
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
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
            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'delete_restore')) {
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
            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'delete_restore')) {
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
