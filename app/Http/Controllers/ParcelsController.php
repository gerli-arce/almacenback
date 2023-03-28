<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\EntryProducts;
use App\Models\Product;
use App\Models\Response;
use App\Models\Stock;
use App\Models\Parcel;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParcelsController extends Controller
{
    public function store(Request $request)
    {

        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'parcles', 'create')) {
                throw new Exception('No tienes permisos para registrar encomiendas');
            }

            if (
                !isset($request->date_send) ||
                !isset($request->date_entry) ||
                !isset($request->_business_designed) ||
                !isset($request->_business_transport) ||
                !isset($request->_provider) ||
                !isset($request->_model) ||
                !isset($request->currency) ||
                !isset($request->warranty) ||
                !isset($request->mount_product) ||
                !isset($request->total) ||
                !isset($request->amount) ||
                !isset($request->value_unity) ||
                !isset($request->price_buy) ||
                !isset($request->_type_operation) ||
                !isset($request->total)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $entryProductJpa = new EntryProducts();

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            if (isset($request->_user)) {
                $entryProductJpa->_user = $request->_user;
            }
            if (isset($request->_client)) {
                $entryProductJpa->_entry = $request->_entry;
            }
            $entryProductJpa->_branch = $branch_->id;
            if(isset($request->_technical)){
                $entryProductJpa->_technical = $request->_technical;
            }
            $entryProductJpa->entry_date = gTrace::getDate('mysql');
            $entryProductJpa->_type_operation = $request->_type_operation;
            if(isset($request->_tower)){
                $entryProductJpa->_tower = $request->_tower;
            }
            if(isset($request->condition_product)){
                $entryProductJpa->condition_product = $request->condition_product;
            }
            if(isset($request->product_status)){
                $entryProductJpa->product_status = $request->product_status;
            }
            $entryProductJpa->status = "1";
            $entryProductJpa->save();

            $parcelJpa = new Parcel();
            $parcelJpa->date_send = $request->date_send;
            $parcelJpa->date_entry = $request->date_entry;
            $parcelJpa->_business_designed = $request->_business_designed;
            $parcelJpa->_business_transport = $request->_business_transport;
            $parcelJpa->_provider = $request->_provider;

            if(isset($request->num_voucher)){
                $parcelJpa->num_voucher = $request->num_voucher;
            }

            if(isset($request->num_guia)){
                $parcelJpa->num_guia = $request->num_guia;
            }

            if(isset($request->num_bill)){
                $parcelJpa->num_guia = $request->num_bill;
            }

            $parcelJpa->_model = $request->_model;
            $parcelJpa->currency = $request->currency;
            $parcelJpa->warranty = $request->warranty;

            if(isset($request->description)){
                $parcelJpa->description = $request->description;
            }

            $parcelJpa->mount_product = $request->mount_product;
            $parcelJpa->total = $request->total;

            if(isset($request->igv)){
                $parcelJpa->igv = $request->igv;
            }

            $parcelJpa->amount = $request->amount;
            $parcelJpa->value_unity = $parcelJpa->value_unity;
            $parcelJpa->price_unity = $request->price_unity;

            if(isset($request->mr_revenue)){
                $parcelJpa->mr_revenue = $request->mr_revenue;
            }

            $parcelJpa->price_buy = $request->price_buy;
            $parcelJpa->_entry_product = $entryProductJpa->id;
            $parcelJpa->status = "1";
            $parcelJpa->save();

            

            $response->setStatus(200);
            $response->setMessage('Producto agregado correctamente');
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ', ln:' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }

    }

    public function paginate(Request $request)
    {

        $hola = '';

    }

    public function update(Request $request)
    {
        // pending
    }

}
