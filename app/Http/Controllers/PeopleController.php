<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\Models\People;
use App\Models\Response;
use App\Models\ViewPeople;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeopleController extends Controller
{
    public function store(Request $request)
    {
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

            if ($request->doc_type == "RUC" && $request->doc_type == "RUC10") {
                if (strlen($request->doc_number) != 11) {
                    throw new Exception("Para el tipo de documento RUC es nesesario que tenga 11 números.");
                }
            }

            if ($request->doc_type == "DNI") {
                if (strlen($request->doc_number) != 8) {
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

            if ($request->birthdate) {
                $peopleJpa->birthdate = $request->birthdate;
            }

            if ($request->gender) {
                $peopleJpa->gender = $request->gender;
            }

            if ($request->email) {
                $peopleJpa->email = $request->email;
            }

            if ($request->phone) {
                $peopleJpa->phone = $request->phone;
            }

            if ($request->department) {
                $peopleJpa->department = $request->department;
            }

            if ($request->province) {
                $peopleJpa->province = $request->province;
            }

            if ($request->distric) {
                $peopleJpa->distric = $request->distric;
            }

            if ($request->address) {
                $peopleJpa->address = $request->address;
            }

            $peopleJpa->type = $request->type;
            $peopleJpa->_branch = $request->_branch;

            $peopleJpa->status = "1";

            $peopleJpa->save();

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

            $query = ViewPeople::select([
                'id',
                'doc_type',
                'doc_number',
                'name',
                'lastname',
                'birthdate',
                'gender',
                'email',
                'phone',
                'department',
                'province',
                'distric',
                'address',
                'type',
                'branch__id',
                'branch__correlative',
                'branch__name',
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

                if ($column == 'doc_type' || $column == '*') {
                    $q->where('doc_type', $type, $value);
                }
                if ($column == 'doc_number' || $column == '*') {
                    $q->where('doc_number', $type, $value);
                }
                if ($column == 'name' || $column == '*') {
                    $q->orWhere('name', $type, $value);
                }
                if ($column == 'lastname' || $column == '*') {
                    $q->orWhere('lastname', $type, $value);
                }
                if ($column == 'birthdate' || $column == '*') {
                    $q->orWhere('birthdate', $type, $value);
                }
                if ($column == 'gender' || $column == '*') {
                    $q->orWhere('gender', $type, $value);
                }
                if ($column == 'email' || $column == '*') {
                    $q->orWhere('email', $type, $value);
                }
                if ($column == 'phone' || $column == '*') {
                    $q->orWhere('phone', $type, $value);
                }
                if ($column == 'department' || $column == '*') {
                    $q->orWhere('department', $type, $value);
                }
                if ($column == 'province' || $column == '*') {
                    $q->orWhere('province', $type, $value);
                }
                if ($column == 'distric' || $column == '*') {
                    $q->orWhere('distric', $type, $value);
                }
                if ($column == 'address' || $column == '*') {
                    $q->orWhere('address', $type, $value);
                }
                if ($column == 'type' || $column == '*') {
                    $q->orWhere('type', $type, $value);
                }
                if ($column == 'branch__name' || $column == '*') {
                    $q->orWhere('branch__name', $type, $value);
                }
                if ($column == 'status' || $column == '*') {
                    $q->orWhere('status', $type, $value);
                }
            });
            $iTotalDisplayRecords = $query->count();

            $peopleJpa = $query->select('*')
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $people = array();
            foreach ($peopleJpa as $personJpa) {
                $person = gJSON::restore($personJpa->toArray(), '__');
                $people[] = $person;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewPeople::count());
            $response->setData($people);
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln ' . $th->getLine());
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

            if (
                !isset($request->id)

            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $PermissionValidation = Permission::select(['permissions.id', 'permissions.permission'])
                ->where('permission', $request->permission)
                ->where('id', '!=', $request->id)
                ->first();

            if ($PermissionValidation) {
                throw new Exception("Este permiso ya existe");
            }

            $permissionJpa = Permission::find($request->id);
            if (!$permissionJpa) {
                throw new Exception("El permiso que solicitada no existe");
            }
            if (isset($request->permission)) {
                $permissionJpa->permission = $request->permission;
            }
            if (isset($request->correlative)) {
                $permissionJpa->correlative = $request->correlative;
            }
            if (isset($request->_view)) {
                $permissionJpa->_view = $request->_view;
            }
            if (isset($request->description)) {
                $permissionJpa->description = $request->description;
            }

            if (isset($request->status)) {
                $permissionJpa->status = $request->status;
            }

            $permissionJpa->save();

            $response->setStatus(200);
            $response->setMessage('El permiso se a actualizado correctamente');
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

    public function delete(Request $request)
    {
        $response = new Response();
        try {

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: Es necesario el ID para esta operación");
            }

            $permissionJpa = Permission::find($request->id);

            if (!$permissionJpa) {
                throw new Exception("Este reguistro no existe");
            }

            $permissionJpa->status = null;
            $permissionJpa->save();

            $response->setStatus(200);
            $response->setMessage('El permiso se a eliminado correctamente');
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

    public function restore(Request $request)
    {
        $response = new Response();
        try {

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: Es necesario el ID para esta operación");
            }

            $viewJpa = Permission::find($request->id);
            if (!$viewJpa) {
                throw new Exception("Este reguistro no existe");
            }
            $viewJpa->status = "1";
            $viewJpa->save();

            $response->setStatus(200);
            $response->setMessage('El permiso a sido restaurado correctamente');
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
