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
use App\Models\Product;
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


    public function setProductsByRoom(Request $request){
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
            $salesProduct->status_sale = "CULMINADA";
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
                    } else {
                        $productJpa->disponibility = "CUARTO ".$roomJpa->name;
                        if ($productJpa->product_status == "NUEVO") {
                            $stock->mount_new = $stock->mount_new - 1;
                        } else if ($productJpa->product_status == "SEMINUEVO") {
                            $stock->mount_second = $stock->mount_second - 1;
                        }
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

                    $productsByRoomJpa = new ProductsByRoom();
                    $productsByRoomJpa->_product = $productJpa->id;
                    $productsByRoomJpa->_room = $request->id;
                    $productsByRoomJpa->mount_new = $product['mount_new'];
                    $productsByRoomJpa->mount_second = $product['mount_second'];
                    $productsByRoomJpa->mount_ill_fated = $product['mount_ill_fated'];
                    $productsByRoomJpa->status = '1';
                    $productsByRoomJpa->save();
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

    public function getProductsByRoom(Request $request, $id){
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'room', 'read')) {
                throw new Exception('No tienes permisos para listar');
            }

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
                'sales_products.status as status',
            ])
                ->join('people as tech', 'sales_products._technical', 'tech.id')
                ->join('branches', 'sales_products._branch', 'branches.id')
                ->whereNotNull('sales_products.status')
                ->where('sales_products._room', $id)
                ->orderBy('id', 'desc')
                ->get();

            if (!$saleProductJpa) {
                throw new Exception('No ay registros');
            }

            $salesProducts = array();
            foreach ($saleProductJpa as $saleProduct) {
                $sale = gJSON::restore($saleProduct->toArray(), '__');

                $detailSaleJpa = DetailSale::select([
                    'detail_sales.id as id',
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
                    'branches.id AS sale_product__branch__id',
                    'branches.name AS sale_product__branch__name',
                    'branches.correlative AS sale_product__branch__correlative',
                    'detail_sales.mount_new as mount_new',
                    'detail_sales.mount_second as mount_second',
                    'detail_sales.mount_ill_fated as mount_ill_fated',
                    'detail_sales.description as description',
                    'detail_sales._sales_product as _sales_product',
                    'detail_sales.status as status',
                ])
                    ->join('products', 'detail_sales._product', 'products.id')
                    ->join('models', 'products._model', 'models.id')
                    ->join('sales_products', 'detail_sales._sales_product', 'sales_products.id')
                    ->join('branches', 'sales_products._branch', 'branches.id')
                    ->whereNotNull('detail_sales.status')
                    ->where('_sales_product', $sale['id'])
                    ->get();

                $details = array();
                foreach ($detailSaleJpa as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                }

                $sale['details'] = $details;

                $salesProducts[] = $sale;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($salesProducts);
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
