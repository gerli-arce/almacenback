<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\Cars;
use App\Models\DetailSale;
use App\Models\Product;
use App\Models\ProductsByCar;
use App\Models\Response;
use App\Models\SalesProducts;
use App\Models\Stock;
use App\Models\ViewCars;
use App\Models\ViewProductsByCar;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CarsController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'create')) {
                throw new Exception("No tienes permisos para agregar movilidades de " . $branch);
            }

            if (
                !isset($request->placa)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $brandValidation = Cars::select(['placa'])
                ->where('placa', $request->placa)
                ->first();

            if ($brandValidation) {
                if ($brandValidation->placa == $request->placa) {
                    throw new Exception("La placa ya fue registrada");
                }
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $carsJpa = new Cars();
            $carsJpa->_branch = $branch_->id;
            $carsJpa->placa = $request->placa;

            if (isset($request->color)) {
                $carsJpa->color = $request->color;
            }

            if (isset($request->num_chasis)) {
                $carsJpa->num_chasis = $request->num_chasis;
            }

            if (isset($request->year)) {
                $carsJpa->year = $request->year;
            }

            if (isset($request->soat)) {
                $carsJpa->soat = $request->soat;
            }

            if (isset($request->expiration_date_soat)) {
                $carsJpa->expiration_date_soat = $request->expiration_date_soat;
            }

            if (isset($request->car_type)) {
                $carsJpa->car_type = $request->car_type;
            };

            if (isset($request->_model)) {
                $carsJpa->_model = $request->_model;
            }

            if (isset($request->property_card)) {
                $carsJpa->property_card = $request->property_card;
            }

            if (isset($request->license)) {
                $carsJpa->license = $request->license;
            }

            if (isset($request->_person)) {
                $carsJpa->_person = $request->_person;
            }

            if (isset($request->_branch)) {
                $carsJpa->_branch = $request->_branch;
            }

            if (
                isset($request->image_type) &&
                isset($request->image_mini) &&
                isset($request->image_full)
            ) {
                if ($request->image_type != 'none') {
                    if (
                        $request->image_type &&
                        $request->image_mini &&
                        $request->image_full
                    ) {
                        $carsJpa->image_type = $request->image_type;
                        $carsJpa->image_mini = base64_decode($request->image_mini);
                        $carsJpa->image_full = base64_decode($request->image_full);
                    } else {
                        $carsJpa->image_type = null;
                        $carsJpa->image_mini = null;
                        $carsJpa->image_full = null;
                    }
                } else {
                    $carsJpa->image_type = null;
                    $carsJpa->image_mini = null;
                    $carsJpa->image_full = null;
                }

            }

            if (isset($request->description)) {
                $carsJpa->description = $request->description;
            }

            $carsJpa->creation_date = gTrace::getDate('mysql');
            $carsJpa->_creation_user = $userid;
            $carsJpa->update_date = gTrace::getDate('mysql');
            $carsJpa->_update_user = $userid;
            $carsJpa->status = "1";
            $carsJpa->save();

            $response->setStatus(200);
            $response->setMessage('La movilidad se a agregado correctamente');
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

    public function search(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception('No tienes permisos para listar movilidades  de ' . $branch);
            }

            $verifyCarJpa = Cars::select([
                'id',
                'placa',
            ])->whereNotNull('status')
                ->WhereRaw("placa LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orWhereRaw("id LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orderBy('placa', 'asc')
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($verifyCarJpa->toArray());
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

    public function getCar(Request $request, $id)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception('No tienes permisos para listar los movilidades  de ' . $branch);
            }

            $carJpa = ViewCars::select('*')->whereNotNull('status')->find($id);
            $car = gJSON::restore($carJpa->toArray(), '__');
            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData([$car]);
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

    public function getCarByTechnical(Request $request, $id){
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception('No tienes permisos para listar los movilidades  de ' . $branch);
            }

            $carJpa = ViewCars::select('*')->whereNotNull('status')->where('person__id', $id)->first();
            if(!$carJpa){
                throw new Exception('No se encontro la movilidad');
            }
            $car = gJSON::restore($carJpa->toArray(), '__');
            $response->setStatus(200);
            $response->setMessage('Movilidad cargada');
            $response->setData([$car]);
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

            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception('No tienes permisos para listar movilidades  de ' . $branch);
            }

            $query = ViewCars::select('*')
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'placa' || $column == '*') {
                    $q->orWhere('placa', $type, $value);
                }
                if ($column == 'color' || $column == '*') {
                    $q->orWhere('color', $type, $value);
                }
                if ($column == 'num_chasis' || $column == '*') {
                    $q->orWhere('num_chasis', $type, $value);
                }
                if ($column == 'year' || $column == '*') {
                    $q->orWhere('year', $type, $value);
                }
                if ($column == 'soat' || $column == '*') {
                    $q->orWhere('soat', $type, $value);
                }
                if ($column == 'property_card' || $column == '*') {
                    $q->orWhere('property_card', $type, $value);
                }
                if ($column == 'model' || $column == '*') {
                    $q->orWhere('model__model', $type, $value);
                }
                if ($column == 'person__name' || $column == '*') {
                    $q->orWhere('person__name', $type, $value);
                }
                if ($column == 'person__lastname' || $column == '*') {
                    $q->orWhere('person__lastname', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            if (!$request->view_all) {
                $query->where('branch__id', $branch_->id);
            }

            $iTotalDisplayRecords = $query->count();
            $carsJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $cars = array();
            foreach ($carsJpa as $carJpa) {
                $car = gJSON::restore($carJpa->toArray(), '__');
                $cars[] = $car;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewCars::count());
            $response->setData($cars);
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

            $userJpa = Cars::select([
                "cars.image_$size as image_content",
                'cars.image_type',

            ])
                ->where('id', $id)
                ->first();

            if (!$userJpa) {
                throw new Exception('No se encontraron datos');
            }
            if (!$userJpa->image_content) {
                throw new Exception('No existe imagen');
            }
            $content = $userJpa->image_content;
            $type = $userJpa->image_type;
            $response->setStatus(200);
        } catch (\Throwable $th) {
            $ruta = '../storage/images/car-default.png';
            $fp = fopen($ruta, 'r');
            $datos_image = fread($fp, filesize($ruta));
            $datos_image = addslashes($datos_image);
            fclose($fp);
            $content = stripslashes($datos_image);
            $type = 'image/png';
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
            if (!gValidate::check($role->permissions, $branch, 'cars', 'update')) {
                throw new Exception('No tienes permisos para actualizar movilidades  de ' . $branch);
            }

            $cardJpa = Cars::select('*')->find($request->id);
            if (!$cardJpa) {
                throw new Exception("No se puede actualizar este registro");
            }

            if (isset($request->placa)) {
                $verifyCarJpa = Cars::select(['id', 'placa'])
                    ->where('placa', $request->placa)
                    ->where('id', '!=', $request->id)
                    ->first();
                if ($verifyCarJpa) {
                    throw new Exception("la placa ya esta registrada");
                }
                $cardJpa->placa = $request->placa;
            }

            if (isset($request->color)) {
                $cardJpa->color = $request->color;
            }

            if (isset($request->num_chasis)) {
                $cardJpa->num_chasis = $request->num_chasis;
            }

            if (isset($request->year)) {
                $cardJpa->year = $request->year;
            }

            if (isset($request->soat)) {
                $cardJpa->soat = $request->soat;
            }

            if (isset($request->expiration_date_soat)) {
                $cardJpa->expiration_date_soat = $request->expiration_date_soat;
            }

            if (isset($request->car_type)) {
                $cardJpa->car_type = $request->car_type;
            };

            if (isset($request->_model)) {
                $cardJpa->_model = $request->_model;
            }

            if (isset($request->property_card)) {
                $cardJpa->property_card = $request->property_card;
            }

            if (isset($request->license)) {
                $cardJpa->license = $request->license;
            }

            if (isset($request->_person)) {
                $cardJpa->_person = $request->_person;
            }

            if (isset($request->_branch)) {
                $cardJpa->_branch = $request->_branch;
            }

            if (
                isset($request->image_type) &&
                isset($request->image_mini) &&
                isset($request->image_full)
            ) {

                if ($request->image_type != 'none') {
                    if (
                        $request->image_type &&
                        $request->image_mini &&
                        $request->image_full
                    ) {
                        $cardJpa->image_type = $request->image_type;
                        $cardJpa->image_mini = base64_decode($request->image_mini);
                        $cardJpa->image_full = base64_decode($request->image_full);
                    } else {
                        $cardJpa->image_type = null;
                        $cardJpa->image_mini = null;
                        $cardJpa->image_full = null;
                    }
                } else {
                    $cardJpa->image_type = null;
                    $cardJpa->image_mini = null;
                    $cardJpa->image_full = null;
                }

            }

            if (isset($request->description)) {
                $cardJpa->description = $request->description;
            }

            $cardJpa->update_date = gTrace::getDate('mysql');
            $cardJpa->_update_user = $userid;

            $cardJpa->save();

            $response->setStatus(200);
            // $response->setData([$cardJpa]);
            $response->setMessage('La movilidad ha sido actualizado correctamente');
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

    public function destroy(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar movilidades en ' . $branch);
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $carJpa = Cars::find($request->id);
            if (!$carJpa) {
                throw new Exception('La movilidad que deseas eliminar no existe');
            }

            $carJpa->update_date = gTrace::getDate('mysql');
            $carJpa->_update_user = $userid;
            $carJpa->status = null;
            $carJpa->save();

            $response->setStatus(200);
            $response->setMessage('La movilidad a sido eliminada correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'cars', 'delete_restore')) {
                throw new Exception('No tienes permisos para restaurar movilidades en ' . $branch);
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $carsJpa = Cars::find($request->id);
            if (!$carsJpa) {
                throw new Exception('La movilidad que deseas restaurar no existe');
            }

            $carsJpa->update_date = gTrace::getDate('mysql');
            $carsJpa->_update_user = $userid;
            $carsJpa->status = "1";
            $carsJpa->save();

            $response->setStatus(200);
            $response->setMessage('La movilidad a sido restaurada correctamente');
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

    public function setProductsByCars(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            // validar permisos
            if (!gValidate::check($role->permissions, $branch, 'cars', 'create')) {
                throw new Exception('No tienes permisos para agregar productos a movilidades en ' . $branch);
            }
            if (
                !isset($request->data) ||
                !isset($request->car)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();

            $salesProduct->_branch = $branch_->id;
            $salesProduct->_car = $request->car['id'];
            $salesProduct->_type_operation = 14;
            $salesProduct->type_intallation = "CAR";
            $salesProduct->status_sale = "AGREGADO";
            $salesProduct->_issue_user = $userid;
            $salesProduct->type_pay = "GASTOS INTERNOS";

            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            foreach ($request->data as $product) {

                $stock = Stock::where('_model', $product['product']['model']['id'])
                    ->where('_branch', $branch_->id)
                    ->first();

                $productJpa = Product::find($product['product']['id']);

                $productByCarJpa_val = ProductsByCar::select('*')
                    ->where('_car', $request->car['id'])
                    ->where('_model', $product['product']['model']['id'])
                    ->first();

                if ($productByCarJpa_val) {
                    if ($product['product']['type'] == 'MATERIAL') {
                        $productByCarJpa_val->mount_new += $product['mount_new'];
                        $productByCarJpa_val->mount_second += $product['mount_second'];
                        $productByCarJpa_val->mount_ill_fated += $product['mount_ill_fated'];
                        $productByCarJpa_val->description = $product['description'];
                        $productByCarJpa_val->save();
                    } else {
                        $productByCarJpa = new ProductsByCar();
                        $productByCarJpa->_car = $request->car['id'];
                        $productByCarJpa->_product = $product['product']['id'];
                        $productByCarJpa->_model = $product['product']['model']['id'];
                        $productByCarJpa->mount_new = $product['mount_new'];
                        $productByCarJpa->mount_second = $product['mount_second'];
                        $productByCarJpa->mount_ill_fated = $product['mount_ill_fated'];
                        $productByCarJpa->description = $product['description'];
                        $productByCarJpa->status = 1;
                        $productByCarJpa->save();
                    }
                } else {
                    if ($product['product']['type'] == 'MATERIAL') {
                        $productByCarJpa = new ProductsByCar();
                        $productByCarJpa->_car = $request->car['id'];
                        $productByCarJpa->_product = $product['product']['id'];
                        $productByCarJpa->_model = $product['product']['model']['id'];
                        $productByCarJpa->mount_new = $product['mount_new'];
                        $productByCarJpa->mount_second = $product['mount_second'];
                        $productByCarJpa->mount_ill_fated = $product['mount_ill_fated'];
                        $productByCarJpa->description = $product['description'];
                        $productByCarJpa->status = 1;
                        $productByCarJpa->save();

                    } else {
                        $productByCarJpa = new ProductsByCar();
                        $productByCarJpa->_car = $request->car['id'];
                        $productByCarJpa->_product = $product['product']['id'];
                        $productByCarJpa->_model = $product['product']['model']['id'];
                        $productByCarJpa->mount_new = $product['mount_new'];
                        $productByCarJpa->mount_second = $product['mount_second'];
                        $productByCarJpa->mount_ill_fated = $product['mount_ill_fated'];
                        $productByCarJpa->description = $product['description'];
                        $productByCarJpa->status = 1;
                        $productByCarJpa->save();
                    }
                }

                $stock->mount_new -= $product['mount_new'];
                $stock->mount_second -= $product['mount_second'];
                $stock->mount_ill_fated -= $product['mount_ill_fated'];

                if ($product['product']['type'] == 'MATERIAL') {
                    $productJpa->mount = $stock->mount_new + $stock->mount_second;
                } else {
                    $productJpa->disponibility = "En el stock vehículo: " . $request->car['placa'];
                    $productJpa->description .= "Se agrego al stock del vehículo: " . $request->car['placa'] . " en la fecha " . gTrace::getDate('mysql');
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

                $stock->save();
                $productJpa->save();
            }

            $response->setStatus(200);
            $response->setMessage('Los productos se han agregado correctamente');
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

    public function paginateProductsByCar(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception('No tienes permisos para listar productos de movilidades en ' . $branch);
            }

            $query = ViewProductsByCar::select('*')
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'car__placa' || $column == '*') {
                    $q->orWhere('car__placa', $type, $value);
                }
                if ($column == 'product__name' || $column == '*') {
                    $q->orWhere('product__name', $type, $value);
                }
                if ($column == 'product__model__model' || $column == '*') {
                    $q->orWhere('product__model__model', $type, $value);
                }
                if ($column == 'mount_new' || $column == '*') {
                    $q->orWhere('mount_new', $type, $value);
                }
                if ($column == 'mount_second' || $column == '*') {
                    $q->orWhere('mount_second', $type, $value);
                }
                if ($column == 'mount_ill_fated' || $column == '*') {
                    $q->orWhere('mount_ill_fated', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            })
                ->where('car__id', $request->car);

            $iTotalDisplayRecords = $query->count();
            $productsByCarJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $productsByCar = array();
            foreach ($productsByCarJpa as $productByCarJpa) {
                $productByCar = gJSON::restore($productByCarJpa->toArray(), '__');
                $productsByCar[] = $productByCar;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewProductsByCar::count());
            $response->setData($productsByCar);
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

    public function reportByCar(Request $request)
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
            $template = file_get_contents('../storage/templates/reportCar.html');

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }
            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();
            $sumary = '';

            $productByCarJpa = ViewProductsByCar::where('car__id', $request->id)->whereNotNull('status')->get();

            $stock_car = [];

            foreach ($productByCarJpa as $products) {
                $product = gJSON::restore($products->toArray(), '__');
                $stock_car[] = $product;
            }

            $models = array();
            foreach ($stock_car as $product) {
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
                        'unity' => $unity,
                    );
                }
            }
            $count = 1;
            $products = array_values($models);
            foreach ($products as $detail) {
                $sumary .= "
                <tr>
                    <td><center style='font-size:12px;'>{$count}</center></td>
                    <td><center style='font-size:12px;'>{$detail['mount_new']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['mount_second']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['mount_ill_fated']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['unity']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['model']}</center></td>
                </tr>
                ";
                $count = $count + 1;
            }
            $template = str_replace(
                [
                    '{id}',
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{placa}',
                    '{num_chasis}',
                    '{year}',
                    '{color}',
                    '{model}',
                    '{soat}',
                    '{type}',
                    '{expiration_date_soat}',
                    '{technical}',
                    '{license}',
                    '{branch_car}',
                    '{description}',
                    '{id_car}',
                    '{summary}',
                ],
                [
                    $request->id,
                    $branch_->name,
                    gTrace::getDate('long'),
                    $request->placa,
                    $request->num_chasis,
                    $request->year,
                    $request->color,
                    $request->model['model'],
                    $request->soat,
                    $request->car_type,
                    $request->expiration_date_soat,
                    $request->person['name'] . ' ' . $request->person['lastname'],
                    $request->license,
                    $request->branch['name'],
                    $request->description,
                    $request->id,
                    $sumary,
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

    public function reportProductsByCar(Request $request)
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
            $template = file_get_contents('../storage/templates/reportProductsCar.html');

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }
            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();
            $sumary = '';

            $productByCarJpa = ViewProductsByCar::where('car__id', $request->id)->whereNotNull('status')->get();

            $stock_car = [];

            foreach ($productByCarJpa as $products) {
                $product = gJSON::restore($products->toArray(), '__');
                $stock_car[] = $product;
            }

            $models = array();
            foreach ($stock_car as $product) {
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
                        'unity' => $unity,
                    );
                }
            }
            $count = 1;
            $products = array_values($models);
            foreach ($products as $detail) {
                $sumary .= "
                <tr>
                    <td><center style='font-size:12px;'>{$count}</center></td>
                    <td><center style='font-size:12px;'>{$detail['mount_new']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['mount_second']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['mount_ill_fated']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['unity']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['model']}</center></td>
                </tr>
                ";
                $count = $count + 1;
            }
            $template = str_replace(
                [
                    '{id}',
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{placa}',
                    '{color}',
                    '{technical}',
                    '{summary}',
                ],
                [
                    str_pad($request->id, 6, "0", STR_PAD_LEFT),
                    $branch_->name,
                    gTrace::getDate('long'),
                    $request->placa,
                    $request->color,
                    $request->person['name'] . ' ' . $request->person['lastname'],
                    $sumary,
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

}
