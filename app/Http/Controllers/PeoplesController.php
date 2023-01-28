<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\gLibraries\gStatus;
use App\Models\Response;
use App\gLibraries\gtrace;
use App\Models\Peoples;
use Exception;

class PeoplesController extends Controller
{
   
  

    public function store(Request $request)
    {
        $response = new Response();
        try {

            $peopleJpa = new Peoples();

            if(isset($request->numId)){
                $peopleJpa->numId = $request->numId;
            }

            if(isset($request->doc_type)){
                $peopleJpa->doc_type = $request->doc_type;
            }

            if(isset($request->doc_number)){
                $peopleJpa->doc_number = $request->doc_number;
            }

            if(isset($request->lastname)){
                $peopleJpa->lastname = $request->lastname;
            }

            if(isset($request->mother_lastname)){
                $peopleJpa->mother_lastname = $request->mother_lastname;
            }

            if(isset($request->name)){
                $peopleJpa->name = $request->name;
            }

            if(isset($request->birthdate)){
                $peopleJpa->birthdate = $request->birthdate;
            }

            if(isset($request->gender)){
                $peopleJpa->gender = $request->gender;
            }

            if(isset($request->email)){
                $peopleJpa->email = $request->email;
            }

            if(isset($request->landline)){
                $peopleJpa->landline = $request->landline;
            }

            if(isset($request->phone)){
                $peopleJpa->phone = $request->phone;
            }

            if(isset($request->ubigeo)){
                $peopleJpa->ubigeo = $request->ubigeo;
            }

            if(isset($request->code_country)){
                $peopleJpa->code_country = $request->code_country;
            }

            if(isset($request->country)){
                $peopleJpa->country = $request->country;
            }

            if(isset($request->code_department)){
                $peopleJpa->code_department = $request->code_department;
            }

            if(isset($request->department)){
                $peopleJpa->department = $request->department;
            }

            if(isset($request->code_province)){
                $peopleJpa->code_province = $request->code_province;
            }

            if(isset($request->province)){
                $peopleJpa->province = $request->province;
            }

            if(isset($request->code_distric)){
                $peopleJpa->code_distric = $request->code_distric;
            }

            if(isset($request->distric)){
                $peopleJpa->distric = $request->distric;
            }

            if(isset($request->type)){
                $peopleJpa->type = $request->type;
            }

            if(isset($request->road_type)){
                $peopleJpa->road_type = $request->road_type;
            }

            if(isset($request->road_number)){
                $peopleJpa->road_number = $request->road_number;
            }

            if(isset($request->road_name)){
                $peopleJpa->road_name = $request->road_name;
            }

            if(isset($request->address)){
                $peopleJpa->address = $request->address;
            }
            
            if(isset($request->equifax)){
                $peopleJpa->equifax = $request->equifax;
            }

            if(isset($request->emailFacElect)){
                $peopleJpa->emailFacElect = $request->emailFacElect;
            }

            if(isset($request->emailFacEleccc)){
                $peopleJpa->emailFacEleccc = $request->emailFacEleccc;
            }

            if(isset($request->tradename)){
                $peopleJpa->tradename = $request->tradename;
            }

            $peopleJpa->save();

            $response->setStatus(200);
            $response->setMessage('Operacion Correcta. Registro agregado correctamente');
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

            $this->validateHeaders($request);
            $this->validateRequest($request);

            if (!isset($request->id)) {
                throw new Exception('Se debe enviar un id de actividad');
            }

            $peopleValidation = Persona::select([
                'people.doc_type',
                'people.doc_number',
            ])
                ->where('doc_type', $request->doc_type)
                ->where('id', '!=', $request->id)
                ->where('doc_number', $request->doc_number)
                ->where('id', '!=', $request->id)
                ->first();

            if ($peopleValidation) {
                throw new Exception("Este registro ya existe");
            }

            $peopleJpa = Persona::find($request->id);
            $peopleJpa->name = $request->name;
            $peopleJpa->lastname = $request->lastname;
            $peopleJpa->doc_type = $request->doc_type;
            $peopleJpa->doc_number = $request->doc_number;

            if($request->bithdate){
                $peopleJpa->bithdate = $request->bithdate;
            }

            if($request->gender){
                $peopleJpa->gender = $request->gender;
            }

            if($request->email){
                $peopleJpa->email = $request->email;
            }

            if($request->phone_prefix){
                $peopleJpa->phone_prefix = $request->phone_prefix;
            }

            if($request->phone_number){
                $peopleJpa->phone_number = $request->phone_number;
            }

            if($request->ubigeo){
                $peopleJpa->ubigeo = $request->ubigeo;
            }

            if($request->address){
                $peopleJpa->address = $request->address;
            }

            $peopleJpa->date_update = gTrace::getDate('mysql');

            if($request->origin){
                $peopleJpa->origin = $request->origin;
            }

            $peopleJpa->service_update =  $request->header('SoDe-Auth-Service');

            if($request->status){
                $peopleJpa->status = $request->status;
            }

            $peopleJpa->save();

            $response->setStatus(200);
            $response->setMessage('Operación Correcta. Registro actualizado correctamente');
            $response->setData([$peopleJpa]);
        } catch (\Throwable $th) {
            $response->setStatus(gStatus::get($th->getCode()));
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }
}
