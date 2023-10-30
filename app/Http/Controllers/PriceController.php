<?php
namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\gLibraries\guid;
use App\Models\{
    Branch,
    DetailSale,
    EntryDetail,
    EntryProducts, People,
    Product, ProductByPlant,
    ViewSales, Response, SalesProducts,
    Stock, StockPlant, ViewPlant,
    ViewDetailsSales, User, ViewPrice,
};
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Dompdf\Dompdf;
use Dompdf\Options;


class PriceController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'price', 'create')) {
                throw new Exception('No tienes permisos para crear cotizaciones');
            }

            if (
                !isset($request->doc_type) ||
                !isset($request->doc_number) ||
                !isset($request->details)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $peopleJpa = People::where('doc_type', $request->doc_type)->where('doc_number', $request->doc_number)->first();
        

            $salesProduct = new SalesProducts();
            if(empty($peopleJpa)){
                $peopleNew = new People();
                $peopleNew->doc_type = $request->doc_type;
                $peopleNew->doc_number = $request->doc_number;
                if(isset($request->name)){
                    $peopleNew->name = $request->name;
                }
                if(isset($request->lastname)){
                    $peopleNew->lastname = $request->lastname;
                }
                if(isset($request->email)){
                    $peopleNew->email = $request->email;
                }
                if(isset($request->phone)){
                    $peopleNew->phone = $request->phone;
                }

                $peopleNew->relative_id = guid::short();
                $peopleNew->type = 'CLIENT';
                $peopleNew->_branch = $branch_->id;
                $peopleNew->_creation_user = $userid;
                $peopleNew->creation_date = gTrace::getDate('mysql');
                $peopleNew->_update_user = $userid;
                $peopleNew->update_date = gTrace::getDate('mysql');
                $peopleNew->status = "1";
                $peopleNew->save();

                $salesProduct->_client = $peopleNew->id;
            }else{
                $salesProduct->_client = $peopleJpa->id;
            }
               
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_type_operation = 13;
            $salesProduct->type_intallation = "COTIZACION";
            
            $salesProduct->status_sale = "PENDIENTE";
            $salesProduct->_issue_user = $userid;
            $salesProduct->type_pay = "GASTOS INTERNOS";
            $salesProduct->price_all = $request->price_all;

            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            if (isset($request->details)) {
                foreach ($request->details as $product) {
                    $detailSale = new DetailSale();
                    $detailSale->_model = $product['model']['id'];
                    $detailSale->mount_new = $product['mount_new'];
                    $detailSale->price_unity = $product['price_unity'];
                    $detailSale->_sales_product = $salesProduct->id;
                    $detailSale->status = '1';
                    $detailSale->save();
                }
            }

            $response->setStatus(200);
            $response->setMessage('La cotizacion se ha gurdado correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ', ln:' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function paginate(Request $request){
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'price', 'read')) {
                throw new Exception('No tienes permisos para listar cotizaciones');
            }

            $query = ViewPrice::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }


            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'doc_type' || $column == '*') {
                    $q->orWhere('client__doc_type', $type, $value);
                }
                if ($column == 'doc_number' || $column == '*') {
                    $q->orWhere('client__doc_number', $type, $value);
                }
                if ($column == 'name' || $column == '*') {
                    $q->orWhere('client__name', $type, $value);
                }
                if ($column == 'lastname' || $column == '*') {
                    $q->orWhere('client__lastname', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();

            $productsJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $products = array();
            foreach ($productsJpa as $product_) {
                $product = gJSON::restore($product_->toArray(), '__');
                $products[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewPrice::count());
            $response->setData($products);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . $th->getLine());
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
            if (!gValidate::check($role->permissions, $branch, 'price', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar cotizaciones');
            }
            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: Es necesario el ID para esta operación");
            }
            $saleProductJpa = SalesProducts::find($request->id);
            if (!$saleProductJpa) {
                throw new Exception("Este reguistro no existe");
            }

            $detailsSalesJpa = DetailSale::where('_sales_product', $saleProductJpa->id)
                ->get();

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            foreach ($detailsSalesJpa as $detail) {
                $detailSale = DetailSale::find($detail['id']);
                $detailSale->status = null;
                $detailSale->save();
            }

            $saleProductJpa->update_date = gTrace::getDate('mysql');
            $saleProductJpa->status = null;
            $saleProductJpa->save();
            $response->setStatus(200);
            $response->setMessage('La cortizacion se a eliminado correctamente.');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'Ln:' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function getDetailsPriceByID(Request $request, $id){
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'price', 'read')) {
                throw new Exception('No tienes permisos para listar detalles de cotizaciones');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $priceJpa = ViewPrice::find($id);

            $detailSaleJpa = DetailSale::select([
                'detail_sales.id as id',
                'brands.id AS brand__id',
                'brands.correlative AS brand__correlative',
                'brands.brand AS brand__brand',
                'brands.relative_id AS brand__relative_id',
                'categories.id AS category__id',
                'categories.category AS category__category',
                'models.id AS model__id',
                'models.model AS model__model',
                'models.relative_id AS model__relative_id',
                'unities.id as unity__id', 
                'unities.name as unity__name', 
                'detail_sales.mount_new as mount_new',
                'detail_sales.mount_second as mount_second',
                'detail_sales.mount_ill_fated as mount_ill_fated',
                'detail_sales.price_unity as price_unity',
                'detail_sales.description as description',
                'detail_sales._sales_product as _sales_product',
                'detail_sales.status as status',
            ])
                ->join('models', 'detail_sales._model', 'models.id')
                ->join('brands', 'models._brand', 'brands.id')
                ->join('categories', 'models._category', 'categories.id')
                ->join('unities', 'models._unity', 'unities.id')
                ->whereNotNull('detail_sales.status')
                ->where('_sales_product', $id)
                ->get();

            $details = array();
            foreach ($detailSaleJpa as $detailJpa) {
                $detail = gJSON::restore($detailJpa->toArray(), '__');
              
                $details[] = $detail;
            }

            $price = gJSON::restore($priceJpa->toArray(), '__');
            $price['products'] = $details;

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($price);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'Ln:' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }
}
