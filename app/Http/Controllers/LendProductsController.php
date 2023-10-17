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
use App\Models\DetailSale;

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
            if (!gValidate::check($role->permissions, $branch, 'people', 'read')) {
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
}
