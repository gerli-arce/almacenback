<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Response;
use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\ViewUsers;
use App\Models\ViewCars;
use App\Models\Branch;
use App\Models\ChargeGasoline;
use App\Models\ViewChargeGasolineByCar;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Support\Facades\DB;

class ChargeGasolineController extends Controller
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
                throw new Exception("No tienes permisos para realizar esta acción");
            }

            if (
                !isset($request->_technical) ||
                !isset($request->_car) ||
                !isset($request->price_all) ||
                !isset($request->gasoline_type) ||
                !isset($request->date)
            ) {
                throw new Exception("Error en los datos de entrada");
            }

            $ChargeGasolineJpa = new ChargeGasoline();
            $ChargeGasolineJpa->_technical = $request->_technical;
            $ChargeGasolineJpa->_car = $request->_car;
            $ChargeGasolineJpa->date = $request->date;
            $ChargeGasolineJpa->gasoline_type = $request->gasoline_type;
            if (isset($request->description)) {
                $ChargeGasolineJpa->description = $request->description;
            }
            $ChargeGasolineJpa->price_all = $request->price_all;

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

            $response->setStatus(200);
            $response->setMessage('Carga de gasolina creada correctamente');
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

    public function update(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'cars', 'update')) {
                throw new Exception("No tienes permisos para realizar esta acción");
            }

            $ChargeGasolineJpa = ChargeGasoline::find($request->id);
            if (!$ChargeGasolineJpa) {
                throw new Exception('No se encontró la revisión técnica');
            }

            if (isset($request->_technical)) {
                $ChargeGasolineJpa->_technical = $request->_technical;
            }

            if (isset($request->date)) {
                $ChargeGasolineJpa->date = $request->date;
            }

            if (isset($request->price_all)) {
                $ChargeGasolineJpa->price_all = $request->price_all;
            }

            if (isset($request->gasoline_type)) {
                $ChargeGasolineJpa->gasoline_type = $request->gasoline_type;
            }

            $ChargeGasolineJpa->description = $request->description;

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

            $ChargeGasolineJpa->update_date = gTrace::getDate('mysql');
            $ChargeGasolineJpa->_update_user = $userid;
            $ChargeGasolineJpa->save();

            $response->setStatus(200);
            $response->setMessage('Revisión técnica actualizada correctamente');
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

            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception('No tienes permisos para listar las revisiones técnicas');
            }

            $query = ViewChargeGasolineByCar::select('*')
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'id' || $column == '*') {
                    $q->orWhere('id', $type, $value);
                }
                if ($column == 'date' || $column == '*') {
                    $q->orWhere('date', $type, $value);
                }
                if ($column == 'technical__name' || $column == '*') {
                    $q->orWhere('technical__name', $type, $value);
                }
                if ($column == 'technical__lastname' || $column == '*') {
                    $q->orWhere('technical__lastname', $type, $value);
                }
                if ($column == 'date' || $column == '*') {
                    $q->orWhere('date', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            })->where('_car', $request->_car);

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
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewChargeGasolineByCar::where('_car', $request->_car)->count());
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

            $reviewJpa = ChargeGasoline::select([
                "charge_gasoline.image_$size as image_content",
                'charge_gasoline.image_type',
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

    public function delete(Request $request, $id)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception("No tienes permisos para realizar esta acción");
            }
            $ChargeGasolineJpa = ChargeGasoline::find($id);
            if (!$ChargeGasolineJpa) {
                throw new Exception('No se encontró la carga de gasolina');
            }
            $ChargeGasolineJpa->status = null;
            $ChargeGasolineJpa->update_date = gTrace::getDate('mysql');
            $ChargeGasolineJpa->_update_user = $userid;
            $ChargeGasolineJpa->save();
            $response->setStatus(200);
            $response->setMessage('Carga de gasolina eliminada correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception("No tienes permisos para realizar esta acción");
            }

            $ChargeGasolineJpa = ChargeGasoline::find($request->id);
            if (!$ChargeGasolineJpa) {
                throw new Exception('No se encontró la carga de gasolina');
            }

            $ChargeGasolineJpa->status = 1;
            $ChargeGasolineJpa->update_date = gTrace::getDate('mysql');
            $ChargeGasolineJpa->_update_user = $userid;
            $ChargeGasolineJpa->save();

            $response->setStatus(200);
            $response->setMessage('Carga de gasolina restaurada correctamente');
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

    public function generateReportByCar(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception('No tienes permisos');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportChargeGasoline.html');

            $ViewChargeGasolineByCarJpa = ViewChargeGasolineByCar::find($request->id);

            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();

            $summary = '';

            $template = str_replace(
                [
                    '{id}',
                    '{placa}',
                    '{technical}',
                    '{date}',
                    '{gasoline}',
                    '{price_all}',
                    '{description}',
                    '{summary}',
                ],
                [
                    $ViewChargeGasolineByCarJpa->id,
                    $request->car['placa'],
                    $ViewChargeGasolineByCarJpa->technical__name . ' ' . $ViewChargeGasolineByCarJpa->technical__lastname,
                    $ViewChargeGasolineByCarJpa->date,
                    $ViewChargeGasolineByCarJpa->gasoline_type,
                    $ViewChargeGasolineByCarJpa->price_all,
                    $ViewChargeGasolineByCarJpa->description,
                    $summary,
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

    public function generateReportdetailsByCar(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception('No tienes permisos');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportChargeGasolineDetails.html');



            $query = ViewChargeGasolineByCar::where('_car', $request->car['id'])
                ->orderBy('date', 'desc');


            if (isset($request->date_start) && isset($request->date_end)) {
                $dateStart = date('Y-m-d', strtotime($request->date_start));
                $dateEnd = date('Y-m-d', strtotime($request->date_end));
                $query->where('date', '>=', $dateStart)->where('date', '<=', $dateEnd);
            }

            $ViewChargeGasolineByCarJpa = $query->get();

            $summary = '';


            $changesGasolineJpa = array();
            $price_all = 0;
            $num_charges = 0;
            foreach ($ViewChargeGasolineByCarJpa as $ChargegasolineJpa) {
                $chargeGasoline = gJSON::restore($ChargegasolineJpa->toArray(), '__');
                $price_all += $chargeGasoline['price_all'];
                $num_charges++;
                if ($request->add_bills) {
                    $summary .= "
                        <div style='page-break-before: always;'>
                            <table>
                                <thead>
                                    <tr>
                                        <td colspan='2'>
                                            CARGA DE {$chargeGasoline['gasoline_type']}
                                        </td>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class='n'>TÉCNICO</td>
                                        <td>{$chargeGasoline['technical']['name']} {$chargeGasoline['technical']['lastname']}</td>
                                    </tr>
                                    <tr>
                                        <td class='n'>FECHA</td>
                                        <td>{$chargeGasoline['date']}</td>
                                    </tr>
                                    <tr>
                                        <td class='n'>TIPO DE COMBUSTIBLE</td>
                                        <td>{$chargeGasoline['gasoline_type']}</td>
                                    </tr>
                                    <tr>
                                        <td class='n'>MONTO TOTAL</td>
                                        <td>S/{$chargeGasoline['price_all']}</td>
                                    </tr>
                                    <tr>
                                        <td class='n'>EJECUTIVO</td>
                                        <td>{$chargeGasoline['person_creation']['name']} {$chargeGasoline['person_creation']['lastname']}</td>
                                    </tr>
                                    <tr>
                                        <td class='n'>DESCRIPCIÓN</td>
                                        <td>{$chargeGasoline['description']}</td>
                                    </tr>
                                    <tr>
                                        <td class='n' colspan='2'>
                                            <center>FACTURA</center>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan='2'>
                                            <center>
                                                <img src='https://almacen.fastnetperu.com.pe/api/charge_gasolineimg/{$chargeGasoline['id']}/full' class='img_bill'>
                                            </center>
                                        </td>
                                    </tr>
                                </tbody>
                               
                            </table>
                        <div>
                    ";
                }

                $changesGasolineJpa[] = $chargeGasoline;
            }

            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();

            $template = str_replace(
                [
                    '{placa}',
                    '{num_charges}',
                    '{date_start}',
                    '{date_end}',
                    '{price_all}',
                    '{summary}',
                ],
                [
                    $request->car['placa'],
                    $num_charges,
                    $request->date_start,
                    $request->date_end,
                    $price_all,
                    $summary,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('REPORTE DE CARGAS DE COMBUSTIBLE.pdf');
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

    public function generateReportGeneral(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception('No tienes permisos');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportChargeGasolineGeneral.html');

            $HOST = 'https://almacen.fastnetperu.com.pe/api';

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $ViewCarsJpa = ViewCars::where('branch__id', $branch_->id)->orderBy('id', 'desc')->whereNotNull('status')->get();


            $viewcarsJpa = array();

            $price_all = 0;
            $mount_cars = 0;
            $charges_all = 0;

            $cars = '';
            foreach ($ViewCarsJpa as $ViewCarJpa) {
                $viewCar = gJSON::restore($ViewCarJpa->toArray(), '__');
                $query = ViewChargeGasolineByCar::where('_car', $viewCar['id'])
                    ->orderBy('date', 'desc');

                if (isset($request->date_start) && isset($request->date_end)) {
                    $dateStart = date('Y-m-d', strtotime($request->date_start));
                    $dateEnd = date('Y-m-d', strtotime($request->date_end));
                    $query->where('date', '>=', $dateStart)->where('date', '<=', $dateEnd);
                }

                $ViewChargeGasolineByCarJpa = $query->get();
                $mount_cars++;
                $chanrgesGasoline = array();

                $mount_charges_by_car = 0;
                $price_all_by_car = 0;
                $charges_gasoline = '';
                foreach ($ViewChargeGasolineByCarJpa as $ChangeGasolineJpa) {
                    $chargeGasoline = gJSON::restore($ChangeGasolineJpa->toArray(), '__');
                    $price_all += $chargeGasoline['price_all'];
                    $charges_all++;
                    $mount_charges_by_car++;
                    $price_all_by_car += $chargeGasoline['price_all'];
                    $chanrgesGasoline[] = $chargeGasoline;

                        $charges_gasoline .= "
                            <tr>
                                <td colspan='2'><center>----------------------------------------</center></td>
                            </tr>
                            <tr>
                                <td colspan='2' class='s'><center>{$chargeGasoline['gasoline_type']}</center></td>
                            </tr>
                            <tr>
                                <td class='sn'>TÉCNICO</td>
                                <td>{$chargeGasoline['technical']['name']} {$chargeGasoline['technical']['lastname']}</td>
                            </tr>
                            <tr>
                                <td class='sn'>FECHA</td>
                                <td>{$chargeGasoline['date']}</td>
                            </tr>
                            <tr>
                                <td class='sn'>MONTO TOTAL</td>
                                <td>S/{$chargeGasoline['price_all']}</td>
                            </tr>
                            <tr>
                                <td class='sn'>EJECUTIVO</td>
                                <td>{$chargeGasoline['person_creation']['name']} {$chargeGasoline['person_creation']['lastname']}</td>
                            </tr>
                            <tr>
                                <td class='sn'>DESCRIPCIÓN</td>
                                <td>{$chargeGasoline['description']}</td>
                            </tr>
                            <tr>
                                <td class='sn' colspan='2'>
                                    <center>FACTURA</center>
                                </td>
                            </tr>
                            <tr>
                                <td class='sn' colspan='2'>
                                    <center>
                                        <img src='{$HOST}/charge_gasolineimg/{$chargeGasoline['id']}/full' class='img_bill'>
                                    </center>
                                </td>
                            </tr>
                    ";
                }

                $viewCar['charges'] = $chanrgesGasoline;

                $viewcarsJpa[] = $viewCar;

                if ($request->add_bills) {

                    $cars .= "
                <table>
                    <thead>
                        <tr>
                            <td colspan='2'>
                                <center>{$viewCar['placa']} - {$viewCar['color']}</center>
                            </td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class='n'>CARGAS TOTALES</td>
                            <td><center>{$mount_charges_by_car}</center></td>
                        </tr>
                        <tr>
                            <td class='n'>MONTO TOTAL</td>
                            <td><center>S/{$price_all_by_car}</center></td>
                        </tr>
                        <tr>
                            <td colspan='2' class='n'><center>{$viewCar['model']['model']}</center></td>
                        </tr>
                        <tr>
                            <td colspan='2'>
                                <center>
                                    <img src='{$HOST}/carimg/{$viewCar['id']}/full' class='img_bill'>
                                </center>                           
                            </td>
                        </tr>
                        {$charges_gasoline}
                    </tbody>
                </table>
                ";
                }else{
                    $cars='
                    <center><h3><i>NO SE MUESTRAN LOS DETALLES</i></h3></center>
                    ';
                }
            }

            $template = str_replace(
                [
                    '{num_cars}',
                    '{num_charges}',
                    '{date_start}',
                    '{date_end}',
                    '{price_all}',
                    '{cars}',
                ],
                [
                    $mount_cars,
                    $charges_all,
                    $request->date_start,
                    $request->date_end,
                    $price_all,
                    $cars,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('REPORTE DE CARGAS DE COMBUSTIBLE.pdf');

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
