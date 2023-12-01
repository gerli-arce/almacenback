<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DetailSale;
use App\Models\People;
use App\Models\Product;
use App\Models\ProductByTechnical;
use App\Models\Response;
use App\Models\SalesProducts;
use App\Models\Stock;
use App\Models\ViewDetailsSales;
use App\Models\ViewPeople;
use App\Models\ViewProductByTechnical;
use App\Models\ViewSales;
use App\Models\ViewUsers;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LendProductsController extends Controller
{
    public function paginate(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'lend', 'read')) {
                throw new Exception('No tienes permisos para listar personas');
            }

            $query = ViewPeople::select([
                'id',
                'doc_type',
                'doc_number',
                'name',
                'lastname',
                'relative_id',
                'birthdate',
                'gender',
                'email',
                'phone',
                'ubigeo',
                'address',
                'type',
                'branch__id',
                'branch__name',
                'branch__correlative',
                'branch__ubigeo',
                'branch__address',
                'branch__description',
                'branch__status',
                'user_creation__username',
                'user_creation__relative_id',
                'creation_date',
                'user_update__id',
                'user_update__username',
                'user_update__relative_id',
                'update_date',
                'status',
            ])
                ->orderBy($request->order['column'], $request->order['dir'])
                ->where(function ($q) {
                    $q->where('type', 'EJECUTIVE')
                        ->orWhere('type', 'TECHNICAL');
                });

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'doc_type' || $column == '*') {
                    $q->orWhere('doc_type', $type, $value);
                }
                if ($column == 'doc_number' || $column == '*') {
                    $q->orWhere('doc_number', $type, $value);
                }
                if ($column == 'name' || $column == '*') {
                    $q->orWhere('name', $type, $value);
                }
                if ($column == 'lastname' || $column == '*') {
                    $q->orWhere('lastname', $type, $value);
                }
                if ($column == 'birthdate' || $column == '*') {
                    $q->orWhere('birthdate', $type, $value);
                }
                if ($column == 'gender' || $column == '*') {
                    $q->orWhere('gender', $type, $value);
                }
                if ($column == 'email' || $column == '*') {
                    $q->orWhere('email', $type, $value);
                }
                if ($column == 'phone' || $column == '*') {
                    $q->orWhere('phone', $type, $value);
                }
                if ($column == 'ubigeo' || $column == '*') {
                    $q->orWhere('ubigeo', $type, $value);
                }
                if ($column == 'address' || $column == '*') {
                    $q->orWhere('address', $type, $value);
                }
                if ($column == 'type' || $column == '*') {
                    $q->orWhere('type', $type, $value);
                }
                if ($column == 'branch__name' || $column == '*') {
                    $q->orWhere('branch__name', $type, $value);
                }
                if ($column == 'status' || $column == '*') {
                    $q->orWhere('status', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();

            $peopleJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $people = array();
            foreach ($peopleJpa as $personJpa) {
                $person = gJSON::restore($personJpa->toArray(), '__');
                $people[] = $person;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewPeople::where(function ($q) {
                $q->where('type', 'EJECUTIVE')
                    ->orWhere('type', 'TECHNICAL');
            })->count());
            $response->setData($people);
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

    public function setLendByPerson(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'lend', 'create')) {
                throw new Exception('No tiene permisos para hacer prestamos');
            }

            if (
                !isset($request->id) ||
                !isset($request->details)
            ) {
                throw new Exception("Error: No deje campos vaciós");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_technical = $request->id;
            $salesProduct->_type_operation = "12";
            $salesProduct->type_intallation = "PRESTAMO";
            $salesProduct->date_sale = gTrace::getDate('mysql');
            $salesProduct->status_sale = "AGREGADO";
            $salesProduct->type_products = "LEND";
            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            foreach ($request->details as $product) {
                $productJpa = Product::find($product['product']['id']);
                $stock = Stock::where('_model', $productJpa->_model)
                    ->where('_branch', $branch_->id)
                    ->first();

                if ($productJpa->type == 'MATERIAL') {
                    $stock->mount_new = $stock->mount_new - $product['mount_new'];
                    $stock->mount_second = $stock->mount_second - $product['mount_second'];
                    $stock->mount_ill_fated = $stock->mount_ill_fated - $product['mount_ill_fated'];

                    $productJpa->mount = $stock->mount_new + $stock->mount_second;

                    $productByTechnicalJpa = ProductByTechnical::where('_technical', $request->id)
                        ->whereNotNull('status')
                        ->where('type', 'LEND')
                        ->where('_model', $product['product']['model']['id'])->first();
                    if ($productByTechnicalJpa) {
                        $productByTechnicalJpa->mount_new = $productByTechnicalJpa->mount_new + $product['mount_new'];
                        $productByTechnicalJpa->mount_second = $productByTechnicalJpa->mount_second + $product['mount_second'];
                        $productByTechnicalJpa->mount_ill_fated = $productByTechnicalJpa->mount_ill_fated + $product['mount_ill_fated'];
                        $productByTechnicalJpa->save();
                    } else {
                        $productByTechnicalJpaNew = new ProductByTechnical();
                        $productByTechnicalJpaNew->_technical = $request->id;
                        $productByTechnicalJpaNew->_product = $productJpa->id;
                        $productByTechnicalJpaNew->_model = $productJpa->_model;
                        $productByTechnicalJpaNew->type = "LEND";
                        $productByTechnicalJpaNew->mount_new = $product['mount_new'];
                        $productByTechnicalJpaNew->mount_second = $product['mount_second'];
                        $productByTechnicalJpaNew->mount_ill_fated = $product['mount_ill_fated'];
                        $productByTechnicalJpaNew->description = $product['description'];
                        $productByTechnicalJpaNew->status = 1;
                        $productByTechnicalJpaNew->save();
                    }

                    $stock->save();
                    $productJpa->save();
                } else {
                    $productJpa->disponibility = "Se presto ha: " . $request->name . ' ' . $request->lastname;
                    $productJpa->save();
                    if ($productJpa->product_status == "NUEVO") {
                        $stock->mount_new = $stock->mount_new - 1;
                    } else if ($productJpa->product_status == "SEMINUEVO") {
                        $stock->mount_second = $stock->mount_second - 1;
                    } else {
                        $stock->mount_ill_fated = $stock->mount_ill_fated - 1;
                    }
                    $stock->save();
                    $productByTechnicalJpaNew = new ProductByTechnical();
                    $productByTechnicalJpaNew->_technical = $request->id;
                    $productByTechnicalJpaNew->_product = $productJpa->id;
                    $productByTechnicalJpaNew->_model = $productJpa->_model;
                    $productByTechnicalJpaNew->type = "LEND";
                    $productByTechnicalJpaNew->mount_new = $product['mount_new'];
                    $productByTechnicalJpaNew->mount_second = $product['mount_second'];
                    $productByTechnicalJpaNew->mount_ill_fated = $product['mount_ill_fated'];
                    $productByTechnicalJpaNew->description = $product['description'];
                    $productByTechnicalJpaNew->status = 1;
                    $productByTechnicalJpaNew->save();
                }

                $detailSale = new DetailSale();
                $detailSale->_product = $productJpa->id;
                $detailSale->mount_new = $product['mount_new'];
                $detailSale->mount_second = $product['mount_second'];
                $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                $detailSale->description = $product['description'];
                $detailSale->_sales_product = $salesProduct->id;
                $detailSale->status = '1';
                $detailSale->save();
            }
            $response->setStatus(200);
            $response->setMessage('Operación correcta, prestamo registrado correctamnete.');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function setLendProductByPerson(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'technicals', 'update')) {
                throw new Exception('No tienes permisos para crear productos');
            }

            if (
                !isset($request->product) ||
                !isset($request->technical)
            ) {
                throw new Exception("Error: No deje campos vaciós");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_technical = $request->technical['id'];
            $salesProduct->_type_operation = "12";
            $salesProduct->type_intallation = "PRESTAMO";
            $salesProduct->date_sale = gTrace::getDate('mysql');
            $salesProduct->status_sale = "AGREGADO";
            $salesProduct->type_products = "LEND";
            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            $detailSale = new DetailSale();
            $detailSale->_product = $request->product['id'];
            $detailSale->mount_new = $request->mount_new;
            $detailSale->mount_second = $request->mount_second;
            $detailSale->mount_ill_fated = $request->mount_ill_fated;
            $detailSale->_sales_product = $salesProduct->id;
            $detailSale->description = $request->description;
            $detailSale->status = '1';
            $detailSale->save();

            $productByTechnicalJpa = ProductByTechnical::where('_technical', $request->technical['id'])
                ->whereNotNull('status')
                ->where('type', 'LEND')
                ->where('_model', $request->product['model']['id'])->first();

            $productByTechnicalJpa->mount_new = $productByTechnicalJpa->mount_new + $request->mount_new;
            $productByTechnicalJpa->mount_second = $productByTechnicalJpa->mount_second + $request->mount_second;
            $productByTechnicalJpa->mount_ill_fated = $productByTechnicalJpa->mount_ill_fated + $request->mount_ill_fated;

            $productJpa = Product::find($request->product['id']);

            $stock = Stock::where('_model', $productJpa->_model)
                ->where('_branch', $branch_->id)
                ->first();

            $stock->mount_new = $stock->mount_new - $request->mount_new;
            $stock->mount_second = $stock->mount_second - $request->mount_second;
            $stock->mount_ill_fated = $stock->mount_ill_fated - $request->mount_ill_fated;
            $stock->save();

            $productJpa->mount = $stock->mount_new + $stock->mount_second;

            $productJpa->save();

            $productByTechnicalJpa->save();
            $response->setStatus(200);
            $response->setMessage('Prestamo agregado correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function getLendsByPerson(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);

            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'lend', 'read')) {
                throw new Exception('No tienes permisos para listar productos');
            }

            $productsJpa = ViewProductByTechnical::where('technical__id', $request->id)
                ->whereNotNull('status')
                ->where('type', 'LEND')->get();

            $products = array();
            foreach ($productsJpa as $productJpa) {
                $product = gJSON::restore($productJpa->toArray(), '__');
                $products[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($products);
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

    public function reportLend(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'lend', 'read')) {
                throw new Exception('No tienes permisos para listar registros de prestamos');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportLend.html');

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }
            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();
            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();

            $dat_technical = People::find($request->technical_id);

            $lendJpa = ViewSales::select([
                'view_sales.id as id',
                'view_sales.client_id as client_id',
                'view_sales.technical_id as technical_id',
                'view_sales.branch__id as branch__id',
                'view_sales.branch__name as branch__name',
                'view_sales.branch__correlative	 as branch__correlative',
                'view_sales.type_operation__id	 as type_operation__id',
                'view_sales.type_operation__operation	 as type_operation__operation',
                'view_sales.tower_id as tower_id',
                'view_sales.plant_id as plant_id',
                'view_sales.room_id as room_id',
                'view_sales.type_intallation as type_intallation',
                'view_sales.date_sale as date_sale',
                'view_sales.issue_date as issue_date',
                'view_sales.issue_user_id as issue_user_id',
                'view_sales.status_sale as status_sale',
                'view_sales.description as description',
                'view_sales.user_creation__id as user_creation__id',
                'view_sales.user_creation__username as user_creation__username',
                'view_sales.user_creation__person__id as user_creation__person__id',
                'view_sales.user_creation__person__name as user_creation__person__name',
                'view_sales.user_creation__person__lastname as user_creation__person__lastname',
                'view_sales.creation_date as creation_date',
                'view_sales.update_user_id as update_user_id',
                'view_sales.update_date as update_date',
                'view_sales.status as status',
            ])
                ->distinct()
                ->leftJoin('view_details_sales', 'view_sales.id', '=', 'view_details_sales.sale_product_id')
                ->where('view_sales.id', $request->id)->first();

            $sale = gJSON::restore($lendJpa->toArray(), '__');
            $detailSalesJpa = ViewDetailsSales::select(['*'])->whereNotNull('status')->where('sale_product_id', $sale['id'])->get();
            $details = array();

            $sumary = '';

            foreach ($detailSalesJpa as $detailJpa) {
                $product = gJSON::restore($detailJpa->toArray(), '__');

                
                $details[] = $product;
                $model = $relativeId = $unity = "";
                if ($product['product']['type'] === "EQUIPO") {
                    $model = $product['product']['model']['model'];
                    $relativeId = $product['product']['model']['relative_id'];
                    $unity = $product['product']['model']['unity']['name'];
                } else {
                    $model = $product['product']['model']['model'];
                    $relativeId = $product['product']['model']['relative_id'];
                    $unity = $product['product']['model']['unity']['name'];
                }
                $mount_new = $product['mount_new'];
                $mount_second = $product['mount_second'];
                $mount_ill_fated = $product['mount_ill_fated'];
                if (isset($models[$model])) {
                    $models[$model]['mount_new'] += $mount_new;
                    $models[$model]['mount_second'] += $mount_second;
                    $models[$model]['mount_ill_fated'] += $mount_ill_fated;
                } else {
                    $models[$model] = array(
                        'model' => $model,
                        'mount_new' => $mount_new,
                        'mount_second' => $mount_second,
                        'mount_ill_fated' => $mount_ill_fated,
                        'relative_id' => $relativeId,
                        'unity' => $unity);
                }
            }
            $count = 1;
            $products = array_values($models);
            foreach ($products as $detail) {
                $sumary .= "
                <tr>
                    <td><center style='font-size:12px;'>{$count}</center></td>
                    <td>
                        <center style='font-size:12px;color:green;'>
                            Nu:{$detail['mount_new']} |
                            Se:{$detail['mount_second']} |
                            Ma:{$detail['mount_ill_fated']}
                        </center>
                    </td>
                    <td><center style='font-size:12px;'>{$detail['unity']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['model']}</center></td>
                </tr>
                ";
                $count = $count + 1;
            }

            $sale['details'] = $details;

            $template = str_replace(
                [
                    '{num}',
                    '{branch_interaction}',
                    '{issue_long_date}',
                    '{user}',
                    '{technical}',
                    '{operation}',
                    '{date_register}',
                    '{prestamista}',
                    '{prestatario}',
                    '{summary}',
                ],
                [
                    $sale['id'],
                    $branch_->name,
                    gTrace::getDate('long'),
                    $user->person__name . ' ' . $user->person__lastname,
                    $dat_technical->name . ' ' . $dat_technical->lastname,
                    $sale['status_sale'],
                    $sale['creation_date'],
                    $dat_technical->name . ' ' . $dat_technical->lastname,
                    $user->person__name . ' ' . $user->person__lastname,
                    $sumary,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('Lend.pdf');

            // $response = new Response();
            // $response->setStatus(200);
            // $response->setData($sale);
            // $response->setMessage('operacion correcta');
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

    public function paginateRecordsLends(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'lend', 'read')) {
                throw new Exception('No tienes permisos para listar las salidas');
            }

            $query = ViewSales::select([
                'view_sales.id as id',
                'view_sales.client_id as client_id',
                'view_sales.technical_id as technical_id',
                'view_sales.branch__id as branch__id',
                'view_sales.branch__name as branch__name',
                'view_sales.branch__correlative	 as branch__correlative',
                'view_sales.type_operation__id	 as type_operation__id',
                'view_sales.type_operation__operation	 as type_operation__operation',
                'view_sales.tower_id as tower_id',
                'view_sales.plant_id as plant_id',
                'view_sales.room_id as room_id',
                'view_sales.type_intallation as type_intallation',
                'view_sales.date_sale as date_sale',
                'view_sales.issue_date as issue_date',
                'view_sales.issue_user_id as issue_user_id',
                'view_sales.status_sale as status_sale',
                'view_sales.description as description',
                'view_sales.user_creation__id as user_creation__id',
                'view_sales.user_creation__username as user_creation__username',
                'view_sales.user_creation__person__id as user_creation__person__id',
                'view_sales.user_creation__person__name as user_creation__person__name',
                'view_sales.user_creation__person__lastname as user_creation__person__lastname',
                'view_sales.creation_date as creation_date',
                'view_sales.update_user_id as update_user_id',
                'view_sales.update_date as update_date',
                'view_sales.status as status',
            ])
                ->distinct()
                ->leftJoin('view_details_sales', 'view_sales.id', '=', 'view_details_sales.sale_product_id')
                ->orderBy('view_sales.' . $request->order['column'], $request->order['dir'])
                ->where('technical_id', $request->search['technical'])
                ->whereNotNUll('view_sales.status');

            $query->where('type_operation__id', '12');

            if (isset($request->search['model'])) {
                $query
                    ->where('view_details_sales.product__model__id', $request->search['model'])
                    ->where('type_intallation', 'PRESTAMO')
                    ->orWhere(function ($q) use ($request) {
                        $q->where('view_details_sales.product__model__id', $request->search['model'])
                            ->where('technical_id', $request->search['technical'])
                            ->where('type_intallation', 'PRESTAMO');
                    })
                    ->where('technical_id', $request->search['technical'])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('view_details_sales.product__model__id', $request->search['model'])
                            ->where('technical_id', $request->search['technical'])
                            ->where('type_intallation', 'DEVOLUCION_PRESTAMO');
                    })
                    ->where('technical_id', $request->search['technical']);
            } else {
                $query->where('type_intallation', 'PRESTAMO')
                    ->orWhere('type_intallation', 'DEVOLUCION_PRESTAMO')
                    ->where('technical_id', $request->search['technical']);
            }

            if (isset($request->search['date_start']) || isset($request->search['date_end'])) {
                $dateStart = date('Y-m-d', strtotime($request->search['date_start']));
                $dateEnd = date('Y-m-d', strtotime($request->search['date_end']));
                $query->where('date_sale', '>=', $dateStart)
                    ->where('date_sale', '<=', $dateEnd);
            }

            $iTotalDisplayRecords = $query->count();

            $salesJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $sales = array();
            foreach ($salesJpa as $saleJpa) {
                $sale = gJSON::restore($saleJpa->toArray(), '__');
                $detailSalesJpa = ViewDetailsSales::select(['*'])->whereNotNull('status')->where('sale_product_id', $sale['id'])->get();
                $details = array();
                foreach ($detailSalesJpa as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                }
                $sale['details'] = $details;
                $sales[] = $sale;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewSales::where('branch__correlative', $branch)->whereNotNUll('status')
                    ->where('branch__correlative', $branch)
                    ->where('technical_id', $request->id)
                    ->where('type_intallation', 'AGREGADO_A_STOCK')
                    ->where('type_operation__id', '10')->count());
            $response->setData($sales);
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

    public function recordTakeOutByTechnical(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'lend', 'update')) {
                throw new Exception('No tienes permisos para actualizar devolver un prestamo');
            }

            if (
                !isset($request->product) ||
                !isset($request->technical)
            ) {
                throw new Exception("Error: No deje campos vaciós");
            }

            if ($request->product['type'] == "MATERIAL") {
                $productByTechnicalJpa = ProductByTechnical::where('_technical', $request->technical['id'])
                    ->where('type', 'LEND')
                    ->where('_model', $request->product['model']['id'])
                    ->first();
            } else {
                $productByTechnicalJpa = ProductByTechnical::where('_technical', $request->technical['id'])
                    ->whereNotNull('status')
                    ->where('_product', $request->product['id'])
                    ->first();
            }

            if (!$productByTechnicalJpa) {
                throw new Exception("Error: El registro no fue encontrado, contactese con el programador");
            }

            $productByTechnicalJpa->mount_new = $productByTechnicalJpa->mount_new - $request->mount_new;
            $productByTechnicalJpa->mount_second = $productByTechnicalJpa->mount_second - $request->mount_second;
            $productByTechnicalJpa->mount_ill_fated = $productByTechnicalJpa->mount_ill_fated - $request->mount_ill_fated;

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_technical = $request->technical['id'];
            $salesProduct->_type_operation = "12";
            $salesProduct->type_intallation = "DEVOLUCION_PRESTAMO";
            $salesProduct->date_sale = gTrace::getDate('mysql');
            $salesProduct->type_products = "LEND";
            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->status_sale = "DEVOLUCION";

            $productJpa = Product::find($request->product['id']);
            $stock = Stock::where('_model', $productJpa->_model)
                ->where('_branch', $branch_->id)
                ->first();
            if ($productJpa->type == "EQUIPO") {
                $productJpa->description .= " (Se presto a " . $request->technical['name'] . ' ' . $request->technical['lastname'] . '), devolvio en la fecha: ' . gTrace::getDate('mysql');
                $productJpa->disponibility = 'DISPONIBLE';
                if ($productJpa->product_status == "NUEVO") {
                    $stock->mount_new += 1;
                } else if ($productJpa->product_status == "SEMINUEVO") {
                    $stock->mount_second += 1;
                } else {
                    $stock->mount_ill_fated += 1;
                }

                $productByTechnicalJpa->status = null;
            } else {
                $stock->mount_new = $stock->mount_new + $request->mount_new;
                $stock->mount_second = $stock->mount_second + $request->mount_second;
                $stock->mount_ill_fated = $stock->mount_ill_fated + $request->mount_ill_fated;
            }

            $stock->save();

            $productJpa->mount = $stock->mount_new + $stock->mount_second;
            $productJpa->save();
            $salesProduct->save();

            $detailSale = new DetailSale();
            $detailSale->_product = $request->product['id'];
            $detailSale->mount_new = $request->mount_new;
            $detailSale->mount_second = $request->mount_second;
            $detailSale->mount_ill_fated = $request->mount_ill_fated;
            $detailSale->description = $request->description;
            $detailSale->_sales_product = $salesProduct->id;
            $detailSale->status = '1';
            $detailSale->save();

            $productByTechnicalJpa->save();
            $response->setStatus(200);
            $response->setMessage('Devolución de productos registrados correctamente');
            $response->setData($productByTechnicalJpa->toArray());
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function generateReportBySearch(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'lend', 'read')) {
                throw new Exception('No tienes permisos para listar registros de prestamos');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportRecordsLends.html');

            // if (
            //     !isset($request->date_start) ||
            //     !isset($request->date_end)
            // ) {
            //     throw new Exception("Error: No deje campos vacíos");
            // }
            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();
            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();
            $dat_technical = People::find($request->technical);

            $query = ViewSales::select([
                'view_sales.id as id',
                'view_sales.client_id as client_id',
                'view_sales.technical_id as technical_id',
                'view_sales.branch__id as branch__id',
                'view_sales.branch__name as branch__name',
                'view_sales.branch__correlative	 as branch__correlative',
                'view_sales.type_operation__id	 as type_operation__id',
                'view_sales.type_operation__operation	 as type_operation__operation',
                'view_sales.tower_id as tower_id',
                'view_sales.plant_id as plant_id',
                'view_sales.room_id as room_id',
                'view_sales.type_intallation as type_intallation',
                'view_sales.date_sale as date_sale',
                'view_sales.issue_date as issue_date',
                'view_sales.issue_user_id as issue_user_id',
                'view_sales.status_sale as status_sale',
                'view_sales.description as description',
                'view_sales.user_creation__id as user_creation__id',
                'view_sales.user_creation__username as user_creation__username',
                'view_sales.user_creation__person__id as user_creation__person__id',
                'view_sales.user_creation__person__name as user_creation__person__name',
                'view_sales.user_creation__person__lastname as user_creation__person__lastname',
                'view_sales.creation_date as creation_date',
                'view_sales.update_user_id as update_user_id',
                'view_sales.update_date as update_date',
                'view_sales.status as status',
            ])
                ->leftJoin('view_details_sales', 'view_sales.id', '=', 'view_details_sales.sale_product_id')
                ->orderBy('view_sales.id', 'desc')
                ->where('view_sales.technical_id', $request->technical)
            // ->whereNotNUll('view_sales.status')
                ->where('branch__correlative', $branch);

            if (isset($request->model)) {
                $query
                    ->where('view_details_sales.product__model__id', $request->model)
                    ->where('type_intallation', 'PRESTAMO')
                    ->orWhere(function ($q) use ($request) {

                        $q->where('view_details_sales.product__model__id', $request->model)
                            ->where('technical_id', $request->technical)
                            ->where('type_intallation', 'PRESTAMO');
                    })
                    ->orWhere(function ($q) use ($request) {
                        $q->where('view_details_sales.product__model__id', $request->model)
                            ->where('technical_id', $request->technical)
                            ->where('type_intallation', 'DEVOLUCION_PRESTAMO');
                    });
            } else {
                $query->where('view_sales.type_intallation', 'PRESTAMO')
                    ->orWhere('view_sales.type_intallation', 'DEVOLUCION_PRESTAMO');
            }

            $query->where('view_sales.type_operation__id', 12);

            if (isset($request->date_start) || isset($request->date_end)) {
                $dateStart = date('Y-m-d', strtotime($request->date_start));
                $dateEnd = date('Y-m-d', strtotime($request->date_end));
                $query->whereBetween('view_sales.date_sale', [$dateStart, $dateEnd]);
            }

            $iTotalDisplayRecords = $query->count();

            $salesJpa = $query->get();

            $sales = array();
            foreach ($salesJpa as $saleJpa) {
                $sale = gJSON::restore($saleJpa->toArray(), '__');
                $detailSalesJpa = ViewDetailsSales::select(['*'])->whereNotNull('status')->where('sale_product_id', $sale['id'])->get();
                $details = array();
                foreach ($detailSalesJpa as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                }
                $sale['details'] = $details;
                $sales[] = $sale;
            }

            $count = 1;
            $view_details = '';
            $sumary = '';
            foreach ($sales as $sale) {

                $technical_details = "";
                $saleProductJpa = SalesProducts::select([
                    'sales_products.id as id',
                    'tech.id as technical__id',
                    'tech.name as technical__name',
                    'tech.lastname as technical__lastname',
                    'sales_products.date_sale as date_sale',
                    'sales_products.status_sale as status_sale',
                    'sales_products.description as description',
                    'sales_products.status as status',
                ])
                    ->join('people as tech', 'sales_products._technical', 'tech.id')
                    ->where('sales_products.id', $sale['id'])->first();

                $technical_details = "
                    <div>
                        <p>Técnico: <strong>{$saleProductJpa->technical__name} {$saleProductJpa->technical__lastname}</strong></p>
                        <p>Fecha: <strong>{$saleProductJpa->date_sale}</strong></p>
                    </div>
                    ";

                $usuario = "
                <div>
                    <p><strong> {$sale['user_creation']['person']['name']} {$sale['user_creation']['person']['lastname']} </strong> </p>
                    <p>{$sale['date_sale']}</p>
                </div>
                ";

                $tipo_instalacion = isset($sale['type_intallation']) ? $sale['type_intallation'] : "<i>sin tipo</i>";
                $tipo_instalacion = str_replace('_', ' ', $tipo_instalacion);

                $datos = "
                    <div>
                        <p><strong>Tipo salida</strong>: {$tipo_instalacion}</p>
                    </div>
                ";

                $sumary .= "
                <tr>
                    <td>{$count}</td>
                    <td>{$usuario}</td>
                    <td>{$datos}</td>
                </tr>
                ";

                $view_details .= "
                <div style='margin-top:8px;'>
                    <p style='margin-buttom: 12px;'>{$count}) <strong>{$tipo_instalacion}</strong> - {$sale['user_creation']['person']['name']} {$sale['user_creation']['person']['lastname']} - {$sale['date_sale']} </p>
                    <div style='margin-buttom: 12px;margin-left:20px;'>
                        {$technical_details}
                    </div>
                    <div style='display: flex;margin-top: 50px;'>";

                foreach ($sale['details'] as $detailJpa) {
                    $details_equipment = 'display:none;';
                    if ($detailJpa['product']['type'] == 'EQUIPO') {
                        $details_equipment = '';
                    }
                    $view_details .= "
                            <div style='border: 2px solid #bbc7d1; border-radius: 9px; width: 300px; display: inline-block; padding:8px; font-size:12px; margin-left:10px;'>
                                <center>
                                    <p><strong>{$detailJpa['product']['model']['model']}</strong></p>
                                    <img src='https://almacen.fastnetperu.com.pe/api/model/{$detailJpa['product']['model']['relative_id']}/mini' style='background-color: #38414a;object-fit: cover; object-position: center center; cursor: pointer; height:50px;margin-top:12px;'></img>
                                    <div style='{$details_equipment}'>
                                        <table class='table_details'>
                                            <tbody>
                                                <tr>
                                                    <td>MAC</td>
                                                    <td>{$detailJpa['product']['mac']}</td>
                                                </tr>
                                                <tr>
                                                    <td>SERIE</td>
                                                    <td>{$detailJpa['product']['serie']}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div>
                                        <table class='table_details'>
                                            <thead>
                                                <tr>
                                                    <td>NUEVOS</td>
                                                    <td>SEMINUEVOS</td>
                                                    <td>MALOGRADOS</td>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>{$detailJpa['mount_new']}</td>
                                                    <td>{$detailJpa['mount_second']}</td>
                                                    <td>{$detailJpa['mount_ill_fated']}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div>
                                        <p><strong>Descripción:</strong>{$detailJpa['description']}</p>
                                    </div>
                                </center>
                            </div>
                        ";
                }

                $view_details .= "
                            </div>
                        </div>
                    ";

                $count = $count + 1;
            }

            $template = str_replace(
                [
                    '{branch_interaction}',
                    '{issue_long_date}',
                    '{user_generate}',
                    '{people_names}',
                    '{date_start_str}',
                    '{date_end_str}',
                    '{summary}',
                    '{details}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $user->person__name . ' ' . $user->person__lastname,
                    $dat_technical->name . ' ' . $dat_technical->lastname,
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

    public function generateReportByLend(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'lend', 'read')) {
                throw new Exception('No tienes permisos para listar registros de prestamos');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportRecordsLends.html');

            // if (
            //     !isset($request->date_start) ||
            //     !isset($request->date_end)
            // ) {
            //     throw new Exception("Error: No deje campos vacíos");
            // }
            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();
            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();
            $dat_technical = People::find($request->technical);

            $query = ViewSales::select([
                'view_sales.id as id',
                'view_sales.client_id as client_id',
                'view_sales.technical_id as technical_id',
                'view_sales.branch__id as branch__id',
                'view_sales.branch__name as branch__name',
                'view_sales.branch__correlative	 as branch__correlative',
                'view_sales.type_operation__id	 as type_operation__id',
                'view_sales.type_operation__operation	 as type_operation__operation',
                'view_sales.tower_id as tower_id',
                'view_sales.plant_id as plant_id',
                'view_sales.room_id as room_id',
                'view_sales.type_intallation as type_intallation',
                'view_sales.date_sale as date_sale',
                'view_sales.issue_date as issue_date',
                'view_sales.issue_user_id as issue_user_id',
                'view_sales.status_sale as status_sale',
                'view_sales.description as description',
                'view_sales.user_creation__id as user_creation__id',
                'view_sales.user_creation__username as user_creation__username',
                'view_sales.user_creation__person__id as user_creation__person__id',
                'view_sales.user_creation__person__name as user_creation__person__name',
                'view_sales.user_creation__person__lastname as user_creation__person__lastname',
                'view_sales.creation_date as creation_date',
                'view_sales.update_user_id as update_user_id',
                'view_sales.update_date as update_date',
                'view_sales.status as status',
            ])
                ->leftJoin('view_details_sales', 'view_sales.id', '=', 'view_details_sales.sale_product_id')
                ->orderBy('view_sales.id', 'desc')
                ->where('view_sales.technical_id', $request->technical)
            // ->whereNotNUll('view_sales.status')
                ->where('branch__correlative', $branch);

            if (isset($request->model)) {
                $query
                    ->where('view_details_sales.product__model__id', $request->model)
                    ->where('type_intallation', 'PRESTAMO')
                    ->orWhere(function ($q) use ($request) {

                        $q->where('view_details_sales.product__model__id', $request->model)
                            ->where('technical_id', $request->technical)
                            ->where('type_intallation', 'PRESTAMO');
                    })
                    ->orWhere(function ($q) use ($request) {
                        $q->where('view_details_sales.product__model__id', $request->model)
                            ->where('technical_id', $request->technical)
                            ->where('type_intallation', 'DEVOLUCION_PRESTAMO');
                    });
            } else {
                $query->where('view_sales.type_intallation', 'PRESTAMO')
                    ->orWhere('view_sales.type_intallation', 'DEVOLUCION_PRESTAMO');
            }

            $query->where('view_sales.type_operation__id', 12);

            if (isset($request->date_start) || isset($request->date_end)) {
                $dateStart = date('Y-m-d', strtotime($request->date_start));
                $dateEnd = date('Y-m-d', strtotime($request->date_end));
                $query->whereBetween('view_sales.date_sale', [$dateStart, $dateEnd]);
            }

            $iTotalDisplayRecords = $query->count();

            $salesJpa = $query->get();

            $sales = array();
            foreach ($salesJpa as $saleJpa) {
                $sale = gJSON::restore($saleJpa->toArray(), '__');
                $detailSalesJpa = ViewDetailsSales::select(['*'])->whereNotNull('status')->where('sale_product_id', $sale['id'])->get();
                $details = array();
                foreach ($detailSalesJpa as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                }
                $sale['details'] = $details;
                $sales[] = $sale;
            }

            $count = 1;
            $view_details = '';
            $sumary = '';
            foreach ($sales as $sale) {

                $technical_details = "";
                $saleProductJpa = SalesProducts::select([
                    'sales_products.id as id',
                    'tech.id as technical__id',
                    'tech.name as technical__name',
                    'tech.lastname as technical__lastname',
                    'sales_products.date_sale as date_sale',
                    'sales_products.status_sale as status_sale',
                    'sales_products.description as description',
                    'sales_products.status as status',
                ])
                    ->join('people as tech', 'sales_products._technical', 'tech.id')
                    ->where('sales_products.id', $sale['id'])->first();

                $technical_details = "
                    <div>
                        <p>Técnico: <strong>{$saleProductJpa->technical__name} {$saleProductJpa->technical__lastname}</strong></p>
                        <p>Fecha: <strong>{$saleProductJpa->date_sale}</strong></p>
                    </div>
                    ";

                $usuario = "
                <div>
                    <p><strong> {$sale['user_creation']['person']['name']} {$sale['user_creation']['person']['lastname']} </strong> </p>
                    <p>{$sale['date_sale']}</p>
                </div>
                ";

                $tipo_instalacion = isset($sale['type_intallation']) ? $sale['type_intallation'] : "<i>sin tipo</i>";
                $tipo_instalacion = str_replace('_', ' ', $tipo_instalacion);

                $datos = "
                    <div>
                        <p><strong>Tipo salida</strong>: {$tipo_instalacion}</p>
                    </div>
                ";

                $sumary .= "
                <tr>
                    <td>{$count}</td>
                    <td>{$usuario}</td>
                    <td>{$datos}</td>
                </tr>
                ";

                $view_details .= "
                <div style='margin-top:8px;'>
                    <p style='margin-buttom: 12px;'>{$count}) <strong>{$tipo_instalacion}</strong> - {$sale['user_creation']['person']['name']} {$sale['user_creation']['person']['lastname']} - {$sale['date_sale']} </p>
                    <div style='margin-buttom: 12px;margin-left:20px;'>
                        {$technical_details}
                    </div>
                    <div style='display: flex;margin-top: 50px;'>";

                foreach ($sale['details'] as $detailJpa) {
                    $details_equipment = 'display:none;';
                    if ($detailJpa['product']['type'] == 'EQUIPO') {
                        $details_equipment = '';
                    }
                    $view_details .= "
                            <div style='border: 2px solid #bbc7d1; border-radius: 9px; width: 300px; display: inline-block; padding:8px; font-size:12px; margin-left:10px;'>
                                <center>
                                    <p><strong>{$detailJpa['product']['model']['model']}</strong></p>
                                    <img src='https://almacen.fastnetperu.com.pe/api/model/{$detailJpa['product']['model']['relative_id']}/mini' style='background-color: #38414a;object-fit: cover; object-position: center center; cursor: pointer; height:50px;margin-top:12px;'></img>
                                    <div style='{$details_equipment}'>
                                        <table class='table_details'>
                                            <tbody>
                                                <tr>
                                                    <td>MAC</td>
                                                    <td>{$detailJpa['product']['mac']}</td>
                                                </tr>
                                                <tr>
                                                    <td>SERIE</td>
                                                    <td>{$detailJpa['product']['serie']}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div>
                                        <table class='table_details'>
                                            <thead>
                                                <tr>
                                                    <td>NUEVOS</td>
                                                    <td>SEMINUEVOS</td>
                                                    <td>MALOGRADOS</td>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>{$detailJpa['mount_new']}</td>
                                                    <td>{$detailJpa['mount_second']}</td>
                                                    <td>{$detailJpa['mount_ill_fated']}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div>
                                        <p><strong>Descripción:</strong>{$detailJpa['description']}</p>
                                    </div>
                                </center>
                            </div>
                        ";
                }

                $view_details .= "
                            </div>
                        </div>
                    ";

                $count = $count + 1;
            }

            $template = str_replace(
                [
                    '{branch_interaction}',
                    '{issue_long_date}',
                    '{user_generate}',
                    '{people_names}',
                    '{date_start_str}',
                    '{date_end_str}',
                    '{summary}',
                    '{details}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $user->person__name . ' ' . $user->person__lastname,
                    $dat_technical->name . ' ' . $dat_technical->lastname,
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

    public function getStockProductByModel(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'lend', 'update')) {
                throw new Exception('No tienes permisos para hacer devoluciones de prestamos');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: Es necesario el ID para esta operación");
            }

            if ($request->product['type'] == 'MATERIAL') {
                $ProductByTechnical = ProductByTechnical::where('_technical', $request->technical['id'])
                    ->where('type', 'LEND')
                    ->where('_model', $request->product['model']['id'])->first();
            } else {
                $ProductByTechnical = ProductByTechnical::where('_technical', $request->technical['id'])
                    ->where('_product', $request->product['id'])->first();
            }

            $response->setData([$ProductByTechnical]);
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
}
