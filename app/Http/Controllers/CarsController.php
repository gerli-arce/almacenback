<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\Cars;
use App\Models\Response;
use App\Models\ViewCars;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

            if (isset($request->color)) {
                $carsJpa->color = $request->color;
            }

            if (isset($request->num_chasis)) {
                $carsJpa->num_chasis = $request->num_chasis;
            }

            if (isset($request->year)) {
                $carsJpa->year = $request->year;
            }

            if (isset($request->soat)) {
                $carsJpa->soat = $request->soat;
            }

            if (isset($request->expiration_date_soat)) {
                $carsJpa->expiration_date_soat = $request->expiration_date_soat;
            }

            if (isset($request->_model)) {
                $carsJpa->_model = $request->_model;
            }

            if (isset($request->property_card)) {
                $carsJpa->property_card = $request->property_card;
            }

            if (isset($request->technical_review)) {
                $carsJpa->technical_review = $request->technical_review;
            }

            if (isset($request->license)) {
                $carsJpa->license = $request->license;
            }

            if (isset($request->_person)) {
                $carsJpa->_person = $request->_person;
            }

            if (isset($request->_branch)) {
                $carsJpa->_branch = $request->_branch;
            }

            if (
                isset($request->image_type) &&
                isset($request->image_mini) &&
                isset($request->image_full)
            ) {
                if ($request->image_type != 'none') {
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

            $verifyCarJpa = Cars::select([
                'id',
                'placa'
            ])->whereNotNull('status')
                ->WhereRaw("placa LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orWhereRaw("id LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orderBy('placa', 'asc')
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($verifyCarJpa->toArray());
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

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'brands', 'read')) {
                throw new Exception('No tienes permisos para listar las marcas  de ' . $branch);
            }

            $query = ViewCars::select('*')
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'placa' || $column == '*') {
                    $q->orWhere('placa', $type, $value);
                }
                if ($column == 'color' || $column == '*') {
                    $q->orWhere('color', $type, $value);
                }
                if ($column == 'num_chasis' || $column == '*') {
                    $q->orWhere('num_chasis', $type, $value);
                }
                if ($column == 'year' || $column == '*') {
                    $q->orWhere('year', $type, $value);
                }
                if ($column == 'soat' || $column == '*') {
                    $q->orWhere('soat', $type, $value);
                }
                if ($column == 'property_card' || $column == '*') {
                    $q->orWhere('property_card', $type, $value);
                }
                if ($column == 'model' || $column == '*') {
                    $q->orWhere('model__model', $type, $value);
                }
                if ($column == 'person__name' || $column == '*') {
                    $q->orWhere('person__name', $type, $value);
                }
                if ($column == 'person__lastname' || $column == '*') {
                    $q->orWhere('person__lastname', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $carsJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $cars = array();
            foreach ($carsJpa as $carJpa) {
                $car = gJSON::restore($carJpa->toArray(), '__');
                $cars[] = $car;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewCars::count());
            $response->setData($cars);
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

    public function image($id, $size)
    {
        $response = new Response();
        $content = null;
        $type = null;
        try {
            if ($size != 'full') {
                $size = 'mini';
            }
            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $userJpa = Cars::select([
                "cars.image_$size as image_content",
                'cars.image_type',

            ])
                ->where('id', $id)
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
        } catch (\Throwable $th) {
            $ruta = '../storage/images/car-default.png';
            $fp = fopen($ruta, 'r');
            $datos_image = fread($fp, filesize($ruta));
            $datos_image = addslashes($datos_image);
            fclose($fp);
            $content = stripslashes($datos_image);
            $type = 'image/png';
            $response->setStatus(200);
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

            $cardJpa = Cars::select('*')->find($request->id);
            if (!$cardJpa) {
                throw new Exception("No se puede actualizar este registro");
            }

            if (isset($request->placa)) {
                $verifyCarJpa = Cars::select(['id', 'placa'])
                    ->where('placa', $request->placa)
                    ->where('id', '!=', $request->id)
                    ->first();
                if ($verifyCarJpa) {
                    throw new Exception("la placa ya esta registrada");
                }
                $cardJpa->placa = $request->placa;
            }

            
            if (isset($request->color)) {
                $cardJpa->color = $request->color;
            }

            if (isset($request->num_chasis)) {
                $cardJpa->num_chasis = $request->num_chasis;
            }

            if (isset($request->year)) {
                $cardJpa->year = $request->year;
            }

            if (isset($request->soat)) {
                $cardJpa->soat = $request->soat;
            }

            if (isset($request->expiration_date_soat)) {
                $cardJpa->expiration_date_soat = $request->expiration_date_soat;
            }

            if (isset($request->_model)) {
                $cardJpa->_model = $request->_model;
            }

            if (isset($request->property_card)) {
                $cardJpa->property_card = $request->property_card;
            }

            if (isset($request->technical_review)) {
                $cardJpa->technical_review = $request->technical_review;
            }

            if (isset($request->license)) {
                $cardJpa->license = $request->license;
            }

            if (isset($request->_person)) {
                $cardJpa->_person = $request->_person;
            }

            if (isset($request->_branch)) {
                $cardJpa->_branch = $request->_branch;
            }


            if (
                isset($request->image_type) &&
                isset($request->image_mini) &&
                isset($request->image_full)
            ) {

                if ($request->image_type != 'none') {
                    if (
                        $request->image_type &&
                        $request->image_mini &&
                        $request->image_full
                    ) {
                        $cardJpa->image_type = $request->image_type;
                        $cardJpa->image_mini = base64_decode($request->image_mini);
                        $cardJpa->image_full = base64_decode($request->image_full);
                    } else {
                        $cardJpa->image_type = null;
                        $cardJpa->image_mini = null;
                        $cardJpa->image_full = null;
                    }
                } else {
                    $cardJpa->image_type = null;
                    $cardJpa->image_mini = null;
                    $cardJpa->image_full = null;
                }

            }

            if (isset($request->description)) {
                $cardJpa->description = $request->description;
            }

            $cardJpa->update_date = gTrace::getDate('mysql');
            $cardJpa->_update_user = $userid;

            $cardJpa->save();

            $response->setStatus(200);
            // $response->setData([$cardJpa]);
            $response->setMessage('La vehiculo ha sido actualizado correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage().'Ln:'.$th->getLine());
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

            $carJpa = Cars::find($request->id);
            if (!$carJpa) {
                throw new Exception('La vehiculo que deseas eliminar no existe');
            }

            $carJpa->update_date = gTrace::getDate('mysql');
            $carJpa->_update_user = $userid;
            $carJpa->status = null;
            $carJpa->save();

            $response->setStatus(200);
            $response->setMessage('La vehiculo a sido eliminada correctamente');
            $response->setData($role->toArray());
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

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'brands', 'delete_restore')) {
                throw new Exception('No tienes permisos para restaurar vehiculos en ' . $branch);
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $carsJpa = Cars::find($request->id);
            if (!$carsJpa) {
                throw new Exception('La vehículo que deseas restaurar no existe');
            }

            $carsJpa->update_date = gTrace::getDate('mysql');
            $carsJpa->_update_user = $userid;
            $carsJpa->status = "1";
            $carsJpa->save();

            $response->setStatus(200);
            $response->setMessage('La vehículo a sido restaurada correctamente');
            $response->setData($role->toArray());
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
