<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\Response;
use App\Models\ReviewTechnicalByCar;
use App\Models\ViewChangesCar;
use App\Models\ViewReviewTechnicalByCar;
use App\Models\PhotographsByReviewTechnical;
use App\Models\ViewUsers;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewTechnicalController extends Controller
{

    public function generateReportByReviewTechnical(Request $request)
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
            $template = file_get_contents('../storage/templates/reportReviewTechnical.html');

            $ReviewTechnicalByCarJpa = ReviewTechnicalByCar::select([
                'id',
                '_car',
                'date',
                'components',
                'description',
                '_technical',
                'price_all',
                '_creation_user',
                'creation_date',
                '_update_user',
                'update_date',
                'status',
            ])->find($request->id);
            $ReviewTechnicalByCarJpa->components = gJSON::parse($ReviewTechnicalByCarJpa->components);

            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();
       
            $summary = '';

            $counter = 1;
            foreach ($ReviewTechnicalByCarJpa->components as $component) {

                $price_unity = isset($component['price_unity']) ? $component['price_unity'] : $component['price'];
                $mount = isset($component['mount']) ? $component['mount'] : 1;
                $price_total = isset($component['price_total']) ? $component['price_total'] : $component['price'];
                
                $summary .= "
                        <tr>
                            <td><center >{$counter}</center></td>
                            <td><center >{$component['component']}</center></td>
                            <td><center >S/{$price_unity}</center></td>
                            <td><center >S/{$mount}</center></td>
                            <td><center >S/{$price_total}</center></td>
                        </tr>
                    ";
                $counter++;
            }

            $PhotographsByReviewTechnicalJpa = PhotographsByReviewTechnical::select(['id', 'description', '_creation_user', 'creation_date', '_update_user', 'update_date'])
            ->where('_review',$request->id)->whereNotNUll('status')
            ->orderBy('id', 'desc')
            ->get();

            $images= '';
            $count = 1;

            foreach($PhotographsByReviewTechnicalJpa as $image){

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
                        <img src='http://almacen.fastnetperu.com.pe/api/review_technicalimg/{$image->id}/full' alt='-' 
                       class='evidences'
                    </center>
                </div>
                ";
                $count +=1;
            }


            $template = str_replace(
                [
                    '{id}',
                    '{id_img}',
                    '{placa}',
                    '{color}',
                    '{property_card}',
                    '{technical}',
                    '{date}',
                    '{risponsible}',
                    '{description}',
                    '{total}',
                    '{summary}',
                    '{images}'
                ],
                [
                    $request->id,
                    $request->id,
                    $request->car['placa'],
                    $request->car['color'],
                    $request->car['property_card'],
                    $request->technical['name'] . ' ' . $request->technical['lastname'],
                    $request->date,
                    $user->person__name . ' ' . $user->person__lastname,
                    $request->description,
                    $request->price_all,
                    $summary,
                    $images
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

            $reviewTechnicalByCarJpa = new ReviewTechnicalByCar();
            $reviewTechnicalByCarJpa->_car = $request->_car;
            $reviewTechnicalByCarJpa->date = $request->date;
            $reviewTechnicalByCarJpa->components = json_encode($request->components); // Convert components to JSON
            $reviewTechnicalByCarJpa->description = $request->description;
            $reviewTechnicalByCarJpa->_technical = $request->_technical;
            $reviewTechnicalByCarJpa->price_all = $request->price_all;

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
                    $reviewTechnicalByCarJpa->image_type = $request->image_type;
                    $reviewTechnicalByCarJpa->image_mini = base64_decode($request->image_mini);
                    $reviewTechnicalByCarJpa->image_full = base64_decode($request->image_full);
                } else {
                    $reviewTechnicalByCarJpa->image_type = null;
                    $reviewTechnicalByCarJpa->image_mini = null;
                    $reviewTechnicalByCarJpa->image_full = null;
                }
            }

            $reviewTechnicalByCarJpa->creation_date = gTrace::getDate('mysql');
            $reviewTechnicalByCarJpa->_creation_user = $userid;
            $reviewTechnicalByCarJpa->update_date = gTrace::getDate('mysql');
            $reviewTechnicalByCarJpa->_update_user = $userid;
            $reviewTechnicalByCarJpa->status = "1";
            $reviewTechnicalByCarJpa->save();

            $reviewData = [
                'id' => $reviewTechnicalByCarJpa->id,
                '_car' => $reviewTechnicalByCarJpa->_car,
                'date' => $reviewTechnicalByCarJpa->date,
                'components' => json_decode($reviewTechnicalByCarJpa->components),
                'description' => $reviewTechnicalByCarJpa->description,
                '_technical' => $reviewTechnicalByCarJpa->_technical,
                'price_all' => $reviewTechnicalByCarJpa->price_all,
                'creation_date' => $reviewTechnicalByCarJpa->creation_date,
                '_creation_user' => $reviewTechnicalByCarJpa->_creation_user,
                'update_date' => $reviewTechnicalByCarJpa->update_date,
                '_update_user' => $reviewTechnicalByCarJpa->_update_user,
                'status' => $reviewTechnicalByCarJpa->status,
            ];

            $response->setStatus(200);
            $response->setMessage('Revisión técnica creada correctamente');
            $response->setData($reviewData);
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

            $reviewJpa = ReviewTechnicalByCar::select([
                "review_technical_by_car.image_$size as image_content",
                'review_technical_by_car.image_type',
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

            $query = ViewReviewTechnicalByCar::select('*')
                ->orderBy($request->order['column'], $request->order['dir']);

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
            $reviewCarJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $reviews_technicals = array();
            foreach ($reviewCarJpa as $reviewJpa) {
                $review = gJSON::restore($reviewJpa->toArray(), '__');
                $reviews_technicals[] = $review
                ;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewChangesCar::where('_car', $request->_car)->count());
            $response->setData($reviews_technicals);
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

            $reviewTechnicalByCarJpa = ReviewTechnicalByCar::find($request->id);
            if (!$reviewTechnicalByCarJpa) {
                throw new Exception('No se encontró la revisión técnica');
            }

            $reviewTechnicalByCarJpa->date = $request->date;
            $reviewTechnicalByCarJpa->components = json_encode($request->components); // Convert components to JSON
            $reviewTechnicalByCarJpa->description = $request->description;
            $reviewTechnicalByCarJpa->_technical = $request->_technical;
            $reviewTechnicalByCarJpa->price_all = $request->price_all;

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
                    $reviewTechnicalByCarJpa->image_type = $request->image_type;
                    $reviewTechnicalByCarJpa->image_mini = base64_decode($request->image_mini);
                    $reviewTechnicalByCarJpa->image_full = base64_decode($request->image_full);
                } else {
                    $reviewTechnicalByCarJpa->image_type = null;
                    $reviewTechnicalByCarJpa->image_mini = null;
                    $reviewTechnicalByCarJpa->image_full = null;
                }
            }

            $reviewTechnicalByCarJpa->update_date = gTrace::getDate('mysql');
            $reviewTechnicalByCarJpa->_update_user = $userid;
            $reviewTechnicalByCarJpa->save();

            $reviewData = [
                'id' => $reviewTechnicalByCarJpa->id,
                '_car' => $reviewTechnicalByCarJpa->_car,
                'date' => $reviewTechnicalByCarJpa->date,
                'components' => json_decode($reviewTechnicalByCarJpa->components),
                'description' => $reviewTechnicalByCarJpa->description,
                '_technical' => $reviewTechnicalByCarJpa->_technical,
                'price_all' => $reviewTechnicalByCarJpa->price_all,
                'creation_date' => $reviewTechnicalByCarJpa->creation_date,
                '_creation_user' => $reviewTechnicalByCarJpa->_creation_user,
                'update_date' => $reviewTechnicalByCarJpa->update_date,
                '_update_user' => $reviewTechnicalByCarJpa->_update_user,
                'status' => $reviewTechnicalByCarJpa->status,
            ];

            $response->setStatus(200);
            $response->setMessage('Revisión técnica actualizada correctamente');
            $response->setData($reviewData);
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

    public function delete(Request $request)
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

            $reviewTechnicalByCarJpa = ReviewTechnicalByCar::find($request->id);
           
            $reviewTechnicalByCarJpa->update_date = gTrace::getDate('mysql');
            $reviewTechnicalByCarJpa->_update_user = $userid;
            $reviewTechnicalByCarJpa->status = null;
            $reviewTechnicalByCarJpa->save();
        
            $response->setStatus(200);
            $response->setMessage('Revisión técnica eliminada correctamente');
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
                !isset($request->_review)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $PhotographsByReviewTechnicalJpa = new PhotographsByReviewTechnical();
            $PhotographsByReviewTechnicalJpa->_review = $request->_review;
            if(isset($request->description)){
                $PhotographsByReviewTechnicalJpa->description = $request->description;
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
                    $PhotographsByReviewTechnicalJpa->image_type = $request->image_type;
                    $PhotographsByReviewTechnicalJpa->image_mini = base64_decode($request->image_mini);
                    $PhotographsByReviewTechnicalJpa->image_full = base64_decode($request->image_full);
                } else {
                    throw new Exception("Una imagen debe ser cargada.");
                }
            } else {
                throw new Exception("Una imagen debe ser cargada.");
            }

            $PhotographsByReviewTechnicalJpa->_creation_user = $userid;
            $PhotographsByReviewTechnicalJpa->creation_date = gTrace::getDate('mysql');
            $PhotographsByReviewTechnicalJpa->_update_user = $userid;
            $PhotographsByReviewTechnicalJpa->update_date = gTrace::getDate('mysql');
            $PhotographsByReviewTechnicalJpa->status = "1";
            $PhotographsByReviewTechnicalJpa->save();

            $response->setStatus(200);
            $response->setMessage('');
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
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $PhotographsByReviewTechnicalJpa = PhotographsByReviewTechnical::find($request->id);
            $PhotographsByReviewTechnicalJpa->description = $request->description;

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
                    $PhotographsByReviewTechnicalJpa->image_type = $request->image_type;
                    $PhotographsByReviewTechnicalJpa->image_mini = base64_decode($request->image_mini);
                    $PhotographsByReviewTechnicalJpa->image_full = base64_decode($request->image_full);
                } 
            } 
           
            $PhotographsByReviewTechnicalJpa->_update_user = $userid;
            $PhotographsByReviewTechnicalJpa->update_date = gTrace::getDate('mysql');
            $PhotographsByReviewTechnicalJpa->save();

            $response->setStatus(200);
            $response->setMessage('Imagen guardada correctamente');
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

            $PhotographsByReviewTechnicalJpa = PhotographsByReviewTechnical::select(['id', 'description', '_creation_user', 'creation_date', '_update_user', 'update_date'])
            ->where('_review', $id)->whereNotNUll('status')
            ->orderBy('id', 'desc')
            ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta.');
            $response->setData($PhotographsByReviewTechnicalJpa->toArray());
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

            $modelJpa = PhotographsByReviewTechnical::select([
                "photographs_by_review_technical.image_$size as image_content",
                'photographs_by_review_technical.image_type',

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

            $PhotographsByReviewTechnicalJpa = PhotographsByReviewTechnical::find($id);
            $PhotographsByReviewTechnicalJpa->_update_user = $userid;
            $PhotographsByReviewTechnicalJpa->update_date = gTrace::getDate('mysql');
            $PhotographsByReviewTechnicalJpa->status = null;
            $PhotographsByReviewTechnicalJpa->save();

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


}
