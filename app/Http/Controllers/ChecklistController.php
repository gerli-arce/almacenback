<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\CheckByReview;
use App\Models\CheckListCar;
use App\Models\Response;
use App\Models\ReviewCar;
use App\Models\ViewCheckByReview;
use App\Models\ViewReviewCar;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChecklistController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'checklist', 'create')) {
                throw new Exception("No tienes permisos para agregar checklist ");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $ReviewCarJpa = new ReviewCar();
            $ReviewCarJpa->_responsible_check = $userid;
            $ReviewCarJpa->date = $request->date;
            $ReviewCarJpa->_car = $request->_car;
            $ReviewCarJpa->_driver = $request->_driver;
            $ReviewCarJpa->description = $request->description;
            $ReviewCarJpa->_creation_user = $userid;
            $ReviewCarJpa->creation_date = gTrace::getDate('mysql');
            $ReviewCarJpa->update_date = gTrace::getDate('mysql');
            $ReviewCarJpa->_update_user = $userid;
            $ReviewCarJpa->status = "1";
            $ReviewCarJpa->save();

            foreach ($request->data as $component) {
                $CheckListCarJpa = new CheckListCar();
                $CheckListCarJpa->_component = $component['id'];
                $CheckListCarJpa->present = $component['dat']['present'];
                $CheckListCarJpa->optimed = $component['dat']['optimed'];
                $CheckListCarJpa->description = $component['dat']['description'];
                $CheckListCarJpa->status = 1;
                $CheckListCarJpa->save();

                $CheckByReviewJpa = new CheckByReview();
                $CheckByReviewJpa->_check = $CheckListCarJpa->id;
                $CheckByReviewJpa->_review = $ReviewCarJpa->id;
                $CheckByReviewJpa->status = 1;
                $CheckByReviewJpa->save();
            }

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

    public function paginate(Request $request)
    {

        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'brands', 'read')) {
                throw new Exception('No tienes permisos para listar las marcas  de ' . $branch);
            }

            $query = ViewReviewCar::select('*')
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
                if ($column == 'driver__name' || $column == '*') {
                    $q->orWhere('driver__name', $type, $value);
                }
                if ($column == 'driver__lastname' || $column == '*') {
                    $q->orWhere('driver__lastname', $type, $value);
                }
                if ($column == 'res_check__name' || $column == '*') {
                    $q->orWhere('res_check__name', $type, $value);
                }
                if ($column == 'res_check__lastname' || $column == '*') {
                    $q->orWhere('res_check__lastname', $type, $value);
                }
                if ($column == 'car__placa' || $column == '*') {
                    $q->orWhere('car__placa', $type, $value);
                }
                if ($column == 'car__placa' || $column == '*') {
                    $q->orWhere('car__placa', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $reviewCarJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $reviews = array();
            foreach ($reviewCarJpa as $reviewJpa) {
                $review = gJSON::restore($reviewJpa->toArray(), '__');
                $reviews[] = $review;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewReviewCar::count());
            $response->setData($reviews);
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

    public function getReviewCarById(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'checklist', 'read')) {
                throw new Exception('No tienes permisos para listar los checklist  de ' . $branch);
            }

            $checksJpa = ViewCheckByReview::select('*')
                ->whereNotNull('status')
                ->where('_review', $request->id)
                ->get();

            $checks = array();
            foreach ($checksJpa as $checkJpa) {
                $check = gJSON::restore($checkJpa->toArray(), '__');
                $checks[] = $check;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($checks);
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
            if (!gValidate::check($role->permissions, $branch, 'checklist', 'update')) {
                throw new Exception("No tienes permisos para actualizar checklist ");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $ReviewCarJpa = ReviewCar::find($request->id);
            $ReviewCarJpa->date = $request->date;
            $ReviewCarJpa->_car = $request->_car;
            $ReviewCarJpa->_driver = $request->_driver;
            $ReviewCarJpa->description = $request->description;
            $ReviewCarJpa->_update_user = $userid;
            $ReviewCarJpa->update_date = gTrace::getDate('mysql');
            $ReviewCarJpa->save();

            foreach ($request->data as $component) {
                $CheckListCarJpa = CheckListCar::find($component['dat']['id']);
                if(!$CheckListCarJpa)
                {
                    $CheckListCarJpaNew = new CheckListCar();
                    $CheckListCarJpaNew->_component = $component['id'];
                    $CheckListCarJpaNew->present = $component['dat']['present'];
                    $CheckListCarJpaNew->optimed = $component['dat']['optimed'];
                    $CheckListCarJpaNew->description = $component['dat']['description'];
                    $CheckListCarJpaNew->status = 1;
                    $CheckListCarJpaNew->save();

                    $CheckByReviewJpa = new CheckByReview();
                    $CheckByReviewJpa->_check = $CheckListCarJpaNew->id;
                    $CheckByReviewJpa->_review = $ReviewCarJpa->id;
                    $CheckByReviewJpa->status = 1;
                    $CheckByReviewJpa->save();
                }else{
                    $CheckListCarJpa->_component = $component['id'];
                    $CheckListCarJpa->present = $component['dat']['present'];
                    $CheckListCarJpa->optimed = $component['dat']['optimed'];
                    $CheckListCarJpa->description = $component['dat']['description'];
                    $CheckListCarJpa->status = 1;
                    $CheckListCarJpa->save();
                }
            }

            $response->setStatus(200);
            $response->setMessage('El checklist se ha actualizado correctamente');
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
