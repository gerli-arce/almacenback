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

class ReviewTechnicalController extends Controller
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

}
