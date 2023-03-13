<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\EntryProducts;
use App\Models\Product;
use App\Models\Response;
use App\Models\Stock;
use App\Models\ViewProducts;
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
                !isset($request->_supplier) ||
                !isset($request->_model) ||
                !isset($request->currency) ||
                !isset($request->price_buy) ||
                !isset($request->price_sale) ||
                !isset($request->condition_product) ||
                !isset($request->status_product) ||
                !isset($request->date_entry)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $entryProduct = new EntryProducts();
            $entryProduct->_user = $userid;
            $entryProduct->entry_date = gTrace::getDate('mysql');
            $entryProduct->_type_entry = $request->_type_entry;
            $entryProduct->status = "1";
            $entryProduct->save();

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            if ($request->type == "EQUIPO") {
                if (!isset($request->data)) {
                    throw new Exception("Error: No deje campos vacíos");
                }
                foreach ($request->data as $product) {
                    // $productValidation = Product::select(['mac', 'serie'])
                    //     ->where('mac', $product['mac'])
                    //     ->orWhere('serie', $product['serie'])
                    //     ->first();
                    // if ($productValidation) {
                    //     if ($productValidation->mac == $product['mac']) {
                    //         throw new Exception("Ya existe un produto con el número MAC: " . $product['mac']);
                    //     }
                    //     if ($productValidation->serie == $product['serie']) {
                    //         throw new Exception("Ya existe un produto con el número de serie: " . $product['serie']);
                    //     }
                    // }

                    $productJpa = new Product();
                    $productJpa->type = $request->type;
                    $productJpa->_branch = $branch_->id;
                    $productJpa->relative_id = guid::short();
                    $productJpa->_brand = $request->_brand;
                    $productJpa->_category = $request->_category;
                    $productJpa->_supplier = $request->_supplier;
                    $productJpa->_model = $request->_model;
                    $productJpa->currency = $request->currency;
                    $productJpa->price_buy = $request->price_buy;
                    $productJpa->price_sale = $request->price_sale;
                    $productJpa->mac = $product['mac'];
                    $productJpa->serie = $product['serie'];
                    $productJpa->mount = "1";
                    if (isset($request->num_gia)) {
                        $productJpa->num_gia = $request->num_gia;
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
                    $productJpa->status_product = $request->status_product;
                    if (isset($request->description)) {
                        $productJpa->description = $request->description;
                    }
                    $productJpa->creation_date = gTrace::getDate('mysql');
                    $productJpa->_creation_user = $userid;
                    $productJpa->update_date = gTrace::getDate('mysql');
                    $productJpa->_update_user = $userid;
                    $productJpa->status = "1";
                    $productJpa->save();

                    $stock = Stock::where('_model', $request->_model)->first();
                    $stock->mount = intval($stock->mount) + 1;
                    $stock->save();
                }
            } else if ($request->type == "MATERIAL") {
                if (!isset($request->mount)) {
                    throw new Exception("Error: No deje campos vacíos");
                }

                $material = Product::select([
                    'id',
                    'mount',
                    '_model',
                    '_category',
                    '_brand',
                ])
                    ->where('_model', $request->_model)
                    ->where('_category', $request->_category)
                    ->where('_brand', $request->_brand)
                    ->first();
                if ($material) {
                    $mount_old = $material->mount;
                    $mount_new = $mount_old + $request->mount;

                    $material->type = $request->type;
                    $material->_branch = $branch_->id;
                    $material->relative_id = guid::short();
                    $material->_brand = $request->_brand;
                    $material->_category = $request->_category;
                    $material->_supplier = $request->_supplier;
                    $material->_model = $request->_model;
                    $material->mount = $mount_new;
                    $material->currency = $request->currency;
                    $material->price_buy = $request->price_buy;
                    $material->price_sale = $request->price_sale;
                    if (isset($request->num_gia)) {
                        $productJpa->num_gia = $request->num_gia;
                    }
                    if (isset($request->num_bill)) {
                        $productJpa->num_bill = $request->num_bill;
                    }
                    if (isset($request->warranty)) {
                        $material->warranty = $request->warranty;
                    }
                    $material->date_entry = $request->date_entry;
                    $material->_entry_product = $entryProduct->id;
                    $material->condition_product = $request->condition_product;
                    $material->status_product = $request->status_product;
                    if (isset($request->description)) {
                        $material->description = $request->description;
                    }
                    $material->creation_date = gTrace::getDate('mysql');
                    $material->_creation_user = $userid;
                    $material->update_date = gTrace::getDate('mysql');
                    $material->_update_user = $userid;
                    $material->status = "1";
                    $material->save();

                    $stock = Stock::where('_model', $request->_model)->first();
                    $stock->mount = intval($stock->mount) + intval($request->mount);
                    $stock->save();
                } else {
                    $productJpa = new Product();
                    $productJpa->type = $request->type;
                    $productJpa->_branch = $branch_->id;
                    $productJpa->relative_id = guid::short();
                    $productJpa->_brand = $request->_brand;
                    $productJpa->_category = $request->_category;
                    $productJpa->_supplier = $request->_supplier;
                    $productJpa->_model = $request->_model;
                    $productJpa->mount = $request->mount;
                    $productJpa->currency = $request->currency;
                    $productJpa->price_buy = $request->price_buy;
                    $productJpa->price_sale = $request->price_sale;
                    if (isset($request->num_gia)) {
                        $productJpa->num_gia = $request->num_gia;
                    }
                    if (isset($request->num_bill)) {
                        $productJpa->num_bill = $request->num_bill;
                    }
                    if (isset($request->warranty)) {
                        $material->warranty = $request->warranty;
                    }
                    if (isset($request->warranty)) {
                        $productJpa->warranty = $request->warranty;
                    }
                    $productJpa->date_entry = $request->date_entry;
                    $productJpa->_entry_product = $entryProduct->id;
                    $productJpa->condition_product = $request->condition_product;
                    $productJpa->status_product = $request->status_product;
                    if (isset($request->description)) {
                        $productJpa->description = $request->description;
                    }
                    $productJpa->creation_date = gTrace::getDate('mysql');
                    $productJpa->_creation_user = $userid;
                    $productJpa->update_date = gTrace::getDate('mysql');
                    $productJpa->_update_user = $userid;
                    $productJpa->status = "1";
                    $productJpa->save();

                    $stock = Stock::where('_model', $request->_model)->first();
                    $stock->mount = intval($stock->mount) + intval($request->mount);
                    $stock->save();
                }
            }

            $response->setStatus(200);
            $response->setMessage('Producto agregado correctamente');
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

                if ($column == 'brand__brand' || $column == '*') {
                    $q->where('brand__brand', $type, $value);
                }
                if ($column == 'category__category' || $column == '*') {
                    $q->where('category__category', $type, $value);
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
                if ($column == 'status_product' || $column == '*') {
                    $q->orWhere('status_product', $type, $value);
                }
                if ($column == 'num_gia' || $column == '*') {
                    $q->orWhere('num_gia', $type, $value);
                }
                if ($column == 'num_bill' || $column == '*') {
                    $q->orWhere('num_bill', $type, $value);
                }
            })->where('branch__correlative', $branch)
                ->where('status_product', '!=', 'VENDIDO');
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

                if ($column == 'brand__brand' || $column == '*') {
                    $q->where('brand__brand', $type, $value);
                }
                if ($column == 'category__category' || $column == '*') {
                    $q->where('category__category', $type, $value);
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
                if ($column == 'status_product' || $column == '*') {
                    $q->orWhere('status_product', $type, $value);
                }
                if ($column == 'num_gia' || $column == '*') {
                    $q->orWhere('num_gia', $type, $value);
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

                if ($column == 'brand__brand' || $column == '*') {
                    $q->where('brand__brand', $type, $value);
                }
                if ($column == 'category__category' || $column == '*') {
                    $q->where('category__category', $type, $value);
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
                if ($column == 'status_product' || $column == '*') {
                    $q->orWhere('status_product', $type, $value);
                }
                if ($column == 'num_gia' || $column == '*') {
                    $q->orWhere('num_gia', $type, $value);
                }
                if ($column == 'num_bill' || $column == '*') {
                    $q->orWhere('num_bill', $type, $value);
                }
            })->where('branch__correlative', $branch)
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

            if (!$productJpa) {
                throw new Exception("Error: El registro que intenta modificar no existe");
            }

            if (!isset($request->mac) && !isset($request->serie)) {
                if (isset($request->mac)) {
                    $productValidation = Product::select(['id', 'mac'])
                        ->where('mac', $request->mac)
                        ->where('id', '!=', $request->id)
                        ->first();

                    if ($productValidation->mac == $request->mac) {
                        throw new Exception("Ya existe otro un produto con el número MAC: " . $request->mac);
                    }
                    $productJpa->mac = $request->mac;
                }
                if (isset($request->serie)) {
                    $productValidation = Product::select(['id', 'serie'])
                        ->orWhere('serie', $request->serie)
                        ->where('id', '!=', $request->id)
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
                    ->orWhere('serie', $request->serie)
                    ->where('id', '!=', $request->id)
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

            if (isset($request->num_gia)) {
                $productJpa->num_gia = $request->num_gia;
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

            if (isset($request->status_product)) {
                $productJpa->status_product = $request->status_product;
            }

            if (isset($request->condition_product)) {
                $productJpa->condition_product = $request->condition_product;
            }

            if (isset($request->description)) {
                $productJpa->description = $request->description;
            }

            if (isset($request->currency)) {
                $productJpa->currency = $request->currency;
            }

            if (isset($request->_model)) {
                $productJpa->_model = $request->_model;
            }

            if (isset($request->_brand)) {
                $productJpa->_brand = $request->_brand;
            }

            if (isset($request->_category)) {
                $productJpa->_category = $request->_category;
            }

            if (isset($request->_supplier)) {
                $productJpa->_supplier = $request->_supplier;
            }

            if (isset($request->warranty)) {
                $productJpa->warranty = $request->warranty;
            }

            if (isset($request->mount)) {
                $productJpa->mount = $request->mount;
                $stock = Stock::where('_model', $productJpa->_model)->first();
                $stock->mount = $request->mount;
                $stock->save();
            }

            $productJpa->update_date = gTrace::getDate('mysql');
            $productJpa->_update_user = $userid;

            if (gValidate::check($role->permissions, $branch, 'products', 'change_status')) {
                if (isset($request->status)) {
                    $productJpa->status = $request->status;
                }
            }

            $productJpa->save();

            $response->setStatus(200);
            $response->setMessage('Producto actualizado correctamente');
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

            $productJpa->mount = 0;
            $productJpa->_update_user = $userid;
            $productJpa->update_date = gTrace::getDate('mysql');
            $productJpa->status = null;
            $productJpa->save();

            $response->setStatus(200);
            $response->setMessage('El producto se a eliminado correctamente');
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
