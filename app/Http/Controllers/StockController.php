<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
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

            $query = ViewStock::select(['*']);

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
                $curencies = "
                <p style='margin-top:0px;magin-bottom:0px;'>Compra:
                <strong>{$currency}{$models['model']['price_buy']}
                </strong></p>
                <p style='margin-top:0px;magin-bottom:0px;'>Nuevo: <strong>{$currency}{$models['model']['price_sale']}
                </strong></p>
                   <p style='margin-top:0px;magin-bottom:0px;'>Seminuevo: <strong>{$currency}{$models['model']['price_sale_second']}
                </strong></p>
                ";

                $stock = "
                <p style='margin-top:0px;magin-bottom:0px;'>Nuevos: <strong>{$models['mount_new']}</strong></p>
                <p style='margin-top:0px;magin-bottom:0px;'>Seminuevos <strong>{$models['mount_second']}</strong></p>
               ";

                $sumary .= "
                <tr>
                    <td class='text-center'>{$models['id']}</td>
                    <td><p><strong style='font-size:14px;'>{$models['model']['model']}</strong></p><img src='https://almacendev.fastnetperu.com.pe/api/model/{$models['model']['relative_id']}/mini' style='background-color: #38414a;object-fit: cover; object-position: center center; cursor: pointer; height:50px;'></img></td>
                    <td>{$curencies}</td>
                    <td class='text-center'>{$stock}</td>
                    <td class=''>{$models['model']['description']}</td>
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

    public function generateReportByProductsSelected(Request $request)
    {
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
                'people.lastname as person__lastname'
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
                $query->where('mount_new', '>', '0');
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

            $stockJpa->save();

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

    // public function regularizar(Request $request)
    // {
    //     $response = new Response();
    //     try {
    //         $models = Models::select('id','model')->get();
    //         $branchs = Branch::select('id', 'name')->get();
    //         $exist = [];
    //         foreach($branchs as $branch){
    //             foreach($models as $model){
    //                 $stockIsExist = Stock::select('id','_model','_branch')
    //                 ->where('_model', $model['id'])
    //                 ->where('_branch', $branch['id'])
    //                 ->first();
    //                 if(!$stockIsExist){
    //                     $stockJpa = new Stock();
    //                     $stockJpa->_model = $model['id'];
    //                     $stockJpa->mount = '0';
    //                     $stockJpa->stock_min = '5';
    //                     $stockJpa->_branch = $branch['id'];
    //                     $stockJpa->status = '1';
    //                     $stockJpa->save();
    //                 }else{
    //                     $exist[] = [
    //                         'model'=>$model['model'],
    //                         'branch'=>$branch['name']
    //                     ];
    //                 }
    //             }
    //         }

    //         $response->setData($exist);
    //         $response->setStatus(200);
    //         $response->setMessage('stocks actualizados correctamente');
    //     } catch (\Throwable$th) {
    //         $response->setStatus(400);
    //         $response->setMessage($th->getMessage());
    //     } finally {
    //         return response(
    //             $response->toArray(),
    //             $response->getStatus()
    //         );
    //     }
    // }
}
