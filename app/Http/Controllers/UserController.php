<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gUid;
use App\gLibraries\gValidate;
use App\Models\Response;
use App\Models\User;
use App\Models\ViewUsers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
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
                    $request->image_type != "none" &&
                    $request->image_mini != "none" &&
                    $request->image_full != "none"
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
            $userJpa->origin = $branch;
            $userJpa->creation_date = gTrace::getDate('mysql');
            $userJpa->_creation_user = $userid;
            $userJpa->update_date = gTrace::getDate('mysql');
            $userJpa->_update_user = $userid;
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

    public function paginate(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'users', 'read')) {
                throw new Exception('No tienes permisos para listar usuarios');
            }

            $dat = gValidate::check($role->permissions, $branch, 'users', 'read');

            $query = ViewUsers::select([
                'id',
                'username',
                'relative_id',
                'person__id',
                'person__doc_type',
                'person__doc_number',
                'person__name',
                'person__lastname',
                'person__birthdate',
                'person__gender',
                'person__ubigeo',
                'person__address',
                'person__type',
                'person__status',
                'branch__name',
                'branch__correlative',
                'branch__ubigeo',
                'branch__address',
                'branch__status',
                'origin',
                'role__id',
                'role__role',
                'role__priority',
                'role__permissions',
                'role__status',
                'creation_date',
                'user_creation__id',
                'user_creation__username',
                'user_creation__relative_id',
                'update_date',
                'user_update__id',
                'user_update__username',
                'user_update__relative_id',
                'status',
            ])
                ->orderBy($request->order['column'], $request->order['dir']);

            // if (!$request->all || !gValidate::check($role->permissions, 'views', 'see_trash')) {
            // }

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'username' || $column == '*') {
                    $q->where('username', $type, $value);
                }
                if ($column == 'person__name' || $column == '*') {
                    $q->where('person__name', $type, $value);
                }
                if ($column == 'person__lastname' || $column == '*') {
                    $q->orWhere('person__lastname', $type, $value);
                }
                if ($column == 'person__doc_number' || $column == '*') {
                    $q->orWhere('person__doc_number', $type, $value);
                }
                if ($column == 'branch__name' || $column == '*') {
                    $q->orWhere('branch__name', $type, $value);
                }
                if ($column == 'role__role' || $column == '*') {
                    $q->orWhere('role__role', $type, $value);
                }
                if ($column == 'status' || $column == '*') {
                    $q->orWhere('status', $type, $value);
                }
            });
            $iTotalDisplayRecords = $query->count();

            $usersJpa = $query->select('*')
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $users = array();
            foreach ($usersJpa as $userJpa) {
                $person = gJSON::restore($userJpa->toArray(), '__');
                $person['role']['permissions'] = gJSON::parse($person['role']['permissions']);
                unset($person['password']);
                unset($person['auth_token']);
                $users[] = $person;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewUsers::count());
            $response->setData($users);
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . $th->getLine());
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

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'users', 'update')) {
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
                $userJpa->username = $request->username;
            }

            if (isset($request->password)) {
                $userJpa->password = password_hash($request->password, PASSWORD_DEFAULT);
            }

            if (isset($request->_branch)) {
                $userJpa->_branch = $request->_branch;
            }

            if (isset($request->_person)) {
                $personValidation = User::select(['id', 'username', '_person'])
                    ->where('_person', '=', $request->_person)
                    ->where('id', '!=', $request->id)->first();
                if ($personValidation) {
                    throw new Exception("Error: Esta persona ya tiene un usuario");
                }
                $userJpa->_person = $request->_person;
            }

            if ($request->_role) {
                $userJpa->_role = $request->_role;
            }

            if (
                isset($request->image_type) &&
                isset($request->image_mini) &&
                isset($request->image_full)
            ) {
                if (
                    $request->image_type != "none" &&
                    $request->image_mini != "none" &&
                    $request->image_full != "none"
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

            $userJpa->update_date = gTrace::getDate('mysql');
            $userJpa->_update_user = $userid;
            $userJpa->status = "1";

            $userJpa->save();

            $response->setStatus(200);
            $response->setMessage('Usuario actualizado correctamente');
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
