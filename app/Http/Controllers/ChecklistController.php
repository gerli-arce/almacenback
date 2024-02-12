<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\CheckByReview;
use App\Models\CheckListCar;
use App\Models\ImagesByReview;
use App\Models\Response;
use App\Models\ReviewCar;
use App\Models\ViewCheckByReview;
use App\Models\ViewReviewCar;
use App\Models\ViewUsers;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChecklistController extends Controller
{

    public function generateReportByReview(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'installation_pending', 'read')) {
                throw new Exception('No tienes permisos para listar instalaciones creadas');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportChecklist.html');

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $viewCheckByReviewJpa = ViewCheckByReview::where('_review', $request->id)->get();

            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();

            $components = array();
            foreach ($viewCheckByReviewJpa as $componentsJpa) {
                $component = gJSON::restore($componentsJpa->toArray(), '__');
                $components[] = $component;
            }
            $sumary = '';
            $fomatComponents = array();
            foreach ($components as $component) {
                $partId = $component['check']['component']['part']['id'];
                $partName = $component['check']['component']['part']['part'];
                if (!isset($fomatComponents[$partId])) {
                    $fomatComponents[$partId] = array(
                        'id' => $partId,
                        'part' => $partName,
                        'component' => array(),
                    );
                }
                array_push($fomatComponents[$partId]['component'], array(
                    'id' => $component['check']['component']['id'],
                    'component' => $component['check']['component']['component'],
                    'present' => $component['check']['present'],
                    'optimed' => $component['check']['optimed'],
                    'description' => $component['check']['description'],
                ));
            }

            foreach ($fomatComponents as $part) {
                $sumary .= "
                <table>
                    <thead>
                        <tr><td colspan='5' style='text-align:center;'>{$part['part']}</td></tr>
                        <tr>
                            <td>#</td>
                            <td>Componente</td>
                            <td>Presente</td>
                            <td>Optima</td>
                            <td>Descripción</td>
                        </tr>
                    </thead>
                    <tbody>";
                $counter = 1;
                foreach ($part['component'] as $component) {
                    $present = $component['present'] == 1 ? 'SI' : 'NO';
                    $optimed = $component['optimed'] == 1 ? 'SI' : 'NO';

                    $presentColor = $component['present'] == 1 ? 'rgba(0, 255, 0, 0.5)' : 'rgba(255, 0, 0, 0.5)';
                    $optimedColor = $component['optimed'] == 1 ? 'rgba(0, 255, 0, 0.5)' : 'rgba(255, 0, 0, 0.5)';

                    $sumary .= "
                        <tr>
                            <td><center >{$counter}</center></td>
                            <td><center >{$component['component']}</center></td>
                            <td style='background-color: {$presentColor};'><center >{$present}</center></td>
                            <td style='background-color: {$optimedColor};'><center >{$optimed}</center></td>
                            <td><center >{$component['description']}</center></td>
                        </tr>
                    ";
                    $counter++;
                }
                $sumary .= "</tbody></table>";
            }
            $template = str_replace(
                [
                    '{tables}',
                    '{num_checklist}',
                    '{placa}',
                    '{color}',
                    '{property_card}',
                    '{technical}',
                    '{date}',
                    '{risponsible}',
                    '{description}',
                ],
                [
                    $sumary,
                    $request->id,
                    $request->car['placa'],
                    $request->car['color'],
                    $request->car['property_card'],
                    $request->driver['name'] . ' ' . $request->driver['lastname'],
                    $request->date,
                    $user->person__name . ' ' . $user->person__lastname,
                    $request->description,
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
            if (!gValidate::check($role->permissions, $branch, 'checklist', 'create')) {
                throw new Exception("No tienes permisos para agregar checklist ");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $ReviewCarJpa = new ReviewCar();
            $ReviewCarJpa->_responsible_check = $userid;
            $ReviewCarJpa->date = $request->date;
            $ReviewCarJpa->_car = $request->_car;
            $ReviewCarJpa->_driver = $request->_driver;
            $ReviewCarJpa->description = $request->description;
            $ReviewCarJpa->_creation_user = $userid;
            $ReviewCarJpa->creation_date = gTrace::getDate('mysql');
            $ReviewCarJpa->update_date = gTrace::getDate('mysql');
            $ReviewCarJpa->_update_user = $userid;
            $ReviewCarJpa->status = "1";
            $ReviewCarJpa->save();

            foreach ($request->data as $component) {
                $CheckListCarJpa = new CheckListCar();
                $CheckListCarJpa->_component = $component['id'];
                $CheckListCarJpa->present = $component['dat']['present'];
                $CheckListCarJpa->optimed = $component['dat']['optimed'];
                $CheckListCarJpa->description = $component['dat']['description'];
                $CheckListCarJpa->status = 1;
                $CheckListCarJpa->save();

                $CheckByReviewJpa = new CheckByReview();
                $CheckByReviewJpa->_check = $CheckListCarJpa->id;
                $CheckByReviewJpa->_review = $ReviewCarJpa->id;
                $CheckByReviewJpa->status = 1;
                $CheckByReviewJpa->save();
            }

            $response->setStatus(200);
            $response->setMessage('El checklist se ha creado correctamente');
            $response->setData($ReviewCarJpa->toArray());
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

            if (!gValidate::check($role->permissions, $branch, 'checklist', 'read')) {
                throw new Exception('No tienes permisos para listar los checklist  de ' . $branch);
            }

            $query = ViewReviewCar::select('*')
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
                if ($column == 'driver__name' || $column == '*') {
                    $q->orWhere('driver__name', $type, $value);
                }
                if ($column == 'driver__lastname' || $column == '*') {
                    $q->orWhere('driver__lastname', $type, $value);
                }
                if ($column == 'res_check__name' || $column == '*') {
                    $q->orWhere('res_check__name', $type, $value);
                }
                if ($column == 'res_check__lastname' || $column == '*') {
                    $q->orWhere('res_check__lastname', $type, $value);
                }
                if ($column == 'car__placa' || $column == '*') {
                    $q->orWhere('car__placa', $type, $value);
                }
                if ($column == 'car__placa' || $column == '*') {
                    $q->orWhere('car__placa', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $reviewCarJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $reviews = array();
            foreach ($reviewCarJpa as $reviewJpa) {
                $review = gJSON::restore($reviewJpa->toArray(), '__');
                $reviews[] = $review;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewReviewCar::count());
            $response->setData($reviews);
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

    public function paginateByIdCar(Request $request)
    {

        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'checklist', 'read')) {
                throw new Exception('No tienes permisos para listar las marcas  de ' . $branch);
            }

            $query = ViewReviewCar::select('*')
                ->where('car__id', $request->car)
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
                if ($column == 'driver__name' || $column == '*') {
                    $q->orWhere('driver__name', $type, $value);
                }
                if ($column == 'driver__lastname' || $column == '*') {
                    $q->orWhere('driver__lastname', $type, $value);
                }
                if ($column == 'res_check__name' || $column == '*') {
                    $q->orWhere('res_check__name', $type, $value);
                }
                if ($column == 'res_check__lastname' || $column == '*') {
                    $q->orWhere('res_check__lastname', $type, $value);
                }
                if ($column == 'car__placa' || $column == '*') {
                    $q->orWhere('car__placa', $type, $value);
                }
                if ($column == 'car__placa' || $column == '*') {
                    $q->orWhere('car__placa', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $reviewCarJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $reviews = array();
            foreach ($reviewCarJpa as $reviewJpa) {
                $review = gJSON::restore($reviewJpa->toArray(), '__');
                $reviews[] = $review;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);

            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewReviewCar::where('car__id', $request->car)->count());
            $response->setData($reviews);
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

    public function getReviewCarById(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'checklist', 'read')) {
                throw new Exception('No tienes permisos para listar los checklist  de ' . $branch);
            }

            $checksJpa = ViewCheckByReview::select('*')
                ->whereNotNull('status')
                ->where('_review', $request->id)
                ->get();

            $checks = array();
            foreach ($checksJpa as $checkJpa) {
                $check = gJSON::restore($checkJpa->toArray(), '__');
                $checks[] = $check;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($checks);
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

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'checklist', 'update')) {
                throw new Exception("No tienes permisos para actualizar checklist ");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $ReviewCarJpa = ReviewCar::find($request->id);
            $ReviewCarJpa->date = $request->date;
            $ReviewCarJpa->_car = $request->_car;
            $ReviewCarJpa->_driver = $request->_driver;
            $ReviewCarJpa->description = $request->description;
            $ReviewCarJpa->_update_user = $userid;
            $ReviewCarJpa->update_date = gTrace::getDate('mysql');
            $ReviewCarJpa->save();

            foreach ($request->data as $component) {
                $CheckListCarJpa = CheckListCar::find($component['dat']['id']);
                if (!$CheckListCarJpa) {
                    $CheckListCarJpaNew = new CheckListCar();
                    $CheckListCarJpaNew->_component = $component['id'];
                    $CheckListCarJpaNew->present = $component['dat']['present'];
                    $CheckListCarJpaNew->optimed = $component['dat']['optimed'];
                    $CheckListCarJpaNew->description = $component['dat']['description'];
                    $CheckListCarJpaNew->status = 1;
                    $CheckListCarJpaNew->save();

                    $CheckByReviewJpa = new CheckByReview();
                    $CheckByReviewJpa->_check = $CheckListCarJpaNew->id;
                    $CheckByReviewJpa->_review = $ReviewCarJpa->id;
                    $CheckByReviewJpa->status = 1;
                    $CheckByReviewJpa->save();
                } else {
                    $CheckListCarJpa->_component = $component['id'];
                    $CheckListCarJpa->present = $component['dat']['present'];
                    $CheckListCarJpa->optimed = $component['dat']['optimed'];
                    $CheckListCarJpa->description = $component['dat']['description'];
                    $CheckListCarJpa->status = 1;
                    $CheckListCarJpa->save();
                }
            }

            $response->setStatus(200);
            $response->setMessage('El checklist se ha actualizado correctamente');
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

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'checklist', 'delete_restore')) {
                throw new Exception("No tienes permisos para eliminar checklist ");
            }

            $ReviewCarJpa = ReviewCar::find($request->id);
            $ReviewCarJpa->status = null;
            $ReviewCarJpa->save();

            $response->setStatus(200);
            $response->setMessage('El checklist se ha eliminado correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'checklist', 'delete_restore')) {
                throw new Exception("No tienes permisos para restaurar checklist ");
            }

            $ReviewCarJpa = ReviewCar::find($request->id);
            $ReviewCarJpa->status = 1;
            $ReviewCarJpa->save();

            $response->setStatus(200);
            $response->setMessage('El checklist se ha restaurado correctamente');
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

            $modelJpa = ImagesByReview::select([
                "images_by_review.image_$size as image_content",
                'images_by_review.image_type',

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
            if (!gValidate::check($role->permissions, $branch, 'cars', 'update')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($request->_review)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $imagesByReviewJpa = new ImagesByReview();
            $imagesByReviewJpa->_review = $request->_review;
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
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $ImagesByReview = ImagesByReview::select(['id', 'description', '_creation_user', 'creation_date'])
            ->where('_review', $id)->whereNotNUll('status')
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
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $ImagesByReviewJpa = ImagesByReview::find($request->id);
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
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'update')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $ImagesByReviewJpa = ImagesByReview::find($id);
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
}
