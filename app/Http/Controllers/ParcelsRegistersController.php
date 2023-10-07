<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\EntryDetail;
use App\Models\EntryProducts;
use App\Models\Models;
use App\Models\Parcel;
use App\Models\Product;
use App\Models\Response;
use App\Models\Stock;
use App\Models\ViewParcelsRegisters;
use App\Models\ViewProducts;
use App\Models\ViewUsers;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParcelsRegistersController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'parcels_registers', 'create')) {
                throw new Exception('No tienes permisos para registrar encomiendas');
            }

            if (
                !isset($request->date_send) ||
                !isset($request->date_entry) ||
                !isset($request->_business_designed) ||
                !isset($request->_business_transport) ||
                !isset($request->price_transport) ||
                !isset($request->_provider) ||
                !isset($request->_model) ||
                !isset($request->currency) ||
                !isset($request->warranty) ||
                !isset($request->mount_product) ||
                !isset($request->_type_operation)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $entryProductJpa = new EntryProducts();

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();
            $model_ = Models::select('id', 'model', 'currency', 'price_buy', 'price_sale', 'mr_revenue')->find($request->_model);

            $entryProductJpa->_user = $userid;
            if (isset($request->_client)) {
                $entryProductJpa->_entry = $request->_entry;
            }
            $entryProductJpa->_branch = $branch_->id;
            if (isset($request->_technical)) {
                $entryProductJpa->_technical = $request->_technical;
            }
            $entryProductJpa->entry_date = gTrace::getDate('mysql');
            $entryProductJpa->_type_operation = $request->_type_operation;
            if (isset($request->_tower)) {
                $entryProductJpa->_tower = $request->_tower;
            }
            if (isset($request->condition_product)) {
                $entryProductJpa->condition_product = $request->condition_product;
            }
            if (isset($request->product_status)) {
                $entryProductJpa->product_status = $request->product_status;
            }
            $entryProductJpa->type_entry = "REGISTRO ENCOMIENDA";
            $entryProductJpa->description = $request->description;
            $entryProductJpa->_creation_user = $userid;
            $entryProductJpa->creation_date = gTrace::getDate('mysql');
            $entryProductJpa->_update_user = $userid;
            $entryProductJpa->update_date = gTrace::getDate('mysql');
            $entryProductJpa->status = "1";
            $entryProductJpa->save();

            $parcelJpa = new Parcel();
            $parcelJpa->date_send = $request->date_send;
            $parcelJpa->date_entry = $request->date_entry;
            $parcelJpa->_business_designed = $request->_business_designed;
            $parcelJpa->_business_transport = $request->_business_transport;
            $parcelJpa->price_transport = $request->price_transport;
            $parcelJpa->_provider = $request->_provider;
            $parcelJpa->parcel_type = "REGISTED";

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
                    $parcelJpa->image_type = $request->image_type;
                    $parcelJpa->image_mini = base64_decode($request->image_mini);
                    $parcelJpa->image_full = base64_decode($request->image_full);
                } else {
                    $parcelJpa->image_type = null;
                    $parcelJpa->image_mini = null;
                    $parcelJpa->image_full = null;
                }
            }

            if (isset($request->num_voucher)) {
                $parcelJpa->num_voucher = $request->num_voucher;
            }

            if (isset($request->num_guia)) {
                $parcelJpa->num_guia = $request->num_guia;
            }

            if (isset($request->num_bill)) {
                $parcelJpa->num_bill = $request->num_bill;
            }

            $parcelJpa->_model = $request->_model;
            $parcelJpa->currency = $request->currency;
            $parcelJpa->warranty = $request->warranty;

            if (isset($request->description)) {
                $parcelJpa->description = $request->description;
            }

            $parcelJpa->mount_product = $request->mount_product;
            $parcelJpa->total = $request->total;

            if (isset($request->igv)) {
                $parcelJpa->igv = $request->igv;
            }

            $parcelJpa->amount = $request->amount;
            $parcelJpa->value_unity = $request->value_unity;
            $parcelJpa->price_unity = $request->price_unity;

            if (isset($request->mr_revenue)) {
                $parcelJpa->mr_revenue = $request->mr_revenue;
            }

            $parcelJpa->price_buy = $request->price_buy;
            $parcelJpa->_entry_product = $entryProductJpa->id;
            $parcelJpa->_branch = $branch_->id;
            $parcelJpa->creation_date = gTrace::getDate('mysql');
            $parcelJpa->_creation_user = $userid;
            $parcelJpa->update_date = gTrace::getDate('mysql');
            $parcelJpa->_update_user = $userid;
            $parcelJpa->status = "1";
            $parcelJpa->save();

            $message_error = "";
            if ($request->type == "EQUIPO") {
                if (!isset($request->data)) {
                    throw new Exception("Error: No deje campos vacíos");
                }

                foreach ($request->data as $product) {
                    $is_duplicate = false;
                    if (isset($product['mac']) && isset($product['serie'])) {
                        $productValidation = Product::select(['mac', 'serie'])
                            ->whereNotNull('mac')
                            ->whereNotNull('serie')
                            ->where('mac', $product['mac'])
                            ->orWhere('serie', $product['serie'])
                            ->first();
                        if ($productValidation) {
                            if ($productValidation->mac == $product['mac']) {
                                $is_duplicate = true;
                                $message_error .= "Ya existe un produto con el número MAC: " . $product['mac'];
                            }
                            if ($productValidation->serie == $product['serie']) {
                                $is_duplicate = true;
                                $message_error .= " || Ya existe un produto con el número de serie: " . $product['serie'];
                            }
                        }
                    } else {
                        if (isset($product['mac'])) {
                            $productValidation = Product::select(['mac'])
                                ->whereNotNull('mac')
                                ->where('mac', $product['mac'])
                                ->first();
                            if ($productValidation) {
                                $is_duplicate = true;
                                $message_error .= "Ya existe un produto con el número MAC: " . $product['mac'];
                            }
                        }
                        if (isset($product['serie'])) {
                            $productValidation = Product::select(['serie'])
                                ->whereNotNull('serie')
                                ->Where('serie', $product['serie'])
                                ->first();
                            if ($productValidation) {
                                $is_duplicate = true;
                                $message_error .= "Ya existe un produto con el número de serie: " . $product['serie'];
                            }
                        }
                    }

                    $productJpa = new Product();
                    $productJpa->type = $request->type;
                    $productJpa->_branch = $branch_->id;
                    $productJpa->_provider = $request->_provider;
                    $productJpa->_model = $request->_model;
                    $productJpa->relative_id = guid::short();
                    $productJpa->mac = $product['mac'];
                    $productJpa->serie = $product['serie'];
                    $productJpa->mount = "1";

                    if ($request->update_price_sale == "NEW") {
                        $productJpa->currency = $request->currency;
                        $productJpa->price_buy = $request->value_unity;
                        $productJpa->price_sale = $request->price_buy;
                        $productJpa->mr_revenue = $request->mr_revenue;
                    } else {
                        $productJpa->currency = $model_->currency;
                        $productJpa->price_buy = $model_->price_buy;
                        $productJpa->price_sale = $model_->price_sale;
                        $productJpa->mr_revenue = $model_->mr_revenue;
                    }

                    if (isset($request->num_voucher)) {
                        $productJpa->num_voucher = $request->num_voucher;
                    }
                    if (isset($request->num_guia)) {
                        $productJpa->num_guia = $request->num_guia;
                    }
                    if (isset($request->num_bill)) {
                        $productJpa->num_bill = $request->num_bill;
                    }
                    if (isset($request->warranty)) {
                        $productJpa->warranty = $request->warranty;
                    }
                    $productJpa->condition_product = $request->condition_product;
                    $productJpa->_entry_product = $entryProductJpa->id;
                    $productJpa->date_entry = $request->date_entry;
                    if (isset($request->description)) {
                        $productJpa->description = $request->description;
                    }
                    $productJpa->condition_product = "POR_ENCOMIENDA";
                    $productJpa->product_status = "NUEVO";
                    $productJpa->disponibility = "DISPONIBLE";
                    $productJpa->creation_date = gTrace::getDate('mysql');
                    $productJpa->_creation_user = $userid;
                    $productJpa->update_date = gTrace::getDate('mysql');
                    $productJpa->_update_user = $userid;
                    $productJpa->status = "1";
                    if (!$is_duplicate) {
                        $productJpa->save();
                    }

                    $entryDetailJpa = new EntryDetail();
                    $entryDetailJpa->_product = $productJpa->id;
                    $entryDetailJpa->mount_new = "1";
                    $entryDetailJpa->_entry_product = $entryProductJpa->id;
                    if (isset($product['description'])) {
                        $entryDetailJpa->description = $product['description'];
                    }
                    $entryDetailJpa->status = "1";
                    if (!$is_duplicate) {
                        $entryDetailJpa->save();
                    }
                }
            } else if ($request->type == "MATERIAL") {
                $productJpa = Product::select([
                    'id',
                    'mount',
                    'num_guia',
                    'num_bill',
                    '_model',
                    '_branch',
                ])
                    ->where('_model', $request->_model)
                    ->where('_branch', $branch_->id)
                    ->first();

                if (isset($productJpa)) {
                    $productJpa->type = $request->type;
                    $productJpa->_branch = $branch_->id;
                    $productJpa->relative_id = guid::short();
                    $productJpa->_model = $request->_model;
                    $productJpa->_provider = $request->_provider;
                    $productJpa->mount = $productJpa->mount + $request->mount_product;
                    if ($request->update_price_sale == "NEW") {
                        $productJpa->currency = $request->currency;
                        $productJpa->price_buy = $request->value_unity;
                        $productJpa->price_sale = $request->price_buy;
                        $productJpa->mr_revenue = $request->mr_revenue;
                    } else {
                        $productJpa->currency = $model_->currency;
                        $productJpa->price_buy = $model_->price_buy;
                        $productJpa->price_sale = $model_->price_sale;
                        $productJpa->mr_revenue = $model_->mr_revenue;
                    }

                    if (isset($request->num_voucher)) {
                        $productJpa->num_voucher = $request->num_voucher;
                    }
                    if (isset($request->num_guia)) {
                        $productJpa->num_guia = $request->num_guia;
                    }
                    if (isset($request->num_bill)) {
                        $productJpa->num_bill = $request->num_bill;
                    }
                    if (isset($request->warranty)) {
                        $productJpa->warranty = $request->warranty;
                    }
                    $productJpa->condition_product = "POR_ENCOMIENDA";
                    $productJpa->product_status = "NUEVO";
                    $productJpa->date_entry = $request->date_entry;
                    if (isset($request->description)) {
                        $productJpa->description = $request->description;
                    }
                    $productJpa->disponibility = "DISPONIBLE";
                    $productJpa->creation_date = gTrace::getDate('mysql');
                    $productJpa->_creation_user = $userid;
                    $productJpa->update_date = gTrace::getDate('mysql');
                    $productJpa->_update_user = $userid;
                    $productJpa->status = "1";
                    $productJpa->save();

                    $entryDetailJpa = new EntryDetail();
                    $entryDetailJpa->_product = $productJpa->id;
                    $entryDetailJpa->mount_new = $request->mount_product;
                    $entryDetailJpa->_entry_product = $entryProductJpa->id;
                    if (isset($product['description'])) {
                        $entryDetailJpa->description = $product['description'];
                    }
                    $entryDetailJpa->status = "1";
                    $entryDetailJpa->save();
                } else {
                    $productJpa = new Product();
                    $productJpa->type = $request->type;
                    $productJpa->_branch = $branch_->id;
                    $productJpa->_provider = $request->_provider;
                    $productJpa->_model = $request->_model;
                    $productJpa->relative_id = guid::short();
                    $productJpa->mount = $request->mount_product;

                    if ($request->update_price_sale == "NEW") {
                        $productJpa->currency = $request->currency;
                        $productJpa->price_buy = $request->value_unity;
                        $productJpa->price_sale = $request->price_buy;
                        $productJpa->mr_revenue = $request->mr_revenue;
                    } else {
                        $productJpa->currency = $model_->currency;
                        $productJpa->price_buy = $model_->price_buy;
                        $productJpa->price_sale = $model_->price_sale;
                        $productJpa->mr_revenue = $model_->mr_revenue;
                    }

                    if (isset($request->num_voucher)) {
                        $productJpa->num_voucher = $request->num_voucher;
                    }
                    if (isset($request->num_guia)) {
                        $productJpa->num_guia = $request->num_guia;
                    }
                    if (isset($request->num_bill)) {
                        $productJpa->num_bill = $request->num_bill;
                    }
                    if (isset($request->warranty)) {
                        $productJpa->warranty = $request->warranty;
                    }
                    $productJpa->condition_product = "POR_ENCOMIENDA";
                    $productJpa->product_status = "NUEVO";
                    $productJpa->date_entry = $request->date_entry;
                    if (isset($request->description)) {
                        $productJpa->description = $request->description;
                    }
                    $productJpa->disponibility = "DISPONIBLE";
                    $productJpa->creation_date = gTrace::getDate('mysql');
                    $productJpa->_creation_user = $userid;
                    $productJpa->update_date = gTrace::getDate('mysql');
                    $productJpa->_update_user = $userid;
                    $productJpa->status = "1";
                    $productJpa->save();

                    $entryDetailJpa = new EntryDetail();
                    $entryDetailJpa->_product = $productJpa->id;
                    $entryDetailJpa->mount_new = $request->mount_product;
                    $entryDetailJpa->_entry_product = $entryProductJpa->id;
                    if (isset($product['description'])) {
                        $entryDetailJpa->description = $product['description'];
                    }
                    $entryDetailJpa->status = "1";
                    $entryDetailJpa->save();
                }
            }

            $stock = Stock::where('_model', $request->_model)
                ->where('_branch', $branch_->id)
                ->first();
            $stock->mount_new = intval($stock->mount_new) + intval($request->mount_product);
            $stock->save();

            $response->setStatus(200);
            $response->setMessage('Producto agregado correctamente .' . $message_error);
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

    public function paginate(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'parcels_registers', 'read')) {
                throw new Exception('No tienes permisos para listar encomiedas registradas');
            }

            $query = ViewParcelsRegisters::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            if (isset($request->search['date_end']) && isset($request->search['date_start'])) {
                $query->where('date_entry', '<=', $request->search['date_end'])
                    ->where('date_entry', '>=', $request->search['date_start']);
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
                if ($column == 'model__model' || $column == '*') {
                    $q->orWhere('model__model', $type, $value);
                }
                if ($column == 'business_designed__name' || $column == '*') {
                    $q->orWhere('business_designed__name', $type, $value);
                }
                if ($column == 'business_designed__ruc' || $column == '*') {
                    $q->orWhere('business_designed__ruc', $type, $value);
                }
                if ($column == 'business_transport__name' || $column == '*') {
                    $q->orWhere('business_transport__name', $type, $value);
                }
                if ($column == 'business_transport__doc_number' || $column == '*') {
                    $q->orWhere('business_transport__doc_number', $type, $value);
                }
                if ($column == 'provider__doc_number' || $column == '*') {
                    $q->orWhere('provider__doc_number', $type, $value);
                }
                if ($column == 'provider__name' || $column == '*') {
                    $q->orWhere('provider__name', $type, $value);
                }
                if ($column == 'provider__lastname' || $column == '*') {
                    $q->orWhere('provider__lastname', $type, $value);
                }
                if ($column == 'num_voucher' || $column == '*') {
                    $q->orWhere('num_voucher', $type, $value);
                }
                if ($column == 'num_guia' || $column == '*') {
                    $q->orWhere('num_guia', $type, $value);
                }
                if ($column == 'num_bill' || $column == '*') {
                    $q->orWhere('num_bill', $type, $value);
                }
                if ($column == 'model__model' || $column == '*') {
                    $q->orWhere('model__model', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
                if ($column == 'total' || $column == '*') {
                    $q->orWhere('total', $type, $value);
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
            $response->setITotalRecords(ViewParcelsRegisters::where('branch__correlative', $branch)->count());
            $response->setData($parcels);
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

            $parcelJpa = Parcel::select([
                "parcels.image_$size as image_content",
                'parcels.image_type',
            ])->find($id);

            if (!$parcelJpa) {
                throw new Exception('No se encontraron datos');
            }
            if (!$parcelJpa->image_content) {
                throw new Exception('No existe imagen');
            }
            $content = $parcelJpa->image_content;
            $type = $parcelJpa->image_type;
            $response->setStatus(200);
        } catch (\Throwable $th) {
            $ruta = '../storage/images/factura-default.png';
            $fp = fopen($ruta, 'r');
            $datos_image = fread($fp, filesize($ruta));
            $datos_image = addslashes($datos_image);
            fclose($fp);
            $content = stripslashes($datos_image);
            $type = 'image/png';
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
            if (!gValidate::check($role->permissions, $branch, 'parcels', 'update')) {
                throw new Exception('No tienes permisos para actualizar encomiendas');
            }

            $parcelJpa = Parcel::select(['id'])->find($request->id);

            if (isset($request->date_send)) {
                $parcelJpa->date_send = $request->date_send;
            }

            if (isset($request->_business_designed)) {
                $parcelJpa->_business_designed = $request->_business_designed;
            }
            if (isset($request->_business_transport)) {
                $parcelJpa->_business_transport = $request->_business_transport;
            }
            if (isset($request->price_transport)) {
                $parcelJpa->price_transport = $request->price_transport;
            }
            if (isset($request->_provider)) {
                $parcelJpa->_provider = $request->_provider;
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
                    $parcelJpa->image_type = $request->image_type;
                    $parcelJpa->image_mini = base64_decode($request->image_mini);
                    $parcelJpa->image_full = base64_decode($request->image_full);
                } else {
                    $parcelJpa->image_type = null;
                    $parcelJpa->image_mini = null;
                    $parcelJpa->image_full = null;
                }
            }
            if (isset($request->num_voucher)) {
                $parcelJpa->num_voucher = $request->num_voucher;
            }
            if (isset($request->num_guia)) {
                $parcelJpa->num_guia = $request->num_guia;
            }
            if (isset($request->num_bill)) {
                $parcelJpa->num_bill = $request->num_bill;
            }
            if (isset($request->_model)) {
                $parcelJpa->_model = $request->_model;
            }
            if (isset($request->currency)) {
                $parcelJpa->currency = $request->currency;
            }
            if (isset($request->warranty)) {
                $parcelJpa->warranty = $request->warranty;
            }
            if (isset($request->description)) {
                $parcelJpa->description = $request->description;
            }
            if (isset($request->mount_product)) {
                $parcelJpa->mount_product = $request->mount_product;
            }
            if (isset($request->total)) {
                $parcelJpa->total = $request->total;
            }
            if (isset($request->igv)) {
                $parcelJpa->igv = $request->igv;
            }
            if (isset($request->amount)) {
                $parcelJpa->amount = $request->amount;
                // $EntryDetailJpa = EntryDetail::where('_entry_product', $parcelJpa->_entry_product)->first();
                // $product = Product::find($EntryDetailJpa->_product);
                // if($product->type == "MATERIAL"){
                //     $EntryDetailJpa->mount = $request->amount;
                //     $EntryDetailJpa->save();
                // }

            }
            if (isset($request->value_unity)) {
                $parcelJpa->value_unity = $request->value_unity;
            }
            if (isset($request->price_unity)) {
                $parcelJpa->price_unity = $request->price_unity;
            }
            if (isset($request->mr_revenue)) {
                $parcelJpa->mr_revenue = $request->mr_revenue;
            }
            if (isset($request->price_buy)) {
                $parcelJpa->price_buy = $request->price_buy;
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

    public function getProductsByParcel(Request $request, $id)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'parcels_registers', 'read')) {
                throw new Exception('No tienes permisos para ver detalles de encomiendas');
            }

            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $parcelJpa = Parcel::find($id);

            $entryDetailJpa = EntryDetail::select([
                'entry_detail.id as id',
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
                'entry_detail.mount_new as mount_new',
                'entry_detail.mount_second as mount_second',
                'entry_detail.mount_ill_fated as mount_ill_fated',
                'entry_detail.description as description',
                'entry_detail._entry_product as _entry_product',
                'entry_detail.status as status',
            ])
                ->join('products', 'entry_detail._product', 'products.id')
                ->join('models', 'products._model', 'models.id')
                ->where('entry_detail._entry_product', $parcelJpa->_entry_product)->get();

            $details = array();
            foreach ($entryDetailJpa as $detailJpa) {
                $detail = gJSON::restore($detailJpa->toArray(), '__');
                $details[] = $detail;
            }

            $response->setStatus(200);
            $response->setData($details);
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

    public function delete(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'parcels', 'delete_restore')) {
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
            if (!gValidate::check($role->permissions, $branch, 'parcels_registers', 'delete_restore')) {
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

    public function generateReport(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'parcels_registers', 'read')) {
                throw new Exception('No tienes permisos para listar encomiendas registradas');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $options->set('enable_html5_parser', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportParcelRegister.html');

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();

            $parcelsJpa = ViewParcelsRegisters::select(['*'])
                ->orderBy('date_entry', 'desc')
                ->whereNotNull('status')
                ->where('branch__correlative', $branch)
                ->where('date_entry', '<=', $request->date_end)
                ->where('date_entry', '>=', $request->date_start)
                ->get();

            $parcels = array();
            foreach ($parcelsJpa as $parcelJpa) {
                $parcel = gJSON::restore($parcelJpa->toArray(), '__');
                $parcels[] = $parcel;
            }
            $sumary = '';
            $bills = '';
            $count = 1;

            foreach ($parcels as $parcel) {
                $model = "
                <div>
                    <p style='font-size: 9px;'><strong>{$parcel['model']['model']}</strong></p>
                    <img class='img-fluid img-thumbnail'
                        src='https://almacen.fastnetperu.com.pe/api/model/{$parcel['model']['relative_id']}/mini' style='background-color: #38414a;object-fit: cover; object-position: center center; cursor: pointer; height:50px;margin:0px;'>
                </div>
                ";

                $reception = "
                <div style='font-size: 9px;'>
                    <p><center><strong>{$parcel['business_designed']['name']}</strong></center></p>
                    <hr>
                    <p>Envio: <strong>{$parcel['date_send']}</strong></p>
                    <p>Recojo: <strong>{$parcel['date_entry']}</strong></p>
                </div>
                ";

                $trasport = "
                <div style='font-size: 9px;'>
                    <p><center><strong>{$parcel['business_transport']['name']}</strong></center></p>
                    <p>DOC: <strong>{$parcel['business_transport']['doc_number']}</strong></p>
                    <p>Nº Comprobante: <strong>{$parcel['num_voucher']}</strong></p>
                    <p>Precio: <strong>S/.{$parcel['price_transport']}</strong></p>
                </div>
                ";

                $provider = "
                <div style='font-size: 9px;'>
                    <p><center><strong>{$parcel['provider']['name']}</strong></center></p>
                    <p>DOC: <strong>{$parcel['provider']['doc_number']}</strong></p>
                    <p>Nº GUIA: <strong>{$parcel['num_guia']}</strong></p>
                    <p>Nº Factura: <strong>{$parcel['num_bill']}</strong></p>
                    <hr>
                    <center>
                        <p><strong>{$parcel['model']['unity']['name']}</strong></p>
                        <p><strong>{$parcel['mount_product']}</strong></p>
                    </center>
                </div>
                ";

                $price = "
                <div style='font-size: 9px;'>
                    <p>Total: <strong>{$parcel['total']}</strong></p>
                    <p>Importe: <strong>{$parcel['amount']}</strong></p>
                    <p>IGV: <strong>{$parcel['igv']}</strong></p>
                    <p>V. Unitario: <strong>{$parcel['value_unity']}</strong></p>
                    <p>P. Unitario: <strong>{$parcel['price_unity']}</strong></p>
                </div>
                ";

                $sumary .= "
                <tr>
                    <td><center >{$count}</center></td>
                    <td><center >{$model}</center></td>
                    <td><center >{$reception}</center></td>
                    <td><center >{$trasport}</center></td>
                    <td><center >{$provider}</center></td>
                    <td><center >{$price}</center></td>
                </tr>
                ";

                $bills .= "
                <div style='page-break-before: always;'>
                    <p><strong>{$count}) {$parcel['model']['model']}</strong></p>
                    <center>
                        <img
                            src='https://almacen.fastnetperu.com.pe/api/parcelimg/{$parcel['id']}/full' alt='-' style='background-color: #38414a;object-fit: cover; object-position: center center; cursor: pointer; height:500px;'>

                    </center>
                </div>
                ";

                $count += 1;
            }

            $template = str_replace(
                [
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{user_generate}',
                    '{date_start_str}',
                    '{date_end_str}',
                    '{summary}',
                    '{bills}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $user->person__name . ' ' . $user->person__lastname,
                    $request->date_start_str,
                    $request->date_end_str,
                    $sumary,
                    $bills,
                ],
                $template
            );
            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('Reporte de registro de encomiendas.pdf');
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

    public function generateReportByParcel(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'parcels_registers', 'read')) {
                throw new Exception('No tienes permisos para listar encomiendas registradas');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $options->set('enable_html5_parser', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportParcelRegisterByParcel.html');

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();

            
            $productJpa = ViewProducts::select(['*'])
            ->orderBy('id', 'desc')->where('num_guia', $request->num_guia)->where('model__id', $request->model['id'])->get();
            
            $sumary = '';
            $user_creation = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $request->creation_user)->first();
            $products = array();

            $count = 1;

            foreach ($productJpa as $product_) {
                $product = gJSON::restore($product_->toArray(), '__');

                $datos = "
                <div>
                    <p>Mac: <strong>{$product['mac']}</strong></p>
                    <p>Serie: <strong>{$product['serie']}</strong></p>
                </div>
                ";
                $estado = "
                <div>
                    <p>Estado: <strong>{$product['product_status']}</strong></p>
                    <p>Disponibilidad: <strong>{$product['disponibility']}</strong></p>
                </div>
                ";
                $sucursal = "
                <div>
                    <strong>{$product['branch']['name']}</strong>
                </div>
                ";
                $sumary.="
                <tr>
                    <td>{$count}</td>
                    <td>{$datos}</td>
                    <td>{$estado}</td>
                    <td>{$sucursal}</td>
                </tr>
                ";
                $count +=1;
                $products[] = $product;
            }


            $parts = explode(" ", $request->date_entry);
            $date = $parts[0];
            $dateFormater = date("Y-m-d", strtotime($date));

            $template = str_replace(
                [
                    '{id_parcel}',
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{user_generate}',
                    '{date_entry}',
                    '{num_bill}',
                    '{num_guia}',
                    '{user_register}',
                    '{model}',
                    '{category}',
                    '{summary}',
                ],
                [
                    str_pad($request->id, 6, "0", STR_PAD_LEFT),
                    $branch_->name,
                    gTrace::getDate('long'),
                    $user->person__name . ' ' . $user->person__lastname,
                    $dateFormater,
                    $request->num_bill,
                    $request->num_guia,
                    $user_creation->person__name.' '.$user_creation->person__lastname,
                    $request->model['model'],
                    $request->model['category']['category'],
                    $sumary,
                ],
                $template
            );
            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('Detalles de encomienda - '.str_pad($request->id, 6, "0", STR_PAD_LEFT).'.pdf');

            // $response = new Response();
            // $response->setStatus(200);
            // $response->setMessage('OPeracion correcta');
            // $response->setData($products);
            // return response(
            //     $response->toArray(),
            //     $response->getStatus()
            // );

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
