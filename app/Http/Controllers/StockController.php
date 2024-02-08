<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\Models;
use App\Models\Product;
use App\Models\Response;
use App\Models\Stock;
use App\Models\User;
use App\Models\ViewStock;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{

    public function generateReportByStockByProducts(Request $request)
    {
        set_time_limit(120);
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'stock', 'read')) {
                throw new Exception('No tienes permisos para listar stock');
            }

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $options = new Options();
            $options->set('isRemoteEnabled', true);

            $pdf = new Dompdf($options);

            $template = file_get_contents('../storage/templates/reportStockByProducts.html');

            $sumary = '';

            $query = ViewStock::select(['*'])->orderBy('category__category', 'asc');

            $stocksJpa = $query->where('branch__correlative', $branch)->get();

            $stocks = array();
            foreach ($stocksJpa as $stockJpa) {
                $stock = gJSON::restore($stockJpa->toArray(), '__');
                $stocks[] = $stock;
            }

            foreach ($stocks as $models) {
                $currency = "$";
                if ($models['model']['currency'] == "SOLES") {
                    $currency = "S/.";
                }

                $product = "
                <div style='font-size:12px'>
                    <p style='margin-top:0px;magin-bottom:0px;'>Modelo: <strong>{$models['model']['model']}</strong></p>
                    <p style='margin-top:0px;magin-bottom:0px;'>Categoria: <strong>{$models['category']['category']}</strong></p>
                </div>
                ";

                $stock = "
                <div style='padding:0px;'>
                    <p style='margin:0px;'>Nuevos: <strong style='font-size:16px'>{$models['mount_new']}</strong></p>
                    <p style='margin:0px'>Seminuevos <strong style='font-size:16px'>{$models['mount_second']}</strong></p>
                    <p style='margin:0px'>Malogrados <strong style='font-size:16px'>{$models['mount_ill_fated']}</strong></p>
                </div>
                ";

                //     $sumary .= "
                //     <tr>
                //         <td class='text-center'>{$models['id']}</td>
                //         <td><p><strong style='font-size:14px;'>{$models['model']['model']}</strong></p><img src='https://almacendev.fastnetperu.com.pe/api/model/{$models['model']['relative_id']}/mini' style='background-color: #38414a;object-fit: cover; object-position: center center; cursor: pointer; height:50px;'></img></td>
                //         <td>{$curencies}</td>
                //         <td class='text-center'>{$stock}</td>
                //         <td class=''>{$models['model']['description']}</td>
                //     </tr>
                // ";

                $actual = "
                <div style='margin-left:35px;'>
                    <input style='width:80px; border:solid 2px #000; height: 20px; margin: 1px;'> <br>
                    <input style='width:80px; border:solid 2px #000;  height: 20px; margin: 1px;'> <br>
                    <input style='width:80px; border:solid 2px #000;  height: 20px; margin: 1px;'> <br>
                </div>
            ";

                $sumary .= "
            <tr>
                <td class='text-center'>{$models['id']}</td>
                <td><p><strong style='font-size:14px;'>{$product}</strong></p></td>
                <td>{$stock}</td>
                <td></td>
            </tr>
        ";
            }

            $template = str_replace(
                [
                    '{branch_name}',
                    '{issue_long_date}',
                    '{summary}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $sumary,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();

            return $pdf->stream('Informe.pdf');
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

    public function generateReportStock(Request $request)
    {
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'stock', 'read')) {
                throw new Exception('No tienes permisos para listar stock');
            }

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $options = new Options();
            $options->set('isRemoteEnabled', true);

            $pdf = new Dompdf($options);

            $template = file_get_contents('../storage/templates/reportStock.html');

            $sumary = '';

            $user = User::select([
                'users.id as id',
                'users.username as username',
                'people.name as person__name',
                'people.lastname as person__lastname',
                'people.doc_number as person__doc_number',
            ])
                ->join('people', 'users._person', 'people.id')
                ->where('users.id', $userid)->first();

            $stocksJpa = ViewStock::select(['*'])
                ->where('branch__correlative', $branch)
                ->where('mount_new', '>', '0')
                ->orWhere('mount_second', '>', '0')
                ->where('branch__correlative', $branch)
                ->orWhere('mount_ill_fated', '>', '0')
                ->where('branch__correlative', $branch)
                ->orderBy('category__category', 'asc')->get();

            $stocks = array();
            foreach ($stocksJpa as $stockJpa) {
                $stock = gJSON::restore($stockJpa->toArray(), '__');
                $stocks[] = $stock;
            }

            $count = 1;

            foreach ($stocks as $models) {
                $currency = "$";
                if ($models['model']['currency'] == "SOLES") {
                    $currency = "S/.";
                }
                // $url = "https://almacen.fastnetperu.com.pe/api/model/{$models['model']['relative_id']}/mini";

                // $headers = @get_headers($url);

                // if ($headers && strpos($headers[0], '200') !== false) {

                // } else {
                //     throw new Exception('La imagen del modelo '. $models['model']['model'] .' no existe con el id '. $models['model']['relative_id'] );
                // }
                $url = "https://almacen.fastnetperu.com.pe/api/model/{$models['model']['relative_id']}/mini";

                $image = "
                    <div>
                        <center>
                            <img src='{$url}' class='img_stock'>
                        </center>
                    </div>
                    ";

                $product = "
                    <div>
                        <p style='margin-top:0px;magin-bottom:0px;'><strong>{$models['category']['category']} {$models['brand']['brand']} {$models['model']['model']}</strong></p>
                    </div>
                    ";

                $stock = "
                    <div>
                        <center style='font-size:14px;'>
                            <p><strong>N: " . ($models['mount_new'] == floor($models['mount_new']) ? floor($models['mount_new']) : $models['mount_new']) . "</strong>    <strong style='margin-left:12px;'>S: " . ($models['mount_second'] == floor($models['mount_second']) ? floor($models['mount_second']) : $models['mount_second']) . "</strong>    <strong style='margin-left:12px;'>M: " . ($models['mount_ill_fated'] == floor($models['mount_ill_fated']) ? floor($models['mount_ill_fated']) : $models['mount_ill_fated']) . "</strong></p>
                        </center>
                    </div>
                ";

               
                $sumary .= "
                    <tr>
                        <td class='text-center'>{$count}</td>
                        <td>{$image}</td>
                        <td><p><strong style='font-size:14px;'>{$product}</strong></p></td>
                        <td>{$stock}</td>
                    </tr>
                    ";
                $count += 1;
            }

            $template = str_replace(
                [
                    '{branch_name}',
                    '{issue_long_date}',
                    '{risponsible}',
                    '{doc_number_responsible}',
                    '{in_charge}',
                    '{doc_number_in_charge}',
                    '{summary}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $user->person__name . ' ' . $user->person__lastname,
                    $user->person__doc_number,
                    'ROMMY MELITHZA PRADO VENEGAS',
                    '70813909',
                    $sumary,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();

            return $pdf->stream('Informe.pdf');
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

    public function generateReportByProductsSelected(Request $request)
    {
        set_time_limit(120);
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'stock', 'read')) {
                throw new Exception('No tienes permisos para listar stock');
            }

            if (!isset($request->data)) {
                throw new Exception("Error: no se enviaron datos para generar el pdf");
            }

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $user = User::select([
                'users.id as id',
                'users.username as username',
                'people.name as person__name',
                'people.lastname as person__lastname',
            ])
                ->join('people', 'users._person', 'people.id')
                ->where('users.id', $userid)->first();

            $options = new Options();
            $options->set('isRemoteEnabled', true);

            $pdf = new Dompdf($options);

            $template = file_get_contents('../storage/templates/reportStockByProductsSelected.html');

            $sumary = '';
            $count = 1;

            foreach ($request->data as $model) {

                $stock = "
                <div>
                    <p>Nuevos: <trong>{$model['mount_new']}</trong></p>
                    <p>Seminuevos: <strong>{$model['mount_second']}</strong></p>
                    <p>Malogrados: <strong>{$model['mount_ill_fated']}</strong></p>
                    <p>Pedido: <strong>{$model['mount_order']}</strong></p>
                </div>
                ";

                $model_ = "
                <center style='font-size:15px;'>
                    <p><strong>{$model['model']['model']}</strong></p>
                    <p>CAT: <strong>{$model['category']['category']}</strong></p>
                </center>
                ";

                $sumary .= "
                <tr>
                    <td><center style='font-size:15px;'>{$count}</center></td>
                    <td><span style='font-size:15px;'>{$stock}</span></td>
                    <td><center style='font-size:15px;'>{$model['model']['unity']['name']}</center></td>
                    <td>{$model_}</td>
                </tr>
                ";
                $count = $count + 1;
            }

            $template = str_replace(
                [
                    '{branch_name}',
                    '{user_name}',
                    '{issue_long_date}',
                    '{summary}',
                ],
                [
                    $branch_->name,
                    $user->person__name . ' ' . $user->person__lastname,
                    gTrace::getDate('long'),
                    $sumary,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();

            return $pdf->stream('Informe.pdf');
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

    public function paginate(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);

            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'stock', 'read')) {
                throw new Exception('No tienes permisos para listar el stock');
            }

            $query = ViewStock::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

            if ($request->all) {
                $query->where(function ($q) use ($request) {
                    $q->where('mount_new', '>', '0')
                        ->orWhere('mount_second', '>', '0');
                })->whereNotNull('status');
            }else{
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
                    $q->orWhere('category__category', $type, $value);
                }
                if ($column == 'model__model' || $column == '*') {
                    $q->orWhere('model__model', $type, $value);
                }
            })->where('branch__correlative', $branch);

            $iTotalDisplayRecords = $query->count();

            $stocksJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $stocks = array();
            foreach ($stocksJpa as $stockJpa) {
                $stock = gJSON::restore($stockJpa->toArray(), '__');
                $stocks[] = $stock;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewStock::count());
            $response->setData($stocks);
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
        $tatus = 400;
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'stock', 'update')) {
                throw new Exception('No tienes permisos para actualizar el stock');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $stockJpa = Stock::find($request->id);

            if (isset($request->stock_min)) {
                $stockJpa->stock_min = $request->stock_min;
            }

            if (isset($request->mount_new)) {
                $stockJpa->mount_new = $request->mount_new;
            }

            if (isset($request->mount_second)) {
                $stockJpa->mount_second = $request->mount_second;
            }

            if (isset($request->mount_ill_fated)) {
                $stockJpa->mount_ill_fated = $request->mount_ill_fated;
            }

            $ProductJpa = Product::where('_model', $stockJpa->_model)->where('_branch', $stockJpa->_branch)->first();

            if ($ProductJpa) {
                if ($ProductJpa->type == "MATERIAL") {
                    $ProductJpa->mount = $stockJpa->mount_new + $stockJpa->mount_second;
                }
                $ProductJpa->save();
            } else {
                $stockJpa->mount_new = 0;
                $stockJpa->mount_second = 0;
                $stockJpa->mount_ill_fated = 0;
                $tatus = 411;
                $stockJpa->save();

                throw new Exception("No tiene este producto en su almacen.");
            }

            $stockJpa->save();

            $response->setStatus(200);
            $response->setMessage('Producto actualizado correctamente');
        } catch (\Throwable $th) {
            $response->setStatus($tatus);
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
        $tatus = 400;
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'stock', 'update')) {
                throw new Exception('No tienes permisos para actualizar el stock');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $stockJpa = Stock::find($request->id);

            $stockJpa->stock_min = 0;
            $stockJpa->mount_new = 0;
            $stockJpa->mount_second = 0;
            $stockJpa->mount_ill_fated = 0;
            $stockJpa->_update_user = $userid;
            $stockJpa->update_date = gTrace::getDate('mysql');
            $stockJpa->status = null;
            $stockJpa->save();

            $response->setStatus(200);
            $response->setMessage('Stock eliminado correctamente');
        } catch (\Throwable $th) {
            $response->setStatus($tatus);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function getStockByModel(Request $request)
    {
        $response = new Response();
        $tatus = 400;
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'stock', 'read')) {
                throw new Exception('No tienes permisos para ver el stock');
            }

            if (
                !isset($request->model_id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();
            $stockJpa = Stock::where('_model', $request->model_id)->where('_branch', $branch_->id)->first();

            $response->setData([$stockJpa]);
            $response->setStatus(200);
            $response->setMessage('Producto actualizado correctamente');
        } catch (\Throwable $th) {
            $response->setStatus($tatus);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function changeStar(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'stock', 'update')) {
                throw new Exception('No tienes permisos para actualizar stock.');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $modelsJpa = Stock::find($request->id);
            if (!$modelsJpa) {
                throw new Exception('El stock que deseas cambiar no existe');
            }

            $modelsJpa->star = $request->star;
            $modelsJpa->save();

            $response->setStatus(200);
            $response->setMessage('El stock a sido cambiado correctamente');
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

    public function RegularizeMountsByModel(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'stock', 'update')) {
                throw new Exception('No tienes permisos para actualizar stock.');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $stockJpa = Stock::find($request->id);
            if (!$stockJpa) {
                throw new Exception('El stock que deseas cambiar no existe');
            }

            $productsJpa = Product::where('_model', $request->model['id'])
                ->where('_branch', $branch_->id)
                ->where('disponibility', 'DISPONIBLE')
                ->where(function ($q) {
                    $q->where('product_status', 'NUEVO')
                        ->orWhere('product_status', 'SEMINUEVO')
                        ->orWhere('product_status', 'MALOGRADO')
                        ->orWhere('product_status', 'POR REVISAR');
                })
                ->whereNotNull('status')
                ->get();

            $new = $second = $ill_fated = 0;

            $type = '';

            if ($productsJpa->isEmpty()) {
                $stockJpa->mount_new = 0;
                $stockJpa->mount_second = 0;
                $stockJpa->mount_ill_fated = 0;
            } else {
                foreach ($productsJpa as $product) {
                    if ($product['type'] == 'EQUIPO') {
                        $type = 'EQUIPO';
                        if ($product['product_status'] == 'NUEVO') {
                            $new += 1;
                        } else if ($product['product_status'] == 'SEMINUEVO') {
                            $second += 1;
                        } else {
                            $ill_fated += 1;
                        }
                    } else {
                        $type = 'MATERIAL';

                        if ($stockJpa->mount_new < 0) {
                            $stockJpa->mount_new = 0;
                        }
                        if ($stockJpa->mount_second < 0) {
                            $stockJpa->mount_second = 0;
                        }
                        if ($stockJpa->mount_ill_fated < 0) {
                            $stockJpa->mount_ill_fated = 0;
                        }

                        $productJpa = Product::find($product['id']);
                        $productJpa->mount = $stockJpa->mount_new + $stockJpa->mount_second;
                        $productJpa->save();
                    }
                }
            }

            if ($type == 'EQUIPO') {
                $stockJpa->mount_new = $new;
                $stockJpa->mount_second = $second;
                $stockJpa->mount_ill_fated = $ill_fated;
            }

            $stockJpa->save();
            $response->setStatus(200);
            $response->setMessage('Pperación Correcta');
            $response->setData($productsJpa->toArray());
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

    public function regularizeMountByBranch(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'stock', 'update')) {
                throw new Exception('No tienes permisos para actualizar stock.');
            }

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $models = Models::select(['id', 'model'])->whereNotNull('status')->get();

            foreach ($models as $model) {

                $stockJpa = Stock::whereNotNull('status')->where('_model', $model['id'])->where('_branch', $branch_->id)->first();

                if ($stockJpa) {

                    $productsJpa = Product::where('_model', $model['id'])
                        ->where('_branch', $branch_->id)
                        ->where('disponibility', 'DISPONIBLE')
                        ->where(function ($q) {
                            $q->where('product_status', 'NUEVO')
                                ->orWhere('product_status', 'SEMINUEVO')
                                ->orWhere('product_status', 'MALOGRADO')
                                ->orWhere('product_status', 'POR REVISAR');
                        })
                        ->whereNotNull('status')
                        ->get();

                    $new = $second = $ill_fated = 0;

                    $type = '';

                    if ($productsJpa->isEmpty()) {
                        $stockJpa->mount_new = 0;
                        $stockJpa->mount_second = 0;
                        $stockJpa->mount_ill_fated = 0;
                    } else {
                        foreach ($productsJpa as $product) {
                            if ($product['type'] == 'EQUIPO') {
                                $type = 'EQUIPO';
                                if ($product['product_status'] == 'NUEVO') {
                                    $new += 1;
                                } else if ($product['product_status'] == 'SEMINUEVO') {
                                    $second += 1;
                                } else {
                                    $ill_fated += 1;
                                }
                            } else {
                                $type = 'MATERIAL';

                                if ($stockJpa->mount_new < 0) {
                                    $stockJpa->mount_new = 0;
                                }
                                if ($stockJpa->mount_second < 0) {
                                    $stockJpa->mount_second = 0;
                                }
                                if ($stockJpa->mount_ill_fated < 0) {
                                    $stockJpa->mount_ill_fated = 0;
                                }

                                $productJpa = Product::find($product['id']);
                                $productJpa->mount = $stockJpa->mount_new + $stockJpa->mount_second;
                                $productJpa->save();
                            }
                        }
                    }
                    if ($type == 'EQUIPO') {
                        $stockJpa->mount_new = $new;
                        $stockJpa->mount_second = $second;
                        $stockJpa->mount_ill_fated = $ill_fated;
                    }
                    $stockJpa->save();
                } else {
                    // throw new Exception('model.'. $model['id']);
                }

            }

            $response->setStatus(200);
            $response->setMessage('Pperación Correcta');
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

    public function regularizar(Request $request)
    {
        $response = new Response();
        try {
            $models = Models::select('id', 'model')->get();
            $branchs = Branch::select('id', 'name')->get();
            $exist = [];
            foreach ($branchs as $branch) {
                foreach ($models as $model) {
                    $stockIsExist = Stock::select('id', '_model', '_branch')
                        ->where('_model', $model['id'])
                        ->where('_branch', $branch['id'])
                        ->first();
                    if (!$stockIsExist) {
                        $stockJpa = new Stock();
                        $stockJpa->_model = $model['id'];
                        $stockJpa->mount_new = '0';
                        $stockJpa->mount_second = '0';
                        $stockJpa->mount_ill_fated = '0';
                        $stockJpa->stock_min = '5';
                        $stockJpa->_branch = $branch['id'];
                        $stockJpa->status = '1';
                        $stockJpa->save();
                    } else {
                        $exist[] = [
                            'model' => $model['model'],
                            'branch' => $branch['name'],
                        ];
                    }
                }
            }

            $response->setData($exist);
            $response->setStatus(200);
            $response->setMessage('stocks actualizados correctamente');
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

    public function proveImagesByModels()
    {
        $response = new Response();
        try {
            $models = Models::select('id', 'model', 'relative_id')->get();
            foreach ($models as $model) {
                try {
                    $options = new Options();
                    $options->set('isRemoteEnabled', true);
                    $pdf = new Dompdf($options);
                    $url = "https://almacen.fastnetperu.com.pe/api/model/{$model['relative_id']}/mini";
                    $template = "
                    <html>
                        <head>
                            <style>
                                @page { margin: 0px; }
                                body { margin: 0px; }
                            </style>
                        </head>
                        <body>
                            <img src='{$url}' style='width:10%; height:10%;'>
                        </body>
                    </html>
                    ";
                    $pdf->loadHTML($template);
                    $pdf->render();
                    $output = $pdf->output();
                    $tempDir = sys_get_temp_dir();
                    file_put_contents("{$tempDir}/pdf.pdf", $output);
                } catch (\Throwable $th) {
                    throw new Exception('La imagen del modelo ' . $model['model'] . ' no existe con el id ' . $model['relative_id'] . 'El error es:' . $th->getMessage());
                }
            }
            $response->setData($models->toArray());
            $response->setStatus(200);
            $response->setMessage('No hay errores en la carga de imagenes');
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
