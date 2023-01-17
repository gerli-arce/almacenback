<?php

namespace App\Http\Controllers;

use App\gLibraries\gFetch;
use App\gLibraries\gStatus;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\Response;
use Exception;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function store(Request $request){
        $response = new Response();
        try {
    
          if (
            !isset($request->name) ||
            !isset($request->correlative) ||
            !isset($request->department) ||
            !isset($request->province) ||
            !isset($request->distric) ||
            !isset($request->address)
          ) {
            throw new Exception("Error: No deje campos vacíos");
          }

          $branchValidation = Branch::select(['name', 'correlative'])
          ->where('name', $request->name)
          ->orWhere('correlative', $request->correlative)
          ->first();
    
          if ($branchValidation) {
            if($branchValidation->name == $request->name){
                throw new Exception("El nombre de la sucursal ya existe");
            }
            if($branchValidation->correlative == $request->correlative){
                throw new Exception("El correlativo de la sucursal ya existe");
            }
          }
    
          $branchJpa = new Branch();
          $branchJpa->name = $request->name;
          $branchJpa->correlative = $request->correlative;
          $branchJpa->department = $request->department;
          $branchJpa->province = $request->province;
          $branchJpa->distric = $request->distric;
          $branchJpa->address = $request->address;
     
          $branchJpa->status ="1";

          $branchJpa->save();
    
          $response->setStatus(200);
          $response->setMessage('Sucursal agregada correctamente');
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
