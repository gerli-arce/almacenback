<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gUid;
use App\gLibraries\gValidate;
use App\Models\EntryProducts;
use App\Models\ViewProducts;
use App\Models\Product;
use App\Models\Response;
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
                !isset($request->_brand) ||
                !isset($request->_category) ||
                !isset($request->_supplier) ||
                !isset($request->_model) ||
                !isset($request->currency) ||
                !isset($request->price_buy) ||
                !isset($request->price_sale) ||
                !isset($request->data) ||
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

            foreach ($request->data as $product) {

                $productValidation = Product::select(['mac', 'serie'])
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

                $productJpa = new Product();
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
                $productJpa->num_gia = $request->num_gia;
                $productJpa->date_entry = $request->date_entry;
                $productJpa->_entry_product = $entryProduct->id;
                $productJpa->condition_product = $request->condition_product;
                $productJpa->status_product = $request->status_product;
                $productJpa->creation_date = gTrace::getDate('mysql');
                $productJpa->_creation_user = $userid;
                $productJpa->update_date = gTrace::getDate('mysql');
                $productJpa->_update_user = $userid;
                $productJpa->status = "1";
                $productJpa->save();
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
            if (!gValidate::check($role->permissions, $branch, 'users', 'read')) {
                throw new Exception('No tienes permisos para listar usuarios');
            }

            $dat = gValidate::check($role->permissions, $branch, 'users', 'read');

            $query = ViewProducts::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
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
                if ($column == 'status' || $column == '*') {
                    $q->orWhere('status', $type, $value);
                }
            });
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
            $response->setITotalRecords(ViewProducts::count());
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
            if (!gValidate::check($role->permissions, $branch, 'users', 'update')) {
                throw new Exception('No tienes permisos para actualizar usuarios');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $userJpa = User::find($request->id);

            if (!$userJpa) {
                throw new Exception("Error: El registro que intenta modificar no existe");
            }

            if (isset($request->username)) {
                $userValidation = User::select(['id', 'username'])
                    ->where('username', $request->username)
                    ->where('id', '!=', $request->id)->first();
                if ($userValidation) {
                    throw new Exception("Este usuario ya existe");
                }
                $userJpa->username = $request->username;
            }

            if (isset($request->password)) {
                $userJpa->password = password_hash($request->password, PASSWORD_DEFAULT);
            }

            if (isset($request->_branch)) {
                $userJpa->_branch = $request->_branch;
            }

            if (isset($request->_person)) {
                $personValidation = User::select(['id', 'username', '_person'])
                    ->where('_person', '=', $request->_person)
                    ->where('id', '!=', $request->id)->first();
                if ($personValidation) {
                    throw new Exception("Error: Esta persona ya tiene un usuario");
                }
                $userJpa->_person = $request->_person;
            }

            if ($request->_role) {
                $userJpa->_role = $request->_role;
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
                    $userJpa->image_type = $request->image_type;
                    $userJpa->image_mini = base64_decode($request->image_mini);
                    $userJpa->image_full = base64_decode($request->image_full);
                } else {
                    $userJpa->image_type = null;
                    $userJpa->image_mini = null;
                    $userJpa->image_full = null;
                }
            }

            $userJpa->update_date = gTrace::getDate('mysql');
            $userJpa->_update_user = $userid;
            $userJpa->status = "1";

            $userJpa->save();

            $response->setStatus(200);
            $response->setMessage('Usuario actualizado correctamente');
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
