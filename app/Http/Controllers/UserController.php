<?php

namespace App\Http\Controllers;

use App\gLibraries\gtrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\Response;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'users', 'create')) {
                throw new Exception('No tienes permisos para crear usuarios');
            }

            if (
                !isset($request->username) ||
                !isset($request->password) ||
                !isset($request->_branch) ||
                !isset($request->_person) ||
                !isset($request->_role)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $userValidation = User::select(['username'])
                ->where('username', '=', $request->username)->first();

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
        } catch (\Throwable$th) {
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

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'users', 'create')) {
                throw new Exception('No tienes permisos para actualizar usuarios');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $userJpa = User::find($request->id);

            if (!$userJpa) {
                throw new Exception("Error: El registro que intenta modificar no existe");
            }

            if (isset($request->username)) {
                $userValidation = User::select(['id', 'username'])
                    ->where('username', $request->username)
                    ->where('id', '!=', $request->id)->first();
                if ($userValidation) {
                    throw new Exception("Este usuario ya existe");
                }
            }

            if (isset($request->password)) {
                $userJpa->password = password_hash($request->password, PASSWORD_DEFAULT);
            }

            if (isset($request->_branch)) {
                $userJpa->_branch = $request->_branch;
            }

            // if(isset($request->_person)){
            //   $personValidation = User::select(['id','username','_person'])
            //   ->where('_person','=',$request->_person)
            //   ->where('id','!=',$request->id)->first();
            //   if($personValidation){
            //     throw new Exception("Error: Esta persona ya tiene un usuario");
            //   }
            // }

            // if($request->_role){

            // }

            // if (
            //   isset($request->image_type) &&
            //   isset($request->image_mini) &&
            //   isset($request->image_full)
            // ) {
            //   if (
            //     $request->image_type &&
            //     $request->image_mini &&
            //     $request->image_full
            //   ) {
            //     $userJpa->image_type = $request->image_type;
            //     $userJpa->image_mini = base64_decode($request->image_mini);
            //     $userJpa->image_full = base64_decode($request->image_full);
            //   } else {
            //     $userJpa->image_type = null;
            //     $userJpa->image_mini = null;
            //     $userJpa->image_full = null;
            //   }
            // }

            // $userJpa->relative_id = guid::short();
            // $userJpa->username = $request->username;
            // $userJpa->password = password_hash($request->password, PASSWORD_DEFAULT);
            // $userJpa->_role = $request->_role;
            // $userJpa->_person = $request->_person;
            // $userJpa->_branch = $request->_branch;
            // $userJpa->origin = $request->origin;
            // $userJpa->creation_date = gTrace::getDate('mysql');
            // $userJpa->status = "1";

            $userJpa->save();

            $response->setStatus(200);
            $response->setMessage('Usuario agregado correctamente');
        } catch (\Throwable$th) {
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
