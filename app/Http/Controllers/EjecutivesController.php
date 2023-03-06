<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\People;
use App\Models\Response;
use App\Models\ViewPeople;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EjecutivesController extends Controller
{
    public function search(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'technicalls', 'read')) {
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
                ->where('type', 'TECHNICAL')
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

    public function paginate(Request $request)
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
                ->where('type', 'EJECUTIVE')
                ->where('branch__correlative',$branch);

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
            $response->setITotalRecords(ViewPeople::where('type', 'TECHNICAL')->where('branch__correlative',$branch)->count());
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
}
