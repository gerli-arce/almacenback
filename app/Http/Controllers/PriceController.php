<?php
namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\DetailSale;
use App\Models\People;
use App\Models\Response;
use App\Models\SalesProducts;
use App\Models\Stock;
use App\Models\ViewPrice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            if (empty($peopleJpa)) {
                $peopleNew = new People();
                $peopleNew->doc_type = $request->doc_type;
                $peopleNew->doc_number = $request->doc_number;
                if (isset($request->name)) {
                    $peopleNew->name = $request->name;
                }
                if (isset($request->lastname)) {
                    $peopleNew->lastname = $request->lastname;
                }
                if (isset($request->email)) {
                    $peopleNew->email = $request->email;
                }
                if (isset($request->phone)) {
                    $peopleNew->phone = $request->phone;
                }
                if (isset($request->address)) {
                    $peopleNew->address = $request->address;
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
            } else {

                if (isset($request->name)) {
                    $peopleJpa->name = $request->name;
                }
                if (isset($request->lastname)) {
                    $peopleJpa->lastname = $request->lastname;
                }
                if (isset($request->email)) {
                    $peopleJpa->email = $request->email;
                }
                if (isset($request->phone)) {
                    $peopleJpa->phone = $request->phone;
                }
                if (isset($request->address)) {
                    $peopleJpa->address = $request->address;
                }

                $peopleJpa->_update_user = $userid;
                $peopleJpa->update_date = gTrace::getDate('mysql');
                $peopleJpa->save();
                $salesProduct->_client = $peopleJpa->id;
            }

            $salesProduct->_branch = $branch_->id;
            $salesProduct->_type_operation = 13;
            $salesProduct->type_intallation = "COTIZACION";

            $salesProduct->status_sale = "PENDIENTE";
            $salesProduct->_issue_user = $userid;
            $salesProduct->type_pay = "NO GASTO";
            $salesProduct->price_all = $request->price_all;

            if (isset($request->description)) {
                $salesProduct->description = $request->description;
            }

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
                    $detailSale->_unity = $product['unity']['id'];
                    $detailSale->mount_new = $product['mount_new'];
                    $detailSale->price_new = $product['model']['price_sale'];
                    $detailSale->mount_second = $product['mount_second'];
                    $detailSale->price_second = $product['model']['price_sale_second'] || 0;
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

    public function update(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'price', 'update')) {
                throw new Exception('No tienes permisos para crear cotizaciones');
            }

            if (
                !isset($request->id) ||
                !isset($request->doc_number) ||
                !isset($request->details)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $peopleJpa = People::where('doc_type', $request->doc_type)->where('doc_number', $request->doc_number)->first();

            $salesProduct = SalesProducts::find($request->id);
            if (empty($peopleJpa)) {
                $peopleNew = new People();
                $peopleNew->doc_type = $request->doc_type;
                $peopleNew->doc_number = $request->doc_number;
                if (isset($request->name)) {
                    $peopleNew->name = $request->name;
                }
                if (isset($request->lastname)) {
                    $peopleNew->lastname = $request->lastname;
                }
                if (isset($request->email)) {
                    $peopleNew->email = $request->email;
                }
                if (isset($request->phone)) {
                    $peopleNew->phone = $request->phone;
                }
                if (isset($request->address)) {
                    $peopleNew->address = $request->address;
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
            } else {
                if (isset($request->name)) {
                    $peopleJpa->name = $request->name;
                }
                if (isset($request->lastname)) {
                    $peopleJpa->lastname = $request->lastname;
                }
                if (isset($request->email)) {
                    $peopleJpa->email = $request->email;
                }
                if (isset($request->phone)) {
                    $peopleJpa->phone = $request->phone;
                }
                if (isset($request->address)) {
                    $peopleJpa->address = $request->address;
                }
                $peopleJpa->_update_user = $userid;
                $peopleJpa->update_date = gTrace::getDate('mysql');
                $peopleJpa->save();
                $salesProduct->_client = $peopleJpa->id;
            }

            $salesProduct->type_intallation = "COTIZACION";
            $salesProduct->_issue_user = $userid;
            $salesProduct->price_all = $request->price_all;
            if (isset($request->description)) {
                $salesProduct->description = $request->description;
            }

            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->save();

            if (isset($request->details)) {
                foreach ($request->details as $product) {
                    if (isset($product['id'])) {
                        $detailSale = DetailSale::find($product['id']);
                        $detailSale->mount_new = $product['mount_new'];
                        $detailSale->price_new = $product['model']['price_sale'];
                        $detailSale->mount_second = $product['mount_second'];
                        $detailSale->price_second = $product['model']['price_sale_second'] || 0;
                        $detailSale->status = '1';
                        $detailSale->save();
                    } else {
                        $detailSale = new DetailSale();
                        $detailSale->_model = $product['model']['id'];
                        $detailSale->mount_new = $product['mount_new'];
                        $detailSale->price_new = $product['model']['price_sale'];
                        $detailSale->mount_second = $product['mount_second'];
                        $detailSale->price_second = $product['model']['price_sale_second'] || 0;
                        $detailSale->_sales_product = $salesProduct->id;
                        $detailSale->status = '1';
                        $detailSale->save();
                    }
                }
            }

            $response->setStatus(200);
            $response->setMessage('La cotizacion se a actualizado correctamente');
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

    public function paginate(Request $request)
    {
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

    public function deleteProduct(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'price', 'update')) {
                throw new Exception('No tienes permisos eliminar producto');
            }
            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: Es necesario el ID para esta operación");
            }

            $detailsSalesJpa = DetailSale::find($request->id);
            $detailsSalesJpa->status = null;
            $detailsSalesJpa->save();

            $response->setStatus(200);
            $response->setMessage('Producto eliminado correctamente.');
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

    public function getDetailsPriceByID(Request $request, $id)
    {
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
                'models.price_sale AS model__price_sale',
                'models.price_sale_second AS model__price_sale_second',
                'models.currency AS model__currency',
                'unities.id as  unity__id',
                'unities.name as unity__name',
                'unities.value as unity__value',
                'detail_sales.mount_new as mount_new',
                'detail_sales.mount_second as mount_second',
                'detail_sales.price_new as price_sale',
                'detail_sales.price_second as price_sale_second',
                'detail_sales.description as description',
                'detail_sales._unity as _unity',
                'detail_sales._sales_product as _sales_product',
                'detail_sales.status as status',
            ])
                ->join('models', 'detail_sales._model', 'models.id')
                ->join('unities', 'detail_sales._unity', 'unities.id')
                ->join('brands', 'models._brand', 'brands.id')
                ->join('categories', 'models._category', 'categories.id')
                ->whereNotNull('detail_sales.status')
                ->where('_sales_product', $id)
                ->get();

            $details = array();
            foreach ($detailSaleJpa as $detailJpa) {
                $detail = gJSON::restore($detailJpa->toArray(), '__');
                $stockJpa = Stock::where('_model', $detail['model']['id'])->where('_branch', $branch_->id)->whereNotNull('status')->first();
                $detail['max_new'] = $stockJpa->mount_new;
                $detail['max_second'] = $stockJpa->mount_second;
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

    public function generateReportByPrice(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'price', 'read')) {
                throw new Exception('No tienes permisos para generar informe');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportPriceMe.html');

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $priceJpa = ViewPrice::find($request->id);

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
                'models.price_sale AS model__price_sale',
                'models.price_sale_second AS model__price_sale_second',
                'models.currency AS model__currency',
                'unities.id as  unity__id',
                'unities.name as unity__name',
                'unities.value as unity__value',
                'detail_sales.mount_new as mount_new',
                'detail_sales.mount_second as mount_second',
                'detail_sales.price_new as price_sale',
                'detail_sales.price_second as price_sale_second',
                'detail_sales.description as description',
                'detail_sales._unity as _unity',
                'detail_sales._sales_product as _sales_product',
                'detail_sales.status as status',
            ])
                ->join('models', 'detail_sales._model', 'models.id')
                ->join('unities', 'detail_sales._unity', 'unities.id')
                ->join('brands', 'models._brand', 'brands.id')
                ->join('categories', 'models._category', 'categories.id')
                ->whereNotNull('detail_sales.status')
                ->where('_sales_product', $request->id)
                ->get();

            $details = array();
            foreach ($detailSaleJpa as $detailJpa) {
                $detail = gJSON::restore($detailJpa->toArray(), '__');
                $details[] = $detail;
            }

            $price = gJSON::restore($priceJpa->toArray(), '__');
            $price['products'] = $details;

            $type_operation = '';

            $sumary = '';

            foreach ($details as $detail) {

                $service = "
                <p>{$detail['brand']['brand']} {$detail['model']['model']}</p> ";

                $price_new = (number_format($detail['price_sale'], 2) * number_format($detail['unity']['value'], 2));
                $price_second = (number_format($detail['price_sale_second'], 2) * number_format($detail['unity']['value'], 2));

                $sumary .= "
                <tr>
                    <td>{$service}</td>
                    <td style='text-align: center;'>" .$detail['unity']['name']. "</td>
                    <td style='text-align: center;'>" . number_format($detail['mount_new']) . "</td>
                    <td style='text-align: center;'>" . number_format($detail['mount_second']) . "</td>
                    <td style='text-align: center;'>S/" . number_format(($price_new), 2) . "</td>
                    <td style='text-align: center;'>S/" . number_format(($price_second), 2) . "</td>
                    <td style='text-align: center;'>S/" . number_format(($price_new * $detail['mount_new']) + ($price_second + $detail['mount_second']), 2) . "</td>
                </tr>
                ";
            }

            // $fecha_hora = $installJpa['issue_date'];
            // $parts_date = explode(" ", $fecha_hora);
            // $fecha = $parts_date[0];
            // $hora = $parts_date[1];

            // $mounts_durability = '';

            $template = str_replace(
                [
                    '{num_cot}',
                    '{client}',
                    '{phone}',
                    '{email}',
                    '{address}',
                    '{description}',
                    '{issue_date}',
                    '{summary}',
                    '{price_all}',
                ],
                [
                    str_pad($price['id'], 6, "0", STR_PAD_LEFT),
                    $price['client']['name'] . ' ' . $price['client']['lastname'],
                    $price['client']['phone'] !== null ? $price['client']['phone'] : '-',
                    $price['client']['email'] !== null ? $price['client']['email'] : '-',
                    $price['client']['address'] !== null ? $price['client']['address'] : '-',
                    $price['description'] !== null ? $price['description'] : '-',
                    $price['creation_date'],
                    $sumary,
                    number_format($price['price_all'], 2),
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('Cotización.pdf');

            // $response = new Response();
            // $response->setData($price);
            // $response->setMessage('Operacion correcta crack');
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

}
