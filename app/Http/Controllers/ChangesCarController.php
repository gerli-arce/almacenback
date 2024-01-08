<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\ChangesCar;
use App\Models\CheckListCar;
use App\Models\Response;
use App\Models\ViewChangesCar;
use App\Models\ViewCheckByReview;
use App\Models\ViewReviewCar;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ChangesCarController extends Controller
{
    public function store(Request $request){
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'changes_car', 'create')) {
                throw new Exception("No tienes permisos para realizar esta acción");
            }

            $changeCarJpa = new ChangesCar();
            $changeCarJpa->change = $request->change;
            $changeCarJpa->_person = $request->_person;
            $changeCarJpa->_car = $request->_car;
            $changeCarJpa->date = $request->date;
            $changeCarJpa->description = $request->description;
            $changeCarJpa->_creation_user = $userid;
            $changeCarJpa->creation_date = gTrace::getDate('mysql');
            $changeCarJpa->status = "1";
            $changeCarJpa->save();
           
            $response->setStatus(200);
            $response->setMessage('El checklist se ha creado correctamente');
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

    public function paginateChangesOil(Request $request){
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'checklist', 'read')) {
                throw new Exception('No tienes permisos para listar las marcas  de ' . $branch);
            }

            $query = ViewChangesCar::select('*')
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
                if ($column == 'person__name' || $column == '*') {
                    $q->orWhere('person__name', $type, $value);
                }
                if ($column == 'person__lastname' || $column == '*') {
                    $q->orWhere('person__lastname', $type, $value);
                }
                if ($column == 'date' || $column == '*') {
                    $q->orWhere('date', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            })->where('_car', $request->car)
            ->where('change', 'OIL');

            $iTotalDisplayRecords = $query->count();
            $reviewCarJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $chenges = array();
            foreach ($reviewCarJpa as $reviewJpa) {
                $review = gJSON::restore($reviewJpa->toArray(), '__');
                $chenges[] = $review;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewChangesCar::where('_car', $request->car)
            ->where('change', 'OIL')->count());
            $response->setData($chenges);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage().'LN: '.$th->getLine().'FL: '.$th->getFile());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }
}
