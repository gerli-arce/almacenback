<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\DetailSale;
use App\Models\DetailsParcel;
use App\Models\EntryDetail;
use App\Models\EntryProducts;
use App\Models\Parcel;
use App\Models\Product;
use App\Models\ViewProducts;
use App\Models\Response;
use App\Models\SalesProducts;
use App\Models\Stock;
use App\Models\ViewParcelsCreated;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParcelsCreatedController extends Controller
{

    public function generateGuia(Request $request)
    {
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'read')) {
                throw new Exception('No tienes permisos para listar encomiedas creadas');
            }

            $options = new Options();
            $options->set('isRemoteEnabled', true);

            $pdf = new Dompdf($options);

            $template = file_get_contents('../storage/templates/reportGuia.html');

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $parcelJpa = Parcel::select([
                'parcels.id as id',
                'parcels.date_send as date_send',
                'parcels.date_entry as date_entry',
                'parcels._business_transport as _business_transport',
                'transport.id as business_transport__id',
                'transport.name as business_transport__name',
                'parcels._branch_send as _branch_send',
                'br_send.id as branch_send__id',
                'br_send.name as branch_send__name',
                'parcels._branch_destination as _branch_destination',
                'br_des.id as branch_destination__id',
                'br_des.name as branch_destination__name',
                'parcels.price_transport as price_transport',
                'responsible.id as responsible_pickup__id',
                'responsible.name as responsible_pickup__name',
                'responsible.lastname as responsible_pickup__lastname',
                'sender.id as sender__id',
                'sender.name as sender__name',
                'sender.lastname as sender__lastname',
                'parcels.parcel_type as parcel_type',
                'parcels.parcel_status as parcel_status',
                'parcels.description as description',
                'parcels._branch as _branch',
                'parcels.creation_date as creation_date',
                'parcels._creation_user as _creation_user',
                'parcels.update_date as update_date',
                'parcels._update_user as _update_user',
                'parcels.status as status',
            ])
                ->join('branches as br_send', 'parcels._branch_send', 'br_send.id')
                ->join('users', 'parcels._creation_user', 'users.id')
                ->join('people as sender', 'users._person', 'sender.id')
                ->join('branches as br_des', 'parcels._branch_destination', 'br_des.id')
                ->join('people as responsible', 'parcels._responsible_pickup', 'responsible.id')
                ->join('transport', 'parcels._business_transport', 'transport.id')
                ->whereNotNull('parcels.status')
                ->find($request->id);

            $parcel = gJSON::restore($parcelJpa->toArray(), '__');

            $detailsParcelJpa = DetailsParcel::select(
                'details_parcel.id as id',
                'details_parcel._parcel as _parcel',
                'details_parcel.mount_new as mount_new',
                'details_parcel.mount_second as mount_second',
                'details_parcel.mount_ill_fated as mount_ill_fated',
                'products.id as product__id',
                'products.type as product__type',
                'products.mac as product__mac',
                'products.price_sale as product__price_sale',
                'products.currency as product__currency',
                'products.serie as product__serie',
                'products.condition_product as product__condition_product',
                'products.product_status as product__product_status',
                'models.id as product__model__id',
                'models.model as product__model__model',
                'models.relative_id as product__model__relative_id',
                'unities.id as product__model__unity__id',
                'unities.name as product__model__unity__name',
                'details_parcel.description as description',
                'details_parcel.status as status'
            )
                ->join('products', 'details_parcel._product', 'products.id')
                ->join('models', 'products._model', 'models.id')
                ->join('unities', 'models._unity', 'unities.id')
                ->where('_parcel', $parcel['id'])
                ->whereNotNull('details_parcel.status')
                ->get();

            $sumary = '';
            $details = [];

            foreach ($detailsParcelJpa as $detail) {
                $detail = gJSON::restore($detail->toArray(), '__');
                $details[] = $detail;
            }

            $models = array();
            foreach ($details as $product) {
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
                        <center style='font-size:12px;'>
                            Nu:<strong>{$detail['mount_new']}</strong> |
                            Se:<strong>{$detail['mount_second']}</strong> |
                            Ma:<strong>{$detail['mount_ill_fated']}</strong>
                        </center>
                    </td>
                    <td><center style='font-size:12px;'>{$detail['unity']}</center></td>
                    <td><center style='font-size:12px;'>{$detail['model']}</center></td>
                </tr>
                ";

                $count = $count + 1;
            }

            $parcel['details'] = $details;

            $template = str_replace(
                [
                    '{branch_name}',
                    '{issue_long_date}',
                    '{num_guia}',
                    '{branch_send}',
                    '{branch_designation}',
                    '{responsible_pickup}',
                    '{date_send}',
                    '{business_transport}',
                    '{transport_price}',
                    '{description}',
                    '{remitente}',
                    '{reseptor}',
                    '{summary}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    str_pad($parcel['id'], 6, "0", STR_PAD_LEFT),
                    $parcel['branch_send']['name'],
                    $parcel['branch_destination']['name'],
                    $parcel['responsible_pickup']['name'] . ' ' . $parcel['responsible_pickup']['lastname'],
                    $parcel['date_send'],
                    $parcel['business_transport']['name'],
                    $parcel['price_transport'],
                    $parcel['description'],
                    $parcel['sender']['name'] . ' ' . $parcel['sender']['lastname'],
                    $parcel['responsible_pickup']['name'] . ' ' . $parcel['responsible_pickup']['lastname'],
                    $sumary,
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

    public function generateReportParcelsSendsByBranchByMonth(Request $request)
    {
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'read')) {
                throw new Exception('No tienes permisos para listar encomiedas creadas');
            }

            $options = new Options();
            $options->set('isRemoteEnabled', true);

            $pdf = new Dompdf($options);

            $template = file_get_contents('../storage/templates/reportForMonthParcelsCreated.html');

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();
            $branch_selected = Branch::find($request->branch);
            $branchId = $branch_->id;

            $query = ViewParcelsCreated::select();

            $query->where(function ($query) use ($branchId, $request) {
                if (isset($request->date_start) || isset($request->date_end)) {
                    $query->where('branch_send__id', $branchId)
                        ->where('branch_destination__id', $request->branch)
                        ->where('date_send', '>=', $request->date_start)
                        ->where('date_send', '<=', $request->date_end);
                } else {
                    $query->where('branch_send__id', $branchId)
                        ->where('branch_destination__id', $request->branch);
                }
            });

            $SalesProductsJpa = $query->get();

            $parcels = array();
            foreach ($SalesProductsJpa as $parcelJpa) {
                $parcel = gJSON::restore($parcelJpa->toArray(), '__');
                $detailsJpa = DetailsParcel::select(
                    'details_parcel.id as id',
                    'details_parcel._parcel as _parcel',
                    'details_parcel.mount_new as mount_new',
                    'details_parcel.mount_second as mount_second',
                    'details_parcel.mount_ill_fated as mount_ill_fated',
                    'products.id as product__id',
                    'products.type as product__type',
                    'products.mac as product__mac',
                    'products.price_sale as product__price_sale',
                    'products.currency as product__currency',
                    'products.serie as product__serie',
                    'products.condition_product as product__condition_product',
                    'products.product_status as product__product_status',
                    'models.id as product__model__id',
                    'models.model as product__model__model',
                    'models.relative_id as product__model__relative_id',
                    'unities.id as product__model__unity__id',
                    'unities.name as product__model__unity__name',
                    'details_parcel.description as description',
                    'details_parcel.status as status'
                )
                    ->join('products', 'details_parcel._product', 'products.id')
                    ->join('models', 'products._model', 'models.id')
                    ->join('unities', 'models._unity', 'unities.id')
                    ->where('_parcel', $parcelJpa['id'])
                    ->whereNotNull('details_parcel.status')
                    ->get();

                $details = array();
                foreach ($detailsJpa as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                }

                $parcel['details'] = $details;
                $parcels['parcels'][] = $parcel;

            }

            $parcels['mount'] = count($parcels['parcels']);

            $rquipments = array();

            $detailsByParcelsend = array();
            foreach ($parcels['parcels'] as $parcelJpa) {
                foreach ($parcelJpa['details'] as $detailsJpa) {
                    $detailsByParcelsend[] = $detailsJpa;
                    if($detailsJpa['product']['type'] === "EQUIPO"){
                        $productJpa = ViewProducts::find($detailsJpa['product']['id']);
                        $product = gJSON::restore($productJpa->toArray(), '__');
                        $rquipments[] = $product;
                    }
                }
            }

            $products = array();

            foreach($rquipments as $productJpa){
                $model = $productJpa['model']['model'];
                $stock = 0;
                $liq = 0;

                if($productJpa['disponibility'] == "DISPONIBLE"){
                    $stock = 1;
                }else{
                    $liq = 1;
                }

                if(isset($products[$model])){
                    $products[$model]['all'] +=1;
                    $products[$model]['stock'] += $stock;
                    $products[$model]['liq'] += $liq; 
                }else{
                    $products[$model] = array(
                        'model'=>$model,
                        'all'=>1,
                        'stock'=>$stock,
                        'liq'=>$liq,
                    );
                }
            }

            $details_send = '';

            foreach($products as $productJpa){
                $details_send .= "
                    <tr>
                        <td><center style='font-size:15px;'>{$productJpa['model']}</center></td>
                        <td><center style='font-size:15px;'>{$productJpa['all']}</center></td>
                        <td><center style='font-size:15px;'>{$productJpa['stock']}</center></td>
                        <td><center style='font-size:15px;'>{$productJpa['liq']}</center></td>
                    </tr>
                ";
            }


            $parcels['details'] = $detailsByParcelsend;


            $models = array();
            foreach ($parcels['details'] as $product) {
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

            $parcels['products'] = array_values($models);


            $count = 1;
            $sumary = '';

            foreach ($parcels['products'] as $detail) {

                $sumary .= "
                <tr>
                    <td><center style='font-size:15px;'>{$count}</center></td>
                    <td><center style='font-size:12px;'>{$detail['model']}</center></td>
                    <td><center style='font-size:15px;'>{$detail['mount_new']}</center></td>
                    <td><center style='font-size:15px;'>{$detail['mount_second']}</center></td>
                    <td><center style='font-size:15px;'>{$detail['mount_ill_fated']}</center></td>
                    <td><center style='font-size:15px;'>{$detail['unity']}</center></td>
                </tr>
                ";

                $count = $count + 1;
            }

            $count = 1;
        

            $template = str_replace(
                [
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{branch_selected}',
                    '{parcels}',
                    '{date_start}',
                    '{date_end}',
                    '{details_send}',
                    '{summary}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $branch_selected->name,
                    $parcels['mount'],
                    $request->date_start,
                    $request->date_end,
                    $details_send,
                    $sumary,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();

            return $pdf->stream('Guia.pdf');

            // $response = new Response();
            // $response->setData($products);
            // $response->setMessage('Operacion correcta');
            // $response->setStatus(200);

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

    public function generateReportParcelsReceivedsByBranchByMonth(Request $request)
    {
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'read')) {
                throw new Exception('No tienes permisos para listar encomiedas creadas');
            }

            $options = new Options();
            $options->set('isRemoteEnabled', true);

            $pdf = new Dompdf($options);

            $template = file_get_contents('../storage/templates/reportForMonthParcelsCreated.html');

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();
            $branch_selected = Branch::find($request->branch);
            $branchId = $branch_->id;

            $query = ViewParcelsCreated::select();

            $query->where(function ($query) use ($branchId, $request) {
                if (isset($request->date_start) || isset($request->date_end)) {
                    $query->where('branch_send__id', $branchId)
                        ->where('branch_destination__id', $request->branch)
                        ->where('date_send', '>=', $request->date_start)
                        ->where('date_send', '<=', $request->date_end);
                } else {
                    $query->where('branch_send__id', $branchId)
                        ->where('branch_destination__id', $request->branch);
                }
            })
                ->orWhere(function ($query) use ($branchId, $request) {
                    if (isset($request->date_start) || isset($request->date_end)) {
                        $query->where('branch_send__id', $request->branch)
                            ->Where('branch_destination__id', $branchId)
                            ->where('date_send', '>=', $request->date_start)
                            ->where('date_send', '<=', $request->date_end);
                    } else {
                        $query->where('branch_send__id', $request->branch)
                            ->Where('branch_destination__id', $branchId);
                    }
                });

            $SalesProductsJpa = $query->get();

            $parcels = array();
            foreach ($SalesProductsJpa as $parcelJpa) {
                $parcel = gJSON::restore($parcelJpa->toArray(), '__');
                $detailsJpa = DetailsParcel::select(
                    'details_parcel.id as id',
                    'details_parcel._parcel as _parcel',
                    'details_parcel.mount_new as mount_new',
                    'details_parcel.mount_second as mount_second',
                    'details_parcel.mount_ill_fated as mount_ill_fated',
                    'products.id as product__id',
                    'products.type as product__type',
                    'products.mac as product__mac',
                    'products.price_sale as product__price_sale',
                    'products.currency as product__currency',
                    'products.serie as product__serie',
                    'products.condition_product as product__condition_product',
                    'products.product_status as product__product_status',
                    'models.id as product__model__id',
                    'models.model as product__model__model',
                    'models.relative_id as product__model__relative_id',
                    'unities.id as product__model__unity__id',
                    'unities.name as product__model__unity__name',
                    'details_parcel.description as description',
                    'details_parcel.status as status'
                )
                    ->join('products', 'details_parcel._product', 'products.id')
                    ->join('models', 'products._model', 'models.id')
                    ->join('unities', 'models._unity', 'unities.id')
                    ->where('_parcel', $parcelJpa['id'])
                    ->whereNotNull('details_parcel.status')
                    ->get();

                $details = array();
                foreach ($detailsJpa as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                }

                $parcel['details'] = $details;
                if ($parcel['branch_send']['id'] == $branch_->id) {
                    $parcels['send'][] = $parcel;
                } else {
                    $parcels['received'][] = $parcel;
                }
            }

            if (isset($parcels['send'])) {
                $parcels['parcels_send'] = count($parcels['send']);
            } else {
                $parcels['parcels_send'] = 0;
            }

            if (isset($parcels['received'])) {
                $parcels['parcels_received'] = count($parcels['received']);
            } else {
                $parcels['parcels_received'] = 0;
            }

            $detailsByParcelsend = array();
            if (isset($parcels['send'])) {
                foreach ($parcels['send'] as $ParcelJpa) {
                    foreach ($ParcelJpa['details'] as $detailsJpa) {
                        $detailsByParcelsend[] = $detailsJpa;
                    }
                }
            }
            $parcels['details_send'] = $detailsByParcelsend;

            $detailsByParcelreceived = array();
            if (isset($parcels['received'])) {
                foreach ($parcels['received'] as $ParcelJpa) {
                    foreach ($ParcelJpa['details'] as $detailJpa) {
                        $detailsByParcelreceived[] = $detailJpa;
                    }
                }
            }
            $parcels['details_received'] = $detailsByParcelreceived;

            $models_send = array();
            foreach ($parcels['details_send'] as $product) {
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
                if (isset($models_send[$model])) {
                    $models_send[$model]['mount_new'] += $mount_new;
                    $models_send[$model]['mount_second'] += $mount_second;
                    $models_send[$model]['mount_ill_fated'] += $mount_ill_fated;
                } else {
                    $models_send[$model] = array(
                        'model' => $model,
                        'mount_new' => $mount_new,
                        'mount_second' => $mount_second,
                        'mount_ill_fated' => $mount_ill_fated,
                        'relative_id' => $relativeId,
                        'unity' => $unity);
                }
            }

            $parcels['products_send'] = array_values($models_send);

            $models_received = array();
            foreach ($parcels['details_received'] as $product) {
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
                if (isset($models_received[$model])) {
                    $models_received[$model]['mount_new'] += $mount_new;
                    $models_received[$model]['mount_second'] += $mount_second;
                    $models_received[$model]['mount_ill_fated'] += $mount_ill_fated;
                } else {
                    $models_received[$model] = array(
                        'model' => $model,
                        'mount_new' => $mount_new,
                        'mount_second' => $mount_second,
                        'mount_ill_fated' => $mount_ill_fated,
                        'relative_id' => $relativeId,
                        'unity' => $unity);
                }
            }

            $parcels['products_received'] = array_values($models_received);

            $count = 1;
            $sumary_send = '';

            foreach ($parcels['products_send'] as $detail) {

                $sumary_send .= "
                <tr>
                    <td><center style='font-size:15px;'>{$count}</center></td>
                    <td><center style='font-size:12px;'>{$detail['model']}</center></td>
                    <td><center style='font-size:15px;'>{$detail['mount_new']}</center></td>
                    <td><center style='font-size:15px;'>{$detail['mount_second']}</center></td>
                    <td><center style='font-size:15px;'>{$detail['mount_ill_fated']}</center></td>
                    <td><center style='font-size:15px;'>{$detail['unity']}</center></td>
                </tr>
                ";

                $count = $count + 1;
            }

            $count = 1;
            $sumary_received = '';

            foreach ($parcels['products_received'] as $detail) {

                $sumary_received .= "
                <tr>
                    <td><center style='font-size:15px;'>{$count}</center></td>
                    <td><center style='font-size:12px;'>{$detail['model']}</center></td>
                    <td><center style='font-size:15px;'>{$detail['mount_new']}</center></td>
                    <td><center style='font-size:15px;'>{$detail['mount_second']}</center></td>
                    <td><center style='font-size:15px;'>{$detail['mount_ill_fated']}</center></td>
                    <td><center style='font-size:15px;'>{$detail['unity']}</center></td>
                </tr>
                ";

                $count = $count + 1;
            }

            $template = str_replace(
                [
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{branch_selected}',
                    '{parcels_send}',
                    '{parcel_received}',
                    '{date_start}',
                    '{date_end}',
                    '{summary_send}',
                    '{summary_received}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $branch_selected->name,
                    $parcels['parcels_send'],
                    $parcels['parcels_received'],
                    $request->date_start,
                    $request->date_end,
                    $sumary_send,
                    $sumary_received,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();

            return $pdf->stream('Guia.pdf');

            // $response = new Response();
            // $response->setData($parcels);
            // $response->setMessage('Operacion correcta');
            // $response->setStatus(200);

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

    public function store(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'create')) {
                throw new Exception('No tienes permisos para crear encomiendas');
            }

            if (
                !isset($request->date_send) ||
                !isset($request->_branch_destination) ||
                !isset($request->_business_transport) ||
                !isset($request->price_transport) ||
                !isset($request->_type_operation) ||
                !isset($request->_responsible_pickup)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            // DATOS DE ENCOMIENDA
            $parcelJpa = new Parcel();
            $parcelJpa->date_send = $request->date_send;
            $parcelJpa->_branch_destination = $request->_branch_destination;
            $parcelJpa->_business_transport = $request->_business_transport;
            $parcelJpa->_responsible_pickup = $request->_responsible_pickup;
            $parcelJpa->price_transport = $request->price_transport;
            $parcelJpa->parcel_type = "GENERATED";
            $parcelJpa->parcel_status = "ENVIADO";
            $parcelJpa->property = "SEND";

            if (isset($request->description)) {
                $parcelJpa->description = $request->description;
            }

            // REGISTRO DE SALIDA DE PRODUCTOS
            $salesProduct = new SalesProducts();
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_type_operation = $request->_type_operation;
            $salesProduct->date_sale = $request->date_send;
            $salesProduct->status_sale = "PENDIENG";
            $salesProduct->_issue_user = $userid;

            if (isset($request->description)) {
                $salesProduct->description = $request->description;
            }

            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            $parcelJpa->_sale_product = $salesProduct->id;
            $parcelJpa->_branch_send = $branch_->id;
            $parcelJpa->_branch = $branch_->id;
            $parcelJpa->creation_date = gTrace::getDate('mysql');
            $parcelJpa->_creation_user = $userid;
            $parcelJpa->update_date = gTrace::getDate('mysql');
            $parcelJpa->_update_user = $userid;
            $parcelJpa->status = "1";
            $parcelJpa->save();

            if (isset($request->products)) {
                foreach ($request->products as $product) {
                    $productJpa = Product::find($product['product']['id']);
                    if ($product['product']['type'] == "MATERIAL") {

                        $stock = Stock::where('_model', $productJpa->_model)
                            ->where('_branch', $branch_->id)
                            ->first();
                        $stock->mount_new = $stock->mount_new - $product['mount_new'];
                        $stock->mount_second = $stock->mount_second - $product['mount_second'];
                        $stock->mount_ill_fated = $stock->mount_ill_fated - $product['mount_ill_fated'];

                        $productJpa->mount = $stock->mount_new + $stock->mount_second;
                        $productJpa->save();
                        $stock->save();
                    } else {

                        $productJpa->disponibility = "EN ENCOMIENDA";

                        $productJpa->save();

                        $stock = Stock::where('_model', $productJpa->_model)
                            ->where('_branch', $branch_->id)
                            ->first();
                        if ($productJpa->product_status == "NUEVO") {
                            $stock->mount_new = $stock->mount_new - 1;
                        } else if ($productJpa->product_status == "SEMINUEVO") {
                            $stock->mount_second = $stock->mount_second - 1;
                        }
                        $stock->save();
                    }

                    $detailsParcelJpa = new DetailsParcel();
                    $detailsParcelJpa->_product = $productJpa->id;
                    $detailsParcelJpa->_parcel = $parcelJpa->id;
                    $detailsParcelJpa->mount_new = $product['mount_new'];
                    $detailsParcelJpa->mount_second = $product['mount_second'];
                    $detailsParcelJpa->mount_ill_fated = $product['mount_ill_fated'];
                    $detailsParcelJpa->status = "ENVIANDO";
                    $detailsParcelJpa->save();

                    $detailSale = new DetailSale();
                    $detailSale->_product = $productJpa->id;
                    $detailSale->mount_new = $product['mount_new'];
                    $detailSale->mount_second = $product['mount_second'];
                    $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                    $detailSale->_sales_product = $salesProduct->id;
                    $detailSale->status = '1';
                    $detailSale->save();
                }
            }

            $response->setStatus(200);
            $response->setMessage('Encomienda creada correctamente');
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

    public function update(Request $request)
    {
        $response = new Response();
        try {
            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'update')) {
                throw new Exception('No tienes permisos para actualizar encomiendas');
            }

            $parcelJpa = Parcel::select(['id'])->find($request->id);

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            if (isset($request->date_send)) {
                $parcelJpa->date_send = $request->date_send;
            }

            if (isset($request->_branch_destination)) {
                $parcelJpa->_branch_destination = $request->_branch_destination;
            }

            if (isset($request->_responsible_pickup)) {
                $parcelJpa->_responsible_pickup = $request->_responsible_pickup;
            }

            if (isset($request->_business_transport)) {
                $parcelJpa->_business_transport = $request->_business_transport;
            }

            if (isset($request->price_transport)) {
                $parcelJpa->price_transport = $request->price_transport;
            }

            if (isset($request->description)) {
                $parcelJpa->description = $request->description;
            }

            if (isset($request->_branch)) {
                $parcelJpa->_branch = $branch_->id;
            }

            $parcelJpa->update_date = gTrace::getDate('mysql');
            $parcelJpa->_update_user = $userid;

            if (gValidate::check($role->permissions, $branch, 'parcels', 'change_status')) {
                if (isset($request->status)) {
                    $parcelJpa->status = $request->status;
                }
            }

            $parcelJpa->save();

            $response->setStatus(200);
            $response->setMessage('La encomienda ha sido actualizado correctamente');
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

    public function updateProductsByParcel(Request $request)
    {
        $response = new Response();
        try {
            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'update')) {
                throw new Exception('No tienes permisos para actualizar encomiendas');
            }

            $parcelJpa = Parcel::find($request->id);
            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $SalesProducts = SalesProducts::find($parcelJpa->_sale_product);

            if (isset($request->data)) {
                foreach ($request->data as $product) {
                    if (isset($product['id'])) {
                        $productJpa = Product::find($product['product']['id']);
                        $detailParcel = DetailsParcel::find($product['id']);
                        $detailSale = DetailSale::where('_product', $detailParcel->_product)->where('_sales_product', $SalesProducts->id)->first();
                        if ($product['product']['type'] == "MATERIAL") {

                            $stock = Stock::where('_model', $productJpa->_model)
                                ->where('_branch', $branch_->id)
                                ->first();

                            if (intval($detailParcel->mount_new) != intval($product['mount_new'])) {
                                if (intval($detailParcel->mount_new) > intval($product['mount_new'])) {
                                    $mount_dif = intval($detailParcel->mount_new) - intval($product['mount_new']);
                                    $stock->mount_new = $stock->mount_new + $mount_dif;
                                } else if (intval($detailParcel->mount) < intval($product['mount'])) {
                                    $mount_dif = intval($product['mount']) - intval($detailParcel->mount);
                                    $stock->mount_new = $stock->mount_new - $mount_dif;
                                }
                            }

                            if (intval($detailParcel->mount_second) != intval($product['mount_second'])) {
                                if (intval($detailParcel->mount_second) > intval($product['mount_second'])) {
                                    $mount_dif = intval($detailParcel->mount_second) - intval($product['mount_second']);
                                    $stock->mount_second = $stock->mount_second + $mount_dif;
                                } else if (intval($detailParcel->mount) < intval($product['mount'])) {
                                    $mount_dif = intval($product['mount']) - intval($detailParcel->mount);
                                    $stock->mount_second = $stock->mount_second - $mount_dif;
                                }
                            }

                            if (intval($detailParcel->mount_ill_fated) != intval($product['mount_ill_fated'])) {
                                if (intval($detailParcel->mount_ill_fated) > intval($product['mount_ill_fated'])) {
                                    $mount_dif = intval($detailParcel->mount_ill_fated) - intval($product['mount_ill_fated']);
                                    $stock->mount_ill_fated = $stock->mount_ill_fated + $mount_dif;
                                } else if (intval($detailParcel->mount) < intval($product['mount'])) {
                                    $mount_dif = intval($product['mount']) - intval($detailParcel->mount);
                                    $stock->mount_ill_fated = $stock->mount_ill_fated - $mount_dif;
                                }
                            }

                            $stock->save();
                            $productJpa->mount = $stock->mount_new + $stock->mount_second;
                            $detailParcel->mount_new = $product['mount_new'];
                            $detailParcel->mount_second = $product['mount_second'];
                            $detailParcel->mount_ill_fated = $product['mount_ill_fated'];

                            $detailSale->mount_new = $product['mount_new'];
                            $detailSale->mount_second = $product['mount_second'];
                            $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                        }

                        $detailParcel->description = $product['description'];
                        $detailParcel->save();
                        $detailSale->save();

                        $productJpa->save();
                    } else {
                        $productJpa = Product::find($product['product']['id']);

                        if ($product['product']['type'] == "MATERIAL") {
                            $stock = Stock::where('_model', $productJpa->_model)
                                ->where('_branch', $branch_->id)
                                ->first();

                            $stock->mount_new = $stock->mount_new - $product['mount_new'];
                            $stock->mount_second = $stock->mount_second - $product['mount_second'];
                            $stock->mount_ill_fated = $stock->mount_ill_fated - $product['mount_ill_fated'];

                            $productJpa->mount = $stock->mount_new - $stock->mount_second;
                            $stock->mount_new = $productJpa->mount;
                            $stock->save();
                        } else {
                            $productJpa->disponibility = "EN ENCOMIENDA";
                            $stock = Stock::where('_model', $productJpa->_model)
                                ->where('_branch', $branch_->id)
                                ->first();
                            if ($productJpa->product_status == "NUEVO") {
                                $stock->mount_new = $stock->mount_new - 1;
                            } else if ($productJpa->product_status == "SEMINUEVO") {
                                $stock->mount_second = $stock->mount_second - 1;
                            } else {
                                $stock->mount_ill_fated = $stock->mount_ill_fated - 1;
                            }
                            $stock->save();
                        }

                        $detailsParcelJpa = new DetailsParcel();
                        $detailsParcelJpa->_product = $productJpa->id;
                        $detailsParcelJpa->_parcel = $parcelJpa->id;
                        $detailsParcelJpa->mount_new = $product['mount_new'];
                        $detailsParcelJpa->mount_second = $product['mount_second'];
                        $detailsParcelJpa->mount_ill_fated = $product['mount_ill_fated'];
                        $detailsParcelJpa->status = "ENVIANDO";
                        $detailsParcelJpa->save();

                        $detailSale = new DetailSale();
                        $detailSale->_product = $productJpa->id;
                        $detailSale->mount_new = $product['mount_new'];
                        $detailSale->mount_second = $product['mount_second'];
                        $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                        $detailSale->_sales_product = $SalesProducts->id;
                        $detailSale->status = '1';
                        $detailSale->save();

                        $productJpa->save();
                    }
                }
            }

            $response->setStatus(200);
            $response->setMessage('Los productos de la liquidación se ha actualizado correctamente.');
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

    public function cancelUseProduct(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);

            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'update')) {
                throw new Exception('No tienes permisos para actualizar');
            }

            if (!isset($request->id)) {
                throw new Exception('Error: No deje campos vacíos');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $parcelJpa = Parcel::find($request->_parcel);
            $parcelJpa->_update_user = $userid;
            $parcelJpa->update_date = gTrace::getDate('mysql');

            $detailParcelJpa = DetailsParcel::find($request->id);
            $detailParcelJpa->status = null;

            $productJpa = Product::find($request->product['id']);
            if ($productJpa->type == "MATERIAL") {

                $stock = Stock::where('_model', $productJpa->_model)
                    ->where('_branch', $branch_->id)
                    ->first();
                $stock->mount_new = $stock->mount_new + $detailParcelJpa->mount_new;
                $stock->mount_second = $stock->mount_second + $detailParcelJpa->mount_second;
                $stock->mount_ill_fated = $stock->mount_ill_fated + $detailParcelJpa->mount_ill_fated;
                $stock->save();
                $productJpa->mount = $stock->mount_new + $stock->mount_second;
            } else if ($productJpa->type == "EQUIPO") {
                $productJpa->disponibility = "DISPONIBLE";
                $stock = Stock::where('_model', $productJpa->_model)
                    ->where('_branch', $branch_->id)
                    ->first();
                if ($productJpa->product_status == "NUEVO") {
                    $stock->mount_new = intval($stock->mount_new) + 1;
                } else if ($productJpa->product_status == "SEMINUEVO") {
                    $stock->mount_second = intval($stock->mount_second) + 1;
                } else {
                    $stock->mount_ill_fated = intval($stock->mount_ill_fated) + 1;
                }
                $stock->save();
            }

            $detailParcelJpa->save();
            $parcelJpa->save();
            $productJpa->save();

            $response->setStatus(200);
            $response->setMessage('Producto sacado de encomienda.');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
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
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'read')) {
                throw new Exception('No tienes permisos para listar encomiedas creadas');
            }

            $query = ViewParcelsCreated::select(['*'])
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
                if ($column == 'date_send' || $column == '*') {
                    $q->orWhere('date_send', $type, $value);
                }
                if ($column == 'date_entry' || $column == '*') {
                    $q->orWhere('date_entry', $type, $value);
                }
                if ($column == 'branch_send__name' || $column == '*') {
                    $q->orWhere('branch_send__name', $type, $value);
                }
                if ($column == 'branch_destination__name' || $column == '*') {
                    $q->orWhere('branch_destination__name', $type, $value);
                }
                if ($column == 'business_transport__name' || $column == '*') {
                    $q->orWhere('business_transport__name', $type, $value);
                }
                if ($column == 'responsible_pickup__doc_number' || $column == '*') {
                    $q->orWhere('responsible_pickup__doc_number', $type, $value);
                }
                if ($column == 'responsible_pickup__name' || $column == '*') {
                    $q->orWhere('responsible_pickup__name', $type, $value);
                }
                if ($column == 'responsible_pickup__lastname' || $column == '*') {
                    $q->orWhere('responsible_pickup__lastname', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            $query->where(function ($q) use ($branch) {
                $q->where(function ($q) use ($branch) {
                    $q->where('branch__correlative', $branch);
                });
                $q->orWhere(function ($q) use ($branch) {
                    $q->where('parcel_status', 'ENTREGADO');
                    $q->where('branch_destination__correlative', $branch);
                });
            });

            $iTotalDisplayRecords = $query->count();
            $parcelsJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $parcels = array();
            foreach ($parcelsJpa as $parcelJpa) {
                $parcel = gJSON::restore($parcelJpa->toArray(), '__');
                $parcels[] = $parcel;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewParcelsCreated::where('branch__correlative', $branch)->count());
            $response->setData($parcels);
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

    public function getParcelsByPerson(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'read')) {
                throw new Exception('No tienes permisos para listar encomiendas');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $parcelJpa = Parcel::select([
                'parcels.id as id',
                'parcels.date_send as date_send',
                'parcels.date_entry as date_entry',
                'parcels._business_transport as _business_transport',
                'transport.id as business_transport__id',
                'transport.name as business_transport__name',
                'parcels._branch_send as _branch_send',
                'br_send.id as branch_send__id',
                'br_send.name as branch_send__name',
                'parcels._branch_destination as _branch_destination',
                'br_des.id as branch_destination__id',
                'br_des.name as branch_destination__name',
                'parcels.price_transport as price_transport',
                'parcels._responsible_pickup as _responsible_pickup',
                'parcels.parcel_type as parcel_type',
                'parcels.parcel_status as parcel_status',
                'parcels.description as description',
                'parcels._branch as _branch',
                'parcels.creation_date as creation_date',
                'parcels._creation_user as _creation_user',
                'parcels.update_date as update_date',
                'parcels._update_user as _update_user',
                'parcels.status as status',
            ])
                ->join('branches as br_send', 'parcels._branch_send', 'br_send.id')
                ->join('branches as br_des', 'parcels._branch_destination', 'br_des.id')
                ->join('transport', 'parcels._business_transport', 'transport.id')
                ->where('_responsible_pickup', $request->id)
                ->where('_branch_destination', $branch_->id)
                ->where('parcels.parcel_status', '!=', 'ENTREGADO')
                ->orderBy('parcels.id', 'desc')
                ->whereNotNull('parcels.status')
                ->get();

            if (!$parcelJpa) {
                throw new Exception('Usted no tiene encomiendas por recibir');
            }

            $parcels = [];
            foreach ($parcelJpa as $parcel) {
                $parcel = gJSON::restore($parcel->toArray(), '__');
                $detailsParcelJpa = DetailsParcel::select(
                    'details_parcel.id',
                    'details_parcel._parcel',
                    'details_parcel.mount_new',
                    'details_parcel.mount_second',
                    'details_parcel.mount_ill_fated',
                    'products.id as product__id',
                    'products.mac as product__mac',
                    'products.serie as product__serie',
                    'products.type as product__type',
                    'models.id as product__model__id',
                    'models.model as product__model__model',
                    'models.relative_id as product__model__relative_id',
                    'details_parcel.description',
                    'details_parcel.status'
                )
                    ->join('products', 'details_parcel._product', 'products.id')
                    ->join('models', 'products._model', 'models.id')
                    ->whereNotNull('details_parcel.status')
                    ->where('_parcel', $parcel['id'])->get();
                $details = [];
                foreach ($detailsParcelJpa as $detail) {
                    $details[] = gJSON::restore($detail->toArray(), '__');
                }
                $parcel['details'] = $details;
                $parcels[] = $parcel;
            }

            $response->setStatus(200);
            $response->setData($parcels);
            $response->setMessage('Encomiendas listadas correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'Ln. ' . $th->getLine() . $th->getFile());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function getParcelByPerson(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'read')) {
                throw new Exception('No tienes permisos para listar encomiendas');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $parcelJpa = Parcel::select([
                'parcels.id as id',
                'parcels.date_send as date_send',
                'parcels.date_entry as date_entry',
                'parcels._business_transport as _business_transport',
                'transport.id as business_transport__id',
                'transport.name as business_transport__name',
                'parcels._branch_send as _branch_send',
                'br_send.id as branch_send__id',
                'br_send.name as branch_send__name',
                'parcels._branch_destination as _branch_destination',
                'br_des.id as branch_destination__id',
                'br_des.name as branch_destination__name',
                'parcels.price_transport as price_transport',
                'responsible.id as responsible_pickup__id',
                'responsible.name as responsible_pickup__name',
                'responsible.lastname as responsible_pickup__lastname',
                'parcels.parcel_type as parcel_type',
                'parcels.parcel_status as parcel_status',
                'parcels.description as description',
                'parcels._branch as _branch',
                'parcels.creation_date as creation_date',
                'parcels._creation_user as _creation_user',
                'parcels.update_date as update_date',
                'parcels._update_user as _update_user',
                'parcels.status as status',
            ])
                ->join('branches as br_send', 'parcels._branch_send', 'br_send.id')
                ->join('branches as br_des', 'parcels._branch_destination', 'br_des.id')
                ->join('people as responsible', 'parcels._responsible_pickup', 'responsible.id')
                ->join('transport', 'parcels._business_transport', 'transport.id')
                ->whereNotNull('parcels.status')
                ->find($request->id);

            $parcel = gJSON::restore($parcelJpa->toArray(), '__');

            $detailsParcelJpa = DetailsParcel::select(
                'details_parcel.id as id',
                'details_parcel._parcel as _parcel',
                'details_parcel.mount_new as mount_new',
                'details_parcel.mount_second as mount_second',
                'details_parcel.mount_ill_fated as mount_ill_fated',
                'products.id as product__id',
                'products.type as product__type',
                'products.mac as product__mac',
                'products.price_sale as product__price_sale',
                'products.currency as product__currency',
                'products.serie as product__serie',
                'products.condition_product as product__condition_product',
                'products.product_status as product__product_status',
                'models.id as product__model__id',
                'models.model as product__model__model',
                'models.relative_id as product__model__relative_id',
                'unities.id as product__model__unity__id',
                'unities.name as product__model__unity__name',
                'details_parcel.description as description',
                'details_parcel.status as status'
            )
                ->join('products', 'details_parcel._product', 'products.id')
                ->join('models', 'products._model', 'models.id')
                ->join('unities', 'models._unity', 'unities.id')
                ->where('_parcel', $parcel['id'])
                ->whereNotNull('details_parcel.status')
                ->get();

            $details = [];
            foreach ($detailsParcelJpa as $detail) {
                $details[] = gJSON::restore($detail->toArray(), '__');
            }

            $parcel['details'] = $details;

            $response->setStatus(200);
            $response->setData($parcel);
            $response->setMessage('Encomienda listadas correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'Ln. ' . $th->getLine() . $th->getFile());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function confirmArrival(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'read')) {
                throw new Exception('No tienes permisos para listar encomiendas');
            }

            if (
                !isset($request->id) ||
                !isset($request->type_operation)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $parcelJpa = Parcel::find($request->id);
            $parcelJpa->date_entry = gTrace::getDate('mysql');
            $parcelJpa->parcel_status = "ENTREGADO";

            $entryProductJpa = new EntryProducts();
            $entryProductJpa->_user = $userid;
            $entryProductJpa->_branch = $branch_->id;
            $entryProductJpa->description = $parcelJpa->description;
            $entryProductJpa->type_entry = "REGISTRO ENCOMIENDA";
            $entryProductJpa->entry_date = gTrace::getDate('mysql');
            $entryProductJpa->_type_operation = $request->type_operation;
            $entryProductJpa->_creation_user = $userid;
            $entryProductJpa->creation_date = gTrace::getDate('mysql');
            $entryProductJpa->_update_user = $userid;
            $entryProductJpa->update_date = gTrace::getDate('mysql');
            $entryProductJpa->status = "1";
            $entryProductJpa->save();

            $parcelJpa->_entry_product = $entryProductJpa->id;

            $detailsParcelJpa = DetailsParcel::where('_parcel', $request->id)->get();

            foreach ($detailsParcelJpa as $detailParcel) {
                $EntryDetailJpa = new EntryDetail();
                $EntryDetailJpa->_product = $detailParcel['_product'];
                $EntryDetailJpa->mount_new = $detailParcel['mount_new'];
                $EntryDetailJpa->mount_second = $detailParcel['mount_second'];
                $EntryDetailJpa->mount_ill_fated = $detailParcel['mount_ill_fated'];
                $EntryDetailJpa->_entry_product = $entryProductJpa->id;
                $EntryDetailJpa->save();

                $productJpa = Product::find($detailParcel['_product']);
                if ($productJpa->type == "EQUIPO") {
                    $productJpa->disponibility = 'DISPONIBLE';
                    $productJpa->condition_product = "POR_ENCOMIENDA";
                    $productJpa->_branch = $branch_->id;
                    $productJpa->save();

                    $stock = Stock::where('_model', $productJpa->_model)
                        ->where('_branch', $branch_->id)
                        ->first();

                    if ($productJpa->product_status == 'NUEVO') {
                        $stock->mount_new = $stock->mount_new + 1;
                    } else if ($productJpa->product_status == 'SEMINUEVO') {
                        $stock->mount_second = $stock->mount_second + 1;
                    } else {
                        $stock->mount_ill_fated = $stock->mount_ill_fated + 1;
                    }

                    $stock->save();

                } else {
                    $productJpa_new = Product::select([
                        'id',
                        'mount',
                        'num_guia',
                        'num_bill',
                        '_model',
                        '_branch',
                    ])
                        ->where('_model', $productJpa->_model)
                        ->where('_branch', $branch_->id)
                        ->first();

                    if (isset($productJpa_new)) {
                        $stock = Stock::where('_model', $productJpa->_model)
                            ->where('_branch', $branch_->id)
                            ->first();

                        $productJpa_new->_provider = "2037";

                        $stock->mount_new = intval($stock->mount_new) + intval($detailParcel['mount_new']);
                        $stock->mount_second = intval($stock->mount_second) + intval($detailParcel['mount_second']);
                        $stock->mount_ill_fated = intval($stock->mount_ill_fated) + intval($detailParcel['mount_ill_fated']);
                        $stock->save();

                        $productJpa_new->mount = $stock->mount_new + $stock->mount_second;

                        $productJpa_new->creation_date = gTrace::getDate('mysql');
                        $productJpa_new->_creation_user = $userid;
                        $productJpa_new->update_date = gTrace::getDate('mysql');
                        $productJpa_new->_update_user = $userid;
                        $productJpa_new->status = "1";
                        $productJpa_new->save();
                    } else {
                        $productJpa_new = new Product();
                        $productJpa_new->type = $productJpa->type;
                        $productJpa_new->_branch = $branch_->id;
                        $productJpa_new->relative_id = guid::short();
                        $productJpa_new->_provider = "2037";
                        $productJpa_new->_model = $productJpa->_model;

                        $stock = Stock::where('_model', $productJpa->_model)
                            ->where('_branch', $branch_->id)
                            ->first();

                        $stock->mount_new = intval($stock->mount_new) + intval($detailParcel['mount_new']);
                        $stock->mount_second = intval($stock->mount_second) + intval($detailParcel['mount_second']);
                        $stock->mount_ill_fated = intval($stock->mount_ill_fated) + intval($detailParcel['mount_ill_fated']);
                        $stock->save();

                        $productJpa_new->mount = $stock->mount_new + $stock->mount_second;
                        $productJpa_new->currency = $productJpa->currency;
                        $productJpa_new->price_buy = $productJpa->price_buy;
                        $productJpa_new->price_sale = $productJpa->price_sale;

                        if (isset($productJpa->warranty)) {
                            $productJpa_new->warranty = $productJpa->warranty;
                        }
                        $productJpa_new->date_entry = $productJpa->date_entry;
                        $productJpa_new->_entry_product = $entryProductJpa->id;
                        $productJpa_new->condition_product = $productJpa->condition_product;
                        $productJpa_new->product_status = $productJpa->product_status;
                        $productJpa_new->disponibility = $productJpa->disponibility;
                        if (isset($productJpa->description)) {
                            $productJpa_new->description = $productJpa->description;
                        }
                        $productJpa_new->creation_date = gTrace::getDate('mysql');
                        $productJpa_new->_creation_user = $userid;
                        $productJpa_new->update_date = gTrace::getDate('mysql');
                        $productJpa_new->_update_user = $userid;
                        $productJpa_new->status = "1";
                        $productJpa_new->save();
                    }
                }
            }

            $parcelJpa->save();

            $response->setStatus(200);
            $response->setMessage('Encomiendas listadas correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'Ln. ' . $th->getLine() . $th->getFile());
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
            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar encomiendas');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $parcelJpa = Parcel::find($request->id);
            if (!$parcelJpa) {
                throw new Exception('La encomienda que deseas eliminar no existe');
            }

            $detailsParcelJpa = DetailsParcel::where('_parcel', $request->id)->get();

            foreach ($detailsParcelJpa as $detailParcel) {

                $productJpa = Product::find($detailParcel['_product']);
                if ($productJpa->type == "EQUIPO") {
                    $productJpa->disponibility = 'DISPONIBLE';
                    $productJpa->condition_product = "POR_ENCOMIENDA";
                    $productJpa->_branch = $branch_->id;
                    $productJpa->save();

                    $stock = Stock::where('_model', $productJpa->_model)
                        ->where('_branch', $branch_->id)
                        ->first();

                    if ($productJpa->product_status == "NUEVO") {
                        $stock->mount_new = $stock->mount_new + 1;
                    } else if ($productJpa->product_status == "SEMINUEVO") {
                        $stock->mount_second = $stock->mount_second + 1;
                    } else {
                        $stock->mount_ill_fated = $stock->mount_ill_fated + 1;
                    }
                    $stock->save();
                } else {
                    $productJpa_new = Product::select([
                        'id',
                        'mount',
                        'num_guia',
                        'num_bill',
                        '_model',
                        '_branch',
                    ])
                        ->where('_model', $productJpa->_model)
                        ->where('_branch', $branch_->id)
                        ->first();

                    if (isset($productJpa_new)) {
                        $stock = Stock::where('_model', $productJpa->_model)
                            ->where('_branch', $branch_->id)
                            ->first();

                        $stock->mount_new = intval($stock->mount_new) + intval($detailParcel['mount_new']);
                        $stock->mount_second = intval($stock->mount_second) + intval($detailParcel['mount_second']);
                        $stock->mount_ill_fated = intval($stock->mount_ill_fated) + intval($detailParcel['mount_ill_fated']);
                        $stock->save();

                        $productJpa_new->mount = $stock->mount_new + $stock->mount_second;

                        $productJpa_new->update_date = gTrace::getDate('mysql');
                        $productJpa_new->_update_user = $userid;
                        $productJpa_new->status = "1";
                        $productJpa_new->save();

                    }
                }
            }

            $parcelJpa->status = null;
            $parcelJpa->save();

            $response->setStatus(200);
            $response->setMessage('La encomienda a sido eliminada correctamente');
            $response->setData($role->toArray());
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
            if (!gValidate::check($role->permissions, $branch, 'parcels_created', 'delete_restore')) {
                throw new Exception('No tienes permisos para encomiendas.');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $parcelJpa = Parcel::find($request->id);
            if (!$parcelJpa) {
                throw new Exception('La encomienda que deseas restaurar no existe');
            }

            $parcelJpa->status = "1";
            $parcelJpa->save();

            $response->setStatus(200);
            $response->setMessage('La encomienda a sido restaurada correctamente');
            $response->setData($role->toArray());
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
