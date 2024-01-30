<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\ChangesCar;
use App\Models\ReviewTechnicalByCar;
use App\Models\ViewReviewTechnicalByCar;
use App\Models\Response;
use App\Models\ViewChangesCar;
use App\Models\ViewCheckByReview;
use App\Models\ViewReviewCar;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewTechnicalController extends Controller
{
    public function store(Request $request){
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'cars', 'create')) {
                throw new Exception("No tienes permisos para realizar esta acción");
            }

            $reviewTechnicalByCarJpa = new ReviewTechnicalByCar();
            $reviewTechnicalByCarJpa->_car = $request->_car;
            $reviewTechnicalByCarJpa->date = $request->date;
            $reviewTechnicalByCarJpa->components = json_encode($request->components); // Convert components to JSON
            $reviewTechnicalByCarJpa->description = $request->description;
            $reviewTechnicalByCarJpa->_technical = $request->_technical;
            $reviewTechnicalByCarJpa->price_all = $request->price_all;

            
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
                    $reviewTechnicalByCarJpa->image_type = $request->image_type;
                    $reviewTechnicalByCarJpa->image_mini = base64_decode($request->image_mini);
                    $reviewTechnicalByCarJpa->image_full = base64_decode($request->image_full);
                } else {
                    $reviewTechnicalByCarJpa->image_type = null;
                    $reviewTechnicalByCarJpa->image_mini = null;
                    $reviewTechnicalByCarJpa->image_full = null;
                }
            }

            $reviewTechnicalByCarJpa->creation_date = gTrace::getDate('mysql');
            $reviewTechnicalByCarJpa->_creation_user = $userid;
            $reviewTechnicalByCarJpa->update_date = gTrace::getDate('mysql');
            $reviewTechnicalByCarJpa->_update_user = $userid;
            $reviewTechnicalByCarJpa->status = "1";
            $reviewTechnicalByCarJpa->save();
         
            $response->setStatus(200);
            $response->setMessage('Revisión técnica creada correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage().'LN: '.$th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function paginate(Request $request){
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception('No tienes permisos para listar las revisiones técnicas');
            }

            $query = ViewReviewTechnicalByCar::select('*')
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
            $reviewCarJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $reviews_technicals = array();
            foreach ($reviewCarJpa as $reviewJpa) {
                $review = gJSON::restore($reviewJpa->toArray(), '__');
                $reviews_technicals[] = $review
                ;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewChangesCar::where('_car', $request->_car)->count());
            $response->setData($reviews_technicals);
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
