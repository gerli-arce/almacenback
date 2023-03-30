<?php

namespace App\Http\Controllers;

use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\gLibraries\gJson;
use App\Models\Branch;
use App\Models\EntryDetail;
use App\Models\EntryProducts;
use App\Models\ViewParcels;
use App\Models\Models;
use App\Models\ViewProducts;
use App\Models\Parcel;
use App\Models\Product;
use App\Models\Response;
use App\Models\Stock;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ParcelsController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'parcels', 'create')) {
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
                !isset($request->total) ||
                !isset($request->amount) ||
                !isset($request->value_unity) ||
                !isset($request->price_buy) ||
                !isset($request->_type_operation) ||
                !isset($request->total)
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
                $parcelJpa->num_guia = $request->num_bill;
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
            $parcelJpa->status = "1";
            $parcelJpa->save();

            if ($request->type == "EQUIPO") {
                if (!isset($request->data)) {
                    throw new Exception("Error: No deje campos vacíos");
                }
                foreach ($request->data as $product) {
                    if (isset($product['mac']) && isset($product['serie'])) {
                        $productValidation = Product::select(['mac', 'serie'])
                            ->whereNotNull('mac')
                            ->whereNotNull('serie')
                            ->where('mac', $product['mac'])
                            ->orWhere('serie', $product['serie'])
                            ->first();
                        if ($productValidation) {
                            if ($productValidation->mac == $product['mac']) {
                                throw new Exception("Ya existe un produto con el número MAC: " . $product['mac']);
                            }
                            if ($productValidation->serie == $product['serie']) {
                                throw new Exception("Ya existe un produto con el número de serie: " . $product['serie']);
                            }
                        }
                    } else {
                        if (isset($product['mac'])) {
                            $productValidation = Product::select(['mac', 'serie'])
                                ->whereNotNull('mac')
                                ->where('mac', $product['mac'])
                                ->first();
                            if ($productValidation) {
                                throw new Exception("Ya existe un produto con el número MAC: " . $product['mac']);
                            }
                        }
                        if (isset($product['serie'])) {
                            $productValidation = Product::select(['mac', 'serie'])
                                ->whereNotNull('serie')
                                ->orWhere('serie', $product['serie'])
                                ->first();
                            if ($productValidation) {
                                throw new Exception("Ya existe un produto con el número de serie: " . $product['serie']);
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
                    $productJpa->save();

                    $entryDetailJpa = new EntryDetail();
                    $entryDetailJpa->_product = $productJpa->id;
                    $entryDetailJpa->mount = "1";
                    $entryDetailJpa->_entry_product = $entryProductJpa->id;
                    if (isset($product['description'])) {
                        $entryDetailJpa->description = $product['description'];
                    }
                    $entryDetailJpa->status = "1";
                    $entryDetailJpa->save();
                }
            } else if ($request->type == "MATERIAL") {
                $productJpa = Product::select([
                    'id',
                    'mount',
                    'num_gia',
                    'num_bill',
                    '_model',
                    '_category',
                    '_brand',
                    '_branch',
                ])
                    ->where('_model', $request->_model)
                    ->where('_category', $request->_category)
                    ->where('_brand', $request->_brand)
                    ->where('_branch', $branch_->id)
                    ->first();

                if (isset($productJpa)) {
                    $mount_old = $productJpa->mount;
                    $mount_new = $mount_old + $request->mount_product;

                    $productJpa->type = $request->type;
                    $productJpa->_branch = $branch_->id;
                    $productJpa->relative_id = guid::short();
                    $productJpa->_model = $request->_model;
                    $productJpa->_provider = $request->_provider;
                    $productJpa->_unity = $request->_unity;
                    $productJpa->mount = $mount_new;
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
                    if (isset($request->num_gia)) {
                        $productJpa->num_gia = $request->num_gia;
                    }
                    if (isset($request->num_bill)) {
                        $productJpa->num_bill = $request->num_bill;
                    }
                    if (isset($request->warranty)) {
                        $productJpa->warranty = $request->warranty;
                    }
                    $productJpa->condition_product = $request->condition_product;
                    $productJpa->product_status = $request->product_status;
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
                    $entryDetailJpa->mount = $request->mount_product;
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
                    if (isset($request->num_gia)) {
                        $productJpa->num_gia = $request->num_gia;
                    }
                    if (isset($request->num_bill)) {
                        $productJpa->num_bill = $request->num_bill;
                    }
                    if (isset($request->warranty)) {
                        $productJpa->warranty = $request->warranty;
                    }
                    $productJpa->condition_product = $request->condition_product;
                    $productJpa->product_status = $request->product_status;
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
                    $entryDetailJpa->mount = $request->mount_product;
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
            $stock->mount = intval($stock->mount) + intval($request->mount_product);
            $stock->save();

            $response->setStatus(200);
            $response->setMessage('Producto agregado correctamente');
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

    public function paginate(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'parcel', 'read')) {
                throw new Exception('No tienes permisos para listar encomiedas');
            }

            $query = ViewParcels::select(['*'])
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
            $response->setITotalRecords(ViewParcels::where('branch__correlative', $branch)->count());
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

            ])->find($id)

            if (!$parcelJpa) {
                throw new Exception('No se encontraron datos');
            }
            if (!$parcelJpa->image_content) {
                throw new Exception('No existe imagen');
            }
            $content = $parcelJpa->image_content;
            $type = $parcelJpa->image_type;
            $response->setStatus(200);
        } catch (\Throwable$th) {
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

}
