<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Response;
use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\ViewUsers;
use App\Models\ViewCars;
use App\Models\User;
use App\Models\Branch;
use App\Models\ChargeGasoline;
use App\Models\ViewChargeGasolineByCar;
use App\Models\PhotographsByChargeGasoline;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Support\Facades\DB;

class ChargeGasolineController extends Controller
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
                throw new Exception("No tienes permisos para realizar esta acción");
            }

            if (
                !isset($request->_technical) ||
                !isset($request->_car) ||
                !isset($request->price_all) ||
                !isset($request->gasoline_type) ||
                !isset($request->date)
            ) {
                throw new Exception("Error en los datos de entrada");
            }

            $ChargeGasolineJpa = new ChargeGasoline();
            $ChargeGasolineJpa->_technical = $request->_technical;
            $ChargeGasolineJpa->_car = $request->_car;
            $ChargeGasolineJpa->date = $request->date;
            $ChargeGasolineJpa->gasoline_type = $request->gasoline_type;
            if (isset($request->description)) {
                $ChargeGasolineJpa->description = $request->description;
            }
            $ChargeGasolineJpa->price_all = $request->price_all;
            $ChargeGasolineJpa->igv = $request->igv;
            $ChargeGasolineJpa->price_engraved = $request->price_engraved;

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
                    $ChargeGasolineJpa->image_type = $request->image_type;
                    $ChargeGasolineJpa->image_mini = base64_decode($request->image_mini);
                    $ChargeGasolineJpa->image_full = base64_decode($request->image_full);
                    $ChargeGasolineJpa->date_image = gTrace::getDate('mysql');
                } else {
                    $ChargeGasolineJpa->image_type = null;
                    $ChargeGasolineJpa->image_mini = null;
                    $ChargeGasolineJpa->image_full = null;
                }
            }

            $ChargeGasolineJpa->creation_date = gTrace::getDate('mysql');
            $ChargeGasolineJpa->_creation_user = $userid;
            $ChargeGasolineJpa->update_date = gTrace::getDate('mysql');
            $ChargeGasolineJpa->_update_user = $userid;
            $ChargeGasolineJpa->status = "1";
            $ChargeGasolineJpa->save();

            $res = [
                'id' => $ChargeGasolineJpa->id,
                '_technical' => $ChargeGasolineJpa->_technical,
                '_car' => $ChargeGasolineJpa->_car,
                'date' => $ChargeGasolineJpa->date,
                'gasoline_type' => $ChargeGasolineJpa->gasoline_type,
                'description' => $ChargeGasolineJpa->description,
                'price_all' => $ChargeGasolineJpa->price_all,
                'creation_date' => $ChargeGasolineJpa->creation_date,
                '_creation_user' => $ChargeGasolineJpa->_creation_user,
                'update_date' => $ChargeGasolineJpa->update_date,
                '_update_user' => $ChargeGasolineJpa->_update_user,
                'status' => $ChargeGasolineJpa->status,
            ];

            $response->setStatus(200);
            $response->setMessage('Carga de gasolina creada correctamente');
            $response->setData($res);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'LN: ' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function getById(Request $request, $id)
    {
        $response = new Response();
        try {
    
            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
    
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception('No tienes permisos para ver la revisión técnica');
            }
    
            $ChargeCarJpa = ViewChargeGasolineByCar::select('*')
                ->where('id', $id)
                ->first();
    
            if (!$ChargeCarJpa) {
                throw new Exception('Carga de gasolina no encontrada');
            }
    
            $review = gJSON::restore($ChargeCarJpa->toArray(), '__');
    
            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($review);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'LN: ' . $th->getLine() . 'FL: ' . $th->getFile());
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

            if (!gValidate::check($role->permissions, $branch, 'cars', 'update')) {
                throw new Exception("No tienes permisos para realizar esta acción");
            }

            $ChargeGasolineJpa = ChargeGasoline::find($request->id);
            if (!$ChargeGasolineJpa) {
                throw new Exception('No se encontró la revisión técnica');
            }

            if (isset($request->_technical)) {
                $ChargeGasolineJpa->_technical = $request->_technical;
            }

            if (isset($request->date)) {
                $ChargeGasolineJpa->date = $request->date;
            }

            if (isset($request->price_all)) {
                $ChargeGasolineJpa->price_all = $request->price_all;
            }

            if (isset($request->igv)) {
                $ChargeGasolineJpa->igv = $request->igv;
            }

            if (isset($request->price_engraved)) {
                $ChargeGasolineJpa->price_engraved = $request->price_engraved;
            }

            if (isset($request->gasoline_type)) {
                $ChargeGasolineJpa->gasoline_type = $request->gasoline_type;
            }

            $ChargeGasolineJpa->description = $request->description;

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
                    $ChargeGasolineJpa->image_type = $request->image_type;
                    $ChargeGasolineJpa->image_mini = base64_decode($request->image_mini);
                    $ChargeGasolineJpa->image_full = base64_decode($request->image_full);
                    $ChargeGasolineJpa->date_image = gTrace::getDate('mysql');
                } else {
                    $ChargeGasolineJpa->image_type = null;
                    $ChargeGasolineJpa->image_mini = null;
                    $ChargeGasolineJpa->image_full = null;
                }
            }

            $ChargeGasolineJpa->update_date = gTrace::getDate('mysql');
            $ChargeGasolineJpa->_update_user = $userid;
            $ChargeGasolineJpa->save();

            $res = [
                'id' => $ChargeGasolineJpa->id,
                '_technical' => $ChargeGasolineJpa->_technical,
                '_car' => $ChargeGasolineJpa->_car,
                'date' => $ChargeGasolineJpa->date,
                'gasoline_type' => $ChargeGasolineJpa->gasoline_type,
                'description' => $ChargeGasolineJpa->description,
                'price_all' => $ChargeGasolineJpa->price_all,
                'creation_date' => $ChargeGasolineJpa->creation_date,
                '_creation_user' => $ChargeGasolineJpa->_creation_user,
                'update_date' => $ChargeGasolineJpa->update_date,
                '_update_user' => $ChargeGasolineJpa->_update_user,
                'status' => $ChargeGasolineJpa->status,
            ];

            $response->setStatus(200);
            $response->setMessage('Revisión técnica actualizada correctamente');
            $response->setData($res);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'LN: ' . $th->getLine());
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

            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception('No tienes permisos para listar las revisiones técnicas');
            }

            $query = ViewChargeGasolineByCar::select('*')
                ->orderBy($request->order['column'], $request->order['dir']);

            if($request->is_bill){
                $query->whereNotNull('image_type');
            }

            if($request->not_bill){
                $query->where('image_type', null);
            }

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'id' || $column == '*') {
                    $q->orWhere('id', $type, $value);
                }
                if ($column == 'date' || $column == '*') {
                    $q->orWhere('date', $type, $value);
                }
                if ($column == 'technical__name' || $column == '*') {
                    $q->orWhere('technical__name', $type, $value);
                }
                if ($column == 'technical__lastname' || $column == '*') {
                    $q->orWhere('technical__lastname', $type, $value);
                }
                if ($column == 'date' || $column == '*') {
                    $q->orWhere('date', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            })->where('_car', $request->_car);

            $iTotalDisplayRecords = $query->count();
            $ChargesCarJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $charges_gasoline = array();
            foreach ($ChargesCarJpa as $ChargecarJpa) {
                $review = gJSON::restore($ChargecarJpa->toArray(), '__');
                $charges_gasoline[] = $review;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewChargeGasolineByCar::where('_car', $request->_car)->count());
            $response->setData($charges_gasoline);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'LN: ' . $th->getLine() . 'FL: ' . $th->getFile());
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

            $reviewJpa = ChargeGasoline::select([
                "charge_gasoline.image_$size as image_content",
                'charge_gasoline.image_type',
            ])
                ->where('id', $id)
                ->first();

            if (!$reviewJpa) {
                throw new Exception('No se encontraron datos');
            }

            if (!$reviewJpa->image_content) {
                throw new Exception('No existe imagen');
            }

            $content = $reviewJpa->image_content;
            $type = $reviewJpa->image_type;
            $response->setStatus(200);
        } catch (\Throwable $th) {
            $ruta = '../storage/images/factura-default.png';
            $fp = fopen($ruta, 'r');
            $datos_image = fread($fp, filesize($ruta));
            $datos_image = addslashes($datos_image);
            fclose($fp);
            $content = stripslashes($datos_image);
            $type = 'image/jpeg';
            $response->setStatus(200);
        } finally {
            return response(
                $content,
                $response->getStatus()
            )->header('Content-Type', $type);
        }
    }

    public function delete(Request $request, $id)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception("No tienes permisos para realizar esta acción");
            }
            $ChargeGasolineJpa = ChargeGasoline::find($id);
            if (!$ChargeGasolineJpa) {
                throw new Exception('No se encontró la carga de gasolina');
            }
            $ChargeGasolineJpa->status = null;
            $ChargeGasolineJpa->update_date = gTrace::getDate('mysql');
            $ChargeGasolineJpa->_update_user = $userid;
            $ChargeGasolineJpa->save();
            $response->setStatus(200);
            $response->setMessage('Carga de gasolina eliminada correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'LN: ' . $th->getLine());
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
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception("No tienes permisos para realizar esta acción");
            }

            $ChargeGasolineJpa = ChargeGasoline::find($request->id);
            if (!$ChargeGasolineJpa) {
                throw new Exception('No se encontró la carga de gasolina');
            }

            $ChargeGasolineJpa->status = 1;
            $ChargeGasolineJpa->update_date = gTrace::getDate('mysql');
            $ChargeGasolineJpa->_update_user = $userid;
            $ChargeGasolineJpa->save();

            $response->setStatus(200);
            $response->setMessage('Carga de gasolina restaurada correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'LN: ' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }


    public function images($id, $size)
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

            $modelJpa = PhotographsByChargeGasoline::select([
                "photographs_by_charge_gasoline.image_$size as image_content",
                'photographs_by_charge_gasoline.image_type',

            ])
                ->where('id', $id)
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
        } catch (\Throwable $th) {
            $ruta = '../storage/images/img-default.jpg';
            $fp = fopen($ruta, 'r');
            $datos_image = fread($fp, filesize($ruta));
            $datos_image = addslashes($datos_image);
            fclose($fp);
            $content = stripslashes($datos_image);
            $type = 'image/jpeg';
            $response->setStatus(200);
        } finally {
            return response(
                $content,
                $response->getStatus()
            )->header('Content-Type', $type);
        }
    }

    public function setImage(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($request->_charge_gasoline)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $imagesByReviewJpa = new PhotographsByChargeGasoline();
            $imagesByReviewJpa->_charge_gasoline = $request->_charge_gasoline;
            $imagesByReviewJpa->description = $request->description;

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
                    $imagesByReviewJpa->image_type = $request->image_type;
                    $imagesByReviewJpa->image_mini = base64_decode($request->image_mini);
                    $imagesByReviewJpa->image_full = base64_decode($request->image_full);
                } else {
                    throw new Exception("Una imagen debe ser cargada.");
                }
            } else {
                throw new Exception("Una imagen debe ser cargada.");
            }

            $imagesByReviewJpa->_creation_user = $userid;
            $imagesByReviewJpa->creation_date = gTrace::getDate('mysql');
            $imagesByReviewJpa->status = "1";
            $imagesByReviewJpa->save();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
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

    public function getImages(Request $request, $id)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $ImagesByReview = PhotographsByChargeGasoline::select(['id', 'description', '_creation_user', 'creation_date'])
            ->where('_charge_gasoline', $id)->whereNotNUll('status')
            ->orderBy('id', 'desc')
            ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta.');
            $response->setData($ImagesByReview->toArray());
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

    public function updateImage(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'read', 'update')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $ImagesByReviewJpa = PhotographsByChargeGasoline::find($request->id);
            $ImagesByReviewJpa->description = $request->description;

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
                    $ImagesByReviewJpa->image_type = $request->image_type;
                    $ImagesByReviewJpa->image_mini = base64_decode($request->image_mini);
                    $ImagesByReviewJpa->image_full = base64_decode($request->image_full);
                } 
            } 
           
            $ImagesByReviewJpa->save();

            $response->setStatus(200);
            $response->setMessage('Imagen actualizada correctamente');
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

    public function deleteImage(Request $request, $id){
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $ImagesByReviewJpa = PhotographsByChargeGasoline::find($id);
            $ImagesByReviewJpa->status = null;
            $ImagesByReviewJpa->save();

            $response->setStatus(200);
            $response->setMessage('Imagen eliminada correctamente');
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

    public function generateReportByCar(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception('No tienes permisos');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportChargeGasoline.html');

            $ViewChargeGasolineByCarJpa = ViewChargeGasolineByCar::find($request->id);

            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();

            $ImagesByReview = PhotographsByChargeGasoline::select(['id', 'description', '_creation_user', 'creation_date'])
            ->where('_charge_gasoline', $request->id)->whereNotNUll('status')
            ->orderBy('id', 'desc')
            ->get();

            $images= '';
            $count = 1;

            foreach($ImagesByReview as $image){

                $userCreation = User::select([
                    'users.id as id',
                    'users.username as username',
                ])
                    ->where('users.id', $image->_creation_user)->first();

                $images .= "
                <div style='page-break-before: always;'>
                    <p><strong>{$count}) {$image->description}</strong></p>
                    <p style='margin-left:18px'>Fecha: {$image->creation_date}</p>
                    <p style='margin-left:18px'>Usuario: {$userCreation->username}</p>
                    <center>
                        <img src='http://almacen.fastnetperu.com.pe/api/charge_gasolineimgs/{$image->id}/full' alt='-' 
                       class='evidences'
                    </center>
                </div>
                ";
                $count +=1;
            }

            $summary = '';

            $template = str_replace(
                [
                    '{id}',
                    '{placa}',
                    '{technical}',
                    '{date}',
                    '{gasoline}',
                    '{price_all}',
                    '{igv}',
                    '{price_engraved}',
                    '{description}',
                    '{date_image}',
                    '{summary}',
                    '{images}',
                ],
                [
                    $ViewChargeGasolineByCarJpa->id,
                    $request->car['placa'],
                    $ViewChargeGasolineByCarJpa->technical__name . ' ' . $ViewChargeGasolineByCarJpa->technical__lastname,
                    $ViewChargeGasolineByCarJpa->date,
                    $ViewChargeGasolineByCarJpa->gasoline_type,
                    $ViewChargeGasolineByCarJpa->price_all,
                    $ViewChargeGasolineByCarJpa->igv,
                    $ViewChargeGasolineByCarJpa->price_engraved,
                    $ViewChargeGasolineByCarJpa->description,
                    $ViewChargeGasolineByCarJpa->date_image,
                    $summary,
                    $images,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('Instlación.pdf');
        } catch (\Throwable $th) {
            $response = new Response();
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function generateReportdetailsByCar(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception('No tienes permisos');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportChargeGasolineDetails.html');



            $query = ViewChargeGasolineByCar::where('_car', $request->car['id'])
                ->orderBy('date', 'desc');
            
                if ($request->is_bills){
                    $query->whereNotNull('image_type');
                }

                if ($request->not_bills){
                    $query->where('image_type', null);
                }
                


            if (isset($request->date_start) && isset($request->date_end)) {
                $dateStart = date('Y-m-d', strtotime($request->date_start));
                $dateEnd = date('Y-m-d', strtotime($request->date_end));
                $query->where('date', '>=', $dateStart)->where('date', '<=', $dateEnd);
            }

            $ViewChargeGasolineByCarJpa = $query->get();

            $summary = '';


            $changesGasolineJpa = array();
            $price_all = 0;
            $num_charges = 0;
            foreach ($ViewChargeGasolineByCarJpa as $ChargegasolineJpa) {
                $chargeGasoline = gJSON::restore($ChargegasolineJpa->toArray(), '__');
                $price_all += $chargeGasoline['price_all'];
                $num_charges++;
                if ($request->add_bills) {
                    $summary .= "
                        <div style='page-break-before: always;'>
                            <table>
                                <thead>
                                    <tr>
                                        <td colspan='2'>
                                            CARGA DE {$chargeGasoline['gasoline_type']}
                                        </td>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class='n'>ID</td>
                                        <td>{$chargeGasoline['id']}</td>
                                    </tr>
                                    <tr>
                                        <td class='n'>TÉCNICO</td>
                                        <td>{$chargeGasoline['technical']['name']} {$chargeGasoline['technical']['lastname']}</td>
                                    </tr>
                                    <tr>
                                        <td class='n'>FECHA</td>
                                        <td>{$chargeGasoline['date']}</td>
                                    </tr>
                                    <tr>
                                        <td class='n'>TIPO DE COMBUSTIBLE</td>
                                        <td>{$chargeGasoline['gasoline_type']}</td>
                                    </tr>
                                    <tr>
                                        <td class='n'>MONTO TOTAL</td>
                                        <td>S/{$chargeGasoline['price_all']}</td>
                                    </tr>
                                    <tr>
                                        <td class='n'>EJECUTIVO</td>
                                        <td>{$chargeGasoline['person_creation']['name']} {$chargeGasoline['person_creation']['lastname']}</td>
                                    </tr>
                                    <tr>
                                        <td class='n'>DESCRIPCIÓN</td>
                                        <td>{$chargeGasoline['description']}</td>
                                    </tr>
                                    <tr>
                                        <td class='n' colspan='2'>
                                            <center>FACTURA</center>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan='2'>
                                            <center>
                                                <img src='https://almacen.fastnetperu.com.pe/api/charge_gasolineimg/{$chargeGasoline['id']}/full' class='img_bill'>
                                            </center>
                                        </td>
                                    </tr>
                                </tbody>
                               
                            </table>
                        <div>
                    ";
                }

                $changesGasolineJpa[] = $chargeGasoline;
            }

            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();

            $template = str_replace(
                [
                    '{placa}',
                    '{num_charges}',
                    '{date_start}',
                    '{date_end}',
                    '{price_all}',
                    '{summary}',
                ],
                [
                    $request->car['placa'],
                    $num_charges,
                    $request->date_start,
                    $request->date_end,
                    $price_all,
                    $summary,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('REPORTE DE CARGAS DE COMBUSTIBLE.pdf');
        } catch (\Throwable $th) {
            $response = new Response();
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function generateReportGeneral(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception('No tienes permisos');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/charge_gasoline/reportChargeGasolineGeneral.html');

            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();

            $HOST = 'https://almacen.fastnetperu.com.pe/api';

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $ViewCarsJpa = ViewCars::where('branch__id', $branch_->id)->orderBy('id', 'desc')->whereNotNull('status')->get();

            $viewcarsJpa = array();

            $price_all = 0;
            $mount_cars = 0;
            $charges_all = 0;
            $num_charges_gasoline = 0;
            $num_charges_petroleo = 0;
            $num_charges_glp = 0;

            $cars = '';
            foreach ($ViewCarsJpa as $ViewCarJpa) {
                $viewCar = gJSON::restore($ViewCarJpa->toArray(), '__');
                $query = ViewChargeGasolineByCar::where('_car', $viewCar['id'])
                    ->orderBy('date', 'desc');

                if (isset($request->date_start) && isset($request->date_end)) {
                    $dateStart = date('Y-m-d', strtotime($request->date_start));
                    $dateEnd = date('Y-m-d', strtotime($request->date_end));
                    $query->where('date', '>=', $dateStart)->where('date', '<=', $dateEnd);
                }

                $ViewChargeGasolineByCarJpa = $query->get();
                $mount_cars++;
                $chanrgesGasoline = array();

                $mount_charges_by_car = 0;
                $price_all_by_car = 0;
                $charges_gasoline = '';
                foreach ($ViewChargeGasolineByCarJpa as $ChangeGasolineJpa) {
                    $chargeGasoline = gJSON::restore($ChangeGasolineJpa->toArray(), '__');
                    $price_all += $chargeGasoline['price_all'];
                    $charges_all++;
                    $mount_charges_by_car++;
                    $price_all_by_car += $chargeGasoline['price_all'];
                    $chanrgesGasoline[] = $chargeGasoline;

                    if($chargeGasoline['gasoline_type']== "GASOLINA"){
                            $num_charges_gasoline++;
                    }else if($chargeGasoline['gasoline_type']== "PETROLEO"){
                            $num_charges_petroleo++;
                    }else if($chargeGasoline['gasoline_type']== "GLP"){
                            $num_charges_glp++;
                    }

                    $charges_gasoline .= "
                            <tr>
                                <td colspan='2'><center>----------------------------------------</center></td>
                            </tr>
                            <tr>
                                <td colspan='2' class='s'><center>{$chargeGasoline['gasoline_type']}</center></td>
                            </tr>
                            <tr>
                                <td class='sn'>TÉCNICO</td>
                                <td>{$chargeGasoline['technical']['name']} {$chargeGasoline['technical']['lastname']}</td>
                            </tr>
                            <tr>
                                <td class='sn'>FECHA</td>
                                <td>{$chargeGasoline['date']}</td>
                            </tr>
                            <tr>
                                <td class='sn'>MONTO TOTAL</td>
                                <td>S/{$chargeGasoline['price_all']}</td>
                            </tr>
                            <tr>
                                <td class='sn'>EJECUTIVO</td>
                                <td>{$chargeGasoline['person_creation']['name']} {$chargeGasoline['person_creation']['lastname']}</td>
                            </tr>
                            <tr>
                                <td class='sn'>DESCRIPCIÓN</td>
                                <td>{$chargeGasoline['description']}</td>
                            </tr>
                            <tr>
                                <td class='sn' colspan='2'>
                                    <center>FACTURA</center>
                                </td>
                            </tr>
                            <tr>
                                <td class='sn' colspan='2'>
                                    <center>
                                        <img src='{$HOST}/charge_gasolineimg/{$chargeGasoline['id']}/full' class='img_bill'>
                                    </center>
                                </td>
                            </tr>
                    ";
                }

                $viewCar['charges'] = $chanrgesGasoline;

                $viewcarsJpa[] = $viewCar;

                if ($request->add_bills) {

                    $cars .= "
                <table>
                    <thead>
                        <tr>
                            <td colspan='2'>
                                <center>{$viewCar['placa']} - {$viewCar['color']}</center>
                            </td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class='n'>CARGAS TOTALES</td>
                            <td><center>{$mount_charges_by_car}</center></td>
                        </tr>
                        <tr>
                            <td class='n'>MONTO TOTAL</td>
                            <td><center>S/{$price_all_by_car}</center></td>
                        </tr>
                        <tr>
                            <td colspan='2' class='n'><center>{$viewCar['model']['model']}</center></td>
                        </tr>
                        <tr>
                            <td colspan='2'>
                                <center>
                                    <img src='{$HOST}/carimg/{$viewCar['id']}/full' class='img_bill'>
                                </center>                           
                            </td>
                        </tr>
                        {$charges_gasoline}
                    </tbody>
                </table>
                ";
                } else {
                    $cars = '
                    <center><h3><i>NO SE MUESTRAN LOS DETALLES</i></h3></center>
                    ';
                }
            }

            $template = str_replace(
                [
                    '{branch}',
                    '{ejecutive}',
                    '{date_creation}',
                    '{num_cars}',
                    '{num_charges_gasoline}',
                    '{num_charges_petroleo}',
                    '{num_charges_glp}',
                    '{num_charges}',
                    '{date_start}',
                    '{date_end}',
                    '{price_all}',
                    '{cars}',
                ],
                [
                    $branch_->name,
                    $user->person__name.' '.$user->person__lastname,
                    gTrace::getDate('mysql'),
                    $mount_cars,
                    $num_charges_gasoline,
                    $num_charges_petroleo,
                    $num_charges_glp,
                    $charges_all,
                    $request->date_start,
                    $request->date_end,
                    $price_all,
                    $cars,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('REPORTE DE CARGAS DE COMBUSTIBLE.pdf');
        } catch (\Throwable $th) {
            $response = new Response();
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }
}
