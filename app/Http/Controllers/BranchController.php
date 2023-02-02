<?php

namespace App\Http\Controllers;

use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\Response;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'branches', 'read')) {
                throw new Exception('No tienes permisos para listar las sucursales');
            }

            $branchesJpa = Branch::select(['*'])->whereNotNull('status')->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($branchesJpa->toArray());
        } catch (\Throwable$th) {
            $response->setMessage($th->getMessage());
            $response->setStatus(400);
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

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'branches', 'create')) {
                throw new Exception('No tienes permisos para listar las sucursales');
            }
            
            if (
                !isset($request->name) ||
                !isset($request->correlative) ||
                !isset($request->ubigeo) ||
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
            $branchJpa->ubigeo = $request->ubigeo;
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
                'ubigeo',
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
                if ($column == 'ubigeo' || $column == '*') {
                    $q->orWhere('ubigeo', $type, $value);
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

    public function getBranch(Request $request){
        $response = new Response();
        try {

            if(!isset($request->id)){
                throw new Exception("Error: No deje campos vacios");
            }

            $branchJpa = Branch::find($request->id);
            if(!$branchJpa){
                throw new Exception("Error: El reguistro solicitado no existe");
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($branchJpa->toArray());
        } catch (\Throwable$th) {
            $response->setMessage($th->getMessage());
            $response->setStatus(400);
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

            if (!$branchJpa) {
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
            if ($request->ubigeo) {
                $branchJpa->ubigeo = $request->ubigeo;
            }
            if ($request->address) {
                $branchJpa->address = $request->address;
            }
            if ($request->status) {
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

    public function delete(Request $request)
    {
        $response = new Response();
        try {
            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branchJpa = Branch::find($request->id);

            if (!$branchJpa) {
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
    public function restore(Request $request)
    {
        $response = new Response();
        try {
            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branchJpa = Branch::find($request->id);

            if (!$branchJpa) {
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
