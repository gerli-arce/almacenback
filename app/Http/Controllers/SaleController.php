<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\DetailSale;
use App\Models\Product;
use App\Models\ViewDetailsSales;
use App\Models\Sale;
use App\Models\Response;
use App\Models\People;
use App\Models\SalesProducts;
use App\Models\Stock;
use App\Models\viewInstallations;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Dompdf\Dompdf;
use Dompdf\Options;

class SaleController extends Controller
{

    public function generateReportBySale(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'sales', 'read')) {
                throw new Exception('No tienes permisos para generar informe');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportSale.html');

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $host = '';

            $detailSaleJpa = DetailSale::select([
                'detail_sales.id as id',
                'products.id AS product__id',
                'products.type AS product__type',
                'branches.id AS product__branch__id',
                'branches.name AS product__branch__name',
                'branches.correlative AS product__branch__correlative',
                'brands.id AS product__model__brand__id',
                'brands.correlative AS product__model__brand__correlative',
                'brands.brand AS product__model__brand__brand',
                'brands.relative_id AS product__model__brand__relative_id',
                'categories.id AS product__model__category__id',
                'categories.category AS product__model__category__category',
                'models.id AS product__model__id',
                'models.model AS product__model__model',
                'models.relative_id AS product__model__relative_id',
                'unities.id as product__model__unity__id',
                'unities.name as product__model__unity__name',
                'products.relative_id AS product__relative_id',
                'products.mac AS product__mac',
                'products.serie AS product__serie',
                'products.price_sale AS product__price_sale',
                'products.currency AS product__currency',
                'products.num_guia AS product__num_guia',
                'products.condition_product AS product__condition_product',
                'products.disponibility AS product__disponibility',
                'products.product_status AS product__product_status',
                'detail_sales.mount_new as mount_new',
                'detail_sales.mount_second as mount_second',
                'detail_sales.mount_ill_fated as mount_ill_fated',
                'detail_sales.description as description',
                'detail_sales._sales_product as _sales_product',
                'detail_sales.status as status',
            ])
                ->join('products', 'detail_sales._product', 'products.id')
                ->join('branches', 'products._branch', 'branches.id')
                ->join('models', 'products._model', 'models.id')
                ->join('brands', 'models._brand', 'brands.id')
                ->join('categories', 'models._category', 'categories.id')
                ->join('unities', 'models._unity', 'unities.id')
                ->whereNotNull('detail_sales.status')
                ->where('_sales_product', $request->id)
                ->get();

            $details = array();
            foreach ($detailSaleJpa as $detailJpa) {
                $detail = gJSON::restore($detailJpa->toArray(), '__');
                $details[] = $detail;
            }

            $saleJpa = Sale::find($request->id);

            // $user = User::select([
            //     'users.id as id',
            //     'users.username as username',
            //     'people.name as person__name',
            //     'people.lastname as person__lastname'
            // ])
            //     ->join('people', 'users._person', 'people.id')
            //     ->where('users.id', $saleJpa->)->first();

            $installJpa = gJSON::restore($saleJpa->toArray(), '__');
            $installJpa['products'] = $details;

            $type_operation = '';

            $sumary = '';

            foreach ($details as $detail) {

                $model = "
                <div>
                    <center>
                        <p style='font-size: 11px; padding:1px;margin:1px;'><strong>{$detail['product']['model']['model']}</strong></p>
                        <p style='font-size: 11px; padding:1px;margin:1px;'><strong>{$detail['product']['model']['category']['category']}</strong></p>
                        <img src='https://almacen.fastnetperu.com.pe/api/model/{$detail['product']['model']['relative_id']}/mini' 
                        style='background-color: #38414a; height:50px;'>
                        <p> <strong style='font-size:10px; margin:0px;'>{$detail['description']}</strong></p>
                    </center>
                </div>
                ";

                $medida = "
                <div>
                    <p>{$detail['product']['model']['unity']['name']}</p>
                    <p>N: {$detail['mount_new']} | S: {$detail['mount_second']} | M: {$detail['mount_ill_fated']}</p>
                </div>
                ";

                $mac_serie = "
                    <div>
                        <p style='font-size: 13px;'>Mac: {$detail['product']['mac']}</p>
                        <p style='font-size: 13px;'>Serie: {$detail['product']['serie']}</p>
                    </div>
                ";

                $sumary .= "
                <tr>
                    <td><center >{$detail['id']}</center></td>
                    <td><center >{$model}</center></td>
                    <td><center >{$medida}</center></td>
                    <td><center >{$mac_serie}</center></td>
                </tr>
                ";
            }

            $fecha_hora = $installJpa['issue_date'];
            $parts_date = explode(" ", $fecha_hora);
            $fecha = $parts_date[0];
            $hora = $parts_date[1];

            $mounts_durability = '';
          
            $template = str_replace(
                [
                    '{num_operation}',
                    '{client}',
                    '{issue_date}',
                    '{date_sale}',
                    '{ejecutive}',
                    '{price}',
                    '{price_work_technical}',
                    '{description}',
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{mounts_durability}',
                    '{summary}',
                ],
                [
                    str_pad($installJpa['id'], 6, "0", STR_PAD_LEFT),
                    $installJpa['client']['name'] . ' ' . $installJpa['client']['lastname'],
                    $fecha .' H:'. $hora,
                    $installJpa['date_sale'],
                    $installJpa['creation_user']['person']['name'] . ' ' . $installJpa['creation_user']['person']['lastname'],
                    'S/' . $installJpa['price_installation'],
                    'S/'.$installJpa['price_work_technical'],
                    $installJpa['description'],
                    $branch_->name,
                    gTrace::getDate('long'),
                    $mounts_durability,
                    $sumary,
                ],
                $template
            );
            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('Instlación.pdf');
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

    public function store(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'sales', 'create')) {
                throw new Exception('No tienes permisos para agregar ventas');
            }

            if (
                !isset($request->_type_operation) ||
                !isset($request->_client) ||
                !isset($request->price_all) ||
                !isset($request->price_sale) ||
                !isset($request->date_sale)
            ) {
                throw new Exception('Error: No deje campos vacíos');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            $salesProduct->_client = $request->_client;
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_type_operation = $request->_type_operation;
            $salesProduct->type_intallation = 'VENTA';
            $salesProduct->date_sale = $request->date_sale;
            $salesProduct->status_sale = "CULMINADA";
            $salesProduct->price_all = $request->price_all;
            $salesProduct->price_installation = $request->price_sale;
            $salesProduct->discount = $request->discount;
            $salesProduct->type_pay = "CONTADO";

            if(isset($request->mount_dues)){
                $salesProduct->mount_dues = $request->mount_dues;
            }else{
                $salesProduct->mount_dues = 1;
            }

            if (isset($request->description)) {
                $salesProduct->description = $request->description;
            }

             if (isset($request->price_work_technical)) {
                $salesProduct->price_work_technical = $request->price_work_technical;
            }


            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            $PeopleJpa = People::where('id', $request->_client)->first();

            if (isset($request->data)) {
                foreach ($request->data as $product) {
                    $productJpa = Product::find($product['product']['id']);
                    $stock = Stock::where('_model', $productJpa->_model)
                        ->where('_branch', $branch_->id)
                        ->first();
                    if ($product['product']['type'] == "MATERIAL") {
                        $stock->mount_new = $stock->mount_new - $product['mount_new'];
                        $stock->mount_second = $stock->mount_second - $product['mount_second'];
                        $stock->mount_ill_fated = $stock->mount_ill_fated - $product['mount_ill_fated'];
                        $productJpa->mount = $stock->mount_new + $stock->mount_second;
                    } else {
                        if ($productJpa->product_status == "NUEVO") {
                            $stock->mount_new = intval($stock->mount_new) - 1;
                        } else if ($productJpa->product_status == "SEMINUEVO") {
                            $stock->mount_second = intval($stock->mount_second) - 1;
                        }
                        $productJpa->disponibility = 'VENDIDO A: '.$PeopleJpa->name.' '.$PeopleJpa->lastname;
                    }
                    $stock->save();
                    $productJpa->save();

                    $detailSale = new DetailSale();
                    $detailSale->_product = $productJpa->id;
                    $detailSale->mount_new = $product['mount_new'];
                    $detailSale->mount_second = $product['mount_second'];
                    $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                    // $detailSale->description = $product['description'];
                    $detailSale->_sales_product = $salesProduct->id;
                    $detailSale->status = '1';
                    $detailSale->save();
                }
            }
            $response->setStatus(200);
            $response->setMessage('Venta agregada correctamente');
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

            if (!gValidate::check($role->permissions, $branch, 'sales', 'read')) {
                throw new Exception('No tienes permisos para listar las ventas');
            }

            $query = Sale::select([
                '*',
            ])
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'client__name' || $column == '*') {
                    $q->orWhere('client__name', $type, $value);
                }
                if ($column == 'client__lastname' || $column == '*') {
                    $q->orWhere('client__lastname', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            })
                ->where('branch__correlative', $branch);

            $iTotalDisplayRecords = $query->count();

            $salesJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $sales = array();
            foreach ($salesJpa as $pending) {
                $saleJpa = gJSON::restore($pending->toArray(), '__');
                $sales[] = $saleJpa;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Sale::where('branch__correlative', $branch)->count());
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

    public function getSaleDetails(Request $request, $id)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'sales', 'read')) {
                throw new Exception('No tienes permisos para ver detalles de venta');
            }

            if (
                !isset($id)
            ) {
                throw new Exception('Error: No deje campos vacíos');
            }

            // $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $viewDetailsSalesJpa = ViewDetailsSales::where('sale_product_id', $id)->whereNotNUll('status')->get();


            $details = array();
            foreach ($viewDetailsSalesJpa as $detailsJpa) {
                $detail = gJSON::restore($detailsJpa->toArray(), '__');
                $details[] = $detail;
            }

            $response->setData($details);
            $response->setStatus(200);
            $response->setMessage('Venta agregada correctamente');
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

    public function update(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);

            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'sales', 'update')) {
                throw new Exception('No tienes permisos para actualizar ventas');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception('Error: No deje campos vacíos');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = SalesProducts::find($request->id);

            $PeopleJpa = People::where('id', $request->_client)->first();

            if (isset($request->_client)) {
                $salesProduct->_client = $request->_client;
                $detailsSalesJpa = DetailSale::where('_sales_product', $salesProduct->id)
                    ->get();
                foreach ($detailsSalesJpa as $detail) {
                    $productJpa = Product::find($detail['_product']);
                    if ($productJpa->type == "EQUIPO") {
                        $productJpa->disponibility = 'VENDIDO A: '.$PeopleJpa->name.' '.$PeopleJpa->lastname;
                        $productJpa->save();
                    }
                }
            }

            if (isset($request->date_sale)) {
                $salesProduct->date_sale = $request->date_sale;
            }
            if (isset($request->price_all)) {
                $salesProduct->price_all = $request->price_all;
            }
            if (isset($request->discount)) {
                $salesProduct->discount = $request->discount;
            }
            if (isset($request->price_sale)) {
                $salesProduct->price_installation = $request->price_sale;
            }
            if (isset($request->mount_dues)) {
                $salesProduct->mount_dues = $request->mount_dues;
            }
            if (isset($request->price_work_technical)) {
                $salesProduct->price_work_technical = $request->price_work_technical;
            }

            $salesProduct->description = $request->description;
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');

            if (isset($request->data)) {
                foreach ($request->data as $product) {
                    if (isset($product['id'])) {
                        $productJpa = Product::find($product['product']['id']);
                        $detailSale = DetailSale::find($product['id']);
                        if ($product['product']['type'] == "MATERIAL") {

                            $stock = Stock::where('_model', $productJpa->_model)
                                ->where('_branch', $branch_->id)
                                ->first();

                            if (intval($detailSale->mount_new) != intval($product['mount_new'])) {
                                if (intval($detailSale->mount_new) > intval($product['mount_new'])) {
                                    $mount_dif = intval($detailSale->mount_new) - intval($product['mount_new']);
                                    $stock->mount_new = $stock->mount_new + $mount_dif;
                                } else if (intval($detailSale->mount_new) < intval($product['mount_new'])) {
                                    $mount_dif = intval($product['mount_new']) - intval($detailSale->mount_new);
                                    $stock->mount_new = $stock->mount_new -  $mount_dif;
                                }
                            }

                            if (intval($detailSale->mount_second) != intval($product['mount_second'])) {
                                if (intval($detailSale->mount_second) > intval($product['mount_second'])) {
                                    $mount_dif = intval($detailSale->mount_second) - intval($product['mount_second']);
                                    $stock->mount_second = $stock->mount_second + $mount_dif;
                                } else if (intval($detailSale->mount_second) < intval($product['mount_second'])) {
                                    $mount_dif = intval($product['mount_second']) - intval($detailSale->mount_second);
                                    $stock->mount_second = $stock->mount_second -  $mount_dif;
                                }
                            }

                            if (intval($detailSale->mount_ill_fated) != intval($product['mount_ill_fated'])) {
                                if (intval($detailSale->mount_ill_fated) > intval($product['mount_ill_fated'])) {
                                    $mount_dif = intval($detailSale->mount_ill_fated) - intval($product['mount_ill_fated']);
                                    $stock->mount_ill_fated = $stock->mount_ill_fated + $mount_dif;
                                } else if (intval($detailSale->mount_ill_fated) < intval($product['mount_ill_fated'])) {
                                    $mount_dif = intval($product['mount_ill_fated']) - intval($detailSale->mount_ill_fated);
                                    $stock->mount_ill_fated = $stock->mount_ill_fated -  $mount_dif;
                                }
                            }


                            $productJpa->mount = $stock->mount_new + $stock->mount_second;
                            $stock->save();
                        }
                        $detailSale->mount_new = $product['mount_new'];
                        $detailSale->mount_second = $product['mount_second'];
                        $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                        $productJpa->save();
                        $detailSale->save();
                    } else {
                        $productJpa = Product::find($product['product']['id']);
                        $stock = Stock::where('_model', $productJpa->_model)
                            ->where('_branch', $branch_->id)
                            ->first();

                        if ($product['product']['type'] == "MATERIAL") {
                            $stock->mount_new = $stock->mount_new - $product['mount_new'];
                            $stock->mount_second = $stock->mount_second - $product['mount_second'];
                            $stock->mount_ill_fated = $stock->mount_ill_fated - $product['mount_ill_fated'];
                            $productJpa->mount = $stock->mount_new+$stock->mount_second;
                        } else {
                            $productJpa->disponibility = 'VENDIDO A: '.$PeopleJpa->name.' '.$PeopleJpa->lastname;
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
                    }
                }
            }
            $salesProduct->save();
            $response->setStatus(200);
            $response->setMessage('Instalación atualizada correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function cancelUseProduct(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);

            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'sales', 'update')) {
                throw new Exception('No tienes permisos para actualizar');
            }

            if (!isset($request->id)) {
                throw new Exception('Error: No deje campos vacíos');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = SalesProducts::find($request->sale_product_id);
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');

            $detailSale = DetailSale::find($request->id);
            $detailSale->status = null;

            $productJpa = Product::find($request->product['id']);

            $stock = Stock::where('_model', $productJpa->_model)
                ->where('_branch', $branch_->id)
                ->first();
            if ($productJpa->type == "MATERIAL") {
                $stock->mount_new = $stock->mount_new + $request->mount_new;
                $stock->mount_second = $stock->mount_second + $request->mount_second;
                $stock->mount_ill_fated = $stock->mount_ill_fated + $request->mount_ill_fated;
            } else if ($productJpa->type == "EQUIPO") {
                $productJpa->disponibility = "DISPONIBLE";
                $stock = Stock::where('_model', $productJpa->_model)
                    ->where('_branch', $branch_->id)
                    ->first();
                if ($productJpa->product_status == "NUEVO") {
                    $stock->mount_new = intval($stock->mount_new) + 1;
                } else if ($productJpa->product_status == "SEMINUEVO") {
                    $stock->mount_second = intval($stock->mount_second) + 1;
                }
                $productJpa->save();
            }

            $productJpa->mount =  $stock->mount_new +  $stock->mount_second;

            $stock->save();
            $detailSale->save();
            $salesProduct->save();
            $productJpa->save();

            $response->setStatus(200);
            $response->setMessage('Liquidación atualizada correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
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

            if (!gValidate::check($role->permissions, $branch, 'sales', 'delete')) {
                throw new Exception('No tienes permisos para eliminar ventas');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception('Error: No deje campos vacíos');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = SalesProducts::find($request->id);
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');

            $detailsSalesJpa = DetailSale::where('_sales_product', $salesProduct->id)
                ->get();
            foreach ($detailsSalesJpa as $detail) {
                $detailSale = DetailSale::find($detail['id']);
                $detailSale->status = null;
                $productJpa = Product::find($detail['_product']);
                $productJpa->disponibility = "DISPONIBLE";
                $stock = Stock::where('_model', $productJpa->_model)
                    ->where('_branch', $branch_->id)
                    ->first();
                 if ($productJpa->type == "MATERIAL") {
                    $stock->mount_new = $stock->mount_new + $detail['mount_new'];
                    $stock->mount_second = $stock->mount_second + $detail['mount_second'];
                    $stock->mount_ill_fated = $stock->mount_ill_fated + $detail['mount_ill_fated'];
                   
                }else{
                    if ($productJpa->product_status == 'NUEVO') {
                        $stock->mount_new = $stock->mount_new + 1;
                    }else if($productJpa->product_status == 'SEMINUEVO'){
                        $stock->mount_second = $stock->mount_second + 1;
                    }
                }
                $stock->save();
                $productJpa->save();
                $detailSale->save();
            }

          
            $salesProduct->save();
            $response->setStatus(200);
            $response->setMessage('Venta eliminada correactamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }
}
