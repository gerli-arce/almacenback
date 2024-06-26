<?php

namespace App\Http\Controllers;

use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Response;
use App\gLibraries\guid;
use App\Models\Transports;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransportsController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'transports', 'create')) {
                throw new Exception('No tienes permisos para agregar tipo de transporte');
            }

            if (
                !isset($request->name) ||
                !isset($request->doc_type) ||
                !isset($request->doc_number) ||
                !isset($request->phone_number)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $roleValidation = Transports::select(['name'])->where('name', $request->name)->first();

            if ($roleValidation) {
                throw new Exception("Error: El tipo de transporte ya existe");
            }

            $transportJpa = new Transports();
            $transportJpa->name = $request->name;
            $transportJpa->doc_type = $request->doc_type;
            $transportJpa->doc_number = $request->doc_number;
            $transportJpa->relative_id = guid::short();
            $transportJpa->phone_number = $request->phone_number;
            if (isset($request->phone_number_secondary)) {
                $transportJpa->phone_number_secondary = $request->phone_number_secondary;
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
                    $transportJpa->image_type = $request->image_type;
                    $transportJpa->image_mini = base64_decode($request->image_mini);
                    $transportJpa->image_full = base64_decode($request->image_full);
                } else {
                    $transportJpa->image_type = null;
                    $transportJpa->image_mini = null;
                    $transportJpa->image_full = null;
                }
            }

            $transportJpa->status = "1";
            $transportJpa->save();

            $response->setStatus(200);
            $response->setMessage('El tipo de transporte se a agregado correctamente');
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

            // [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            // if ($status != 200) {
            //     throw new Exception($message);
            // }
            // if (!gValidate::check($role->permissions, $branch, 'categories', 'read')) {
            //     throw new Exception('No tienes permisos para listar categorias');
            // }

            $peopleJpa = Transports::select([
                'id',
                'name',
                'doc_number'
            ])->whereNotNull('status')
                ->WhereRaw("name LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orWhereRaw("doc_number LIKE CONCAT('%', ?, '%')", [$request->term])
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

    public function image($relative_id, $size)
    {
        $response = new Response();
        $content = null;
        $type = null;
        try {
            if ($size != 'full') {
                $size = 'mini';
            }
            if (
                !isset($relative_id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $modelJpa = Transports::select([
                "transport.image_$size as image_content",
                'transport.image_type',

            ])
                ->where('relative_id', $relative_id)
                ->first();

            if (!$modelJpa) {
                throw new Exception('No se encontraron datos');
            }
            if (!$modelJpa->image_content) {
                throw new Exception('No existe imagen');
            }
            $content = $modelJpa->image_content;
            $type = $modelJpa->image_type;
            $response->setStatus(200);
        } catch (\Throwable$th) {
            $ruta = '../storage/images/transport_default.jpg';
            $fp = fopen($ruta, 'r');
            $datos_image = fread($fp, filesize($ruta));
            $datos_image = addslashes($datos_image);
            fclose($fp);
            $content = stripslashes($datos_image);
            $type = 'image/jpeg';
            $response->setStatus(400);
        } finally {
            return response(
                $content,
                $response->getStatus()
            )->header('Content-Type', $type);
        }
    }

    public function index(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'categories', 'read')) {
                throw new Exception('No tienes permisos para listar las categorias de ' . $branch);
            }

            $categoriesJpa = Category::whereNotNull('status')->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($categoriesJpa->toArray());
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

    public function paginate(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'transports', 'read')) {
                throw new Exception('No tienes permisos para listar los tipos de transporte ');
            }

            $query = Transports::select(
                [
                    'id',
                    'name',
                    'doc_type',
                    'doc_number',
                    'relative_id',
                    'phone_number',
                    'phone_number_secondary',
                    'status',
                ]
            )
                ->orderBy($request->order['column'], $request->order['dir']);

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
                if ($column == 'doc_type' || $column == '*') {
                    $q->orWhere('doc_type', $type, $value);
                }
                if ($column == 'doc_number' || $column == '*') {
                    $q->orWhere('doc_number', $type, $value);
                }
                if ($column == 'phone_number' || $column == '*') {
                    $q->orWhere('phone_number', $type, $value);
                }
                if ($column == 'phone_number_secondary' || $column == '*') {
                    $q->orWhere('phone_number_secondary', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $transportsJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Transports::count());
            $response->setData($transportsJpa->toArray());
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
            if (!gValidate::check($role->permissions, $branch, 'transports', 'update')) {
                throw new Exception('No tienes permisos para actualizar tipos de transporte');
            }

            $transportsJpa = Transports::find($request->id);
            if (!$transportsJpa) {
                throw new Exception("No se puede actualizar este registro");
            }

            if (isset($request->name)) {
                $verifyCatJpa = Transports::select(['id', 'name', 'status'])
                    ->where('name', $request->name)
                    ->where('id', '!=', $request->id)
                    ->whereNotNull('status')
                    ->first();
                if ($verifyCatJpa) {
                    throw new Exception("Elija otro nombre para este tipo de transporte");
                }
                $transportsJpa->name = $request->name;
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
                    $transportsJpa->image_type = $request->image_type;
                    $transportsJpa->image_mini = base64_decode($request->image_mini);
                    $transportsJpa->image_full = base64_decode($request->image_full);
                } else {
                    $transportsJpa->image_type = null;
                    $transportsJpa->image_mini = null;
                    $transportsJpa->image_full = null;
                }
            }


            if (isset($request->doc_type)) {
                $transportsJpa->doc_type = $request->doc_type;
            }

            if (isset($request->doc_number)) {
                $transportsJpa->doc_number = $request->doc_number;
            }
            if (isset($request->phone_number)) {
                $transportsJpa->phone_number = $request->phone_number;
            }

            if (isset($request->phone_number_secondary)) {
                $transportsJpa->phone_number_secondary = $request->phone_number_secondary;
            }

            if (gValidate::check($role->permissions, $branch, 'transports', 'change_status')) {
                if (isset($request->status)) {
                    $transportsJpa->status = $request->status;
                }
            }

            $transportsJpa->save();

            $response->setStatus(200);
            $response->setMessage('El tipo de transporte ha sido actualizado correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'transports', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar tipos de transporte');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $transportsJpa = Transports::find($request->id);
            if (!$transportsJpa) {
                throw new Exception('El tipo de trasporte que deseas eliminar no existe');
            }

          
            $transportsJpa->status = null;
            $transportsJpa->save();

            $response->setStatus(200);
            $response->setMessage('El tipo de transporte a sido eliminada correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'transports', 'delete_restore')) {
                throw new Exception('No tienes permisos para restaurar tipos de transporte');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $transportsJpa = Transports::find($request->id);
            if (!$transportsJpa) {
                throw new Exception('El tipo de transporte que deseas restaurar no existe');
            }

            $transportsJpa->status = "1";
            $transportsJpa->save();

            $response->setStatus(200);
            $response->setMessage('El tipo de transporte a sido restaurado correctamente');
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
