<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Response;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {
            if (
                !isset($request->name) ||
                !isset($request->correlative) ||
                !isset($request->department) ||
                !isset($request->province) ||
                !isset($request->distric) ||
                !isset($request->address)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branchValidation = Branch::select(['name', 'correlative'])
                ->where('name', $request->name)
                ->orWhere('correlative', $request->correlative)
                ->first();

            if ($branchValidation) {
                if ($branchValidation->name == $request->name) {
                    throw new Exception("El nombre de la sucursal ya existe");
                }
                if ($branchValidation->correlative == $request->correlative) {
                    throw new Exception("El correlativo de la sucursal ya existe");
                }
            }

            $branchJpa = new Branch();
            $branchJpa->name = $request->name;
            $branchJpa->correlative = $request->correlative;
            $branchJpa->department = $request->department;
            $branchJpa->province = $request->province;
            $branchJpa->distric = $request->distric;
            $branchJpa->address = $request->address;

            if ($request->description) {
                $branchJpa->description = $request->description;
            }

            $branchJpa->status = "1";

            $branchJpa->save();

            $response->setStatus(200);
            $response->setMessage('Sucursal agregada correctamente');
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
            $query = Branch::select([
                'id',
                'name',
                'correlative',
                'department',
                'province',
                'distric',
                'address',
                'description',
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

                if ($column == 'name' || $column == '*') {
                    $q->where('name', $type, $value);
                }
                if ($column == 'correlative' || $column == '*') {
                    $q->where('correlative', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
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

            });
            $iTotalDisplayRecords = $query->count();
            $branchJpa = $query->select('*')
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Branch::count());
            $response->setData($branchJpa->toArray());
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

            $branchJpa = Branch::find($request->id);

            if(!$branchJpa){
              throw new Exception("El registro que trata de actualizar no existe");
            }

            if (isset($request->name) && isset($request->correlative)) {
                $branchValidation = Branch::select(['id', 'name', 'correlative'])
                    ->where('name', $request->name)
                    ->where('id', '!=', $request->id)
                    ->orWhere('correlative', $request->correlative)
                    ->where('id', '!=', $request->id)
                    ->first();

                if ($branchValidation) {
                    if ($branchValidation->name == $request->name) {
                        throw new Exception("El nombre de la sucursal ya existe");
                    }
                    if ($branchValidation->correlative == $request->correlative) {
                        throw new Exception("El correlativo de la sucursal ya existe");
                    }
                }
                $branchJpa->name = $request->name;
                $branchJpa->correlative = $request->correlative;
            } else {
                if (isset($request->name)) {
                    $branchValidation = Branch::select(['id', 'name'])
                        ->where('name', $request->name)
                        ->where('id', '!=', $request->id)
                        ->first();

                    if ($branchValidation) {
                        throw new Exception("El nombre de la sucursal ya existe");
                    }
                    $branchJpa->name = $request->name;
                }
                if (isset($request->correlative)) {
                    $branchValidation = Branch::select(['id', 'correlative'])
                        ->where('correlative', $request->correlative)
                        ->where('id', '!=', $request->id)
                        ->first();

                    if ($branchValidation) {
                        throw new Exception("El correlativo de la sucursal ya existe");
                    }
                    $branchJpa->correlative = $request->correlative;
                }
            }

            if ($request->description) {
                $branchJpa->description = $request->description;
            }
            if($request->department){
              $branchJpa->department = $request->department;
            }
            if($request->province){
              $branchJpa->province = $request->province;
            }
            if($request->distric){
              $branchJpa->distric = $request->distric;
            }
            if($request->address){
              $branchJpa->address = $request->address;
            }
            if($request->status){
              $branchJpa->status = "1";
            }

            $branchJpa->save();

            $response->setStatus(200);
            $response->setMessage('Sucursal actualizada correctamente');
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

    public function delete(Request $request){
      $response = new Response();
      try {
          if (
              !isset($request->id)
          ) {
              throw new Exception("Error: No deje campos vacíos");
          }

          $branchJpa = Branch::find($request->id);

          if(!$branchJpa){
            throw new Exception("El registro que intenta eliminar no existe");
          }

          $branchJpa->status = null;
          
          $branchJpa->save();

          $response->setStatus(200);
          $response->setMessage('Sucursal eliminada correctamente');
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
    public function restore(Request $request){
      $response = new Response();
      try {
          if (
              !isset($request->id)
          ) {
              throw new Exception("Error: No deje campos vacíos");
          }

          $branchJpa = Branch::find($request->id);

          if(!$branchJpa){
            throw new Exception("El registro que intenta restaurar no existe");
          }

          $branchJpa->status = "1";
          
          $branchJpa->save();

          $response->setStatus(200);
          $response->setMessage('Sucursal restaurada correctamente');
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
