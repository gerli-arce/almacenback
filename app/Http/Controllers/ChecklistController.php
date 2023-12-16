<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\Cars;
use App\Models\Response;
use App\Models\ViewCars;
use App\Models\CheckByReview;
use App\Models\CheckListCar;
use App\Models\ReviewCar;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChecklistController extends Controller
{
    public function store(Request $request){
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
            $ReviewCarJpa->_creation_user = $userid;
            $ReviewCarJpa->creation_date = gTrace::getDate('mysql');
            $ReviewCarJpa->_creation_user = $userid;
            $ReviewCarJpa->update_date = gTrace::getDate('mysql');
            $ReviewCarJpa->_update_user = $userid;
            $ReviewCarJpa->status = "1";
            $ReviewCarJpa->save();

            foreach($request->data as $component){
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
}
