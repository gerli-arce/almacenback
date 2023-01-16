<?php

namespace App\Http\Controllers;

use App\Generated\Address;
use App\Generated\Business;
use App\Generated\Contact;
use App\Generated\Document;
use App\Generated\Person;
use App\gLibraries\gFetch;
use App\gLibraries\gStatus;
use App\gLibraries\gValidate;
use App\Models\People;
use App\Models\Response;
use Exception;
use Illuminate\Http\Request;

class PeopleController extends Controller
{
    public function store(Request $request){
        $response = new Response();
        $personJpa = new Person();
        try {
    
          if (
            !isset($request->doc_type) ||
            !isset($request->doc_number) ||
            !isset($request->name) ||
            !isset($request->lastname)
          ) {
            throw new Exception("Error: No deje campos vacíos");
          }

          if($request->doc_type == "RUC" && $request->doc_type == "RUC10" ){
            if( strlen($request->doc_number) != 11){
                throw new Exception("Para el tipo de documento RUC es nesesario que tenga 11 números.");
            } 
          }

          if($request->doc_type == "DNI"){
            if( strlen($request->doc_number) != 8){
                throw new Exception("Para el tipo de documento DNI es nesesario que tenga 8 números.");
            } 
          }

          $userValidation = User::select(['doc_type', 'doc_number'])
          ->where('doc_type', $request->doc_type)
          ->where('doc_number', $request->doc_number)
          ->first();
    
          if ($userValidation) {
            throw new Exception("Este usuario ya existe");
          }
    
          $peopleJpa = new People();
          if($request->doc_type == "RUC" && $request->doc_type == "RUC10"){
            $peopleJpa->doc_type = $request->doc_type;
            $peopleJpa->doc_number = $request->doc_number;
            $peopleJpa->name = $request->name;
            $peopleJpa->actividad_economica ->actibidad;
          }

  
          // if (
          //   isset($request->phone_prefix) &&
          //   isset($request->phone_number)
          // ) {
          //   $userJpa->phone_prefix = $request->phone_prefix;
          //   $userJpa->phone_number = $request->phone_number;
          // }
          // if (isset($request->email)) {
          //   $userJpa->email = $request->email;
          // }
    
          $userJpa->save();
    
          $response->setStatus(200);
          $response->setMessage('Usuario agregado correctamente');
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
