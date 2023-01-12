<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\Response;
use App\Generated\GPerson;
use App\gLibraries\guid;
use App\gLibraries\gjson;
use App\gLibraries\gvalidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Http\Controllers\Controller;

class PersonController extends Controller
{
    public function store(Request $request){
        $response = new Response();
        try {
    
          if (
            !isset($request->doc_type) ||
            !isset($request->doc_number) ||
            !isset($request->name) ||
            !isset($request->lastname) 
          ) {
            throw new Exception("Error: No deje campos vacíos");
          }
    
          $personValidation = Person::select([
            'doc_type',
            'doc_number'
            ])
            ->where('doc_type', $request->doc_type)
            ->where('doc_number', $request->doc_number)
            ->first();
    
          if ($personValidation) {
            throw new Exception("Este usuario ya existe");
          }
    
          $PersonJpa = new Person();
          $PersonJpa->doc_type = $request->doc_type;
          $PersonJpa->doc_number = $request->doc_number;
          $PersonJpa->name = $request->name;
          $PersonJpa->lastname = $request->lastname;

          if($request->address){
            $PersonJpa->address = $request->address;
          }
          
          if($request->gender){
            $PersonJpa->gender = $request->gender;
          }

          if($request->email){
            $PersonJpa->email = $request->email;
          }

          if($request->phone){
            $PersonJpa->phone = $request->phone;
          }

          $PersonJpa->status = "1";
    
          $PersonJpa->save();
    
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

    public function searchByTypeDocByNroDoc(Request $request, $doc_type, $doc_number){
      $response = new Response();
      try {
  
        if (
          !isset($doc_type) ||
          !isset($doc_number) 
        ) {
          throw new Exception("Error: No deje campos vacíos");
        }
  
        $personJpa = Person::where('doc_type','=',$doc_type)
        ->where('doc_number','=', $doc_number)->first();

        $res = new GPerson();
        $res->setId($personJpa->id);
        $res->setDocType($personJpa->doc_type);
        $res->setDocNumber($personJpa->doc_number);
        $res->setName($personJpa->name);
        $res->setLastName($personJpa->lastname);
        
        $response->setData($personJpa->toArray());
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
