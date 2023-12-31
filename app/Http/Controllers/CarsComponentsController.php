<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gValidate;
use App\Models\CarComponents;
use App\Models\ViewComponentsByPart;
use App\Models\Response;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CarsComponentsController extends Controller
{

    public function index(Request $request)  {
        $response = new Response();
        try {
             
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars_components', 'read')) {
                throw new Exception('No tienes permisos para agregar componentes de vheículo');
            }

            $query = ViewComponentsByPart::select('*')->whereNotNull('status')->get();
            $components = array();
            foreach ($query as $componentnJpa) {
                $component = gJSON::restore($componentnJpa->toArray(), '__');
                $components[] = $component;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($components);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln ' . $th->getLine());
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
            if (!gValidate::check($role->permissions, $branch, 'cars_components', 'create')) {
                throw new Exception('No tienes permisos para agregar componentes de vheículo');
            }

            if (
                !isset($request->component) ||
                !isset($request->_part)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $carsComponentsValidation = CarComponents::select(['component'])
                ->where('component', $request->component)
                ->where('_part', $request->_part)
                ->first();

            if ($carsComponentsValidation) {
                throw new Exception("El componente ya existe.");
            }

            $carComponentsJpa = new CarComponents();
            $carComponentsJpa->component = $request->component;
            $carComponentsJpa->_part = $request->_part;

            $carComponentsJpa->description = $request->description;

            $carComponentsJpa->status = "1";

            $carComponentsJpa->save();

            $response->setStatus(200);
            $response->setMessage('Componente agregada correctamente');
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

    public function paginate(Request $request)
    {
        $response = new Response();
        try {

             [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars_components', 'read')) {
                throw new Exception('No tienes permisos para editar componentes de vheículo');
            }


            $query = ViewComponentsByPart::select('*')
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'component' || $column == '*') {
                    $q->where('component', $type, $value);
                }
                if ($column == 'part' || $column == '*') {
                    $q->where('parts_car__part', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }

            });
            $iTotalDisplayRecords = $query->count();
            $componentsJpa = $query->select('*')
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $components = array();
            foreach ($componentsJpa as $componentnJpa) {
                $component = gJSON::restore($componentnJpa->toArray(), '__');
                $components[] = $component;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(CarComponents::count());
            $response->setData($components);
        } catch (\Throwable $th) {
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

            $componentValidation = CarComponents::select(['components_car.id', 'components_car.component'])
                ->where('component', $request->component)
                ->where('id', '!=', $request->id)
                ->first();

            if ($componentValidation) {
                throw new Exception("Este componente ya existe");
            }

            $componentJpa = CarComponents::find($request->id);
            if (!$componentJpa) {
                throw new Exception("El componente que solicitada no existe");
            }
            if (isset($request->component)) {
                $componentJpa->component = $request->component;
            }
           
            if (isset($request->_part)) {
                $componentJpa->_part = $request->_part;
            }
            if (isset($request->description)) {
                $componentJpa->description = $request->description;
            }

            if (isset($request->status)) {
                $componentJpa->status = $request->status;
            }

            $componentJpa->save();

            $response->setStatus(200);
            $response->setMessage('El componente se a actualizado correctamente');
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

    public function delete(Request $request)
    {
        $response = new Response();
        try {

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: Es necesario el ID para esta operación");
            }

            $carComponentJpa = CarComponents::find($request->id);

            if (!$carComponentJpa) {
                throw new Exception("Este reguistro no existe");
            }

            $carComponentJpa->status = null;
            $carComponentJpa->save();

            $response->setStatus(200);
            $response->setMessage('El componente se a eliminado correctamente');
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

    public function restore(Request $request)
    {
        $response = new Response();
        try {

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: Es necesario el ID para esta operación");
            }

            $carComponentJpa = CarComponents::find($request->id);
            if (!$carComponentJpa) {
                throw new Exception("Este reguistro no existe");
            }
            $carComponentJpa->status = "1";
            $carComponentJpa->save();

            $response->setStatus(200);
            $response->setMessage('El componente a sido restaurado correctamente');
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
