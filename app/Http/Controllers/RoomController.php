<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\gLibraries\guid;
use App\Models\Response;
use App\Models\Room;
use App\Models\ProductsByRoom;
use App\Models\SalesProducts;
use App\Models\DetailSale;
use App\Models\Branch;
use App\Models\ViewDetailsSales;
use App\Models\ViewSales;
use App\Models\Product;
use App\Models\ViewProductByRoom;
use App\Models\EntryProducts;
use App\Models\EntryDetail;
use App\Models\ViewStockRoom;
use App\Models\Stock;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'room', 'create')) {
                throw new Exception('No tienes permisos para listar los cuartos del sistema');
            }

            if (
                !isset($request->name)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $roomJpa = new Room();
            $roomJpa->name = $request->name;
            if ($request->description) {
                $roomJpa->description = $request->description;
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
                    $roomJpa->image_type = $request->image_type;
                    $roomJpa->image_mini = base64_decode($request->image_mini);
                    $roomJpa->image_full = base64_decode($request->image_full);
                } else {
                    $roomJpa->image_type = null;
                    $roomJpa->image_mini = null;
                    $roomJpa->image_full = null;
                }
            }

            $roomJpa->relative_id = guid::short();
            $roomJpa->creation_date = gTrace::getDate('mysql');
            $roomJpa->_creation_user = $userid;
            $roomJpa->update_date = gTrace::getDate('mysql');
            $roomJpa->_update_user = $userid;
            $roomJpa->status = "1";
            $roomJpa->save();

            $response->setStatus(200);
            $response->setMessage('El rol se a agregado correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'room', 'create')) {
                throw new Exception('No tienes permisos para listar los cuartos del sistema');
            }

            if (
                !isset($request->name)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $roomJpa = Room::find($request->id);
            $roomJpa->name = $request->name;
            $roomJpa->description = $request->description;

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
                    $roomJpa->image_type = $request->image_type;
                    $roomJpa->image_mini = base64_decode($request->image_mini);
                    $roomJpa->image_full = base64_decode($request->image_full);
                } else {
                    $roomJpa->image_type = null;
                    $roomJpa->image_mini = null;
                    $roomJpa->image_full = null;
                }
            }

            $roomJpa->update_date = gTrace::getDate('mysql');
            $roomJpa->_update_user = $userid;
            $roomJpa->save();

            $response->setStatus(200);
            $response->setMessage('Operación correcta, datos de cuarto actualizados correctamente.');
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
            if (!gValidate::check($role->permissions, $branch, 'room', 'read')) {
                throw new Exception('No tienes permisos para listar usuarios');
            }

            $dat = gValidate::check($role->permissions, $branch, 'users', 'read');

            $query = Room::select([
                'id',
                'name',
                'description',
                'relative_id',
                '_creation_user',
                'creation_date',
                '_update_user',
                'update_date',
                'status',
            ])
                ->orderBy($request->order['column'], $request->order['dir']);

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

                if ($column == 'name' || $column == '*') {
                    $q->where('name', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->where('description', $type, $value);
                }
            });
            $iTotalDisplayRecords = $query->count();

            $roomsJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();



            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Room::count());
            $response->setData($roomsJpa->toArray());
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

    public function image($relative_id, $size)
    {
        $response = new Response();
        $content = null;
        $type = null;
        try {
            if ($size != 'full') {
                $size = 'mini';
            }
            if (
                !isset($relative_id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $roomJpa = Room::select([
                "room.image_$size as image_content",
                'room.image_type',

            ])
                ->where('relative_id', $relative_id)
                ->first();

            if (!$roomJpa) {
                throw new Exception('No se encontraron datos');
            }

            if (!$roomJpa->image_content) {
                throw new Exception('No existe imagen');
            }

            $content = $roomJpa->image_content;
            $type = $roomJpa->image_type;
            $response->setStatus(200);
        } catch (\Throwable $th) {
            $ruta = '../storage/images/room-default.jpg';
            $fp = fopen($ruta, 'r');
            $datos_image = fread($fp, filesize($ruta));
            $datos_image = addslashes($datos_image);
            fclose($fp);
            $content = stripslashes($datos_image);
            $type = 'image/jpeg';
            $response->setStatus(400);
        } finally {
            return response(
                $content,
                $response->getStatus()
            )->header('Content-Type', $type);
        }
    }

    public function setProductsByRoom(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'room', 'update')) {
                throw new Exception('No tienes permisos para actualizar torre');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();

            $salesProduct->_branch = $branch_->id;
            $salesProduct->_room = $request->id;
            $salesProduct->_type_operation = $request->_type_operation;
            $salesProduct->type_intallation = "ROOM";
            $salesProduct->status_sale = "ENTRADA";
            $salesProduct->_issue_user = $userid;
            $salesProduct->type_pay = "GASTOS INTERNOS";

            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            $roomJpa = Room::find($request->id);

            if (isset($request->data)) {
                foreach ($request->data as $product) {
                    $productJpa = Product::find($product['product']['id']);
                    $stock = Stock::where('_model', $productJpa->_model)
                        ->where('_branch', $branch_->id)
                        ->first();
                    if ($product['product']['type'] == "MATERIAL") {
                        $stock->mount_new = $stock->mount_new -  $product['mount_new'];
                        $stock->mount_second = $stock->mount_second -  $product['mount_second'];
                        $stock->mount_ill_fated = $stock->mount_ill_fated -  $product['mount_ill_fated'];
                        $productJpa->mount = $stock->mount_new -   $stock->mount_second;
                        $productsByRoomJpa = ProductsByRoom::where('_product', $productJpa->id)->where('_room', $request->id)->first();
                        if ($productsByRoomJpa) {
                            $productsByRoomJpa->mount_new +=  $product['mount_new'];
                            $productsByRoomJpa->mount_second += $product['mount_second'];
                            $productsByRoomJpa->mount_ill_fated += $product['mount_ill_fated'];
                            $productsByRoomJpa->save();
                        } else {
                            $productsByRoomJpa = new ProductsByRoom();
                            $productsByRoomJpa->_product = $productJpa->id;
                            $productsByRoomJpa->_room = $request->id;
                            $productsByRoomJpa->mount_new = $product['mount_new'];
                            $productsByRoomJpa->mount_second = $product['mount_second'];
                            $productsByRoomJpa->mount_ill_fated = $product['mount_ill_fated'];
                            $productsByRoomJpa->status = '1';
                            $productsByRoomJpa->save();
                        }
                    } else {
                        $productJpa->disponibility = "CUARTO " . $roomJpa->name;
                        if ($productJpa->product_status == "NUEVO") {
                            $stock->mount_new = $stock->mount_new - 1;
                        } else if ($productJpa->product_status == "SEMINUEVO") {
                            $stock->mount_second = $stock->mount_second - 1;
                        }

                        $productsByRoomJpa = new ProductsByRoom();
                        $productsByRoomJpa->_product = $productJpa->id;
                        $productsByRoomJpa->_room = $request->id;
                        $productsByRoomJpa->mount_new = $product['mount_new'];
                        $productsByRoomJpa->mount_second = $product['mount_second'];
                        $productsByRoomJpa->mount_ill_fated = $product['mount_ill_fated'];
                        $productsByRoomJpa->status = '1';
                        $productsByRoomJpa->save();
                    }

                    $stock->save();
                    $productJpa->save();

                    $detailSale = new DetailSale();
                    $detailSale->_product = $productJpa->id;
                    $detailSale->mount_new = $product['mount_new'];
                    $detailSale->mount_second = $product['mount_second'];
                    $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                    $detailSale->_sales_product = $salesProduct->id;
                    $detailSale->status = '1';
                    $detailSale->save();
                }
            }

            $response->setStatus(200);
            $response->setMessage('Registro agregado correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'Ln. ' . $th->getLine() . $th->getFile());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function getProductsByRoom(Request $request, $id)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'room', 'read')) {
                throw new Exception('No tienes permisos para listar');
            }

            $productByRoomJpa = ViewProductByRoom::where('room__id', $id)->whereNotNull('status')->get();

            $products_room = [];

            foreach ($productByRoomJpa as $products) {
                $product = gJSON::restore($products->toArray(), '__');
                $products_room[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($products_room);
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

    public function getRecordsByRoom(Request $request){
       
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'record_sales', 'read')) {
                throw new Exception('No tienes permisos para listar las salidas');
            }

            $query = ViewSales::select([
                '*',
            ])
                ->orderBy($request->order['column'], $request->order['dir'])
                ->whereNotNUll('status')
                ->where('type_intallation', 'ROOM')
                ->where('room_id', $request->search['room_id'])
                ->where('type_operation__id', '11');

            if (isset($request->search['date_start']) || isset($request->search['date_end'])) {
                $query->where('date_sale', '>=', $request->search['date_start'])
                    ->where('date_sale', '<=', $request->search['date_end']);
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
                    $detail =  gJSON::restore($detailJpa->toArray(), '__');
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

    public function paginateProductsByRoom(Request $request){
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'tower', 'read')) {
                throw new Exception('No tienes permisos para listar modelos');
            }

            $query = ViewStockRoom::select(['*'])->orderBy($request->order['column'], $request->order['dir'])
                ->whereNotNull('status');

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'product__model__model' || $column == '*') {
                    $q->orWhere('product__model__model', $type, $value);
                }
                if ($column == 'product__mac' || $column == '*') {
                    $q->orWhere('product__mac', $type, $value);
                }
                if ($column == 'product__serie' || $column == '*') {
                    $q->orWhere('product__serie', $type, $value);
                }
            })->where('room__id', $request->search['room']);

            $iTotalDisplayRecords = $query->count();
            $towerJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $stock = array();
            foreach ($towerJpa as $productJpa) {
                $product = gJSON::restore($productJpa->toArray(), '__');
                $stock[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewStockRoom::where('room__id', $request->search['room'])->count());
            $response->setData($stock);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage().' Ln:'.$th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function searchProductsByRoom(Request $request){
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'towers', 'update')) {
                throw new Exception('No tienes permisos para actualizar');
            }

            $ProductByRoomJpa = ProductsByRoom::where('_product', $request->product['id'])->where('_room', $request->room['id'])->first();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData([$ProductByRoomJpa]);
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

    public function retunProductsByRoom(Request $request){
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'towers', 'update')) {
                throw new Exception('No tienes permisos para actualizar');
            }

            $roomJpa = Room::find($request->id);

            if(!$roomJpa){
                throw new Exception('El cuarto no existe');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_room = $request->id;
            $salesProduct->_type_operation = $request->_type_operation;
            $salesProduct->type_intallation = "ROOM";
            $salesProduct->status_sale = "SALIDA";
            $salesProduct->_issue_user = $userid;
            $salesProduct->type_pay = "GASTOS INTERNOS";
            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "0";
            $salesProduct->save();

            $entryProductsJpa = new EntryProducts();
            $entryProductsJpa->_user = $userid;
            $entryProductsJpa->_branch = $branch_->id;
            $entryProductsJpa->_type_operation = $request->_type_operation;
            $entryProductsJpa->_room = $request->id;
            $entryProductsJpa->type_entry = "DEVOLUCION DE CENTRAL";
            $entryProductsJpa->entry_date = gTrace::getDate('mysql');
            $entryProductsJpa->_creation_user = $userid;
            $entryProductsJpa->creation_date = gTrace::getDate('mysql');
            $entryProductsJpa->_update_user = $userid;
            $entryProductsJpa->update_date = gTrace::getDate('mysql');
            $entryProductsJpa->status = "1";
            $entryProductsJpa->save();

            if (isset($request->data)) {
                foreach ($request->data as $product) {

                    $productJpa = Product::find($product['product']['id']);
                    $stock = Stock::where('_model', $productJpa->_model)
                        ->where('_branch', $branch_->id)
                        ->first();

                    $productByRoomJpa = ProductsByRoom::find($product['id']);

                    if ($product['product']['type'] == "MATERIAL") {
                        $stock->mount_new = $stock->mount_new + $product['mount_new'];
                        $stock->mount_second = $stock->mount_second + $product['mount_second'];
                        $stock->mount_ill_fated = $stock->mount_ill_fated + $product['mount_ill_fated'];
                        $productByRoomJpa->mount_new = $productByRoomJpa->mount_new - $product['mount_new'];
                        $productByRoomJpa->mount_second = $productByRoomJpa->mount_second - $product['mount_second'];
                        $productByRoomJpa->mount_ill_fated = $productByRoomJpa->mount_ill_fated - $product['mount_ill_fated'];
                        $productJpa->mount = $stock->mount_new + $stock->mount_second;
                    } else {
                        $productJpa->disponibility = "DISPONIBLE";
                        $productJpa->condition_product = "DEVUELTO DE LA TORRE: " . $roomJpa->name;
                        if ($productJpa->product_status == "NUEVO") {
                            $stock->mount_new = $stock->mount_new + 1;
                        } else if ($productJpa->product_status == "SEMINUEVO") {
                            $stock->mount_second = $stock->mount_second + 1;
                        }
                        $productByRoomJpa->status = null;
                    }

                    $productByRoomJpa->save();
                    $stock->save();
                    $productJpa->save();

                    $detailSale = new DetailSale();
                    $detailSale->_product = $productJpa->id;
                    $detailSale->mount_new = $product['mount_new'];
                    $detailSale->mount_second = $product['mount_second'];
                    $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                    $detailSale->_sales_product = $salesProduct->id;
                    $detailSale->status = '1';
                    $detailSale->save();

                    $entryDetail = new EntryDetail();
                    $entryDetail->_product = $productJpa->id;
                    $entryDetail->mount_new = $product['mount_new'];
                    $entryDetail->mount_second = $product['mount_second'];
                    $entryDetail->mount_ill_fated = $product['mount_ill_fated'];
                    $entryDetail->_entry_product = $entryProductsJpa->id;
                    $entryDetail->status = "1";
                    $entryDetail->save();
                }
            }

            $roomJpa->update_date = gTrace::getDate('mysql');
            $roomJpa->_update_user = $userid;
            $roomJpa->save();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
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

}
