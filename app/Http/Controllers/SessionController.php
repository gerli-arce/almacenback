<?php

namespace App\Http\Controllers;

use App\gLibraries\gFetch;
use App\gLibraries\gJson;
use App\gLibraries\guid;
use App\gLibraries\gStatus;
use App\gLibraries\gValidate;
use App\Models\ViewUsers;
use App\Models\User;
use App\Models\Response;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    
    public function login(Request $request)
    {
        $response = new Response();
        try {

            if(!isset($request->username) && !isset($request->password)){
                throw new Exception("Ingrese su credenciales para iniciar sesión");
            }
           
            if(!isset($request->username)){
                throw new Exception("El nombre de usuario debe ser enviado");
            }

            if(!isset($request->password)){
                throw new Exception("La contraseña debe ser enviada");
            }

            $userJpa = User::select([
                'users.id',
                'users.username',
                'users.password',
                'users.relative_id',
                'users.auth_token',
                'people.id AS person__id',
                'people.doc_type AS person__doc_type',
                'people.doc_number AS person__doc_number',
                'people.name AS person__name',
                'people.lastname AS person__lastname',
                'people.birthdate AS person__birthdate',
                'people.gender AS person__gender',
                'people.email AS person__email',
                'people.phone AS person__phone',
                'people.ubigeo AS person__ubigeo',
                'people.address AS person__address',
                'people.type AS person__type',
                'people.status AS person__status',
                'branches.id AS branch__id',
                'branches.name AS branch__name',
                'branches.correlative AS branch__correlative',
                'branches.ubigeo AS branch__ubigeo',
                'branches.address AS branch__address',
                'branches.status AS branch__status',
                'users.origin',
                'roles.id AS role__id',
                'roles.role AS role__role',
                'roles.priority AS role__priority',
                'roles.permissions AS role__permissions',
                'roles.status AS role__status',
                'users.creation_date',
                'users.status',
            ])
            ->where('username', $request->username)
            ->leftjoin('people','users._person','=','people.id')
            ->leftjoin('branches','users._branch','=','branches.id')
            ->leftjoin('roles','users._role','=','roles.id')
            ->first();

            if(!$userJpa){
                throw new Exception("Error: Usuario no existe");
            }

            if(!password_verify($request->password, $userJpa->password)){
                throw new Exception("Error: Contraseña incorrecta");
            }

            if(!$userJpa->status){
                throw new Exception("Error: Usuario inactivo");
            }

            $userJpa->relative_id = '12';
            $userJpa->auth_token = guid::long();

            $userJpa->save();

            $user = gJSON::restore($userJpa->toArray(),'__');
            unset($user['id']);
            unset($user['password']);
            $user['role']['permissions'] = gJSON::parse($user['role']['permissions']);

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($user);
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

   
    public function logout(Request $request)
    {
        $response = new Response();
        try {
            if (
                !isset($request->relative_id)
            ) {
                throw new Exception("Error: no deje campos vaciós");
            }
            if ($request->header('SoDe-Auth-Token') == null || $request->header('SoDe-Auth-User') == null) {
                throw new Exception('Error: Datos de cabesera deben ser enviados');
            }
            $userJpaValidation = User::select([
                'users.username',
                'users.auth_token'
            ])
            ->where('auth_token', $request->header('SoDe-Auth-Token'))
            ->where('username', $request->header('SoDe-Auth-User'))
            ->first();

            if (!$userJpaValidation) {
                throw new Exception('Error: Usted no puede realizar esta operación (SUS DATOS DE USUARIO SON INCORRECTOS)');
            }

            $userJpa = User::select([
                'users.id',
                'users.username',
                'users.auth_token'
            ])->where('relative_id', $request->relative_id)
            ->first();

            $userJpa ->auth_token = null;
            $userJpa ->save();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData([]);
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

    public function verify(Request $request)
    {
        $response = new Response();
        try {
           
            $userJpa = User::select([
                'users.id',
                'users.username',
                'users.password',
                'users.relative_id',
                'users.auth_token',
                'people.id AS person__id',
                'people.doc_type AS person__doc_type',
                'people.doc_number AS person__doc_number',
                'people.name AS person__name',
                'people.lastname AS person__lastname',
                'people.birthdate AS person__birthdate',
                'people.gender AS person__gender',
                'people.email AS person__email',
                'people.phone AS person__phone',
                'people.ubigeo AS person__ubigeo',
                'people.address AS person__address',
                'people.type AS person__type',
                'people.status AS person__status',
                'branches.id AS branch__id',
                'branches.name AS branch__name',
                'branches.correlative AS branch__correlative',
                'branches.ubigeo AS branch__ubigeo',
                'branches.address AS branch__address',
                'branches.status AS branch__status',
                'users.origin',
                'roles.id AS role__id',
                'roles.role AS role__role',
                'roles.priority AS role__priority',
                'roles.permissions AS role__permissions',
                'roles.status AS role__status',
                'users.creation_date',
                'users.status',
            ])
            ->where('username', '=', $request->header('auth-user'))
            ->where('auth_token', '=', $request->header('auth-token'))
            ->leftjoin('people','users._person','=','people.id')
            ->leftjoin('branches','users._branch','=','branches.id')
            ->leftjoin('roles','users._role','=','roles.id')
            ->first();

            if (!$userJpa) {
                throw new Exception('No tienes una sesión activa');
            }

            $user = gJSON::restore($userJpa->toArray(),'__');
            unset($user['id']);
            unset($user['password']);
            $user['role']['permissions'] = gJSON::parse($user['role']['permissions']);

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($user);
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
