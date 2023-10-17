<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Response;
use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\ViewPeople;
use App\Models\Branch;
use App\Models\SalesProducts;
use App\Models\Product;
use App\Models\Stock;
use App\Models\ProductByTechnical;
use App\Models\ViewProductByTechnical;
use App\Models\ViewDetailsSales;
use App\Models\DetailSale;
use App\Models\ViewSales;

use Exception;


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
                ->where('type', 'EJECUTIVE')
                ->orWhere('type', 'TECHNICAL');

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
            $response->setITotalRecords(ViewPeople::count());
            $response->setData($people);
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
                    }else{
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

    public function getLendsByPerson(Request $request){
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
    
    public function paginateRecordsEpp(Request $request)
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
                ->whereNotNUll('view_sales.status')
            // ->where('branch__correlative', $branch)
                ->where('type_products', 'LEND');

            $query->where('type_operation__id', '12');

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
}
