<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\People;
use App\Models\Response;
use App\Models\ViewPeople;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientsController extends Controller
{
    public function search(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'technicals', 'read')) {
                throw new Exception('No tienes permisos para listar técnicos');
            }

            $peopleJpa = ViewPeople::select([
                'id',
                'type',
                'doc_number',
                'name',
                'lastname',
            ])->whereNotNull('status')
                ->WhereRaw("doc_number LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orWhereRaw("name LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orWhereRaw("lastname LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orderBy('doc_number', 'asc')
                ->where('type', 'CLIENT')
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($peopleJpa->toArray());
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

    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'clients', 'create')) {
                throw new Exception('No tienes permisos para agregar clientes');
            }

            if (
                !isset($request->doc_type) ||
                !isset($request->doc_number) ||
                !isset($request->name) ||
                !isset($request->_branch)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            // if (strlen($request->doc_number) != 8) {
            //     throw new Exception("Para el tipo de documento DNI es nesesario que tenga 8 números.");
            // }

            $userValidation = People::select(['doc_type', 'doc_number'])
                ->where('doc_type', $request->doc_type)
                ->where('doc_number', $request->doc_number)
                ->first();

            if ($userValidation) {
                throw new Exception("Esta registro ya existe");
            }

            $peopleJpa = new People();
            $peopleJpa->doc_type = $request->doc_type;
            $peopleJpa->doc_number = $request->doc_number;
            $peopleJpa->name = $request->name;
            $peopleJpa->lastname = $request->lastname;
            $peopleJpa->relative_id = guid::short();

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
                    $peopleJpa->image_type = $request->image_type;
                    $peopleJpa->image_mini = base64_decode($request->image_mini);
                    $peopleJpa->image_full = base64_decode($request->image_full);
                } else {
                    $peopleJpa->image_type = null;
                    $peopleJpa->image_mini = null;
                    $peopleJpa->image_full = null;
                }
            }

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

            if ($request->ubigeo) {
                $peopleJpa->ubigeo = $request->ubigeo;
            }

            if ($request->address) {
                $peopleJpa->address = $request->address;
            }
            $peopleJpa->_creation_user = $userid;
            $peopleJpa->creation_date = gTrace::getDate('mysql');
            $peopleJpa->_update_user = $userid;
            $peopleJpa->update_date = gTrace::getDate('mysql');
            $peopleJpa->type = "CLIENT";
            $peopleJpa->_branch = $request->_branch;
            $peopleJpa->status = "1";
            $peopleJpa->save();

            $response->setStatus(200);
            $response->setMessage('Cliente agregado correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'clients', 'read')) {
                throw new Exception('No tienes permisos para listar clientes');
            }

            $query = ViewPeople::select([
                'id',
                'doc_type',
                'doc_number',
                'name',
                'lastname',
                'relative_id',
                'birthdate',
                'gender',
                'email',
                'phone',
                'ubigeo',
                'address',
                'type',
                'branch__id',
                'branch__name',
                'branch__correlative',
                'branch__ubigeo',
                'branch__address',
                'branch__description',
                'branch__status',
                'user_creation__username',
                'user_creation__relative_id',
                'creation_date',
                'user_update__id',
                'user_update__username',
                'user_update__relative_id',
                'update_date',
                'status',
            ])
                ->orderBy($request->order['column'], $request->order['dir'])
                ->where('type', 'CLIENT');

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
                if ($column == 'ubigeo' || $column == '*') {
                    $q->orWhere('ubigeo', $type, $value);
                }
                if ($column == 'address' || $column == '*') {
                    $q->orWhere('address', $type, $value);
                }
                if ($column == 'branch__name' || $column == '*') {
                    $q->orWhere('branch__name', $type, $value);
                }
                if ($column == 'status' || $column == '*') {
                    $q->orWhere('status', $type, $value);
                }
            });
            $iTotalDisplayRecords = $query->count();

            $peopleJpa = $query
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
            $response->setITotalRecords(ViewPeople::where('type', 'CLIENT')->count());
            $response->setData($people);
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

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'clients', 'update')) {
                throw new Exception('No tienes permisos para actualizar clientes');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $personJpa = People::find($request->id);

            if (!$personJpa) {
                throw new Exception("Esta persona no existe");
            }

            if (isset($request->doc_type) && isset($request->doc_number)) {
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
                $personJpa->doc_type = $request->doc_type;
                $personJpa->doc_number = $request->doc_number;
            }

            $userValidation = People::select(['id', 'doc_type', 'doc_number'])
                ->where('doc_type', $request->doc_type)
                ->where('doc_number', $request->doc_number)
                ->where('id', '!=', $request->id)
                ->first();

            if ($userValidation) {
                throw new Exception("Esta persona ya existe");
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
                    $personJpa->image_type = $request->image_type;
                    $personJpa->image_mini = base64_decode($request->image_mini);
                    $personJpa->image_full = base64_decode($request->image_full);
                } else {
                    $personJpa->image_type = null;
                    $personJpa->image_mini = null;
                    $personJpa->image_full = null;
                }
            }

            if (isset($request->name)) {
                $personJpa->name = $request->name;
            }

            if (isset($request->lastname)) {
                $personJpa->lastname = $request->lastname;
            }

            if (isset($request->birthdate)) {
                $personJpa->birthdate = $request->birthdate;
            }

            if (isset($request->gender)) {
                $personJpa->gender = $request->gender;
            }

            if (isset($request->email)) {
                $personJpa->email = $request->email;
            }

            if (isset($request->phone)) {
                $personJpa->phone = $request->phone;
            }

            if (isset($request->ubigeo)) {
                $personJpa->ubigeo = $request->ubigeo;
            }

            if (isset($request->address)) {
                $personJpa->address = $request->address;
            }

            if (isset($request->type)) {
                $personJpa->type = $request->type;
            }

            if (isset($request->_branch)) {
                $personJpa->_branch = $request->_branch;
            }

            if (gValidate::check($role->permissions, $branch, 'clients', 'change_status')) {
                if (isset($request->status)) {
                    $personJpa->status = $request->status;
                }
            }

            $personJpa->_update_user = $userid;
            $personJpa->update_date = gTrace::getDate('mysql');

            $personJpa->save();

            $response->setStatus(200);
            $response->setMessage('El cliente se a actualizado correctamente');
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
