<?php

namespace App\Http\Controllers;

use App\gLibraries\gFetch;
use App\gLibraries\gStatus;
use App\gLibraries\gValidate;
use App\Models\View;
use App\Models\Response;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ViewController extends Controller
{

    public function index (Response $rsponse){
        $response = new Response();
        try {

            $viewsJpa = View::whereNotNull('status')->orderBy('view', 'ASC')->get();
            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($viewsJpa->toArray());
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

    public function store(Request $request){
        $response = new Response();
        try {
    
          if (
            !isset($request->view) ||
            !isset($request->correlative) ||
            !isset($request->path)
          ) {
            throw new Exception("Error: No deje campos vacíos");
          }

          $viewValidation = View::select(['view', 'path'])
          ->where('view', $request->view)
          ->orWhere('path', $request->path)
          ->orWhere('correlative', $request->correlative)
          ->first();
    
          if ($viewValidation) {
            if($viewValidation->view == $request->view){
                throw new Exception("El nombre de la vista ya existe");
            }
            if($viewValidation->path == $request->path){
                throw new Exception("La ruta ya existe");
            }
            if($viewValidation->correlative == $request->correlative){
                throw new Exception("El correlativo ya existe");
            }
          }
    
          $viewJpa = new View();
          $viewJpa->view = $request->view;
          $viewJpa->correlative = $request->correlative;
          $viewJpa->path = $request->path;

          if($request->description){
            $viewJpa->description = $request->description;
          }
     
          $viewJpa->status ="1";

          $viewJpa->save();
    
          $response->setStatus(200);
          $response->setMessage('Vista agregada correctamente');
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

            $query = View::orderBy($request->order['column'], 
            $request->order['dir']);

            // if (!$request->all || !gValidate::check($role->permissions, 'views', 'see_trash')) {
            // }

            if(!$request->all){
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'view' || $column == '*') {
                    $q->where('view', $type, $value);
                }
                if ($column == 'correlative' || $column == '*') {
                    $q->where('correlative', $type, $value);
                }
                if ($column == 'path' || $column == '*') {
                    $q->orWhere('path', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
                if ($column == 'status' || $column == '*') {
                    $q->orWhere('status', $type, $value);
                }
            });
            $iTotalDisplayRecords = $query->count();
            $viewsJpa = $query->select('*')
                ->skip($request->start)
                ->take($request->length)
                ->get();
            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(View::count());
            $response->setData($viewsJpa->toArray());
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

    public function update(Request $request)
    {
        $response = new Response();
        try {

            if (
                !isset($request->id)

            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $viewValidation = View::select(['view', 'correlative', 'path'])
                ->where('id', '!=', $request->id)
                ->where(function ($q) use ($request) {
                    $q->where('view', $request->view);
                    $q->orWhere('correlative', $request->correlative);
                    $q->orWhere('path', $request->path);
                })
                ->first();

            if ($viewValidation) {
                if ($viewValidation->view == $request->view) {
                    throw new Exception("Escoja otro nombre para la vista");
                }
                if ($viewValidation->correlative == $request->correlative) {
                    throw new Exception("Escoja correlativo para esta vista");
                }
                if ($viewValidation->path == $request->path) {
                    throw new Exception("Escoja otra ruta para esta vista");
                }
            }

            $viewJpa = View::find($request->id);
            if (!$viewJpa) {
                throw new Exception("La vista solicitada no existe");
            }
            if(isset($request->view)){
                $viewJpa->view = $request->view;
            }
            if(isset($request->correlative)){
                $viewJpa->correlative = $request->correlative;
            }
            if(isset($request->path)){
                $viewJpa->path = $request->path;
            }
            if(isset($request->description)){
                $viewJpa->description = $request->description;
            }
            
            if(isset($request->status)){
                $viewJpa->status = $request->status;
            }

            $viewJpa->save();

            $response->setStatus(200);
            $response->setMessage('La vista se a actualizado correctamente');
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

            $viewJpa = View::find($request->id);

            if (!$viewJpa) {
                throw new Exception("Este reguistro no existe");
            }

            $viewJpa->status = null;
            $viewJpa->save();

            $response->setStatus(200);
            $response->setMessage('La vista se a eliminado correctamente');
            $response->setData($viewJpa->toArray());
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

            $viewJpa = View::find($request->id);
            if (!$viewJpa) {
                throw new Exception("Este reguistro no existe");
            }
            $viewJpa->status = "1";
            $viewJpa->save();

            $response->setStatus(200);
            $response->setMessage('La vsita a sido restaurado correctamente');
            $response->setData($viewJpa->toArray());
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
