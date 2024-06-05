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
use App\Models\Response;
use App\Models\SalesProducts;
use App\Models\Stock;
use App\Models\ViewProducts;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductsController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'products', 'create')) {
                throw new Exception('No tienes permisos para crear productos');
            }

            if (
                !isset($request->type) ||
                !isset($request->_brand) ||
                !isset($request->_category) ||
                !isset($request->_provider) ||
                !isset($request->_model) ||
                !isset($request->currency) ||
                !isset($request->price_buy) ||
                !isset($request->price_sale) ||
                !isset($request->disponibility) ||
                !isset($request->product_status) ||
                !isset($request->condition_product) ||
                !isset($request->date_entry)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }
            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $entryProduct = new EntryProducts();
            $entryProduct->_user = $userid;
            $entryProduct->_branch = $branch_->id;
            $entryProduct->type_entry = "REGISTRO";
            $entryProduct->entry_date = gTrace::getDate('mysql');
            $entryProduct->_type_operation = $request->_type_operation;
            $entryProduct->description = $request->description;
            $entryProduct->status = "1";
            $entryProduct->_creation_user = $userid;
            $entryProduct->creation_date = gTrace::getDate('mysql');
            $entryProduct->_update_user = $userid;
            $entryProduct->update_date = gTrace::getDate('mysql');
            $entryProduct->save();
            $message_error = '';
            $exist_diplicates = false;
            if ($request->type == "EQUIPO") {
                if (!isset($request->data)) {
                    throw new Exception("Error: No deje campos vacíos");
                }
                foreach ($request->data as $product) {
                    $reppet_product = false;

                    if (isset($product['mac']) && isset($product['serie'])) {
                        $productValidation = Product::select(['mac', 'serie'])
                            ->whereNotNull('mac')
                            ->whereNotNull('serie')
                            ->where('mac', $product['mac'])
                            ->orWhere('serie', $product['serie'])
                            ->first();
                        if ($productValidation) {
                            if ($productValidation->mac == $product['mac']) {
                                $message_error .= "|| Ya existe un produto con el número MAC: " . $product['mac'];
                                $reppet_product = true;
                                $exist_diplicates = true;
                            }
                            if ($productValidation->serie == $product['serie']) {
                                $message_error .= "Ya existe un produto con el número de serie: " . $product['serie'];
                                $reppet_product = true;
                                $exist_diplicates = true;
                            }
                        }
                    } else {
                        if (isset($product['mac'])) {
                            $productValidation = Product::select(['mac'])
                                ->whereNotNull('mac')
                                ->where('mac', $product['mac'])
                                ->first();
                            if ($productValidation) {
                                $message_error .= "Ya existe un produto con el número MAC: " . $product['mac'];
                                $reppet_product = true;
                                $exist_diplicates = true;
                            }
                        }
                        if (isset($product['serie'])) {
                            $productValidation = Product::select(['serie'])
                                ->whereNotNull('serie')
                                ->where('serie', $product['serie'])
                                ->first();
                            if ($productValidation) {
                                $message_error .= "Ya existe un produto con el número de serie: " . $product['serie'];
                                $reppet_product = true;
                                $exist_diplicates = true;
                            }
                        }
                    }

                    $productJpa = new Product();
                    $productJpa->type = $request->type;
                    $productJpa->_branch = $branch_->id;
                    $productJpa->relative_id = guid::short();
                    $productJpa->_provider = $request->_provider;
                    $productJpa->_model = $request->_model;
                    $productJpa->currency = $request->currency;
                    $productJpa->price_buy = $request->price_buy;
                    $productJpa->price_sale = $request->price_sale;
                    $productJpa->mac = $product['mac'];
                    $productJpa->serie = $product['serie'];
                    $productJpa->mount = "1";
                    if (isset($request->num_guia)) {
                        $productJpa->num_guia = $request->num_guia;
                    }
                    if (isset($request->num_bill)) {
                        $productJpa->num_bill = $request->num_bill;
                    }
                    if (isset($request->warranty)) {
                        $productJpa->warranty = $request->warranty;
                    }
                    $productJpa->date_entry = $request->date_entry;
                    $productJpa->_entry_product = $entryProduct->id;
                    $productJpa->condition_product = $request->condition_product;
                    $productJpa->product_status = $request->product_status;
                    $productJpa->disponibility = $request->disponibility;
                    if (isset($request->description)) {
                        $productJpa->description = $request->description;
                    }
                    $productJpa->creation_date = gTrace::getDate('mysql');
                    $productJpa->_creation_user = $userid;
                    $productJpa->update_date = gTrace::getDate('mysql');
                    $productJpa->_update_user = $userid;
                    $productJpa->status = "1";
                    if (!$reppet_product) {
                        $productJpa->save();
                    }

                    $entryDetail = new EntryDetail();
                    $stock = Stock::where('_model', $request->_model)
                        ->where('_branch', $branch_->id)
                        ->first();
                    if ($request->product_status == "SEMINUEVO") {
                        $entryDetail->mount_second = 1;
                        $stock->mount_second = intval($stock->mount_second) + 1;
                    } else if ($request->product_status == "NUEVO") {
                        $entryDetail->mount_new = 1;
                        $stock->mount_new = intval($stock->mount_new) + 1;
                    } else {
                        $entryDetail->mount_ill_fated = 1;
                        $stock->mount_ill_fated = intval($stock->mount_ill_fated) + 1;
                    }

                    if (!$reppet_product) {
                        $stock->_update_user = $userid;
                        $stock->update_date = gTrace::getDate('mysql');
                        $stock->save();
                    }

                    $entryDetail->_product = $productJpa->id;
                    $entryDetail->_entry_product = $entryProduct->id;
                    $entryDetail->status = "1";
                    if (!$reppet_product) {
                        $entryDetail->save();
                    }
                }
            } else if ($request->type == "MATERIAL") {
                if (!isset($request->mount)) {
                    throw new Exception("Error: No deje campos vacíos");
                }

                $stock = Stock::where('_model', $request->_model)
                    ->where('_branch', $branch_->id)
                    ->first();

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

                    if ($request->product_status == "SEMINUEVO") {
                        $stock->mount_second = intval($stock->mount_second) + $request->mount;
                        $productJpa->mount = $productJpa->mount + $request->mount;
                    } else if ($request->product_status == "NUEVO") {
                        $productJpa->mount = $productJpa->mount + $request->mount;
                        $stock->mount_new = intval($stock->mount_new) + $request->mount;
                    } else if ($request->product_status == "MALOGRADO") {
                        $stock->mount_ill_fated = intval($stock->mount_ill_fated) + $request->mount;
                    }

                    $productJpa->type = $request->type;
                    $productJpa->_branch = $branch_->id;
                    $productJpa->relative_id = guid::short();
                    $productJpa->_provider = $request->_provider;
                    $productJpa->_model = $request->_model;
                    $productJpa->currency = $request->currency;
                    $productJpa->price_buy = $request->price_buy;
                    $productJpa->price_sale = $request->price_sale;
                    if (isset($request->num_guia)) {
                        $productJpa->num_guia = $request->num_guia;
                    }
                    if (isset($request->num_bill)) {
                        $productJpa->num_bill = $request->num_bill;
                    }
                    if (isset($request->warranty)) {
                        $productJpa->warranty = $request->warranty;
                    }
                    $productJpa->date_entry = $request->date_entry;
                    $productJpa->_entry_product = $entryProduct->id;
                    $productJpa->condition_product = $request->condition_product;
                    $productJpa->product_status = "NUEVO";
                    $productJpa->disponibility = $request->disponibility;
                    if (isset($request->description)) {
                        $productJpa->description = $request->description;
                    }
                    $productJpa->creation_date = gTrace::getDate('mysql');
                    $productJpa->_creation_user = $userid;
                    $productJpa->update_date = gTrace::getDate('mysql');
                    $productJpa->_update_user = $userid;
                    $productJpa->status = "1";
                    $productJpa->save();

                    $entryDetail = new EntryDetail();
                    if ($request->product_status == 'NUEVO') {
                        $entryDetail->mount_new = $request->mount;
                    } else if ($request->product_status == 'SEMINUEVO') {
                        $entryDetail->mount_second = $request->mount;
                    }
                    $entryDetail->_product = $productJpa->id;
                    $entryDetail->_entry_product = $entryProduct->id;
                    $entryDetail->status = "1";
                    $entryDetail->save();
                } else {
                    $productJpa = new Product();
                    $productJpa->type = $request->type;
                    $productJpa->_branch = $branch_->id;
                    $productJpa->relative_id = guid::short();
                    $productJpa->_provider = $request->_provider;
                    $productJpa->_model = $request->_model;
                    $productJpa->currency = $request->currency;
                    $productJpa->price_buy = $request->price_buy;
                    $productJpa->price_sale = $request->price_sale;
                    if (isset($request->num_guia)) {
                        $productJpa->num_guia = $request->num_guia;
                    }
                    if (isset($request->num_bill)) {
                        $productJpa->num_bill = $request->num_bill;
                    }
                    if (isset($request->warranty)) {
                        $productJpa->warranty = $request->warranty;
                    }
                    if (isset($request->warranty)) {
                        $productJpa->warranty = $request->warranty;
                    }
                    $productJpa->date_entry = $request->date_entry;
                    $productJpa->_entry_product = $entryProduct->id;
                    $productJpa->condition_product = $request->condition_product;
                    $productJpa->product_status = $request->product_status;
                    $productJpa->disponibility = $request->disponibility;
                    if (isset($request->description)) {
                        $productJpa->description = $request->description;
                    }
                    $productJpa->creation_date = gTrace::getDate('mysql');
                    $productJpa->_creation_user = $userid;
                    $productJpa->update_date = gTrace::getDate('mysql');
                    $productJpa->_update_user = $userid;
                    $productJpa->status = "1";

                    if ($request->product_status == "SEMINUEVO") {
                        $stock->mount_second = intval($stock->mount_second) + $request->mount;
                        $productJpa->mount = $request->mount;
                    } else if ($request->product_status == "NUEVO") {
                        $stock->mount_new = intval($stock->mount_new) + $request->mount;
                        $productJpa->mount = $request->mount;
                    } else if ($request->product_status == "MALOGRADO") {
                        $stock->mount_ill_fated = intval($stock->mount_new) + $request->mount;
                        $productJpa->mount = 0;
                    }

                    $productJpa->save();

                    $entryDetail = new EntryDetail();
                    if ($request->product_status == 'NUEVO') {
                        $entryDetail->mount_new = $request->mount;
                    } else if ($request->product_status == 'SEMINUEVO') {
                        $entryDetail->mount_second = $request->mount;
                    }
                    $entryDetail->_product = $productJpa->id;
                    $entryDetail->_entry_product = $entryProduct->id;
                    $entryDetail->status = "1";
                    $entryDetail->save();
                }
                $stock->_update_user = $userid;
                $stock->update_date = gTrace::getDate('mysql');
                $stock->save();
            }

            if ($exist_diplicates) {
                $response->setStatus(203);
            } else {
                $response->setStatus(200);
            }

            $response->setMessage('Producto agregado correctamente || ' . $message_error);
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
            if (!gValidate::check($role->permissions, $branch, 'products', 'read')) {
                throw new Exception('No tienes permisos para listar productos');
            }

            $query = ViewProducts::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            if (isset($request->search['brand'])) {
                $query->where('model__brand__id', $request->search['brand']);
            }
            if (isset($request->search['category'])) {
                $query->where('model__category__id', $request->search['category']);
            }
            if (isset($request->search['model'])) {
                $query->where('model__id', $request->search['model']);
            }
            if (isset($request->search['product_status'])) {
                $query->where('product_status', $request->search['product_status']);
            }
            if (isset($request->search['condition_product'])) {
                $query->where('condition_product', $request->search['condition_product']);
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'id' || $column == '*') {
                    $q->orWhere('id', $type, $value);
                }

                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
                if ($column == 'model__brand__brand' || $column == '*') {
                    $q->orWhere('model__brand__brand', $type, $value);
                }
                if ($column == 'model__category__category' || $column == '*') {
                    $q->orWhere('model__category__category', $type, $value);
                }
                if ($column == 'model__model' || $column == '*') {
                    $q->orWhere('model__model', $type, $value);
                }
                if ($column == 'mac' || $column == '*') {
                    $q->orWhere('mac', $type, $value);
                }
                if ($column == 'serie' || $column == '*') {
                    $q->orWhere('serie', $type, $value);
                }
                if ($column == 'price_buy' || $column == '*') {
                    $q->orWhere('price_buy', $type, $value);
                }
                if ($column == 'disponibility' || $column == '*') {
                    $q->orWhere('disponibility', $type, $value);
                }
                if ($column == 'num_guia' || $column == '*') {
                    $q->orWhere('num_guia', $type, $value);
                }
                if ($column == 'num_bill' || $column == '*') {
                    $q->orWhere('num_bill', $type, $value);
                }
            })->where('branch__correlative', $branch)
                ->where('disponibility', '=', 'DISPONIBLE');
            $iTotalDisplayRecords = $query->count();

            $productsJpa = $query

                ->skip($request->start)
                ->take($request->length)
                ->get();

            $products = array();
            foreach ($productsJpa as $product_) {
                $product = gJSON::restore($product_->toArray(), '__');
                $products[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewProducts::where('branch__correlative', $branch)->count());
            $response->setData($products);
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

    public function paginateMaterials(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'products', 'read')) {
                throw new Exception('No tienes permisos para listar productos');
            }

            $query = ViewProducts::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            if (isset($request->search['brand'])) {
                $query->orWhere('brand__id', $request->search['brand']);
            }
            if (isset($request->search['category'])) {
                $query->orWhere('category__id', $request->search['category']);
            }
            if (isset($request->search['model'])) {
                $query->orWhere('model__id', $request->search['model']);
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'model__brand__brand' || $column == '*') {
                    $q->orWhere('model__brand__brand', $type, $value);
                }
                if ($column == 'model__category__category' || $column == '*') {
                    $q->orWhere('model__category__category', $type, $value);
                }
                if ($column == 'model__model' || $column == '*') {
                    $q->orWhere('model__model', $type, $value);
                }
                if ($column == 'mac' || $column == '*') {
                    $q->orWhere('mac', $type, $value);
                }
                if ($column == 'serie' || $column == '*') {
                    $q->orWhere('serie', $type, $value);
                }
                if ($column == 'price_buy' || $column == '*') {
                    $q->orWhere('price_buy', $type, $value);
                }
                if ($column == 'disponibility' || $column == '*') {
                    $q->orWhere('disponibility', $type, $value);
                }
                if ($column == 'num_guia' || $column == '*') {
                    $q->orWhere('num_guia', $type, $value);
                }
                if ($column == 'num_bill' || $column == '*') {
                    $q->orWhere('num_bill', $type, $value);
                }
            })->where('branch__correlative', $branch)
                ->where('type', 'MATERIAL');
            $iTotalDisplayRecords = $query->count();

            $productsJpa = $query

                ->skip($request->start)
                ->take($request->length)
                ->get();

            $products = array();
            foreach ($productsJpa as $product_) {
                $product = gJSON::restore($product_->toArray(), '__');
                $products[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewProducts::where('branch__correlative', $branch)->count());
            $response->setData($products);
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

    public function paginateEquipment(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'products', 'read')) {
                throw new Exception('No tienes permisos para listar productos');
            }

            $query = ViewProducts::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            if (isset($request->search['brand'])) {
                $query->where('brand__id', $request->search['brand']);
            }
            if (isset($request->search['category'])) {
                $query->where('category__id', $request->search['category']);
            }
            if (isset($request->search['model'])) {
                $query->where('model__id', $request->search['model']);
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'model__brand__brand' || $column == '*') {
                    $q->where('model__brand__brand', $type, $value);
                }
                if ($column == 'model__category__category' || $column == '*') {
                    $q->where('model__category__category', $type, $value);
                }
                if ($column == 'model__model' || $column == '*') {
                    $q->orWhere('model__model', $type, $value);
                }
                if ($column == 'mac' || $column == '*') {
                    $q->orWhere('mac', $type, $value);
                }
                if ($column == 'serie' || $column == '*') {
                    $q->orWhere('serie', $type, $value);
                }
                if ($column == 'price_buy' || $column == '*') {
                    $q->orWhere('price_buy', $type, $value);
                }
                if ($column == 'disponibility' || $column == '*') {
                    $q->orWhere('disponibility', $type, $value);
                }
                if ($column == 'num_guia' || $column == '*') {
                    $q->orWhere('num_guia', $type, $value);
                }
                if ($column == 'num_bill' || $column == '*') {
                    $q->orWhere('num_bill', $type, $value);
                }
            })->where('branch__correlative', $branch)
                ->where('disponibility', '=', 'DISPONIBLE')
                ->where('type', 'EQUIPO');

            $iTotalDisplayRecords = $query->count();
            $productsJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $products = array();
            foreach ($productsJpa as $product_) {
                $product = gJSON::restore($product_->toArray(), '__');
                $products[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewProducts::where('branch__correlative', $branch)->count());
            $response->setData($products);
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

    public function paginateEquipmentAll(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'all_equipment', 'read')) {
                throw new Exception('No tienes permisos para listar productos');
            }

            $query = ViewProducts::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            if (isset($request->search['brand'])) {
                $query->where('brand__id', $request->search['brand']);
            }
            if (isset($request->search['category'])) {
                $query->where('category__id', $request->search['category']);
            }
            if (isset($request->search['model'])) {
                $query->where('model__id', $request->search['model']);
            }
            if (isset($request->search['branch'])) {
                $query->where('branch__id', $request->search['branch']);
            }
            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'model__brand__brand' || $column == '*') {
                    $q->orWhere('model__brand__brand', $type, $value);
                }
                if ($column == 'branch__name' || $column == '*') {
                    $q->orWhere('branch__name', $type, $value);
                }
                if ($column == 'model__category__category' || $column == '*') {
                    $q->where('model__category__category', $type, $value);
                }
                if ($column == 'model__model' || $column == '*') {
                    $q->orWhere('model__model', $type, $value);
                }
                if ($column == 'mac' || $column == '*') {
                    $q->orWhere('mac', $type, $value);
                }
                if ($column == 'serie' || $column == '*') {
                    $q->orWhere('serie', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
                if ($column == 'price_buy' || $column == '*') {
                    $q->orWhere('price_buy', $type, $value);
                }
                if ($column == 'disponibility' || $column == '*') {
                    $q->orWhere('disponibility', $type, $value);
                }
                if ($column == 'num_guia' || $column == '*') {
                    $q->orWhere('num_guia', $type, $value);
                }
                if ($column == 'num_bill' || $column == '*') {
                    $q->orWhere('num_bill', $type, $value);
                }
            })->where('type', 'EQUIPO');

            $iTotalDisplayRecords = $query->count();
            $productsJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $products = array();
            foreach ($productsJpa as $product_) {
                $product = gJSON::restore($product_->toArray(), '__');
                $products[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewProducts::where('branch__correlative', $branch)->count());
            $response->setData($products);
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

    public function update(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'products', 'update')) {
                throw new Exception('No tienes permisos para actualizar productos');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $productJpa = Product::find($request->id);
            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            if (!$productJpa) {
                throw new Exception("Error: El registro que intenta modificar no existe");
            }

            if (!isset($request->mac) && !isset($request->serie)) {
                if (isset($request->mac)) {
                    $productValidation = Product::select(['id', 'mac'])
                        ->where('mac', $request->mac)
                        ->where('id', '!=', $request->id)
                        ->where('mac', '!=', "")
                        ->whereNotNull('mac')
                        ->first();

                    if ($productValidation->mac == $request->mac) {
                        throw new Exception("Ya existe otro un produto con el número MAC: " . $request->mac);
                    }
                    $productJpa->mac = $request->mac;
                }
                if (isset($request->serie)) {
                    $productValidation = Product::select(['id', 'serie'])
                        ->where('serie', $request->serie)
                        ->where('id', '!=', $request->id)
                        ->where('mac', '!=', "")
                        ->whereNotNull('mac')
                        ->first();

                    if ($productValidation->serie == $request->serie) {
                        throw new Exception("Ya existe otro un produto con el número de serie: " . $request->serie);
                    }
                    $productJpa->serie = $request->serie;
                }
            } else {
                $productValidation = Product::select(['id', 'mac', 'serie'])
                    ->where('mac', $request->mac)
                    ->where('id', '!=', $request->id)
                    ->whereNotNull('mac')
                    ->orWhere('serie', $request->serie)
                    ->where('id', '!=', $request->id)
                    ->whereNotNull('serie')
                    ->first();

                if ($productValidation) {
                    if ($productValidation->mac == $request->mac) {
                        throw new Exception("Ya existe otro un produto con el número MAC: " . $request->mac);
                    }
                    if ($productValidation->serie == $request->serie) {
                        throw new Exception("Ya existe otro un produto con el número de serie: " . $request->serie);
                    }
                }

                $productJpa->mac = $request->mac;
                $productJpa->serie = $request->serie;
            }

            if (isset($request->num_guia)) {
                $productJpa->num_guia = $request->num_guia;
            }
            if (isset($request->num_bill)) {
                $productJpa->num_bill = $request->num_bill;
            }
            if (isset($request->price_buy)) {
                $productJpa->price_buy = $request->price_buy;
            }
            if (isset($request->price_sale)) {
                $productJpa->price_sale = $request->price_sale;
            }
            if (isset($request->product_status)) {
                if ($productJpa->type == "EQUIPO") {
                    if ($productJpa->product_status != $request->product_status) {

                        if ($request->product_status == "SEMINUEVO" && $productJpa->product_status != "SEMINUEVO") {
                            if ($productJpa->product_status == "NUEVO") {
                                $stock = Stock::where('_model', $productJpa->_model)
                                    ->where('_branch', $branch_->id)->first();
                                $stock->mount_second = (intval($stock->mount_second) + 1);
                                $stock->mount_new = (intval($stock->mount_new) - 1);
                                $stock->_update_user = $userid;
                                $stock->update_date = gTrace::getDate('mysql');
                                $stock->save();
                            } else {
                                $stock = Stock::where('_model', $productJpa->_model)
                                    ->where('_branch', $branch_->id)->first();
                                $stock->mount_second = intval($stock->mount_second) + 1;
                                $stock->_update_user = $userid;
                                $stock->update_date = gTrace::getDate('mysql');
                                $stock->save();
                            }
                        }

                        if ($request->product_status == "NUEVO" && $productJpa->product_status != "NUEVO") {
                            if ($productJpa->product_status == "SEMINUEVO") {
                                $stock = Stock::where('_model', $productJpa->_model)
                                    ->where('_branch', $branch_->id)->first();
                                $stock->mount_second = intval($stock->mount_second) - 1;
                                $stock->mount_new = intval($stock->mount_new) + 1;
                                $stock->_update_user = $userid;
                                $stock->update_date = gTrace::getDate('mysql');
                                $stock->save();
                            } else {
                                $stock = Stock::where('_model', $productJpa->_model)
                                    ->where('_branch', $branch_->id)->first();
                                $stock->mount_new = intval($stock->mount_new) + 1;
                                $stock->_update_user = $userid;
                                $stock->update_date = gTrace::getDate('mysql');
                                $stock->save();
                            }
                        }

                        if ($request->product_status == "MALOGRADO" && $productJpa->product_status != "MALOGRADO") {
                            if ($productJpa->product_status == "NUEVO") {
                                $stock = Stock::where('_model', $productJpa->_model)
                                    ->where('_branch', $branch_->id)->first();
                                $stock->mount_new = intval($stock->mount_new) - 1;
                                $stock->_update_user = $userid;
                                $stock->update_date = gTrace::getDate('mysql');
                                $stock->save();
                            } else if ($productJpa->product_status == "SEMINUEVO") {
                                $stock = Stock::where('_model', $productJpa->_model)
                                    ->where('_branch', $branch_->id)->first();
                                $stock->mount_second = intval($stock->mount_second) - 1;
                                $stock->_update_user = $userid;
                                $stock->update_date = gTrace::getDate('mysql');
                                $stock->save();
                            }
                        }

                        if ($request->product_status == "POR REVISAR" && $productJpa->product_status != "POR REVISAR") {
                            if ($productJpa->product_status == "NUEVO") {
                                $stock = Stock::where('_model', $productJpa->_model)
                                    ->where('_branch', $branch_->id)->first();
                                $stock->mount_new = intval($stock->mount_new) - 1;
                                $stock->_update_user = $userid;
                                $stock->update_date = gTrace::getDate('mysql');
                                $stock->save();
                            } else if ($productJpa->product_status == "SEMINUEVO") {
                                $stock = Stock::where('_model', $productJpa->_model)
                                    ->where('_branch', $branch_->id)->first();
                                $stock->mount_second = intval($stock->mount_second) - 1;
                                $stock->_update_user = $userid;
                                $stock->update_date = gTrace::getDate('mysql');
                                $stock->save();
                            }
                        }
                    }
                }
                $productJpa->product_status = $request->product_status;
            }
            if (isset($request->condition_product)) {
                $productJpa->condition_product = $request->condition_product;
            }
            if (isset($request->product_status)) {
                $productJpa->product_status = $request->product_status;
            }
            $productJpa->description = $request->description;
            if (isset($request->currency)) {
                $productJpa->currency = $request->currency;
            }
            if (isset($request->_model)) {
                $productJpa->_model = $request->_model;
            }
            if (isset($request->_branch)) {
                $productJpa->_branch = $request->_branch;
            }
            if (isset($request->_provider)) {
                $productJpa->_provider = $request->_provider;
            }

            if (isset($request->warranty)) {
                $productJpa->warranty = $request->warranty;
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            // if (isset($request->mount)) {
            //     $productJpa->mount = $request->mount;
            //     if (isset($request->product_status)) {
            //         if ($productJpa->type == "MATERIAL") {
            //             $stock = Stock::where('_model', $productJpa->_model)
            //                 ->where('_branch', $branch_->id)->first();
            //             $stock->mount_new = $request->mount;
            //             $stock->save();
            //         }
            //     }
            // }

            $productJpa->update_date = gTrace::getDate('mysql');
            $productJpa->_update_user = $userid;

            if (gValidate::check($role->permissions, $branch, 'products', 'change_status')) {
                if (isset($request->status)) {
                    $productJpa->status = $request->status;
                }
            }
            $productJpa->update_date = gTrace::getDate('mysql');
            $productJpa->_update_user = $userid;
            $productJpa->save();

            $response->setStatus(200);
            $response->setMessage('Producto actualizado correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'products', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar productos');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: Es necesario el ID para esta operación");
            }

            $productJpa = Product::find($request->id);
            if (!$productJpa) {
                throw new Exception("Este reguistro no existe");
            }
            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();
            $stock = Stock::where('_model', $productJpa->_model)
                ->where('_branch', $branch_->id)
                ->first();

            if ($request->reazon) {
                if ($request->reazon == "RETURN_TO_PROVIDER") {
                    $salesProduct = new SalesProducts();
                    $salesProduct->_branch = $branch_->id;
                    $salesProduct->_type_operation = 15;
                    $salesProduct->date_sale = gTrace::getDate('mysql');
                    $salesProduct->status_sale = "PENDING";
                    $salesProduct->description = "DEVOLUCIÓN A PROVEEDOR";
                    $salesProduct->_client = $productJpa->provider_return;
                    $salesProduct->type_intallation = "DEVOLUCIÓN A PROVEEDOR";
                    $salesProduct->_issue_user = $userid;
                    $salesProduct->_creation_user = $userid;
                    $salesProduct->creation_date = gTrace::getDate('mysql');
                    $salesProduct->_update_user = $userid;
                    $salesProduct->update_date = gTrace::getDate('mysql');
                    $salesProduct->status = "1";
                    $salesProduct->save();

                    $detailSale = new DetailSale();
                    $detailSale->_product = $productJpa->id;
                    $detailSale->mount_new = $stock->mount_new;
                    $detailSale->mount_second = $stock->mount_second;
                    $detailSale->mount_ill_fated = $stock->mount_ill_fated;
                    $detailSale->_sales_product = $salesProduct->id;
                    $detailSale->status = '1';
                    $detailSale->save();
                    $productJpa->description .= "Se devovio al proveedor en la fecha: " . gTrace::getDate('mysql');

                } else if ($request->reazon == "OTHER_REAZON") {
                    $productJpa->description .= "Se elimino por la razon: " . $request->indique_reazon . ", En la fecha: " . gTrace::getDate('mysql');
                }
            }

            $productJpa->mount = 0;
            $productJpa->_update_user = $userid;
            $productJpa->update_date = gTrace::getDate('mysql');
            $productJpa->status = null;
            $productJpa->save();

            if ($productJpa->type == 'EQUIPO') {
                if ($productJpa->product_status == "NUEVO") {

                    $stock->mount_new = intval($stock->mount_new) - 1;
                    $stock->_update_user = $userid;
                    $stock->update_date = gTrace::getDate('mysql');
                    $stock->save();
                }
                if ($productJpa->product_status == "SEMINUEVO") {
                    $stock = Stock::where('_model', $productJpa->_model)
                        ->where('_branch', $branch_->id)
                        ->first();
                    $stock->mount_second = intval($stock->mount_second) - 1;
                    $stock->_update_user = $userid;
                    $stock->update_date = gTrace::getDate('mysql');
                    $stock->save();
                }
            } else {
                // $stock = Stock::where('_model', $productJpa->_model)
                //     ->where('_branch', $branch_->id)
                //     ->first();
                // $stock->mount_new = 0;
                // $stock->save();
                throw new Exception("Haga esta operación desde su STOCK.");
            }

            $response->setStatus(200);
            $response->setMessage('El producto se a eliminado correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'products', 'delete_restore')) {
                throw new Exception('No tienes permisos para restaurar productos');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: Es necesario el ID para esta operación");
            }

            $productJpa = Product::find($request->id);
            if (!$productJpa) {
                throw new Exception("Este reguistro no existe");
            }
            $productJpa->_update_user = $userid;
            $productJpa->update_date = gTrace::getDate('mysql');
            $productJpa->status = "1";
            $productJpa->save();

            $response->setStatus(200);
            $response->setMessage('El producto a sido restaurado correctamente');
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

    public function getProductById(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'products', 'read')) {
                throw new Exception('No tienes permisos para ver productos');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: Es necesario el ID para esta operación");
            }

            $productJpa = Product::select('*')
                ->join('models', 'products._model', 'models.id')
                ->find($request->id);

            if (!$productJpa) {
                throw new Exception("Este reguistro no existe");
            }

            $response->setData($productJpa);
            $response->setStatus(200);
            $response->setMessage('El producto a sido restaurado correctamente');
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

    public function paginateEPP(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'all_equipment', 'read')) {
                throw new Exception('No tienes permisos para listar productos');
            }

            $query = ViewProducts::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir'])->where('model__category__category', 'EPP');

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'model__brand__brand' || $column == '*') {
                    $q->orWhere('model__brand__brand', $type, $value);
                }
                if ($column == 'branch__name' || $column == '*') {
                    $q->orWhere('branch__name', $type, $value);
                }
                if ($column == 'model__category__category' || $column == '*') {
                    $q->where('model__category__category', $type, $value);
                }
                if ($column == 'model__model' || $column == '*') {
                    $q->orWhere('model__model', $type, $value);
                }
                if ($column == 'mac' || $column == '*') {
                    $q->orWhere('mac', $type, $value);
                }
                if ($column == 'serie' || $column == '*') {
                    $q->orWhere('serie', $type, $value);
                }
                if ($column == 'price_buy' || $column == '*') {
                    $q->orWhere('price_buy', $type, $value);
                }
                if ($column == 'disponibility' || $column == '*') {
                    $q->orWhere('disponibility', $type, $value);
                }
                if ($column == 'num_guia' || $column == '*') {
                    $q->orWhere('num_guia', $type, $value);
                }
                if ($column == 'num_bill' || $column == '*') {
                    $q->orWhere('num_bill', $type, $value);
                }
            })->where('type', 'MATERIAL')
                ->where('branch__correlative', $branch);

            $iTotalDisplayRecords = $query->count();
            $productsJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $products = array();
            foreach ($productsJpa as $product_) {
                $product = gJSON::restore($product_->toArray(), '__');
                $products[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewProducts::where('branch__correlative', $branch)->count());
            $response->setData($products);
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

    public function getProductsByNumberGuia(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'all_equipment', 'read')) {
                throw new Exception('No tienes permisos para listar productos');
            }

            $productJpa = ViewProducts::select(['*'])
                ->orderBy('id', 'desc')->where('num_guia', $request->num_guia)->where('model__id', $request->model['id'])->get();

            $products = array();
            foreach ($productJpa as $product_) {
                $product = gJSON::restore($product_->toArray(), '__');
                $products[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($products);
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

    public function generateReportProductsByBranch(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'products', 'read')) {
                throw new Exception('No tienes permisos para listar productos');
            }

            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/products/allProducts.html');

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $query = ViewProducts::select(['*'])
                ->orderBy('model__id', 'desc')->whereNotNull('status')
                ->where('branch__correlative', $branch)
                ->where('disponibility', '=', 'DISPONIBLE')
                ->where('type', 'EQUIPO');

            if (isset($request->model)) {
                $query->where('model__id', $request->model);
            }
            $productsJpa = $query->get();
            $monut_equipment = $query->count();

            $summary = "";
            $count = 1;
            $products = array();
            foreach ($productsJpa as $product_) {
                $product = gJSON::restore($product_->toArray(), '__');
                $products[] = $product;
                $details = "
                    <div>
                        <p><strong>MAC:</strong> {$product['mac']}</p>
                        <p><strong>SERIE: </strong>{$product['serie']}</p>
                    </div>
                ";
                $summary .= "
                <tr>
                    <td><center>{$count}</center></td>
                    <td>{$details}</td>
                    <td>{$product['model']['model']}</td>
                    <td>{$product['product_status']}</td>
                    <td>{$product['description']}</td>
                </tr>
                ";

                $count++;
            }

            $template = str_replace(
                [
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{summary}',
                    '{mount_equipments}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $summary,
                    $monut_equipment,
                ],
                $template
            );

            // $response->setStatus(200);
            // $response->setMessage('Operación correcta');
            // $response->setData($products);
            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('PRODUCTOS.pdf');
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
