<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\EntryDetail;
use App\Models\EntryProducts;
use App\Models\Product;
use App\Models\Response;
use App\Models\ViewDetailEntry;
use App\Models\ViewUsers;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EntrysController extends Controller
{
    public function paginate(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'record_entrys', 'read')) {
                throw new Exception('No tienes permisos para listar técnicos');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $query = EntryProducts::select(
                [
                    'entry_products.id as id',
                    'users.id as user__id',
                    'users.username as user__username',
                    'people.id as user__person__id',
                    'people.name as user__person__name',
                    'people.lastname as user__person__lastname',
                    'entry_products._client AS _client',
                    'entry_products._technical AS _technical',
                    'entry_products._branch AS _branch',
                    'entry_products._type_operation AS _type_operation',
                    'operation_types.id as operation_type__id',
                    'operation_types.operation as operation_type__operation',
                    'entry_products._tower AS _tower',
                    'entry_products._plant AS _plant',
                    'entry_products.type_entry AS type_entry',
                    'entry_products.entry_date AS entry_date',
                    'entry_products.description AS description',
                    'entry_products.condition_product AS condition_product',
                    'entry_products.product_status AS product_status',
                    'entry_products._creation_user AS _creation_user',
                    'entry_products.creation_date AS creation_date',
                    'entry_products.status AS status',
                ]
            )
                ->join('users', 'entry_products._user', 'users.id')
                ->join('people', 'users._person', 'people.id')
                ->join('operation_types', 'entry_products._type_operation', 'operation_types.id')
                ->where('entry_products._branch', $branch_->id)
                ->orderBy($request->order['column'], $request->order['dir']);

            if (isset($request->search['date_start']) || isset($request->search['date_end'])) {
                $dateStart = date('Y-m-d 00:00:00', strtotime($request->search['date_start']));
                $dateEnd = date('Y-m-d 23:59:59', strtotime($request->search['date_end']));

                $query->where('entry_products.entry_date', '>=', $dateStart)
                    ->where('entry_products.entry_date', '<=', $dateEnd);
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'user' || $column == '*') {
                    $q->orWhere('users.username', $type, $value);
                }
                if ($column == 'operation' || $column == '*') {
                    $q->orWhere('operation_types.operation', $type, $value);
                }
            });
            $iTotalDisplayRecords = $query->count();

            $entrysJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $entrys = array();
            foreach ($entrysJpa as $entryJpa) {
                $entry = gJSON::restore($entryJpa->toArray(), '__');
                $entrys[] = $entry;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(EntryProducts::where('_branch', $branch_->id)->count());
            $response->setData($entrys);
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

    public function getProductsByEntry(Request $request, $id)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'record_entrys', 'read')) {
                throw new Exception('No tienes permisos para ver detalles de encomiendas');
            }

            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $entryDetailJpa = EntryDetail::select([
                'entry_detail.id as id',
                'products.id AS product__id',
                'products.type AS product__type',
                'models.id AS product__model__id',
                'models.model AS product__model__model',
                'models.relative_id AS product__model__relative_id',
                'products.relative_id AS product__relative_id',
                'products.mac AS product__mac',
                'products.serie AS product__serie',
                'products.price_sale AS product__price_sale',
                'products.currency AS product__currency',
                'products.num_guia AS product__num_guia',
                'products.condition_product AS product__condition_product',
                'products.disponibility AS product__disponibility',
                'products.product_status AS product__product_status',
                'entry_detail.mount_new as mount_new',
                'entry_detail.mount_second as mount_second',
                'entry_detail.mount_ill_fated as mount_ill_fated',
                'entry_detail.description as description',
                'entry_detail._entry_product as _entry_product',
                'entry_detail.status as status',
            ])
                ->join('products', 'entry_detail._product', 'products.id')
                ->join('models', 'products._model', 'models.id')
                ->where('entry_detail._entry_product', $id)->get();

            $details = array();
            foreach ($entryDetailJpa as $detailJpa) {
                $detail = gJSON::restore($detailJpa->toArray(), '__');
                $details[] = $detail;
            }

            $response->setStatus(200);
            $response->setData($details);
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

    public function getProductsProductsByEntry(Request $request, $id)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'record_entrys', 'read')) {
                throw new Exception('No tienes permisos para ver detalles de encomiendas');
            }

            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $productJpa = Product::select([
                'products.id AS product__id',
                'products.type AS product__type',
                'products.relative_id AS product__relative_id',
                'products.mac AS product__mac',
                'products.serie AS product__serie',
                'products.price_sale AS product__price_sale',
                'products.currency AS product__currency',
                'products.num_guia AS product__num_guia',
                'products.condition_product AS product__condition_product',
                'products.disponibility AS product__disponibility',
                'products.product_status AS product__product_status',
                'products._entry_product AS product__product_status',
                'models.id AS product__model__id',
                'models.model AS product__model__model',
                'models.relative_id AS product__model__relative_id',
            ])
                ->join('models', 'products._model', 'models.id')
                ->where('products._entry_product', $id)->get();

            $details = array();
            foreach ($productJpa as $detailJpa) {
                $detail = gJSON::restore($detailJpa->toArray(), '__');
                $details[] = $detail;
            }

            $response->setStatus(200);
            $response->setData($details);
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

    public function generateReportByDate(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'record_entrys', 'read')) {
                throw new Exception('No tienes permisos para listar entradas creadas');
            }

            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportEntrysByDate.html');

            $sumary = '';

            $branch_ = Branch::select('id', 'correlative', 'name')->where('correlative', $branch)->first();
            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();

            $query = EntryProducts::select(
                [
                    'entry_products.id as id',
                    'users.id as user__id',
                    'users.username as user__username',
                    'people.id as user__person__id',
                    'people.name as user__person__name',
                    'people.lastname as user__person__lastname',
                    'entry_products._client AS _client',
                    'entry_products._technical AS _technical',
                    'entry_products._branch AS _branch',
                    'entry_products._type_operation AS _type_operation',
                    'operation_types.id as operation_type__id',
                    'operation_types.operation as operation_type__operation',
                    'entry_products._tower AS _tower',
                    'entry_products._plant AS _plant',
                    'entry_products.type_entry AS type_entry',
                    'entry_products.entry_date AS entry_date',
                    'entry_products.description AS description',
                    'entry_products.condition_product AS condition_product',
                    'entry_products.product_status AS product_status',
                    'entry_products._creation_user AS _creation_user',
                    'entry_products.creation_date AS creation_date',
                    'entry_products.status AS status',
                ]
            )
                ->join('users', 'entry_products._user', 'users.id')
                ->join('people', 'users._person', 'people.id')
                ->join('operation_types', 'entry_products._type_operation', 'operation_types.id')
                ->where('entry_products._branch', $branch_->id)
                ->orderBy('entry_products.id', 'desc');

            if (isset($request->date_start) || isset($request->date_end)) {
                $dateStart = date('Y-m-d 00:00:00', strtotime($request->date_start));
                $dateEnd = date('Y-m-d 23:59:59', strtotime($request->date_end));

                $query->where('entry_products.entry_date', '>=', $dateStart)
                    ->where('entry_products.entry_date', '<=', $dateEnd);
            }

            $entrysJpa = $query->get();

            $entrys = array();

            $count = 1;

            $view_details = '';

            foreach ($entrysJpa as $entryJpa) {
                $entry = gJSON::restore($entryJpa->toArray(), '__');

                $usuario = "
                    <div>
                        <center>
                            <strong>
                                {$entry['user']['person']['name']} {$entry['user']['person']['lastname']}
                            </strong>
                            <br>
                            <strong>
                                {$entry['entry_date']}
                            </strong>
                        </center>
                    </div>
                ";

                $datos = "
                    <div>
                        <p>Tipo: <strong>{$entry['type_entry']}</strong></p>
                        <p>Operación: <strong>{$entry['operation_type']['operation']}</strong></p>
                        <p>Descripcion: <strong>{$entry['description']}</strong></p>
                    </div>
                ";

                $sumary .= "
               <tr style='font-size:11px;'>
                    <td>{$count}</td>
                    <td>{$usuario}</td>
                    <td>{$datos}</td>
               </tr>
                ";

                $view_details .= "
                    <div>
                        <p><strong>{$count}) {$entry['user']['person']['name']} {$entry['user']['person']['lastname']}: {$entry['entry_date']}</strong></p>
                        <div style='display: flex; flex-wrap: wrap; justify-content: space-between;margin-top: 50px;'>
                ";

                $productJpa = ViewDetailEntry::where('_entry_product', $entryJpa['id'])->get();
                $details = array();
                foreach ($productJpa as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details_equipment = 'display:none;';
                    if ($detail['product']['type'] == 'EQUIPO') {
                        $details_equipment = '';
                    }
                    $view_details .= "
                    <div style='border: 2px solid #bbc7d1; border-radius: 9px; width: 25%; display: inline-block; padding:8px; font-size:12px; margin-left:10px;'>
                        <center>
                            <p><strong>{$detail['product']['model']['model']}</strong></p>
                            <img src='https://almacen.fastnetperu.com.pe/api/model/{$detail['product']['model']['relative_id']}/mini' style='background-color: #38414a;object-fit: cover; object-position: center center; cursor: pointer; height:50px;margin-top:12px;'></img>
                            <div style='{$details_equipment}'>
                                <p>Mac: <strong>{$detail['product']['mac']}</strong><p>
                                <p>Serie: <strong>{$detail['product']['serie']}</strong></p>
                            </div>
                            <div>
                                <p style='font-size:20px; color:#2f6593'>Nu:{$detail['mount_new']} | Se:{$detail['mount_second']} | Ma:{$detailJpa['mount_ill_fated']}</p>
                            </div>
                        </center>
                    </div>
                ";

                    $details[] = $detail;
                }

                $view_details .= "
                            </div>
                    </div>
                ";
                $entry['details'] = $details;
                $entrys[] = $entry;
                $count += 1;
            }

            $template = str_replace(
                [
                    '{branch_name}',
                    '{user_name}',
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{date_start_str}',
                    '{date_end_str}',
                    '{summary}',
                    '{view_details}',
                ],
                [
                    $branch_->name,
                    $user->person__name . ' ' . $user->person__lastname,
                    $branch_->name,
                    gTrace::getDate('long'),
                    $request->date_start_str,
                    $request->date_end_str,
                    $sumary,
                    $view_details,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('Guia.pdf');

            // $response = new Response();
            // $response->setStatus(200);
            // $response->setMessage('Operacion correcta');
            // $response->setData($entrys);
            // return response(
            //     $response->toArray(),
            //     $response->getStatus()
            // );
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
