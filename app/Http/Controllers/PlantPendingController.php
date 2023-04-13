<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\DetailsParcel;
use App\Models\EntryDetail;
use App\Models\EntryProducts;
use App\Models\Plant;
use App\Models\SalesProducts;
use App\Models\DetailSale;
use App\Models\Product;
use App\Models\Response;
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
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            $salesProduct->_technical = $request->_technical;
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_plant = $request->id;
            $salesProduct->_type_operation = $request->_type_operation;
            $salesProduct->type_intallation = "PLANTA";
            $salesProduct->date_sale = $request->date_sale;
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

                    if ($product['product']['type'] == "MATERIAL") {
                        $stock = Stock::where('_model', $productJpa->_model)
                            ->where('_branch', $branch_->id)
                            ->first();
                        $stock->mount = intval($stock->mount) - $product['mount'];
                        $stock->save();
                    } else {
                        $productJpa->disponibility = "PLANTA";
                        $stock = Stock::where('_model', $productJpa->_model)
                            ->where('_branch', $branch_->id)
                            ->first();
                        $stock->mount = intval($stock->mount) - 1;
                        $stock->save();
                        $productJpa->save();
                    }

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
                'sales_products.description as description',
            ])
            ->join('people as tech','sales_products._technical', 'tech.id')
            ->join('branches','sales_products._branch', 'branches.id')
            ->where('_plant', $id)->first();

            if(!$saleProductJpa){
                throw new Exception('No ay registros');
            }

            $saleProduct = gJSON::restore($saleProductJpa->toArray(), '__');

            // $detailSaleJpa = DetailSale::select([
            //     'detail_sales.id as id',
            //     'products.id AS product__id',
            //     'products.type AS product__type',
            //     'models.id AS product__model__id',
            //     'models.model AS product__model__model',
            //     'models.relative_id AS product__model__relative_id',
            //     'products.relative_id AS product__relative_id',
            //     'products.mac AS product__mac',
            //     'products.serie AS product__serie',
            //     'products.price_sale AS product__price_sale',
            //     'products.currency AS product__currency',
            //     'products.num_guia AS product__num_guia',
            //     'products.condition_product AS product__condition_product',
            //     'products.disponibility AS product__disponibility',
            //     'products.product_status AS product__product_status',
            //     'sales_products.',
            //     'branches.id AS sale_product__branch__id',
            //     'branches.name AS sale_product__branch__name',
            //     'branches.correlative AS sale_product__branch__correlative',
            //     'detail_sales.mount as mount',
            //     'detail_sales.description as description',
            //     'detail_sales._sales_product as _sales_product',
            //     'detail_sales.status as status',
            
            // ])
            //     ->join('products', 'detail_sales._product', 'products.id')
            //     ->join('models', 'products._model', 'models.id')
            //     ->join('sales_products', 'detail_sales._sales_product', 'sales_products.id')
            //     ->join('branches', 'sales_products._branch', 'branches.id')
            //     ->whereNotNull('detail_sales.status')
            //     ->where('_sales_product', $id)
            //     ->get();

            // $details = array();
            // foreach ($detailSaleJpa as $detailJpa) {
            //     $detail = gJSON::restore($detailJpa->toArray(), '__');
            //     $details[] = $detail;
            // }

            // $InstallationJpa = viewInstallations::find($id);

            // $installJpa = gJSON::restore($InstallationJpa->toArray(), '__');
            // $installJpa['products'] = $details;

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($saleProduct);
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

    public function getParcelsByPerson(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'read')) {
                throw new Exception('No tienes permisos para listar encomiendas');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $parcelJpa = Parcel::select([
                'parcels.id as id',
                'parcels.date_send as date_send',
                'parcels.date_entry as date_entry',
                'parcels._business_transport as _business_transport',
                'transport.id as business_transport__id',
                'transport.name as business_transport__name',
                'parcels._branch_send as _branch_send',
                'br_send.id as branch_send__id',
                'br_send.name as branch_send__name',
                'parcels._branch_destination as _branch_destination',
                'br_des.id as branch_destination__id',
                'br_des.name as branch_destination__name',
                'parcels.price_transport as price_transport',
                'parcels._responsible_pickup as _responsible_pickup',
                'parcels.parcel_type as parcel_type',
                'parcels.parcel_status as parcel_status',
                'parcels.description as description',
                'parcels._branch as _branch',
                'parcels.creation_date as creation_date',
                'parcels._creation_user as _creation_user',
                'parcels.update_date as update_date',
                'parcels._update_user as _update_user',
                'parcels.status as status',
            ])
                ->join('branches as br_send', 'parcels._branch_send', 'br_send.id')
                ->join('branches as br_des', 'parcels._branch_destination', 'br_des.id')
                ->join('transport', 'parcels._business_transport', 'transport.id')
                ->where('_responsible_pickup', $request->id)
                ->where('_branch_destination', $branch_->id)
                ->where('parcels.parcel_status', '!=', 'ENTREGADO')
                ->orderBy('parcels.id', 'desc')
                ->get();

            if (!$parcelJpa) {
                throw new Exception('Usted no tiene encomiendas por recibir');
            }

            $parcels = [];
            foreach ($parcelJpa as $parcel) {
                $parcel = gJSON::restore($parcel->toArray(), '__');
                $detailsParcelJpa = DetailsParcel::select(
                    'details_parcel.id',
                    'details_parcel._parcel',
                    'details_parcel.mount',
                    'products.id as product__id',
                    'products.type as product__type',
                    'models.id as product__model__id',
                    'models.model as product__model__model',
                    'models.relative_id as product__model__relative_id',
                    'details_parcel.description',
                    'details_parcel.status'
                )
                    ->join('products', 'details_parcel._product', 'products.id')
                    ->join('models', 'products._model', 'models.id')
                    ->where('_parcel', $parcel['id'])->get();
                $details = [];
                foreach ($detailsParcelJpa as $detail) {
                    $details[] = gJSON::restore($detail->toArray(), '__');
                }
                $parcel['details'] = $details;
                $parcels[] = $parcel;
            }

            $response->setStatus(200);
            $response->setData($parcels);
            $response->setMessage('Encomiendas listadas correctamente');
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

    public function getParcelByPerson(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'read')) {
                throw new Exception('No tienes permisos para listar encomiendas');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $parcelJpa = Parcel::select([
                'parcels.id as id',
                'parcels.date_send as date_send',
                'parcels.date_entry as date_entry',
                'parcels._business_transport as _business_transport',
                'transport.id as business_transport__id',
                'transport.name as business_transport__name',
                'parcels._branch_send as _branch_send',
                'br_send.id as branch_send__id',
                'br_send.name as branch_send__name',
                'parcels._branch_destination as _branch_destination',
                'br_des.id as branch_destination__id',
                'br_des.name as branch_destination__name',
                'parcels.price_transport as price_transport',
                'parcels._responsible_pickup as _responsible_pickup',
                'parcels.parcel_type as parcel_type',
                'parcels.parcel_status as parcel_status',
                'parcels.description as description',
                'parcels._branch as _branch',
                'parcels.creation_date as creation_date',
                'parcels._creation_user as _creation_user',
                'parcels.update_date as update_date',
                'parcels._update_user as _update_user',
                'parcels.status as status',
            ])
                ->join('branches as br_send', 'parcels._branch_send', 'br_send.id')
                ->join('branches as br_des', 'parcels._branch_destination', 'br_des.id')
                ->join('transport', 'parcels._business_transport', 'transport.id')
                ->find($request->id);

            $parcel = gJSON::restore($parcelJpa->toArray(), '__');

            $detailsParcelJpa = DetailsParcel::select(
                'details_parcel.id',
                'details_parcel._parcel',
                'details_parcel.mount',
                'products.id as product__id',
                'products.type as product__type',
                'models.id as product__model__id',
                'models.model as product__model__model',
                'models.relative_id as product__model__relative_id',
                'details_parcel.description',
                'details_parcel.status'
            )
                ->join('products', 'details_parcel._product', 'products.id')
                ->join('models', 'products._model', 'models.id')
                ->where('_parcel', $parcel['id'])->get();

            $details = [];
            foreach ($detailsParcelJpa as $detail) {
                $details[] = gJSON::restore($detail->toArray(), '__');
            }
            $parcel['details'] = $details;

            $response->setStatus(200);
            $response->setData($parcel);
            $response->setMessage('Encomienda listadas correctamente');
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

    public function confirmArrival(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'read')) {
                throw new Exception('No tienes permisos para listar encomiendas');
            }

            if (
                !isset($request->id) ||
                !isset($request->type_operation)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $parcelJpa = Parcel::find($request->id);
            $parcelJpa->date_entry = gTrace::getDate('mysql');
            $parcelJpa->parcel_status = "ENTREGADO";

            $entryProductJpa = new EntryProducts();
            $entryProductJpa->_user = $userid;
            $entryProductJpa->_branch = $branch_->id;
            $entryProductJpa->entry_date = gTrace::getDate('mysql');
            $entryProductJpa->_type_operation = $request->type_operation;
            $entryProductJpa->status = "1";
            $entryProductJpa->save();
            $parcelJpa->_entry_product = $entryProductJpa->id;

            $detailsParcelJpa = DetailsParcel::where('_parcel', $request->id)->get();

            foreach ($detailsParcelJpa as $detailParcel) {
                $EntryDetailJpa = new EntryDetail();
                $EntryDetailJpa->_product = $detailParcel['_product'];
                $EntryDetailJpa->mount = $detailParcel['mount'];
                $EntryDetailJpa->_entry_product = $entryProductJpa->id;
                $EntryDetailJpa->save();

                $productJpa = Product::find($detailParcel['_product']);
                if ($productJpa->type == "EQUIPO") {
                    $productJpa->disponibility = 'DISPONIBLE';
                    $productJpa->condition_product = "POR_ENCOMIENDA";
                    $productJpa->_branch = $branch_->id;
                    $productJpa->save();
                } else {
                    $productJpa_new = Product::select([
                        'id',
                        'mount',
                        'num_guia',
                        'num_bill',
                        '_model',
                        '_branch',
                    ])
                        ->where('_model', $productJpa->_model)
                        ->where('_branch', $branch_->id)
                        ->first();

                    if (isset($productJpa_new)) {
                        $mount_old = $productJpa_new->mount;
                        $mount_new = $mount_old + $detailParcel['mount'];

                        $productJpa_new->_provider = "2037";
                        $productJpa_new->mount = $mount_new;

                        $productJpa_new->creation_date = gTrace::getDate('mysql');
                        $productJpa_new->_creation_user = $userid;
                        $productJpa_new->update_date = gTrace::getDate('mysql');
                        $productJpa_new->_update_user = $userid;
                        $productJpa_new->status = "1";
                        $productJpa_new->save();

                        $stock = Stock::where('_model', $productJpa->_model)
                            ->where('_branch', $branch_->id)
                            ->first();

                        $stock->mount = intval($stock->mount) + intval($detailParcel['mount']);
                        $stock->save();

                    } else {
                        $productJpa_new = new Product();
                        $productJpa_new->type = $productJpa->type;
                        $productJpa_new->_branch = $branch_->id;
                        $productJpa_new->relative_id = guid::short();
                        $productJpa_new->_provider = "2037";
                        $productJpa_new->_model = $productJpa->_model;
                        $productJpa_new->mount = $detailParcel['mount'];
                        $productJpa_new->currency = $productJpa->currency;
                        $productJpa_new->price_buy = $productJpa->price_buy;
                        $productJpa_new->price_sale = $productJpa->price_sale;

                        if (isset($productJpa->warranty)) {
                            $productJpa_new->warranty = $productJpa->warranty;
                        }
                        $productJpa_new->date_entry = $productJpa->date_entry;
                        $productJpa_new->_entry_product = $entryProductJpa->id;
                        $productJpa_new->condition_product = $productJpa->condition_product;
                        $productJpa_new->product_status = $productJpa->product_status;
                        $productJpa_new->disponibility = $productJpa->disponibility;
                        if (isset($productJpa->description)) {
                            $productJpa_new->description = $productJpa->description;
                        }
                        $productJpa_new->creation_date = gTrace::getDate('mysql');
                        $productJpa_new->_creation_user = $userid;
                        $productJpa_new->update_date = gTrace::getDate('mysql');
                        $productJpa_new->_update_user = $userid;
                        $productJpa_new->status = "1";
                        $productJpa_new->save();

                        $stock = Stock::where('_model', $productJpa->_model)
                            ->where('_branch', $branch_->id)
                            ->first();
                        $stock->mount = intval($stock->mount) + intval($detailParcel['mount']);
                        $stock->save();
                    }
                }

            }

            $parcel_newJpa = new Parcel();
            $parcel_newJpa->date_send = $parcelJpa->date_send;
            $parcel_newJpa->date_entry = gTrace::getDate('mysql');
            $parcel_newJpa->_branch_send = $parcelJpa->_branch_send;
            $parcel_newJpa->_branch_destination = $parcelJpa->_branch_destination;
            $parcel_newJpa->_business_transport = $parcelJpa->_business_transport;
            $parcel_newJpa->price_transport = $parcelJpa->price_transport;
            $parcel_newJpa->_responsible_pickup = $parcelJpa->_responsible_pickup;
            $parcel_newJpa->parcel_type = "GENERATED";
            $parcel_newJpa->parcel_status = "ENTREGADO";
            $parcel_newJpa->_branch = $branch_->id;
            $parcel_newJpa->description = $parcelJpa->description;
            $parcel_newJpa->_entry_product = $entryProductJpa->id;
            $parcel_newJpa->property = "RECEIVED";
            $parcel_newJpa->creation_date = gTrace::getDate('mysql');
            $parcel_newJpa->_creation_user = $userid;
            $parcel_newJpa->update_date = gTrace::getDate('mysql');
            $parcel_newJpa->_update_user = $userid;
            $parcel_newJpa->status = "1";
            $parcel_newJpa->save();

            $parcelJpa->save();

            $response->setStatus(200);
            $response->setMessage('Encomiendas listadas correctamente');
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
