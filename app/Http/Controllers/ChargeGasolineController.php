<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Response;
use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
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
                !isset($request->date)
            ) {
                throw new Exception("Error en los datos de entrada");
            }

            $ChargeGasolineJpa = new ChargeGasoline();
            $ChargeGasolineJpa->_technical = $request->_technical;
            $ChargeGasolineJpa->_car = $request->_car;
            $ChargeGasolineJpa->date = $request->date;
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

            $ChargeGasolineJpa->_technical = $request->_technical;
            $ChargeGasolineJpa->date = $request->date;
            $ChargeGasolineJpa->description = $request->description;
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
                $charges_gasoline[] = $review
                ;
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



}
