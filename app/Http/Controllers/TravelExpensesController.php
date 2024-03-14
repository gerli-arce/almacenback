<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\ChargeGasoline;
use App\Models\Branch;
use App\Models\Response;
use App\Models\TravelExpenses;
use App\Models\ViewTravelExpenses;
use App\Models\ViewUsers;

use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TravelExpensesController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'technicals', 'create')) {
                throw new Exception("No tienes permisos para realizar esta acción");
            }

            if (
                !isset($request->mobility_type) ||
                !isset($request->date_expense) ||
                !isset($request->price_all)
            ) {
                throw new Exception("Error en los datos de entrada");
            }

            $TravelExpensesJpa = new TravelExpenses();
            $TravelExpensesJpa->_technical = $request->technical['id'];
            $TravelExpensesJpa->mobility_type = $request->mobility_type;
            $TravelExpensesJpa->date_expense = $request->date_expense;
            $TravelExpensesJpa->description = $request->description_charge_gasoline;
            $TravelExpensesJpa->expense_price_all = $request->expense_price_all;
            $TravelExpensesJpa->price_all = $request->price_all;
            $TravelExpensesJpa->expenses = json_encode($request->expenses);

            if ($request->mobility_type == "PASAJERO") {
                $TravelExpensesJpa->price_drive = $request->price_drive;
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
                        $TravelExpensesJpa->image_type = $request->image_type;
                        $TravelExpensesJpa->image_mini = base64_decode($request->image_mini);
                        $TravelExpensesJpa->image_full = base64_decode($request->image_full);
                    } else {
                        $TravelExpensesJpa->image_type = null;
                        $TravelExpensesJpa->image_mini = null;
                        $TravelExpensesJpa->image_full = null;
                    }
                }

            } else {

                $ChargeGasolineJpa = new ChargeGasoline();
                $ChargeGasolineJpa->_technical = $request->technical['id'];
                $ChargeGasolineJpa->_car = $request->car_movility;
                $ChargeGasolineJpa->date = $request->date_expense;
                $ChargeGasolineJpa->gasoline_type = $request->gasoline_type;
                if (isset($request->description_charge_gasoline)) {
                    $ChargeGasolineJpa->description = $request->description_charge_gasoline;
                }
                $ChargeGasolineJpa->price_all = $request->price_gasoline;
                $ChargeGasolineJpa->igv = $request->price_igv;
                $ChargeGasolineJpa->price_engraved = $request->price_engraved;

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
                        $ChargeGasolineJpa->image_type = $request->image_type;
                        $ChargeGasolineJpa->image_mini = base64_decode($request->image_mini);
                        $ChargeGasolineJpa->image_full = base64_decode($request->image_full);
                    } else {
                        $ChargeGasolineJpa->image_type = null;
                        $ChargeGasolineJpa->image_mini = null;
                        $ChargeGasolineJpa->image_full = null;
                    }
                }

                $ChargeGasolineJpa->creation_date = gTrace::getDate('mysql');
                $ChargeGasolineJpa->_creation_user = $userid;
                $ChargeGasolineJpa->update_date = gTrace::getDate('mysql');
                $ChargeGasolineJpa->_update_user = $userid;
                $ChargeGasolineJpa->status = "1";
                $ChargeGasolineJpa->save();

                $TravelExpensesJpa->_change_gasoline = $ChargeGasolineJpa->id;
                $TravelExpensesJpa->_car = $request->car_movility;
            }

            $TravelExpensesJpa->creation_date = gTrace::getDate('mysql');
            $TravelExpensesJpa->_creation_user = $userid;
            $TravelExpensesJpa->update_date = gTrace::getDate('mysql');
            $TravelExpensesJpa->_update_user = $userid;
            $TravelExpensesJpa->status = "1";
            $TravelExpensesJpa->save();

            $res = [
                'id' => $TravelExpensesJpa->id, 
                'mobility_type' => $TravelExpensesJpa->mobility_type,
                'date_expense' => $TravelExpensesJpa->date_expense,
                'description' => $TravelExpensesJpa->description,
                'expense_price_all' => $TravelExpensesJpa->expense_price_all,
                'price_all' => $TravelExpensesJpa->price_all,
                'expenses' => json_decode($TravelExpensesJpa->expenses),
                '_change_gasoline' => $TravelExpensesJpa->_change_gasoline ?? null, 
            ];
    
            if ($request->mobility_type === 'AUTOMÓVIL') {
                $res['car_movility'] = $ChargeGasolineJpa->_car;
                $res['gasoline_type'] = $ChargeGasolineJpa->gasoline_type;
                $res['price_gasoline'] = $ChargeGasolineJpa->price_all;
                $res['price_igv'] = $ChargeGasolineJpa->igv;
                $res['price_engraved'] = $ChargeGasolineJpa->price_engraved;
            }else{
                $res['price_drive'] = $ChargeGasolineJpa->price_drive;
            }
    
            $response->setStatus(200);
            $response->setMessage('Gasto de viaje creado correctamente');
            $response->setData($res);

        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'LN: ' . $th->getLine());
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

            if (!gValidate::check($role->permissions, $branch, 'technicals', 'read')) {
                throw new Exception('No tienes permisos');
            }

            $query = ViewTravelExpenses::select('*')
                ->orderBy($request->order['column'], $request->order['dir'])
                ->where('_technical', $request->_technical);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
               
                if ($column == 'mobility_type' || $column == '*') {
                    $q->where('mobility_type', $type, $value);
                }
                if ($column == 'date_expense' || $column == '*') {
                    $q->orWhere('date_expense', $type, $value);
                }
                if ($column == 'price_all' || $column == '*') {
                    $q->orWhere('price_all', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
                if ($column == 'status' || $column == '*') {
                    $q->orWhere('status', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();

            $ChargesCarJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $charges_gasoline = array();
            foreach ($ChargesCarJpa as $ChargecarJpa) {
                $review = gJSON::restore($ChargecarJpa->toArray(), '__');
                $charges_gasoline[] = $review;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correctaaa');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewTravelExpenses::where('_car', $request->_car)->count());
            $response->setData($charges_gasoline);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'LN: ' . $th->getLine() . 'FL: ' . $th->getFile());
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
                throw new Exception("No tienes permisos para realizar esta acción");
            }

            $TravelExpensesJpa = TravelExpenses::find($request->id);

            if (!$TravelExpensesJpa) {
                throw new Exception("Gasto de viaje no encontrado");
            }

            if (
                !isset($request->mobility_type) ||
                !isset($request->date_expense) ||
                !isset($request->price_all)
            ) {
                throw new Exception("Error en los datos de entrada");
            }

            $TravelExpensesJpa->mobility_type = $request->mobility_type;
            $TravelExpensesJpa->date_expense = $request->date_expense;
            $TravelExpensesJpa->description = $request->description_charge_gasoline;
            $TravelExpensesJpa->expense_price_all = $request->expense_price_all;
            $TravelExpensesJpa->price_all = $request->price_all;
            $TravelExpensesJpa->expenses = json_encode($request->expenses);

            $ChargeGasolineJpa = null;

            if ($request->mobility_type == "PASAJERO") {
                $TravelExpensesJpa->price_drive = $request->price_drive;

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
                        $TravelExpensesJpa->image_type = $request->image_type;
                        $TravelExpensesJpa->image_mini = base64_decode($request->image_mini);
                        $TravelExpensesJpa->image_full = base64_decode($request->image_full);
                    } else {
                        $TravelExpensesJpa->image_type = null;
                        $TravelExpensesJpa->image_mini = null;
                        $TravelExpensesJpa->image_full = null;
                    }
                }

            } else {

                $ChargeGasolineJpa = ChargeGasoline::find($TravelExpensesJpa->_change_gasoline);

                if ($ChargeGasolineJpa) {
                    $ChargeGasolineJpa->date = $request->date_expense;
                    $ChargeGasolineJpa->gasoline_type = $request->gasoline_type;
                    if (isset($request->description_charge_gasoline)) {
                        $ChargeGasolineJpa->description = $request->description_charge_gasoline;
                    }
                    $ChargeGasolineJpa->price_all = $request->price_gasoline;
                    $ChargeGasolineJpa->igv = $request->price_igv;
                    $ChargeGasolineJpa->price_engraved = $request->price_engraved;

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
                            $ChargeGasolineJpa->image_type = $request->image_type;
                            $ChargeGasolineJpa->image_mini = base64_decode($request->image_mini);
                            $ChargeGasolineJpa->image_full = base64_decode($request->image_full);
                        } else {
                            $ChargeGasolineJpa->image_type = null;
                            $ChargeGasolineJpa->image_mini = null;
                            $TravelExpensesJpa->image_full = null;
                        }
                    }
                } else {
                    throw new Exception("Carga de gasolina no encontrada");
                }

                $ChargeGasolineJpa->update_date = gTrace::getDate('mysql');
                $ChargeGasolineJpa->_update_user = $userid;
                $ChargeGasolineJpa->save();
            }

            $TravelExpensesJpa->update_date = gTrace::getDate('mysql');
            $TravelExpensesJpa->_update_user = $userid;
            $TravelExpensesJpa->save();


            $res = [
                'id' => $TravelExpensesJpa->id, 
                'mobility_type' => $TravelExpensesJpa->mobility_type,
                'date_expense' => $TravelExpensesJpa->date_expense,
                'description' => $TravelExpensesJpa->description,
                'expense_price_all' => $TravelExpensesJpa->expense_price_all,
                'price_all' => $TravelExpensesJpa->price_all,
                'expenses' => json_decode($TravelExpensesJpa->expenses),
                '_change_gasoline' => $TravelExpensesJpa->_change_gasoline ?? null, 
            ];
    
            if ($request->mobility_type === 'AUTOMÓVIL') {
                $res['car_movility'] = $ChargeGasolineJpa->_car;
                $res['gasoline_type'] = $ChargeGasolineJpa->gasoline_type;
                $res['price_gasoline'] = $ChargeGasolineJpa->price_all;
                $res['price_igv'] = $ChargeGasolineJpa->igv;
                $res['price_engraved'] = $ChargeGasolineJpa->price_engraved;
            }else{
                $res['price_drive'] = $ChargeGasolineJpa->price_drive;
            }
    

            $response->setStatus(200);
            $response->setMessage('Gasto de viaje actualizado correctamente');
            $response->setData($res);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'LN: ' . $th->getLine());
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

            $reviewJpa = TravelExpenses::select([
                "travel_expenses.image_$size as image_content",
                'travel_expenses.image_type',
            ])
                ->where('id', $id)
                ->first();

            if (!$reviewJpa) {
                throw new Exception('No se encontraron datos');
            }

            if (!$reviewJpa->image_content) {
                throw new Exception('No existe imagen');
            }

            $content = $reviewJpa->image_content;
            $type = $reviewJpa->image_type;
            $response->setStatus(200);
        } catch (\Throwable $th) {
            $ruta = '../storage/images/factura-default.png';
            $fp = fopen($ruta, 'r');
            $datos_image = fread($fp, filesize($ruta));
            $datos_image = addslashes($datos_image);
            fclose($fp);
            $content = stripslashes($datos_image);
            $type = 'image/jpeg';
            $response->setStatus(200);
        } finally {
            return response(
                $content,
                $response->getStatus()
            )->header('Content-Type', $type);
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
            if (!gValidate::check($role->permissions, $branch, 'technicals', 'read')) {
                throw new Exception("No tienes permisos para realizar esta acción");
            }
            $TravelExpensesJpa = TravelExpenses::find($request->id);
            if (!$TravelExpensesJpa) {
                throw new Exception('No se encontró el viatico');
            }
            $TravelExpensesJpa->status = null;
            $TravelExpensesJpa->update_date = gTrace::getDate('mysql');
            $TravelExpensesJpa->_update_user = $userid;
            $TravelExpensesJpa->save();
            $response->setStatus(200);
            $response->setMessage('Viatico eliminado correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'LN: ' . $th->getLine());
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
            if (!gValidate::check($role->permissions, $branch, 'technicals', 'read')) {
                throw new Exception("No tienes permisos para realizar esta acción");
            }
            $TravelExpensesJpa = TravelExpenses::find($request->id);
            if (!$TravelExpensesJpa) {
                throw new Exception('No se encontró el viatico');
            }
            $TravelExpensesJpa->status = 1;
            $TravelExpensesJpa->update_date = gTrace::getDate('mysql');
            $TravelExpensesJpa->_update_user = $userid;
            $TravelExpensesJpa->save();
            $response->setStatus(200);
            $response->setMessage('Viatico restaurado correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'LN: ' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function GenerareReportByExpense(Request $request){
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'technicals', 'read')) {
                throw new Exception('No tienes permisos');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportExpense.html');

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $TravelExpenses = TravelExpenses::select([
                'id',
                '_change_gasoline',
                '_car',
                '_technical',
                'mobility_type',
                'date_expense',
                'expenses',
                'expense_price_all',
                'price_drive',
                'description',
                'price_all',
                '_creation_user',
                'creation_date',
                '_update_user',
                'update_date',
                'status',
            ])->find($request->id);

            $TravelExpenses->expenses = gJSON::parse($TravelExpenses->expenses);

            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();
       
            $summary = '';
            $item_transport = '';

            if($TravelExpenses['mobility_type'] == "MOVILIDAD"){
                $ChargeGasolineJpa = ChargeGasoline::select([
                    'id',
                    'gasoline_type',
                    'price_all',
                    'igv',
                    'price_engraved'
                    ])->where('id', $TravelExpenses['_change_gasoline'])->first();

                $item_transport .= "
                <tr>
                    <td><center >1</center></td>
                    <td><center >{$TravelExpenses['mobility_type']} - {$ChargeGasolineJpa->gasoline_type}</center></td>
                    <td><center >S/{$ChargeGasolineJpa->price_all}</center></td>
                    <td><center >1</center></td>
                    <td><center >S/{$ChargeGasolineJpa->price_all}</center></td>
                </tr>
                ";
            }else{
                $item_transport .= "
                <tr>
                    <td><center >1</center></td>
                    <td><center >TRANSPORTE - {$TravelExpenses['mobility_type']}</center></td>
                    <td><center >S/{$TravelExpenses['price_drive']}</center></td>
                    <td><center >1</center></td>
                    <td><center >S/{$TravelExpenses['price_drive']}</center></td>
                </tr>
                ";
            }

            $counter = 1;
            foreach ($TravelExpenses->expenses as $expenses) {

                $price_unity = isset($expenses['price_unity']) ? $expenses['price_unity'] : $expenses['price'];
                $mount = isset($expenses['mount']) ? $expenses['mount'] : 1;
                $price_total = isset($expenses['price_total']) ? $expenses['price_total'] : $expenses['price'];
                
                $summary .= "
                        <tr>
                            <td><center >{$counter}</center></td>
                            <td><center >".strtoupper($expenses['description'])."</center></td>
                            <td><center >S/{$price_unity}</center></td>
                            <td><center >{$mount}</center></td>
                            <td><center >S/{$price_total}</center></td>
                        </tr>
                    ";
                $counter++;
            }

            // // $PhotographsByReviewTechnicalJpa = PhotographsByReviewTechnical::select(['id', 'description', '_creation_user', 'creation_date', '_update_user', 'update_date'])
            // ->where('_review',$request->id)->whereNotNUll('status')
            // ->orderBy('id', 'desc')
            // ->get();

            // $images= '';
            // $count = 1;

            // foreach($PhotographsByReviewTechnicalJpa as $image){

            //     $userCreation = User::select([
            //         'users.id as id',
            //         'users.username as username',
            //     ])
            //         ->where('users.id', $image->_creation_user)->first();

            //     $images .= "
            //     <div style='page-break-before: always;'>
            //         <p><strong>{$count}) {$image->description}</strong></p>
            //         <p style='margin-left:18px'>Fecha: {$image->creation_date}</p>
            //         <p style='margin-left:18px'>Usuario: {$userCreation->username}</p>
            //         <center>
            //             <img src='http://almacen.fastnetperu.com.pe/api/review_technicalimg/{$image->id}/full' alt='-' 
            //            class='evidences'
            //         </center>
            //     </div>
            //     ";
            //     $count +=1;
            // }


            $template = str_replace(
                [
                    '{id}',
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{description}',
                    '{technical}',
                    '{date}',
                    '{movility}',
                    '{summary}',
                    '{total}'
                ],
                [
                    $request->id,
                    $branch_->name,
                    gTrace::getDate('long'),
                    strtoupper($TravelExpenses['description']),
                    strtoupper($request->technical['name'].' '.$request->technical['lastname']),
                    $request->date_expense,
                    $item_transport,
                    $summary,
                    $TravelExpenses['price_all']
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


}
