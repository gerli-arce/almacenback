<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\DetailSale;
use App\Models\Cars;
use App\Models\EntryDetail;
use App\Models\Response;
use App\Models\SalesProducts;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Dompdf\Dompdf;
use Dompdf\Options;

class CarsController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'create')) {
                throw new Exception("No tienes permisos para agregar vehiculos en ");
            }

            if (
                !isset($request->placa)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $brandValidation = Cars::select(['placa'])
                ->where('placa', $request->placa)
                ->first();

            if ($brandValidation) {
                if ($brandValidation->placa == $request->placa) {
                    throw new Exception("La placa ya fue registrada");
                }
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $carsJpa = new Cars();
            $carsJpa->_branch = $branch_->id;
            $carsJpa->placa = $request->placa;

            if(isset($request->color)){
                $carsJpa->color = $request->color;
            }

            if(isset($request->num_chasis)){
                $carsJpa->num_chasis = $request->num_chasis;
            }

            if(isset($request->year)){
                $carsJpa->year = $request->year;
            }

            if(isset($request->soat)){
                $carsJpa->soat = $request->soat;
            }

            if(isset($request->_model)){
                $carsJpa->_model = $request->_model;
            }

            if(isset($request->property_card)){
                $carsJpa->property_card = $request->property_card;
            }

            if(isset($request->_person)){
                $carsJpa->_person = $request->_person;
            }

            if(isset($request->_branch)){
                $carsJpa->_branch = $request->_branch;
            }

            if (
                isset($request->image_type) &&
                isset($request->image_mini) &&
                isset($request->image_full)
            ) {
                if (
                    $request->image_type &&
                    $request->image_mini &&
                    $request->image_full
                ) {
                    $carsJpa->image_type = $request->image_type;
                    $carsJpa->image_mini = base64_decode($request->image_mini);
                    $carsJpa->image_full = base64_decode($request->image_full);
                } else {
                    $carsJpa->image_type = null;
                    $carsJpa->image_mini = null;
                    $carsJpa->image_full = null;
                }
            }

            if (isset($request->description)) {
                $carsJpa->description = $request->description;
            }

            $carsJpa->creation_date = gTrace::getDate('mysql');
            $carsJpa->_creation_user = $userid;
            $carsJpa->update_date = gTrace::getDate('mysql');
            $carsJpa->_update_user = $userid;
            $carsJpa->status = "1";
            $carsJpa->save();

            $response->setStatus(200);
            $response->setMessage('La movilidad se a agregado correctamente');
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

    public function search(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'brands', 'read')) {
                throw new Exception('No tienes permisos para listar marcas');
            }

            $peopleJpa = Brand::select([
                'id',
                'correlative',
                'brand',
                'relative_id',
            ])->whereNotNull('status')
                ->WhereRaw("brand LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orWhereRaw("id LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orderBy('brand', 'asc')
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

            if (!gValidate::check($role->permissions, $branch, 'brands', 'read')) {
                throw new Exception('No tienes permisos para listar las marcas  de ' . $branch);
            }

            $query = Brand::select([
                'id',
                'correlative',
                'brand',
                'description',
                'relative_id',
                'creation_date',
                '_creation_user',
                'update_date',
                '_update_user',
                'status',
            ])
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'correlative' || $column == '*') {
                    $q->where('correlative', $type, $value);
                }
                if ($column == 'brand' || $column == '*') {
                    $q->where('brand', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $brandsJpa = $query->select('id',
                'correlative',
                'brand',
                'description',
                'relative_id',
                'creation_date',
                '_creation_user',
                'update_date',
                '_update_user',
                'status')
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Brand::count());
            $response->setData($brandsJpa->toArray());
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

            $userJpa = Brand::select([
                "brands.image_$size as image_content",
                'brands.image_type',

            ])
                ->where('relative_id', $relative_id)
                ->first();

            if (!$userJpa) {
                throw new Exception('No se encontraron datos');
            }
            if (!$userJpa->image_content) {
                throw new Exception('No existe imagen');
            }
            $content = $userJpa->image_content;
            $type = $userJpa->image_type;
            $response->setStatus(200);
        } catch (\Throwable$th) {
            $ruta = '../storage/images/brands-default.jpg';
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
            if (!gValidate::check($role->permissions, $branch, 'brands', 'update')) {
                throw new Exception('No tienes permisos para actualizar marcas');
            }

            $brandJpa = Brand::select(['id'])-> find($request->id);
            if (!$brandJpa) {
                throw new Exception("No se puede actualizar este registro");
            }

            if (isset($request->brand)) {
                $verifyCatJpa = Brand::select(['id', 'brand'])
                    ->where('brand', $request->brand)
                    ->where('id', '!=', $request->id)
                    ->first();
                if ($verifyCatJpa) {
                    throw new Exception("Elija otro nombre para esta marca");
                }
                $brandJpa->brand = $request->brand;
            }

            if (isset($request->correlative)) {
                $verifyCatJpa = Brand::select(['id', 'correlative'])
                    ->where('correlative', $request->correlative)
                    ->where('id', '!=', $request->id)
                    ->first();
                if ($verifyCatJpa) {
                    throw new Exception("Elija otro correlativo para esta marca");
                }
                $brandJpa->correlative = $request->correlative;
            }

            if (
                isset($request->image_type) &&
                isset($request->image_mini) &&
                isset($request->image_full)
            ) {
                if (
                    $request->image_type &&
                    $request->image_mini &&
                    $request->image_full
                ) {
                    $brandJpa->image_type = $request->image_type;
                    $brandJpa->image_mini = base64_decode($request->image_mini);
                    $brandJpa->image_full = base64_decode($request->image_full);
                } else {
                    $brandJpa->image_type = null;
                    $brandJpa->image_mini = null;
                    $brandJpa->image_full = null;
                }
            }

            if (isset($request->description)) {
                $brandJpa->description = $request->description;
            }

            if (gValidate::check($role->permissions, $branch, 'brands', 'change_status')) {
                if (isset($request->status)) {
                    $brandJpa->status = $request->status;
                }
            }

            $brandJpa->update_date = gTrace::getDate('mysql');
            $brandJpa->_update_user = $userid;

            $brandJpa->save();

            $response->setStatus(200);
            $response->setMessage('La categoria ha sido actualizado correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'brands', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar marcas en ' . $branch);
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $brandJpa = Brand::find($request->id);
            if (!$brandJpa) {
                throw new Exception('La categoria que deseas eliminar no existe');
            }

            $brandJpa->update_date = gTrace::getDate('mysql');
            $brandJpa->_update_user = $userid;
            $brandJpa->status = null;
            $brandJpa->save();

            $response->setStatus(200);
            $response->setMessage('La marca a sido eliminada correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'brands', 'delete_restore')) {
                throw new Exception('No tienes permisos para restaurar marcas en ' . $branch);
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $categoriesJpa = Brand::find($request->id);
            if (!$categoriesJpa) {
                throw new Exception('La marca que deseas restaurar no existe');
            }

            $categoriesJpa->update_date = gTrace::getDate('mysql');
            $categoriesJpa->_update_user = $userid;
            $categoriesJpa->status = "1";
            $categoriesJpa->save();

            $response->setStatus(200);
            $response->setMessage('La marca a sido restaurada correctamente');
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
