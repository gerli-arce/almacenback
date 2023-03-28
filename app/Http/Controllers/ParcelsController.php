<?php

namespace App\Http\Controllers;

use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\EntryProducts;
use App\Models\Parcel;
use App\Models\Product;
use App\Models\Response;
use App\Models\Stock;
use Exception;
use Illuminate\Http\Request;

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

            if (!gValidate::check($role->permissions, $branch, 'parcels', 'create')) {
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
            if (isset($request->_technical)) {
                $entryProductJpa->_technical = $request->_technical;
            }
            $entryProductJpa->entry_date = gTrace::getDate('mysql');
            $entryProductJpa->_type_operation = $request->_type_operation;
            if (isset($request->_tower)) {
                $entryProductJpa->_tower = $request->_tower;
            }
            if (isset($request->condition_product)) {
                $entryProductJpa->condition_product = $request->condition_product;
            }
            if (isset($request->product_status)) {
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

            if (isset($request->num_voucher)) {
                $parcelJpa->num_voucher = $request->num_voucher;
            }

            if (isset($request->num_guia)) {
                $parcelJpa->num_guia = $request->num_guia;
            }

            if (isset($request->num_bill)) {
                $parcelJpa->num_guia = $request->num_bill;
            }

            $parcelJpa->_model = $request->_model;
            $parcelJpa->currency = $request->currency;
            $parcelJpa->warranty = $request->warranty;

            if (isset($request->description)) {
                $parcelJpa->description = $request->description;
            }

            $parcelJpa->mount_product = $request->mount_product;
            $parcelJpa->total = $request->total;

            if (isset($request->igv)) {
                $parcelJpa->igv = $request->igv;
            }

            $parcelJpa->amount = $request->amount;
            $parcelJpa->value_unity = $parcelJpa->value_unity;
            $parcelJpa->price_unity = $request->price_unity;

            if (isset($request->mr_revenue)) {
                $parcelJpa->mr_revenue = $request->mr_revenue;
            }

            $parcelJpa->price_buy = $request->price_buy;
            $parcelJpa->_entry_product = $entryProductJpa->id;
            $parcelJpa->status = "1";
            $parcelJpa->save();

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            if ($request->type == "EQUIPO") {
                if (!isset($request->data)) {
                    throw new Exception("Error: No deje campos vacíos");
                }
                foreach ($request->data as $product) {
                    $productValidation = Product::select(['mac', 'serie'])
                        ->where('mac', $product['mac'])
                        ->orWhere('serie', $product['serie'])
                        ->first();
                    if ($productValidation) {
                        if ($productValidation->mac == $product['mac']) {
                            throw new Exception("Ya existe un produto con el número MAC: " . $product['mac']);
                        }
                        if ($productValidation->serie == $product['serie']) {
                            throw new Exception("Ya existe un produto con el número de serie: " . $product['serie']);
                        }
                    }

                    $productJpa = new Product();
                    $productJpa->type = $request->type;
                    $productJpa->_branch = $branch_->id;
                    $productJpa->relative_id = guid::short();
                    $productJpa->_brand = $request->_brand;
                    $productJpa->_category = $request->_category;
                    $productJpa->_supplier = $request->_supplier;
                    $productJpa->_model = $request->_model;
                    $productJpa->_unity = $request->_unity;
                    $productJpa->currency = $request->currency;
                    $productJpa->price_buy = $request->price_buy;
                    $productJpa->price_sale = $request->price_sale;
                    $productJpa->mac = $product['mac'];
                    $productJpa->serie = $product['serie'];
                    $productJpa->mount = "1";
                    if (isset($request->num_gia)) {
                        $productJpa->num_gia = $request->num_gia;
                    }
                    if (isset($request->num_bill)) {
                        $productJpa->num_bill = $request->num_bill;
                    }
                    if (isset($request->warranty)) {
                        $productJpa->warranty = $request->warranty;
                    }
                    $productJpa->date_entry = $request->date_entry;
                    $productJpa->_entry_product = $entryProduct->id;
                    $productJpa->condition_product = $request->condition_product;
                    $productJpa->product_status = $request->product_status;
                    $productJpa->disponibility = $request->disponibility;
                    if (isset($request->description)) {
                        $productJpa->description = $request->description;
                    }
                    $productJpa->creation_date = gTrace::getDate('mysql');
                    $productJpa->_creation_user = $userid;
                    $productJpa->update_date = gTrace::getDate('mysql');
                    $productJpa->_update_user = $userid;
                    $productJpa->status = "1";
                    $productJpa->save();

                    $stock = Stock::where('_model', $request->_model)
                        ->where('_branch', $branch_->id)
                        ->first();
                    $stock->mount = intval($stock->mount) + 1;
                    $stock->save();
                }
            } else if ($request->type == "MATERIAL") {
                if (!isset($request->mount)) {
                    throw new Exception("Error: No deje campos vacíos");
                }

                $material = Product::select([
                    'id',
                    'mount',
                    'num_gia',
                    'num_bill',
                    '_model',
                    '_category',
                    '_brand',
                ])
                    ->where('_model', $request->_model)
                    ->where('_category', $request->_category)
                    ->where('_brand', $request->_brand)
                    ->where('_branch', $branch_->id)
                    ->first();

                if (isset($material)) {
                    $mount_old = $material->mount;
                    $mount_new = $mount_old + $request->mount;

                    $material->type = $request->type;
                    $material->_branch = $branch_->id;
                    $material->relative_id = guid::short();
                    $material->_brand = $request->_brand;
                    $material->_category = $request->_category;
                    $material->_supplier = $request->_supplier;
                    $material->_model = $request->_model;
                    $material->_unity = $request->_unity;
                    $material->mount = $mount_new;
                    $material->currency = $request->currency;
                    $material->price_buy = $request->price_buy;
                    $material->price_sale = $request->price_sale;
                    if (isset($request->num_gia)) {
                        $material->num_gia = $request->num_gia;
                    }
                    if (isset($request->num_bill)) {
                        $material->num_bill = $request->num_bill;
                    }
                    if (isset($request->warranty)) {
                        $material->warranty = $request->warranty;
                    }
                    $material->date_entry = $request->date_entry;
                    $material->_entry_product = $entryProduct->id;
                    $material->condition_product = $request->condition_product;
                    $material->product_status = $request->product_status;
                    $material->disponibility = $request->disponibility;
                    if (isset($request->description)) {
                        $material->description = $request->description;
                    }
                    $material->creation_date = gTrace::getDate('mysql');
                    $material->_creation_user = $userid;
                    $material->update_date = gTrace::getDate('mysql');
                    $material->_update_user = $userid;
                    $material->status = "1";
                    $material->save();

                    $stock = Stock::where('_model', $request->_model)
                        ->where('_branch', $branch_->id)
                        ->first();
                    $stock->mount = intval($stock->mount) + intval($request->mount);
                    $stock->save();
                } else {
                    $productJpa = new Product();
                    $productJpa->type = $request->type;
                    $productJpa->_branch = $branch_->id;
                    $productJpa->relative_id = guid::short();
                    $productJpa->_brand = $request->_brand;
                    $productJpa->_category = $request->_category;
                    $productJpa->_supplier = $request->_supplier;
                    $productJpa->_model = $request->_model;
                    $productJpa->_unity = $request->_unity;
                    $productJpa->mount = $request->mount;
                    $productJpa->currency = $request->currency;
                    $productJpa->price_buy = $request->price_buy;
                    $productJpa->price_sale = $request->price_sale;
                    if (isset($request->num_gia)) {
                        $productJpa->num_gia = $request->num_gia;
                    }
                    if (isset($request->num_bill)) {
                        $productJpa->num_bill = $request->num_bill;
                    }
                    if (isset($request->warranty)) {
                        $material->warranty = $request->warranty;
                    }
                    if (isset($request->warranty)) {
                        $productJpa->warranty = $request->warranty;
                    }
                    $productJpa->date_entry = $request->date_entry;
                    $productJpa->_entry_product = $entryProduct->id;
                    $productJpa->condition_product = $request->condition_product;
                    $productJpa->product_status = $request->product_status;
                    $productJpa->disponibility = $request->disponibility;
                    if (isset($request->description)) {
                        $productJpa->description = $request->description;
                    }
                    $productJpa->creation_date = gTrace::getDate('mysql');
                    $productJpa->_creation_user = $userid;
                    $productJpa->update_date = gTrace::getDate('mysql');
                    $productJpa->_update_user = $userid;
                    $productJpa->status = "1";
                    $productJpa->save();

                    $stock = Stock::where('_model', $request->_model)
                        ->where('_branch', $branch_->id)
                        ->first();
                    $stock->mount = intval($stock->mount) + intval($request->mount);
                    $stock->save();
                }
            }

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
