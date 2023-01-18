<?php

namespace App\Http\Controllers;

use App\gLibraries\gFetch;
use App\gLibraries\gJson;
use App\gLibraries\gtrace;
use App\gLibraries\guid;
use App\gLibraries\gStatus;
use App\gLibraries\gValidate;
use App\Models\ViewPermissionsByView;
use App\Models\User;
use App\Models\Response;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function store(Request $request)
    {
      $response = new Response();
      try {
  
        if (
          !isset($request->username) ||
          !isset($request->password) ||
          !isset($request->_branch) ||
          !isset($request->_person) ||
          !isset($request->_role) 
        ) {
          throw new Exception("Error: No deje campos vacíos");
        }
  
        $userValidation = User::select(['users.username'])->where('username', $request->username)->first();
  
        if ($userValidation) {
          throw new Exception("Este usuario ya existe");
        }
  
        $userJpa = new User();
  
        if (
          isset($request->image_type) &&
          isset($request->image_mini) &&
          isset($request->image_full)
        ) {
          if (
            $request->image_type &&
            $request->image_mini &&
            $request->image_full
          ) {
            $userJpa->image_type = $request->image_type;
            $userJpa->image_mini = base64_decode($request->image_mini);
            $userJpa->image_full = base64_decode($request->image_full);
          } else {
            $userJpa->image_type = null;
            $userJpa->image_mini = null;
            $userJpa->image_full = null;
          }
        }
  
        $userJpa->relative_id = guid::short();
        $userJpa->username = $request->username;
        $userJpa->password = password_hash($request->password, PASSWORD_DEFAULT);
        $userJpa->_role = $request->_role;
        $userJpa->_person = $request->_person;
        $userJpa->_branch = $request->_branch;
        $userJpa->origin = $request->origin;
        $userJpa->creation_date = gTrace::getDate('mysql');
        $userJpa->status = "1";
  
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
