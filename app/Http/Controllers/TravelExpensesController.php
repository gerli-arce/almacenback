<?php

namespace App\Http\Controllers;

use App\gLibraries\gTrace;
use App\gLibraries\gJson;
use App\gLibraries\gValidate;
use App\Models\ChargeGasoline;
use App\Models\Response;
use App\Models\TravelExpenses;
use App\Models\ViewTravelExpenses;
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

            if (!gValidate::check($role->permissions, $branch, 'travel_expenses', 'create')) {
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

    public function paginate(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'travel_expenses', 'read')) {
                throw new Exception('No tienes permisos');
            }

            $query = ViewTravelExpenses::select('*')
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
                if ($column == 'mobility_type' || $column == '*') {
                    $q->orWhere('mobility_type', $type, $value);
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
            })->where('_technical', $request->_technical);

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


}
