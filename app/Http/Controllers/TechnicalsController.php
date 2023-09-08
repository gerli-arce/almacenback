<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\DetailSale;
use App\Models\People;
use App\Models\Product;
use App\Models\ProductByTechnical;
use App\Models\RecordProductByTechnical;
use App\Models\Response;
use App\Models\SalesProducts;
use App\Models\Stock;
use App\Models\ViewDetailsSales;
use App\Models\ViewPeople;
use App\Models\ViewProductByTechnical;
use App\Models\ViewSales;
use App\Models\ViewUsers;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TechnicalsController extends Controller
{
    public function search(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'technicals', 'read')) {
                throw new Exception('No tienes permisos para listar técnicos');
            }

            $peopleJpa = ViewPeople::select([
                'id',
                'type',
                'doc_number',
                'name',
                'lastname',
            ])->whereNotNull('status')
                ->WhereRaw("doc_number LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orWhereRaw("name LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orWhereRaw("lastname LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orderBy('doc_number', 'asc')
                ->where('type', 'TECHNICAL')
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($peopleJpa->toArray());
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

    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'technicals', 'create')) {
                throw new Exception('No tienes permisos para agregar técnicos');
            }

            if (
                !isset($request->doc_type) ||
                !isset($request->doc_number) ||
                !isset($request->name) ||
                !isset($request->lastname) ||
                !isset($request->_branch)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            if (strlen($request->doc_number) != 8) {
                throw new Exception("Para el tipo de documento DNI es nesesario que tenga 8 números.");
            }

            $userValidation = People::select(['doc_type', 'doc_number'])
                ->where('doc_type', $request->doc_type)
                ->where('doc_number', $request->doc_number)
                ->first();

            if ($userValidation) {
                throw new Exception("Esta registro ya existe");
            }

            $peopleJpa = new People();
            $peopleJpa->doc_type = $request->doc_type;
            $peopleJpa->doc_number = $request->doc_number;
            $peopleJpa->name = $request->name;
            $peopleJpa->lastname = $request->lastname;
            $peopleJpa->relative_id = guid::short();

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
                    $peopleJpa->image_type = $request->image_type;
                    $peopleJpa->image_mini = base64_decode($request->image_mini);
                    $peopleJpa->image_full = base64_decode($request->image_full);
                } else {
                    $peopleJpa->image_type = null;
                    $peopleJpa->image_mini = null;
                    $peopleJpa->image_full = null;
                }
            }

            if ($request->birthdate) {
                $peopleJpa->birthdate = $request->birthdate;
            }

            if ($request->gender) {
                $peopleJpa->gender = $request->gender;
            }

            if ($request->email) {
                $peopleJpa->email = $request->email;
            }

            if ($request->phone) {
                $peopleJpa->phone = $request->phone;
            }

            if ($request->ubigeo) {
                $peopleJpa->ubigeo = $request->ubigeo;
            }

            if ($request->address) {
                $peopleJpa->address = $request->address;
            }
            $peopleJpa->_creation_user = $userid;
            $peopleJpa->creation_date = gTrace::getDate('mysql');
            $peopleJpa->_update_user = $userid;
            $peopleJpa->update_date = gTrace::getDate('mysql');
            $peopleJpa->type = "TECHNICAL";
            $peopleJpa->_branch = $request->_branch;

            $peopleJpa->status = "1";

            $peopleJpa->save();

            $response->setStatus(200);
            $response->setMessage('Tecnico agregado correctamente');
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

    // public function registerProductByTechnical(Request $request)
    // {
    //     $response = new Response();
    //     try {
    //         [$branch, $status, $message, $role, $userid] = gValidate::get($request);
    //         if ($status != 200) {
    //             throw new Exception($message);
    //         }

    //         if (!gValidate::check($role->permissions, $branch, 'technicals', 'read')) {
    //             throw new Exception('No tienes permisos para crear productos');
    //         }

    //         if (
    //             !isset($request->id) ||
    //             !isset($request->details)
    //         ) {
    //             throw new Exception("Error: No deje campos vaciós");
    //         }

    //         $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

    //         foreach ($request->details as $product) {

    //             $recordProductByTechnicalJpa = new RecordProductByTechnical();
    //             $recordProductByTechnicalJpa->_user = $userid;
    //             $recordProductByTechnicalJpa->_technical = $request->id;
    //             $recordProductByTechnicalJpa->_product = $product['product']['id'];
    //             $recordProductByTechnicalJpa->type_operation = "AGREGADO";
    //             $recordProductByTechnicalJpa->date_operation = gTrace::getDate('mysql');
    //             $recordProductByTechnicalJpa->mount = $product['mount'];
    //             $recordProductByTechnicalJpa->description = $product['description'];
    //             $recordProductByTechnicalJpa->save();

    //             $productJpa = Product::find($product['product']['id']);

    //             $mount = $productJpa->mount - $product['mount'];
    //             $productJpa->mount = $mount;

    //             $stock = Stock::where('_model', $productJpa->_model)
    //                 ->where('_branch', $branch_->id)
    //                 ->first();
    //             $stock->mount_new = $mount;
    //             $stock->save();
    //             $productJpa->save();

    //             $productByTechnicalJpa = new ProductByTechnical();
    //             $productByTechnicalJpa->_technical = $request->id;
    //             $productByTechnicalJpa->_product = $product['product']['id'];
    //             $productByTechnicalJpa->mount = $product['mount'];
    //             $productByTechnicalJpa->description = $product['description'];
    //             $productByTechnicalJpa->save();
    //         }
    //         $response->setStatus(200);
    //         $response->setMessage('Productos agregados correctamente al stock del técnico');
    //     } catch (\Throwable $th) {
    //         $response->setStatus(400);
    //         $response->setMessage($th->getMessage() . 'ln' . $th->getLine());
    //     } finally {
    //         return response(
    //             $response->toArray(),
    //             $response->getStatus()
    //         );
    //     }
    // }

    public function addStockTechnicalByProduct(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'technicals', 'update')) {
                throw new Exception('No tienes permisos para crear productos');
            }

            if (
                !isset($request->product) ||
                !isset($request->technical)
            ) {
                throw new Exception("Error: No deje campos vaciós");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_technical = $request->technical['id'];
            $salesProduct->_type_operation = "10";
            $salesProduct->type_intallation = "AGREGADO_A_STOCK";
            $salesProduct->date_sale = gTrace::getDate('mysql');
            $salesProduct->status_sale = "AGREGADO";
            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            $detailSale = new DetailSale();
            $detailSale->_product = $request->product['id'];
            $detailSale->mount_new = $request->mount_new;
            $detailSale->mount_second = $request->mount_second;
            $detailSale->mount_ill_fated = $request->mount_ill_fated;
            $detailSale->_sales_product = $salesProduct->id;
            $detailSale->status = '1';
            $detailSale->save();

            $productByTechnicalJpa = ProductByTechnical::where('_technical', $request->technical['id'])
                ->where('_model', $request->product['model']['id'])->first();

            $productByTechnicalJpa->mount_new = $productByTechnicalJpa->mount_new + $request->mount_new;
            $productByTechnicalJpa->mount_second = $productByTechnicalJpa->mount_second + $request->mount_second;
            $productByTechnicalJpa->mount_ill_fated = $productByTechnicalJpa->mount_ill_fated + $request->mount_ill_fated;

            $productJpa = Product::find($request->product['id']);

            $stock = Stock::where('_model', $productJpa->_model)
                ->where('_branch', $branch_->id)
                ->first();

            $stock->mount_new = $stock->mount_new - $request->mount_new;
            $stock->mount_second = $stock->mount_second - $request->mount_second;
            $stock->mount_ill_fated = $stock->mount_ill_fated - $request->mount_ill_fated;
            $stock->save();

            $productJpa->mount = $stock->mount_new + $stock->mount_second;

            $productJpa->save();

            $productByTechnicalJpa->save();
            $response->setStatus(200);
            $response->setMessage('Productos agregados correctamente al stock del técnico');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function addEPPTechnicalByProduct(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'technicals', 'update')) {
                throw new Exception('No tienes permisos para crear productos');
            }

            if (
                !isset($request->product) ||
                !isset($request->technical)
            ) {
                throw new Exception("Error: No deje campos vaciós");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_technical = $request->technical['id'];
            $salesProduct->_type_operation = "10";
            $salesProduct->type_intallation = "AGREGADO_A_STOCK";
            $salesProduct->date_sale = gTrace::getDate('mysql');
            $salesProduct->status_sale = "AGREGADO";
            $salesProduct->type_products = "EPP";
            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            $detailSale = new DetailSale();
            $detailSale->_product = $request->product['id'];
            $detailSale->mount_new = $request->mount_new;
            $detailSale->mount_second = $request->mount_second;
            $detailSale->mount_ill_fated = $request->mount_ill_fated;
            $detailSale->_sales_product = $salesProduct->id;
            $detailSale->status = '1';
            $detailSale->save();

            $productByTechnicalJpa = ProductByTechnical::where('_technical', $request->technical['id'])
                ->where('_model', $request->product['model']['id'])->first();

            $productByTechnicalJpa->mount_new = $productByTechnicalJpa->mount_new + $request->mount_new;
            $productByTechnicalJpa->mount_second = $productByTechnicalJpa->mount_second + $request->mount_second;
            $productByTechnicalJpa->mount_ill_fated = $productByTechnicalJpa->mount_ill_fated + $request->mount_ill_fated;

            $productJpa = Product::find($request->product['id']);

            $stock = Stock::where('_model', $productJpa->_model)
                ->where('_branch', $branch_->id)
                ->first();

            $stock->mount_new = $stock->mount_new - $request->mount_new;
            $stock->mount_second = $stock->mount_second - $request->mount_second;
            $stock->mount_ill_fated = $stock->mount_ill_fated - $request->mount_ill_fated;
            $stock->save();

            $productJpa->mount = $stock->mount_new + $stock->mount_second;

            $productJpa->save();

            $productByTechnicalJpa->save();
            $response->setStatus(200);
            $response->setMessage('Productos agregados correctamente al stock del técnico');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function recordTakeOutProductByTechnical(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'technicals', 'update')) {
                throw new Exception('No tienes permisos para actualizar productos de técnico');
            }

            if (
                !isset($request->product) ||
                !isset($request->technical) ||
                !isset($request->reazon)
            ) {
                throw new Exception("Error: No deje campos vaciós");
            }

            $productByTechnicalJpa = ProductByTechnical::where('_technical', $request->technical['id'])
                ->where('_product', $request->product['id'])
                ->first();

            $productByTechnicalJpa->mount_new = $productByTechnicalJpa->mount_new - $request->mount_new;
            $productByTechnicalJpa->mount_second = $productByTechnicalJpa->mount_second - $request->mount_second;
            $productByTechnicalJpa->mount_ill_fated = $productByTechnicalJpa->mount_ill_fated - $request->mount_ill_fated;

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_technical = $request->technical['id'];
            $salesProduct->_type_operation = "10";
            $salesProduct->type_intallation = "SACADO_DE_STOCK";
            $salesProduct->date_sale = gTrace::getDate('mysql');
            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";

            if ($request->reazon == "ILLFATED") {
                $salesProduct->status_sale = "MALOGRADO";
            } else if ($request->reazon == "STORE") {
                $salesProduct->status_sale = "USO EN ALMACEN";
            } else if ($request->reazon == "RETURN") {
                $salesProduct->status_sale = "DEVOLUCION";
                $productJpa = Product::find($request->product['id']);
                $stock = Stock::where('_model', $productJpa->_model)
                    ->where('_branch', $branch_->id)
                    ->first();
                $stock->mount_new = $stock->mount_new + $request->mount_new;
                $stock->mount_second = $stock->mount_second + $request->mount_second;
                $stock->mount_ill_fated = $stock->mount_ill_fated + $request->mount_ill_fated;
                $stock->save();
                $productJpa->mount = $stock->mount_new + $stock->mount_second;
                $productJpa->save();
            } else if ($request->reazon == "DISCOUNT") {
                $salesProduct->status_sale = "DESCUENTO MALOGRADO-NO-JUSTIFICCADO";
            }
            $salesProduct->save();

            $detailSale = new DetailSale();
            $detailSale->_product = $request->product['id'];
            $detailSale->mount_new = $request->mount_new;
            $detailSale->mount_second = $request->mount_second;
            $detailSale->mount_ill_fated = $request->mount_ill_fated;
            $detailSale->_sales_product = $salesProduct->id;
            $detailSale->status = '1';
            $detailSale->save();

            $productByTechnicalJpa->save();
            $response->setStatus(200);
            $response->setMessage('Salida de productos registrados correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function recordTakeOutEPPByTechnical(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'technicals', 'update')) {
                throw new Exception('No tienes permisos para actualizar productos de técnico');
            }

            if (
                !isset($request->product) ||
                !isset($request->technical) ||
                !isset($request->reazon)
            ) {
                throw new Exception("Error: No deje campos vaciós");
            }

            $productByTechnicalJpa = ProductByTechnical::where('_technical', $request->technical['id'])
                ->where('_product', $request->product['id'])
                ->first();

            $productByTechnicalJpa->mount_new = $productByTechnicalJpa->mount_new - $request->mount_new;
            $productByTechnicalJpa->mount_second = $productByTechnicalJpa->mount_second - $request->mount_second;
            $productByTechnicalJpa->mount_ill_fated = $productByTechnicalJpa->mount_ill_fated - $request->mount_ill_fated;

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_technical = $request->technical['id'];
            $salesProduct->_type_operation = "10";
            $salesProduct->type_intallation = "SACADO_DE_STOCK";
            $salesProduct->date_sale = gTrace::getDate('mysql');
            $salesProduct->type_products = "EPP";
            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";

            if ($request->reazon == "ILLFATED") {
                $salesProduct->status_sale = "MALOGRADO";
            } else if ($request->reazon == "STORE") {
                $salesProduct->status_sale = "USO EN ALMACEN";
            } else if ($request->reazon == "RETURN") {
                $salesProduct->status_sale = "DEVOLUCION";
                $productJpa = Product::find($request->product['id']);
                $stock = Stock::where('_model', $productJpa->_model)
                    ->where('_branch', $branch_->id)
                    ->first();
                $stock->mount_new = $stock->mount_new + $request->mount_new;
                $stock->mount_second = $stock->mount_second + $request->mount_second;
                $stock->mount_ill_fated = $stock->mount_ill_fated + $request->mount_ill_fated;
                $stock->save();
                $productJpa->mount = $stock->mount_new + $stock->mount_second;
                $productJpa->save();
            } else if ($request->reazon == "DISCOUNT") {
                $salesProduct->status_sale = "DESCUENTO MALOGRADO-NO-JUSTIFICCADO";
            }
            $salesProduct->save();

            $detailSale = new DetailSale();
            $detailSale->_product = $request->product['id'];
            $detailSale->mount_new = $request->mount_new;
            $detailSale->mount_second = $request->mount_second;
            $detailSale->mount_ill_fated = $request->mount_ill_fated;
            $detailSale->_sales_product = $salesProduct->id;
            $detailSale->status = '1';
            $detailSale->save();

            $productByTechnicalJpa->save();
            $response->setStatus(200);
            $response->setMessage('Salida de productos registrados correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function getProductsByTechnical(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'technicals', 'read')) {
                throw new Exception('No tienes permisos para listar productos');
            }

            $productsJpa = ViewProductByTechnical::where('technical__id', $request->id)->whereNotNull('status')->get();

            $products = array();
            foreach ($productsJpa as $productJpa) {
                $product = gJSON::restore($productJpa->toArray(), '__');
                $products[] = $product;
            }
            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($products);
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

    public function getProductsByTechnicalStock(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'technicals', 'read')) {
                throw new Exception('No tienes permisos para listar productos');
            }

            $productsJpa = ViewProductByTechnical::where('technical__id', $request->id)->where('type', 'PRODUCTO')->get();

            $products = array();
            foreach ($productsJpa as $productJpa) {
                $product = gJSON::restore($productJpa->toArray(), '__');
                $products[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($products);
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

    public function getEpp(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);

            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'technicals', 'read')) {
                throw new Exception('No tienes permisos para listar productos');
            }

            $productsJpa = ViewProductByTechnical::where('technical__id', $request->id)->where('type', 'EPP')->get();

            $products = array();
            foreach ($productsJpa as $productJpa) {
                $product = gJSON::restore($productJpa->toArray(), '__');
                $products[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($products);
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

    public function getRecordProductsByTechnical(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'technicals', 'read')) {
                throw new Exception('No tienes permisos para actualizar personas');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $query = RecordProductByTechnical::select([
                'record_product_by_technical.id as id',
                'users.id as user__id',
                'users.username as user__username',
                'record_product_by_technical._technical',
                'products.id as product__id',
                'models.id as product__model__id',
                'models.model as product__model__model',
                'models.relative_id as product__model__relative_id',
                'record_product_by_technical.type_operation as type_operation',
                'record_product_by_technical.date_operation as date_operation',
                'record_product_by_technical.mount as mount',
                'record_product_by_technical.description as description',
            ])
                ->join('users', 'record_product_by_technical._user', 'users.id')
                ->join('products', 'record_product_by_technical._product', 'products.id')
                ->join('models', 'products._model', 'models.id');

            $query = $query->orderBy('id', 'desc')
                ->where('record_product_by_technical._technical', $request->id);

            if (isset($request->date_start) && isset($request->date_end) && isset($request->reazon)) {
                $query = $query->where('record_product_by_technical.date_operation', '>=', $request->date_start)
                    ->where('record_product_by_technical.date_operation', '<=', $request->date_end);
                if ($request->reazon != '*') {
                    if ($request->reazon == "ILLFATED") {
                        $query = $query->where('record_product_by_technical.type_operation', 'MALOGRADO');
                    } else if ($request->reazon == "STORE") {
                        $query = $query->where('record_product_by_technical.type_operation', 'USO EN ALMACEN');
                    } else if ($request->reazon == "RETURN") {
                        $query = $query->where('record_product_by_technical.type_operation', 'DEVOLUCION');
                    } else if ($request->reazon == "DISCOUNT") {
                        $query = $query->where('record_product_by_technical.type_operation', 'DESCUENTO MALOGRADO-NO-JUSTIFICCADO');
                    } else if ($request->reazon == "ADD") {
                        $query = $query->where('record_product_by_technical.type_operation', 'AGREGADO');
                    }
                }
            }

            $recordProducts = $query->get();

            $records = array();
            foreach ($recordProducts as $recordJpa) {
                $record = gJSON::restore($recordJpa->toArray(), '__');
                $records[] = $record;
            }

            $response->setData($records);
            $response->setStatus(200);
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

    public function paginate(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'technicals', 'read')) {
                throw new Exception('No tienes permisos para listar técnicos');
            }

            $query = ViewPeople::select([
                'id',
                'doc_type',
                'doc_number',
                'name',
                'lastname',
                'relative_id',
                'birthdate',
                'gender',
                'email',
                'phone',
                'ubigeo',
                'address',
                'type',
                'branch__id',
                'branch__name',
                'branch__correlative',
                'branch__ubigeo',
                'branch__address',
                'branch__description',
                'branch__status',
                'user_creation__username',
                'user_creation__relative_id',
                'creation_date',
                'user_update__id',
                'user_update__username',
                'user_update__relative_id',
                'update_date',
                'status',
            ])
                ->orderBy($request->order['column'], $request->order['dir'])
                ->where('type', 'TECHNICAL')
                ->where('branch__correlative', $branch);

            // if (!$request->all || !gValidate::check($role->permissions, 'views', 'see_trash')) {
            // }

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'doc_type' || $column == '*') {
                    $q->orWhere('doc_type', $type, $value);
                }
                if ($column == 'doc_number' || $column == '*') {
                    $q->orWhere('doc_number', $type, $value);
                }
                if ($column == 'name' || $column == '*') {
                    $q->orWhere('name', $type, $value);
                }
                if ($column == 'lastname' || $column == '*') {
                    $q->orWhere('lastname', $type, $value);
                }
                if ($column == 'birthdate' || $column == '*') {
                    $q->orWhere('birthdate', $type, $value);
                }
                if ($column == 'gender' || $column == '*') {
                    $q->orWhere('gender', $type, $value);
                }
                if ($column == 'email' || $column == '*') {
                    $q->orWhere('email', $type, $value);
                }
                if ($column == 'phone' || $column == '*') {
                    $q->orWhere('phone', $type, $value);
                }
                if ($column == 'ubigeo' || $column == '*') {
                    $q->orWhere('ubigeo', $type, $value);
                }
                if ($column == 'address' || $column == '*') {
                    $q->orWhere('address', $type, $value);
                }
                if ($column == 'branch__name' || $column == '*') {
                    $q->orWhere('branch__name', $type, $value);
                }
                if ($column == 'status' || $column == '*') {
                    $q->orWhere('status', $type, $value);
                }
            });
            $iTotalDisplayRecords = $query->count();

            $peopleJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $people = array();
            foreach ($peopleJpa as $personJpa) {
                $person = gJSON::restore($personJpa->toArray(), '__');
                $people[] = $person;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewPeople::where('type', 'TECHNICAL')->where('branch__correlative', $branch)->count());
            $response->setData($people);
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

    public function update(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'technicals', 'update')) {
                throw new Exception('No tienes permisos para actualizar personas');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $personJpa = People::find($request->id);

            if (!$personJpa) {
                throw new Exception("Esta persona no existe");
            }

            if (isset($request->doc_type) && isset($request->doc_number)) {
                if ($request->doc_type == "RUC" && $request->doc_type == "RUC10") {
                    if (strlen($request->doc_number) != 11) {
                        throw new Exception("Para el tipo de documento RUC es nesesario que tenga 11 números.");
                    }
                }
                if ($request->doc_type == "DNI") {
                    if (strlen($request->doc_number) != 8) {
                        throw new Exception("Para el tipo de documento DNI es nesesario que tenga 8 números.");
                    }
                }
                $personJpa->doc_type = $request->doc_type;
                $personJpa->doc_number = $request->doc_number;
            }

            $userValidation = People::select(['id', 'doc_type', 'doc_number'])
                ->where('doc_type', $request->doc_type)
                ->where('doc_number', $request->doc_number)
                ->where('id', '!=', $request->id)
                ->first();

            if ($userValidation) {
                throw new Exception("Esta persona ya existe");
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
                    $personJpa->image_type = $request->image_type;
                    $personJpa->image_mini = base64_decode($request->image_mini);
                    $personJpa->image_full = base64_decode($request->image_full);
                } else {
                    $personJpa->image_type = null;
                    $personJpa->image_mini = null;
                    $personJpa->image_full = null;
                }
            }

            if (isset($request->name)) {
                $personJpa->name = $request->name;
            }

            if (isset($request->lastname)) {
                $personJpa->lastname = $request->lastname;
            }

            if (isset($request->birthdate)) {
                $personJpa->birthdate = $request->birthdate;
            }

            if (isset($request->gender)) {
                $personJpa->gender = $request->gender;
            }

            if (isset($request->email)) {
                $personJpa->email = $request->email;
            }

            if (isset($request->phone)) {
                $personJpa->phone = $request->phone;
            }

            if (isset($request->ubigeo)) {
                $personJpa->ubigeo = $request->ubigeo;
            }

            if (isset($request->address)) {
                $personJpa->address = $request->address;
            }

            if (isset($request->_branch)) {
                $personJpa->_branch = $request->_branch;
            }

            if (gValidate::check($role->permissions, $branch, 'technicals', 'change_status')) {
                if (isset($request->status)) {
                    $personJpa->status = $request->status;
                }
            }

            $personJpa->_update_user = $userid;
            $personJpa->update_date = gTrace::getDate('mysql');

            $personJpa->save();

            $response->setStatus(200);
            $response->setMessage('La persona se a actualizado correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'technicals', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar técnicos');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: Es necesario el ID para esta operación");
            }

            $personJpa = People::find($request->id);

            if (!$personJpa) {
                throw new Exception("Este reguistro no existe");
            }

            $personJpa->_update_user = $userid;
            $personJpa->update_date = gTrace::getDate('mysql');
            $personJpa->status = null;
            $personJpa->save();

            $response->setStatus(200);
            $response->setMessage('Técnico se a eliminado correctamente');
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

    public function changeStatusStockTechnical(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'technicals', 'update')) {
                throw new Exception('No tienes permisos para actualizar estado de productos');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: Es necesario el ID para esta operación");
            }

            $ProductByTechnicalJpa = ProductByTechnical::find($request->id);

            if (!$ProductByTechnicalJpa) {
                throw new Exception("Este reguistro no existe");
            }

            if ($request->status == 1) {
                $ProductByTechnicalJpa->status = null;
            } else {
                $ProductByTechnicalJpa->status = 1;
            }

            $ProductByTechnicalJpa->save();

            $response->setStatus(200);
            $response->setMessage('Registro actualizado correctamente');
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

    public function registersPrductsByTechnicals(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'technicals', 'update')) {
                throw new Exception('No tienes permisos para crear agregar productos a stock de técnicos');
            }

            if (
                !isset($request->id) ||
                !isset($request->details)
            ) {
                throw new Exception("Error: No deje campos vaciós");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_technical = $request->id;
            $salesProduct->_type_operation = "10";
            $salesProduct->type_intallation = "AGREGADO_A_STOCK";
            $salesProduct->date_sale = gTrace::getDate('mysql');
            $salesProduct->status_sale = "AGREGADO";
            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            foreach ($request->details as $product) {
                $productJpa = Product::find($product['product']['id']);
                $stock = Stock::where('_model', $productJpa->_model)
                    ->where('_branch', $branch_->id)
                    ->first();

                if ($productJpa->type == 'MATERIAL') {
                    $stock->mount_new = $stock->mount_new - $product['mount_new'];
                    $stock->mount_second = $stock->mount_second - $product['mount_second'];
                    $stock->mount_ill_fated = $stock->mount_ill_fated - $product['mount_ill_fated'];

                    $productJpa->mount = $stock->mount_new + $stock->mount_second;
                    $stock->save();
                    $productJpa->save();

                    $productByTechnicalJpa = ProductByTechnical::where('_technical', $request->id)
                    ->where('_model', $product['product']['model']['id'])->first();
                    if ($productByTechnicalJpa) {
                        $productByTechnicalJpa->mount_new = $productByTechnicalJpa->mount_new + $product['mount_new'];
                        $productByTechnicalJpa->mount_second = $productByTechnicalJpa->mount_second + $product['mount_second'];
                        $productByTechnicalJpa->mount_ill_fated = $productByTechnicalJpa->mount_ill_fated + $product['mount_ill_fated'];
                        $productByTechnicalJpa->save();
                    } else {
                        $productByTechnicalJpaNew = new ProductByTechnical();
                        $productByTechnicalJpaNew->_technical = $request->id;
                        $productByTechnicalJpaNew->_product = $productJpa->id;
                        $productByTechnicalJpaNew->type = $request->type;
                        $productByTechnicalJpaNew->mount_new = $product['mount_new'];
                        $productByTechnicalJpaNew->mount_second = $product['mount_second'];
                        $productByTechnicalJpaNew->mount_ill_fated = $product['mount_ill_fated'];
                        $productByTechnicalJpaNew->description = $product['description'];
                        $productByTechnicalJpaNew->save();
                    }
                } else {
                    $productJpa->disponibility = "En stok de: " . $request->name . ' ' . $request->lastname;
                    $productJpa->save();
                    if ($productJpa->product_status == "NUEVO") {
                        $stock->mount_new = $stock->mount_new - 1;
                    } else if ($productJpa->product_status == "SEMINUEVO") {
                        $stock->mount_second = $stock->mount_second - 1;
                    }
                    $stock->save();
                    $productByTechnicalJpaNew = new ProductByTechnical();
                    $productByTechnicalJpaNew->_technical = $request->id;
                    // $productByTechnicalJpaNew->_product = $productJpa->id;
                    $productByTechnicalJpaNew->_model = $productJpa->_model;
                    $productByTechnicalJpaNew->type = $request->type;
                    $productByTechnicalJpaNew->mount_new = $product['mount_new'];
                    $productByTechnicalJpaNew->mount_second = $product['mount_second'];
                    $productByTechnicalJpaNew->mount_ill_fated = $product['mount_ill_fated'];
                    $productByTechnicalJpaNew->description = $product['description'];
                    $productByTechnicalJpaNew->save();
                }

                $detailSale = new DetailSale();
                $detailSale->_product = $productJpa->id;
                $detailSale->mount_new = $product['mount_new'];
                $detailSale->mount_second = $product['mount_second'];
                $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                $detailSale->description = $product['description'];
                $detailSale->_sales_product = $salesProduct->id;
                $detailSale->status = '1';
                $detailSale->save();
            }
            $response->setStatus(200);
            $response->setMessage('Productos agregados correctamente al stock del técnico');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function registersEPPsByTechnicals(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'technicals', 'update')) {
                throw new Exception('No tienes permisos para crear agregar productos a stock de técnicos');
            }

            if (
                !isset($request->id) ||
                !isset($request->details)
            ) {
                throw new Exception("Error: No deje campos vaciós");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_technical = $request->id;
            $salesProduct->_type_operation = "10";
            $salesProduct->type_intallation = "AGREGADO_A_STOCK";
            $salesProduct->date_sale = gTrace::getDate('mysql');
            $salesProduct->status_sale = "AGREGADO";
            $salesProduct->type_products = "EPP";
            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            foreach ($request->details as $product) {
                $productJpa = Product::find($product['product']['id']);
                $stock = Stock::where('_model', $productJpa->_model)
                    ->where('_branch', $branch_->id)
                    ->first();

                if ($productJpa->type == 'MATERIAL') {
                    $stock->mount_new = $stock->mount_new - $product['mount_new'];
                    $stock->mount_second = $stock->mount_second - $product['mount_second'];
                    $stock->mount_ill_fated = $stock->mount_ill_fated - $product['mount_ill_fated'];

                    $productJpa->mount = $stock->mount_new + $stock->mount_second;
                    $stock->save();
                    $productJpa->save();

                    $productByTechnicalJpa = ProductByTechnical::where('_technical', $request->id)
                    ->where('_model', $product['product']['model']['id'])->first();
                    if ($productByTechnicalJpa) {
                        $productByTechnicalJpa->mount_new = $productByTechnicalJpa->mount_new + $product['mount_new'];
                        $productByTechnicalJpa->mount_second = $productByTechnicalJpa->mount_second + $product['mount_second'];
                        $productByTechnicalJpa->mount_ill_fated = $productByTechnicalJpa->mount_ill_fated + $product['mount_ill_fated'];
                        $productByTechnicalJpa->save();
                    } else {
                        $productByTechnicalJpaNew = new ProductByTechnical();
                        $productByTechnicalJpaNew->_technical = $request->id;
                        $productByTechnicalJpaNew->_product = $productJpa->id;
                        $productByTechnicalJpaNew->type = $request->type;
                        $productByTechnicalJpaNew->mount_new = $product['mount_new'];
                        $productByTechnicalJpaNew->mount_second = $product['mount_second'];
                        $productByTechnicalJpaNew->mount_ill_fated = $product['mount_ill_fated'];
                        $productByTechnicalJpaNew->description = $product['description'];
                        $productByTechnicalJpaNew->save();
                    }
                } else {
                    $productJpa->disponibility = "En stok de: " . $request->name . ' ' . $request->lastname;
                    $productJpa->save();
                    if ($productJpa->product_status == "NUEVO") {
                        $stock->mount_new = $stock->mount_new - 1;
                    } else if ($productJpa->product_status == "SEMINUEVO") {
                        $stock->mount_second = $stock->mount_second - 1;
                    }
                    $stock->save();
                    $productByTechnicalJpaNew = new ProductByTechnical();
                    $productByTechnicalJpaNew->_technical = $request->id;
                    $productByTechnicalJpaNew->_product = $productJpa->id;
                    $productByTechnicalJpaNew->type = $request->type;
                    $productByTechnicalJpaNew->mount_new = $product['mount_new'];
                    $productByTechnicalJpaNew->mount_second = $product['mount_second'];
                    $productByTechnicalJpaNew->mount_ill_fated = $product['mount_ill_fated'];
                    $productByTechnicalJpaNew->description = $product['description'];
                    $productByTechnicalJpaNew->save();
                }

                $detailSale = new DetailSale();
                $detailSale->_product = $productJpa->id;
                $detailSale->mount_new = $product['mount_new'];
                $detailSale->mount_second = $product['mount_second'];
                $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                $detailSale->description = $product['description'];
                $detailSale->_sales_product = $salesProduct->id;
                $detailSale->status = '1';
                $detailSale->save();
            }
            $response->setStatus(200);
            $response->setMessage('Productos agregados correctamente al stock del técnico');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function paginateRecords(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'technicals', 'read')) {
                throw new Exception('No tienes permisos para listar las salidas');
            }

            $query = ViewSales::select([
                'view_sales.id as id',
                'view_sales.client_id as client_id',
                'view_sales.technical_id as technical_id',
                'view_sales.branch__id as branch__id',
                'view_sales.branch__name as branch__name',
                'view_sales.branch__correlative	 as branch__correlative',
                'view_sales.type_operation__id	 as type_operation__id',
                'view_sales.type_operation__operation	 as type_operation__operation',
                'view_sales.tower_id as tower_id',
                'view_sales.plant_id as plant_id',
                'view_sales.room_id as room_id',
                'view_sales.type_intallation as type_intallation',
                'view_sales.date_sale as date_sale',
                'view_sales.issue_date as issue_date',
                'view_sales.issue_user_id as issue_user_id',
                'view_sales.status_sale as status_sale',
                'view_sales.description as description',
                'view_sales.user_creation__id as user_creation__id',
                'view_sales.user_creation__username as user_creation__username',
                'view_sales.user_creation__person__id as user_creation__person__id',
                'view_sales.user_creation__person__name as user_creation__person__name',
                'view_sales.user_creation__person__lastname as user_creation__person__lastname',
                'view_sales.creation_date as creation_date',
                'view_sales.update_user_id as update_user_id',
                'view_sales.update_date as update_date',
                'view_sales.status as status',
            ])
                ->leftJoin('view_details_sales', 'view_sales.id', '=', 'view_details_sales.sale_product_id')
                ->orderBy('view_sales.' . $request->order['column'], $request->order['dir'])
                ->where('technical_id', $request->search['technical'])
                ->whereNotNUll('view_sales.status')
                ->where('branch__correlative', $branch);

            if (isset($request->search['model'])) {
                $query
                    ->where('view_details_sales.product__model__id', $request->search['model'])
                    ->where('type_intallation', 'AGREGADO_A_STOCK')
                    ->orWhere(function ($q) use ($request) {

                        $q->where('view_details_sales.product__model__id', $request->search['model'])
                            ->where('technical_id', $request->search['technical'])
                            ->where('type_intallation', 'AGREGADO_A_STOCK');
                    })
                    ->orWhere(function ($q) use ($request) {
                        $q->where('view_details_sales.product__model__id', $request->search['model'])
                            ->where('technical_id', $request->search['technical'])
                            ->where('type_intallation', 'SACADO_DE_STOCK');
                    });
            } else {
                $query->where('type_intallation', 'AGREGADO_A_STOCK')
                    ->orWhere('type_intallation', 'SACADO_DE_STOCK');
            }

            $query->where('type_operation__id', '10');

            if (isset($request->search['date_start']) || isset($request->search['date_end'])) {
                $dateStart = date('Y-m-d', strtotime($request->search['date_start']));
                $dateEnd = date('Y-m-d', strtotime($request->search['date_end']));
                $query->where('date_sale', '>=', $dateStart)
                    ->where('date_sale', '<=', $dateEnd);
            }

            $iTotalDisplayRecords = $query->count();

            $salesJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $sales = array();
            foreach ($salesJpa as $saleJpa) {
                $sale = gJSON::restore($saleJpa->toArray(), '__');
                $detailSalesJpa = ViewDetailsSales::select(['*'])->whereNotNull('status')->where('sale_product_id', $sale['id'])->get();
                $details = array();
                foreach ($detailSalesJpa as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                }
                $sale['details'] = $details;
                $sales[] = $sale;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewSales::where('branch__correlative', $branch)->whereNotNUll('status')
                    ->where('branch__correlative', $branch)
                    ->where('technical_id', $request->id)
                    ->where('type_intallation', 'AGREGADO_A_STOCK')
                    ->where('type_operation__id', '10')->count());
            $response->setData($sales);
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

    public function paginateRecordsEpp(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'technicals', 'read')) {
                throw new Exception('No tienes permisos para listar las salidas');
            }

            $query = ViewSales::select([
                'view_sales.id as id',
                'view_sales.client_id as client_id',
                'view_sales.technical_id as technical_id',
                'view_sales.branch__id as branch__id',
                'view_sales.branch__name as branch__name',
                'view_sales.branch__correlative	 as branch__correlative',
                'view_sales.type_operation__id	 as type_operation__id',
                'view_sales.type_operation__operation	 as type_operation__operation',
                'view_sales.tower_id as tower_id',
                'view_sales.plant_id as plant_id',
                'view_sales.room_id as room_id',
                'view_sales.type_intallation as type_intallation',
                'view_sales.date_sale as date_sale',
                'view_sales.issue_date as issue_date',
                'view_sales.issue_user_id as issue_user_id',
                'view_sales.status_sale as status_sale',
                'view_sales.description as description',
                'view_sales.user_creation__id as user_creation__id',
                'view_sales.user_creation__username as user_creation__username',
                'view_sales.user_creation__person__id as user_creation__person__id',
                'view_sales.user_creation__person__name as user_creation__person__name',
                'view_sales.user_creation__person__lastname as user_creation__person__lastname',
                'view_sales.creation_date as creation_date',
                'view_sales.update_user_id as update_user_id',
                'view_sales.update_date as update_date',
                'view_sales.status as status',
            ])
                ->leftJoin('view_details_sales', 'view_sales.id', '=', 'view_details_sales.sale_product_id')
                ->orderBy('view_sales.' . $request->order['column'], $request->order['dir'])
                ->where('technical_id', $request->search['technical'])
                ->whereNotNUll('view_sales.status')
                ->where('branch__correlative', $branch)
                ->where('type_products', 'EPP');

            $query->where('type_operation__id', '10');

            if (isset($request->search['date_start']) || isset($request->search['date_end'])) {
                $dateStart = date('Y-m-d', strtotime($request->search['date_start']));
                $dateEnd = date('Y-m-d', strtotime($request->search['date_end']));
                $query->where('date_sale', '>=', $dateStart)
                    ->where('date_sale', '<=', $dateEnd);
            }

            $iTotalDisplayRecords = $query->count();

            $salesJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $sales = array();
            foreach ($salesJpa as $saleJpa) {
                $sale = gJSON::restore($saleJpa->toArray(), '__');
                $detailSalesJpa = ViewDetailsSales::select(['*'])->whereNotNull('status')->where('sale_product_id', $sale['id'])->get();
                $details = array();
                foreach ($detailSalesJpa as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                }
                $sale['details'] = $details;
                $sales[] = $sale;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewSales::where('branch__correlative', $branch)->whereNotNUll('status')
                    ->where('branch__correlative', $branch)
                    ->where('technical_id', $request->id)
                    ->where('type_intallation', 'AGREGADO_A_STOCK')
                    ->where('type_operation__id', '10')->count());
            $response->setData($sales);
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

    public function generateReportBySearch(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'technicals', 'read')) {
                throw new Exception('No tienes permisos para listar registros de técnicos');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportRecordsTechnicals.html');

            if (
                !isset($request->date_start) ||
                !isset($request->date_end)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }
            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();
            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();
            $dat_technical = People::find($request->technical);

            $query = ViewSales::select([
                'view_sales.id as id',
                'view_sales.client_id as client_id',
                'view_sales.technical_id as technical_id',
                'view_sales.branch__id as branch__id',
                'view_sales.branch__name as branch__name',
                'view_sales.branch__correlative	 as branch__correlative',
                'view_sales.type_operation__id	 as type_operation__id',
                'view_sales.type_operation__operation	 as type_operation__operation',
                'view_sales.tower_id as tower_id',
                'view_sales.plant_id as plant_id',
                'view_sales.room_id as room_id',
                'view_sales.type_intallation as type_intallation',
                'view_sales.date_sale as date_sale',
                'view_sales.issue_date as issue_date',
                'view_sales.issue_user_id as issue_user_id',
                'view_sales.status_sale as status_sale',
                'view_sales.description as description',
                'view_sales.user_creation__id as user_creation__id',
                'view_sales.user_creation__username as user_creation__username',
                'view_sales.user_creation__person__id as user_creation__person__id',
                'view_sales.user_creation__person__name as user_creation__person__name',
                'view_sales.user_creation__person__lastname as user_creation__person__lastname',
                'view_sales.creation_date as creation_date',
                'view_sales.update_user_id as update_user_id',
                'view_sales.update_date as update_date',
                'view_sales.status as status',
            ])
                ->leftJoin('view_details_sales', 'view_sales.id', '=', 'view_details_sales.sale_product_id')
                ->orderBy('view_sales.id', 'desc')
                ->where('view_sales.technical_id', $request->technical)
                ->whereNotNUll('view_sales.status')
                ->where('branch__correlative', $branch);

            if (isset($request->model)) {
                $query
                    ->where('view_details_sales.product__model__id', $request->model)
                    ->where('type_intallation', 'AGREGADO_A_STOCK')
                    ->orWhere(function ($q) use ($request) {

                        $q->where('view_details_sales.product__model__id', $request->model)
                            ->where('technical_id', $request->technical)
                            ->where('type_intallation', 'AGREGADO_A_STOCK');
                    })
                    ->orWhere(function ($q) use ($request) {
                        $q->where('view_details_sales.product__model__id', $request->model)
                            ->where('technical_id', $request->technical)
                            ->where('type_intallation', 'SACADO_DE_STOCK');
                    });
            } else {
                $query->where('view_sales.type_intallation', 'AGREGADO_A_STOCK')
                    ->orWhere('view_sales.type_intallation', 'SACADO_DE_STOCK');
            }

            $query->where('view_sales.type_operation__id', 10);

            if (isset($request->date_start) || isset($request->date_end)) {
                $dateStart = date('Y-m-d', strtotime($request->date_start));
                $dateEnd = date('Y-m-d', strtotime($request->date_end));
                $query->whereBetween('view_sales.date_sale', [$dateStart, $dateEnd]);
            }

            $iTotalDisplayRecords = $query->count();

            $salesJpa = $query->get();

            $sales = array();
            foreach ($salesJpa as $saleJpa) {
                $sale = gJSON::restore($saleJpa->toArray(), '__');
                $detailSalesJpa = ViewDetailsSales::select(['*'])->whereNotNull('status')->where('sale_product_id', $sale['id'])->get();
                $details = array();
                foreach ($detailSalesJpa as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                }
                $sale['details'] = $details;
                $sales[] = $sale;
            }

            $count = 1;
            $view_details = '';
            $sumary = '';
            foreach ($sales as $sale) {

                $technical_details = "";
                $saleProductJpa = SalesProducts::select([
                    'sales_products.id as id',
                    'tech.id as technical__id',
                    'tech.name as technical__name',
                    'tech.lastname as technical__lastname',
                    'sales_products.date_sale as date_sale',
                    'sales_products.status_sale as status_sale',
                    'sales_products.description as description',
                    'sales_products.status as status',
                ])
                    ->join('people as tech', 'sales_products._technical', 'tech.id')
                    ->where('sales_products.id', $sale['id'])->first();

                $technical_details = "
                    <div>
                        <p>Técnico: <strong>{$saleProductJpa->technical__name} {$saleProductJpa->technical__lastname}</strong></p>
                        <p>Fecha: <strong>{$saleProductJpa->date_sale}</strong></p>
                    </div>
                    ";

                $usuario = "
                <div>
                    <p style='color:#71b6f9;'>{$sale['user_creation']['username']}</p>
                    <p><strong> {$sale['user_creation']['person']['name']} {$sale['user_creation']['person']['lastname']} </strong> </p>
                    <p>{$sale['date_sale']}</p>
                </div>
                ";

                $tipo_instalacion = isset($sale['type_intallation']) ? $sale['type_intallation'] : "<i>sin tipo</i>";
                $tipo_instalacion = str_replace('_', ' ', $tipo_instalacion);

                $datos = "
                    <div>
                        <p>Tipo operación <strong>{$sale['type_operation']['operation']}</strong></p>
                        <p>Tipo salida: <strong>{$tipo_instalacion}</strong></p>
                        <p>Descripción: <strong>{$sale['description']}</strong></p>
                    </div>
                ";

                $sumary .= "
                <tr>
                    <td>{$count}</td>
                    <td>{$usuario}</td>
                    <td>{$datos}</td>
                </tr>
                ";

                $view_details .= "
                <div style='margin-top:8px;'>
                    <p style='margin-buttom: 12px;'>{$count}) <strong>{$sale['type_operation']['operation']}</strong> - {$sale['user_creation']['person']['name']} {$sale['user_creation']['person']['lastname']} - {$sale['date_sale']} </p>
                    <div style='margin-buttom: 12px;margin-left:20px;'>
                        {$technical_details}
                    </div>
                    <div style='display: flex; flex-wrap: wrap; justify-content: space-between;margin-top: 50px;'>";

                foreach ($sale['details'] as $detailJpa) {
                    $details_equipment = 'display:none;';
                    if ($detailJpa['product']['type'] == 'EQUIPO') {
                        $details_equipment = '';
                    }
                    $view_details .= "
                            <div style='border: 2px solid #bbc7d1; border-radius: 9px; width: 25%; display: inline-block; padding:8px; font-size:12px; margin-left:10px;'>
                                <center>
                                    <p><strong>{$detailJpa['product']['model']['model']}</strong></p>
                                    <img src='https://almacen.fastnetperu.com.pe/api/model/{$detailJpa['product']['model']['relative_id']}/mini' style='background-color: #38414a;object-fit: cover; object-position: center center; cursor: pointer; height:50px;margin-top:12px;'></img>
                                    <div style='{$details_equipment}'>
                                        <p>Mac: <strong>{$detailJpa['product']['mac']}</strong><p>
                                        <p>Serie: <strong>{$detailJpa['product']['serie']}</strong></p>
                                    </div>
                                    <div>
                                        <p style='font-size:20px; color:#2f6593'>Nu:{$detailJpa['mount_new']} | Se:{$detailJpa['mount_second']} | Ma:{$detailJpa['mount_ill_fated']}</p>
                                    </div>
                                </center>
                            </div>
                        ";
                }

                $view_details .= "
                            </div>
                        </div>
                    ";

                $count = $count + 1;
            }

            $template = str_replace(
                [
                    '{branch_interaction}',
                    '{issue_long_date}',
                    '{user_generate}',
                    '{people}',
                    '{people_names}',
                    '{date_start_str}',
                    '{date_end_str}',
                    '{summary}',
                    '{details}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $user->person__name . ' ' . $user->person__lastname,
                    'TÉCNICO',
                    $dat_technical->name . ' ' . $dat_technical->lastname,
                    $request->date_start_str,
                    $request->date_end_str,
                    $sumary,
                    $view_details,
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

    public function generateReportBySearchEPP(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'technicals', 'read')) {
                throw new Exception('No tienes permisos para listar registros de técnicos');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportRecordsTechnicals.html');

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();
            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();
            $dat_technical = People::find($request->technical);

            $query = ViewSales::select([
                'view_sales.id as id',
                'view_sales.client_id as client_id',
                'view_sales.technical_id as technical_id',
                'view_sales.branch__id as branch__id',
                'view_sales.branch__name as branch__name',
                'view_sales.branch__correlative	 as branch__correlative',
                'view_sales.type_operation__id	 as type_operation__id',
                'view_sales.type_operation__operation	 as type_operation__operation',
                'view_sales.tower_id as tower_id',
                'view_sales.plant_id as plant_id',
                'view_sales.room_id as room_id',
                'view_sales.type_intallation as type_intallation',
                'view_sales.date_sale as date_sale',
                'view_sales.issue_date as issue_date',
                'view_sales.issue_user_id as issue_user_id',
                'view_sales.status_sale as status_sale',
                'view_sales.description as description',
                'view_sales.user_creation__id as user_creation__id',
                'view_sales.user_creation__username as user_creation__username',
                'view_sales.user_creation__person__id as user_creation__person__id',
                'view_sales.user_creation__person__name as user_creation__person__name',
                'view_sales.user_creation__person__lastname as user_creation__person__lastname',
                'view_sales.creation_date as creation_date',
                'view_sales.update_user_id as update_user_id',
                'view_sales.update_date as update_date',
                'view_sales.status as status',
            ])
                ->leftJoin('view_details_sales', 'view_sales.id', '=', 'view_details_sales.sale_product_id')
                ->orderBy('view_sales.id', 'DESC')
                ->where('technical_id', $request->technical)
                ->whereNotNUll('view_sales.status')
                ->where('branch__correlative', $branch)
                ->where('type_products', 'EPP');

            $query->where('type_operation__id', '10');

            if (isset($request->date_start) || isset($request->date_end)) {
                $dateStart = date('Y-m-d', strtotime($request->date_start));
                $dateEnd = date('Y-m-d', strtotime($request->date_end));
                $query->whereBetween('view_sales.date_sale', [$dateStart, $dateEnd]);
            }

            $salesJpa = $query->get();

            $sales = array();
            foreach ($salesJpa as $saleJpa) {
                $sale = gJSON::restore($saleJpa->toArray(), '__');
                $detailSalesJpa = ViewDetailsSales::select(['*'])->whereNotNull('status')->where('sale_product_id', $sale['id'])->get();
                $details = array();
                foreach ($detailSalesJpa as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                }
                $sale['details'] = $details;
                $sales[] = $sale;
            }

            $count = 1;
            $view_details = '';
            $sumary = '';
            foreach ($sales as $sale) {

                $technical_details = "";
                $saleProductJpa = SalesProducts::select([
                    'sales_products.id as id',
                    'tech.id as technical__id',
                    'tech.name as technical__name',
                    'tech.lastname as technical__lastname',
                    'sales_products.date_sale as date_sale',
                    'sales_products.status_sale as status_sale',
                    'sales_products.description as description',
                    'sales_products.status as status',
                ])
                    ->join('people as tech', 'sales_products._technical', 'tech.id')
                    ->where('sales_products.id', $sale['id'])->first();

                $technical_details = "
                    <div>
                        <p>Técnico: <strong>{$saleProductJpa->technical__name} {$saleProductJpa->technical__lastname}</strong></p>
                        <p>Fecha: <strong>{$saleProductJpa->date_sale}</strong></p>
                    </div>
                    ";

                $usuario = "
                <div>
                    <p style='color:#71b6f9;'>{$sale['user_creation']['username']}</p>
                    <p><strong> {$sale['user_creation']['person']['name']} {$sale['user_creation']['person']['lastname']} </strong> </p>
                    <p>{$sale['date_sale']}</p>
                </div>
                ";

                $tipo_instalacion = isset($sale['type_intallation']) ? $sale['type_intallation'] : "<i>sin tipo</i>";
                $tipo_instalacion = str_replace('_', ' ', $tipo_instalacion);

                $datos = "
                    <div>
                        <p>Tipo operación <strong>{$sale['type_operation']['operation']}</strong></p>
                        <p>Tipo salida: <strong>{$tipo_instalacion}</strong></p>
                        <p>Descripción: <strong>{$sale['description']}</strong></p>
                    </div>
                ";

                $sumary .= "
                <tr>
                    <td>{$count}</td>
                    <td>{$usuario}</td>
                    <td>{$datos}</td>
                </tr>
                ";

                $view_details .= "
                <div style='margin-top:8px;'>
                    <p style='margin-buttom: 12px;'>{$count}) <strong>{$sale['type_operation']['operation']}</strong> - {$sale['user_creation']['person']['name']} {$sale['user_creation']['person']['lastname']} - {$sale['date_sale']} </p>
                    <div style='margin-buttom: 12px;margin-left:20px;'>
                        {$technical_details}
                    </div>
                    <div style='display: flex; flex-wrap: wrap; justify-content: space-between;margin-top: 50px;'>";

                foreach ($sale['details'] as $detailJpa) {
                    $details_equipment = 'display:none;';
                    if ($detailJpa['product']['type'] == 'EQUIPO') {
                        $details_equipment = '';
                    }
                    $view_details .= "
                            <div style='border: 2px solid #bbc7d1; border-radius: 9px; width: 25%; display: inline-block; padding:8px; font-size:12px; margin-left:10px;'>
                                <center>
                                    <p><strong>{$detailJpa['product']['model']['model']}</strong></p>
                                    <img src='https://almacen.fastnetperu.com.pe/api/model/{$detailJpa['product']['model']['relative_id']}/mini' style='background-color: #38414a;object-fit: cover; object-position: center center; cursor: pointer; height:50px;margin-top:12px;'></img>
                                    <div style='{$details_equipment}'>
                                        <p>Mac: <strong>{$detailJpa['product']['mac']}</strong><p>
                                        <p>Serie: <strong>{$detailJpa['product']['serie']}</strong></p>
                                    </div>
                                    <div>
                                        <p style='font-size:20px; color:#2f6593'>Nu:{$detailJpa['mount_new']} | Se:{$detailJpa['mount_second']} | Ma:{$detailJpa['mount_ill_fated']}</p>
                                    </div>
                                </center>
                            </div>
                        ";
                }

                $view_details .= "
                            </div>
                        </div>
                    ";

                $count = $count + 1;
            }

            $template = str_replace(
                [
                    '{branch_interaction}',
                    '{issue_long_date}',
                    '{user_generate}',
                    '{people}',
                    '{people_names}',
                    '{date_start_str}',
                    '{date_end_str}',
                    '{summary}',
                    '{details}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $user->person__name . ' ' . $user->person__lastname,
                    'TÉCNICO',
                    $dat_technical->name . ' ' . $dat_technical->lastname,
                    $request->date_start_str,
                    $request->date_end_str,
                    $sumary,
                    $view_details,
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

    public function getStockProductByModel(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'technicals', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar técnicos');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: Es necesario el ID para esta operación");
            }

            $ProductByTechnical = ProductByTechnical::where('_technical', $request->technical['id'])->where('_product', $request->product['id'])->first();

            $response->setData([$ProductByTechnical]);
            $response->setStatus(200);
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
}
