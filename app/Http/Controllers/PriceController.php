<?php
namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
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
            }


            $salesProduct = new SalesProducts();
                $salesProduct->_client = $request->_technical;
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_plant = $request->_plant;
            $salesProduct->_type_operation = $request->_type_operation;
            $salesProduct->type_intallation = "PLANTA";
            if (isset($request->date_sale)) {
                $salesProduct->date_sale = $request->date_sale;
            }
            $salesProduct->status_sale = "PENDIENTE";
            $salesProduct->_issue_user = $userid;
            $salesProduct->type_pay = "GASTOS INTERNOS";

            if (isset($request->description)) {
                $salesProduct->description = $request->description;
            }

            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            if (isset($request->data)) {
                foreach ($request->data as $product) {
                    $productJpa = Product::find($product['product']['id']);
                    $stockPlantJpa = StockPlant::find($product['id']);

                    if ($product['product']['type'] == "MATERIAL") {
                        $stockPlantJpa->mount_new = $stockPlantJpa->mount_new - $product['mount_new'];
                        $stockPlantJpa->mount_second = $stockPlantJpa->mount_second - $product['mount_second'];
                        $stockPlantJpa->mount_ill_fated = $stockPlantJpa->mount_ill_fated - $product['mount_ill_fated'];
                    } else {
                        $stockPlantJpa->status = null;
                        $productJpa->disponibility = "PLANTA: " . $plantJpa->name;
                    }

                    $productJpa->save();
                    $stockPlantJpa->save();

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
            }

            $response->setStatus(200);
            $response->setMessage('El proyecto se ha creado correctamente');
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
