<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\DetailSale;
use App\Models\DetailsParcel;
use App\Models\EntryDetail;
use App\Models\EntryProducts;
use App\Models\Models;
use App\Models\Parcel;
use App\Models\Product;
use App\Models\Response;
use App\Models\SalesProducts;
use App\Models\Stock;
use App\Models\ViewParcelsCreated;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParcelsCreatedController extends Controller
{

    public function store(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'create')) {
                throw new Exception('No tienes permisos para crear encomiendas');
            }

            if (
                !isset($request->date_send) ||
                !isset($request->_branch_destination) ||
                !isset($request->_business_transport) ||
                !isset($request->price_transport) ||
                !isset($request->_type_operation) ||
                !isset($request->_responsible_pickup)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            // DATOS DE ENCOMIENDA
            $parcelJpa = new Parcel();
            $parcelJpa->date_send = $request->date_send;
            $parcelJpa->_branch_destination = $request->_branch_destination;
            $parcelJpa->_business_transport = $request->_business_transport;
            $parcelJpa->_responsible_pickup = $request->_responsible_pickup;
            $parcelJpa->price_transport = $request->price_transport;
            $parcelJpa->parcel_type = "GENERATED";
            $parcelJpa->parcel_status = "ENVIADO";
            $parcelJpa->property = "SEND";

            if (isset($request->description)) {
                $parcelJpa->description = $request->description;
            }

            // REGISTRO DE SALIDA DE PRODUCTOS
            $salesProduct = new SalesProducts();
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_type_operation = $request->_type_operation;
            $salesProduct->date_sale = $request->date_send;
            $salesProduct->status_sale = "PENDIENG";
            $salesProduct->_issue_user = $userid;

            if (isset($request->description)) {
                $salesProduct->description = $request->description;
            }

            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            $parcelJpa->_sale_product = $salesProduct->id;
            $parcelJpa->_branch_send = $branch_->id;
            $parcelJpa->_branch = $branch_->id;
            $parcelJpa->creation_date = gTrace::getDate('mysql');
            $parcelJpa->_creation_user = $userid;
            $parcelJpa->update_date = gTrace::getDate('mysql');
            $parcelJpa->_update_user = $userid;
            $parcelJpa->status = "1";
            $parcelJpa->save();

            if (isset($request->products)) {
                foreach ($request->products as $product) {
                    $productJpa = Product::find($product['product']['id']);
                    if ($product['product']['type'] == "MATERIAL") {
                        $detailsParcelJpa = new DetailsParcel();
                        $detailsParcelJpa->_product = $productJpa->id;
                        $detailsParcelJpa->_parcel = $parcelJpa->id;
                        $detailsParcelJpa->mount = $product['mount'];
                        $detailsParcelJpa->status = "ENVIANDO";
                        $detailsParcelJpa->save();

                        $mount = $productJpa->mount - $product['mount'];
                        $productJpa->mount = $mount;
                        $productJpa->save();

                        $stock = Stock::where('_model', $productJpa->_model)
                            ->where('_branch', $branch_->id)
                            ->first();

                        $stock->mount = $mount;
                        $stock->save();

                    } else {
                        $detailsParcelJpa = new DetailsParcel();
                        $detailsParcelJpa->_product = $productJpa->id;
                        $detailsParcelJpa->_parcel = $parcelJpa->id;
                        $detailsParcelJpa->mount = $product['mount'];
                        $detailsParcelJpa->status = "ENVIANDO";
                        $detailsParcelJpa->save();
                        $productJpa->disponibility = "EN ENCOMIENDA";
                        $productJpa->save();

                        $stock = Stock::where('_model', $productJpa->_model)
                            ->where('_branch', $branch_->id)
                            ->first();

                        $stock->mount = $stock->mount - 1;
                        $stock->save();
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
            $response->setMessage('Encomienda creada correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'update')) {
                throw new Exception('No tienes permisos para actualizar encomiendas');
            }

            $parcelJpa = Parcel::select(['id'])->find($request->id);

            if (isset($request->date_send)) {
                $parcelJpa->date_send = $request->date_send;
            }

            if (isset($request->_branch_destination)) {
                $parcelJpa->_branch_destination = $request->_branch_destination;
            }

            if (isset($request->_responsible_pickup)) {
                $parcelJpa->_responsible_pickup = $request->_responsible_pickup;
            }

            if (isset($request->_business_transport)) {
                $parcelJpa->_business_transport = $request->_business_transport;
            }

            if (isset($request->price_transport)) {
                $parcelJpa->price_transport = $request->price_transport;
            }

            if (isset($request->description)) {
                $parcelJpa->description = $request->description;
            }

            if (isset($request->_branch)) {
                $parcelJpa->_branch = $branch_->id;
            }

            $parcelJpa->update_date = gTrace::getDate('mysql');
            $parcelJpa->_update_user = $userid;

            if (gValidate::check($role->permissions, $branch, 'parcels', 'change_status')) {
                if (isset($request->status)) {
                    $parcelJpa->status = $request->status;
                }
            }

            $parcelJpa->save();

            $response->setStatus(200);
            $response->setMessage('La encomienda ha sido actualizado correctamente');
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

            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'read')) {
                throw new Exception('No tienes permisos para listar encomiedas creadas');
            }

            $query = ViewParcelsCreated::select(['*'])
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
                if ($column == 'date_send' || $column == '*') {
                    $q->orWhere('date_send', $type, $value);
                }
                if ($column == 'date_entry' || $column == '*') {
                    $q->orWhere('date_entry', $type, $value);
                }
                if ($column == 'branch_send__name' || $column == '*') {
                    $q->orWhere('branch_send__name', $type, $value);
                }
                if ($column == 'branch_destination__name' || $column == '*') {
                    $q->orWhere('branch_destination__name', $type, $value);
                }
                if ($column == 'business_transport__name' || $column == '*') {
                    $q->orWhere('business_transport__name', $type, $value);
                }
                if ($column == 'responsible_pickup__doc_number' || $column == '*') {
                    $q->orWhere('responsible_pickup__doc_number', $type, $value);
                }
                if ($column == 'responsible_pickup__name' || $column == '*') {
                    $q->orWhere('responsible_pickup__name', $type, $value);
                }
                if ($column == 'responsible_pickup__lastname' || $column == '*') {
                    $q->orWhere('responsible_pickup__lastname', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }

            })->where('branch__correlative', $branch);

            $iTotalDisplayRecords = $query->count();
            $parcelsJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $parcels = array();
            foreach ($parcelsJpa as $parcelJpa) {
                $parcel = gJSON::restore($parcelJpa->toArray(), '__');
                $parcels[] = $parcel;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewParcelsCreated::where('branch__correlative', $branch)->count());
            $response->setData($parcels);
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
