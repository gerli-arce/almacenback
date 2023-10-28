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
    EntryProducts,
    People,
    Product,
    ProductByPlant,
    ViewSales,
    Response,
    SalesProducts,
    Stock,
    StockPlant,
    ViewPlant,
    ViewDetailsSales,
    User
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
                    $detailSale->_model = $product['product']['model']['id'];
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
}
