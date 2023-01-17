<?php

namespace App\Http\Controllers;


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
        try {
    
          if (
            !isset($request->doc_type) ||
            !isset($request->doc_number) ||
            !isset($request->name) ||
            !isset($request->lastname) ||
            !isset($request->type) ||
            !isset($request->_branch)
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

          $userValidation = People::select(['doc_type', 'doc_number'])
          ->where('doc_type', $request->doc_type)
          ->where('doc_number', $request->doc_number)
          ->first();
    
          if ($userValidation) {
            throw new Exception("Este usuario ya existe");
          }
    
          $peopleJpa = new People();
          $peopleJpa->doc_type = $request->doc_type;
          $peopleJpa->doc_number = $request->doc_number;
          $peopleJpa->name = $request->name;
          $peopleJpa->lastname = $request->lastname;

          if($request->birthdate){
            $peopleJpa->birthdate = $request->birthdate;
          }

          if($request->gender){
            $peopleJpa->gender = $request->gender;
          }

          if($request->email){
            $peopleJpa->email = $request->email;
          }

          if($request->phone){
            $peopleJpa->phone = $request->phone;
          }

          if($request->department){
            $peopleJpa->department = $request->department;
          }

          if($request->province){
            $peopleJpa->province = $request->province;
          }

          if($request->distric){
            $peopleJpa->distric = $request->distric;
          }

          if($request->address){
            $peopleJpa->address = $request->address;
          }

          $peopleJpa->type = $request->type;
          $peopleJpa->_branch = $request->_branch;

          $peopleJpa->status ="1";

          $peopleJpa->save();
    
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

