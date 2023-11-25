<?php

namespace App\Http\Controllers;

use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Response;
use App\Models\PartsCars;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartsCarsController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars_parts', 'create')) {
                throw new Exception('No tienes permisos en ' . $branch);
            }

            if (
                !isset($request->part)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $partsCarJpa = new PartsCars();
            $partsCarJpa->part = $request->part;
            $partsCarJpa->status = 1;
            $partsCarJpa->save();

            $response->setStatus(200);
            $response->setMessage('La parte del vehículo se a agregado correctamente');
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


    public function search(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            // if (!gValidate::check($role->permissions, $branch, 'unities', 'read')) {
            //     throw new Exception('No tienes permisos para listar unidades');
            // }

            $peopleJpa = PartsCars::select([
                'id',
                'acronym',
                'name',
            ])->whereNotNull('status')
                ->WhereRaw("acronym LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orWhereRaw("name LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orderBy('name', 'asc')
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

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'cars_parts', 'read')) {
                throw new Exception('No tienes permisos de ' . $branch);
            }

            $query = PartsCars::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

                if(!$request->all){
                    $query->whereNotNull('status');
                }
    
            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'part' || $column == '*') {
                    $q->where('part', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $partsCarJpa = $query->select('*')
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(PartsCars::count());
            $response->setData($partsCarJpa->toArray());
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

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars_parts', 'update')) {
                throw new Exception('No tienes permisos para actualizar parte del vehiculo');
            }

            $partsCarJpa = PartsCars::find($request->id);
            if (!$partsCarJpa) {
                throw new Exception("No se puede actualizar este registro");
            }
            
            if(isset($request->part)){
                $partsCarJpa->part = $request->part;
            }

            $partsCarJpa->description = $request->description;
            $partsCarJpa->save();

            $response->setStatus(200);
            $response->setMessage('La parte del vehiculo ha sido actualizada correctamente');
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

    public function destroy(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars_parts', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar parte del vehiculo en ' . $branch);
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $partsCarJpa = PartsCars::find($request->id);
            if (!$partsCarJpa) {
                throw new Exception('La parte del vehiculo que deseas eliminar no existe');
            }

            $partsCarJpa->status = null;
            $partsCarJpa->save();

            $response->setStatus(200);
            $response->setMessage('La parte del vehiculo a sido eliminada correctamente');
            $response->setData($role->toArray());
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

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars_parts', 'delete_restore')) {
                throw new Exception('No tienes permisos para restaurar parte del vehiculo en ' . $branch);
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $partCarsJpa = PartsCars::find($request->id);
            if (!$partCarsJpa) {
                throw new Exception('La parte del vehiculo que deseas restaurar no existe');
            }

            $partCarsJpa->status = "1";
            $partCarsJpa->save();

            $response->setStatus(200);
            $response->setMessage('La parte del vehiculo a sido restaurada correctamente');
            $response->setData($role->toArray());
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
