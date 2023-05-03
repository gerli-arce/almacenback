<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\EntryDetail;
use App\Models\EntryProducts;
use App\Models\Response;
use Exception;
use Illuminate\Http\Request;

class EntrysController extends Controller
{
    public function paginate(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'technicals', 'read')) {
                throw new Exception('No tienes permisos para listar técnicos');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $query = EntryProducts::select(
                [
                    'entry_products.id as id',
                    'users.id as user__id',
                    'users.username as user__username',
                    'people.id as user__person__id',
                    'people.name as user__person__name',
                    'people.lastname as user__person__lastname',
                    'entry_products._client AS _client',
                    'entry_products._technical AS _technical',
                    'entry_products._branch AS _branch',
                    'entry_products._type_operation AS _type_operation',
                    'operation_types.id as operation_type__id',
                    'operation_types.operation as operation_type__operation',
                    'entry_products._tower AS _tower',
                    'entry_products._plant AS _plant',
                    'entry_products.type_entry AS type_entry',
                    'entry_products.entry_date AS entry_date',
                    'entry_products.description AS description',
                    'entry_products.condition_product AS condition_product',
                    'entry_products.product_status AS product_status',
                    'entry_products._creation_user AS _creation_user',
                    'entry_products.creation_date AS creation_date',
                    'entry_products.status AS status',
                ]
            )
                ->join('users', 'entry_products._user', 'users.id')
                ->join('people', 'users._person', 'people.id')
                ->join('operation_types', 'entry_products._type_operation', 'operation_types.id')
                ->where('entry_products._branch', $branch_->id)
                ->orderBy($request->order['column'], $request->order['dir']);

            // $query->where(function ($q) use ($request) {
            //     $column = $request->search['column'];
            //     $type = $request->search['regex'] ? 'like' : '=';
            //     $value = $request->search['value'];
            //     $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

            //     if ($column == 'doc_type' || $column == '*') {
            //         $q->where('doc_type', $type, $value);
            //     }
            //     if ($column == 'doc_number' || $column == '*') {
            //         $q->where('doc_number', $type, $value);
            //     }
            //     if ($column == 'name' || $column == '*') {
            //         $q->orWhere('name', $type, $value);
            //     }
            //     if ($column == 'lastname' || $column == '*') {
            //         $q->orWhere('lastname', $type, $value);
            //     }
            //     if ($column == 'birthdate' || $column == '*') {
            //         $q->orWhere('birthdate', $type, $value);
            //     }
            //     if ($column == 'gender' || $column == '*') {
            //         $q->orWhere('gender', $type, $value);
            //     }
            //     if ($column == 'email' || $column == '*') {
            //         $q->orWhere('email', $type, $value);
            //     }
            //     if ($column == 'phone' || $column == '*') {
            //         $q->orWhere('phone', $type, $value);
            //     }
            //     if ($column == 'ubigeo' || $column == '*') {
            //         $q->orWhere('ubigeo', $type, $value);
            //     }
            //     if ($column == 'address' || $column == '*') {
            //         $q->orWhere('address', $type, $value);
            //     }
            //     if ($column == 'branch__name' || $column == '*') {
            //         $q->orWhere('branch__name', $type, $value);
            //     }
            //     if ($column == 'status' || $column == '*') {
            //         $q->orWhere('status', $type, $value);
            //     }
            // });
            $iTotalDisplayRecords = $query->count();

            $entrysJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $entrys = array();
            foreach ($entrysJpa as $entryJpa) {
                $entry = gJSON::restore($entryJpa->toArray(), '__');

                $detailsJpa = EntryDetail::where('_entry_product', $entry['id'])->whereNotNull('status')->get();

                $details = [];
                foreach($detailsJpa as $detailJpa){
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] =$detail; 
                }

                $entry['details'] = $details;

                $entrys[] = $entry;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(EntryProducts::where('_branch', $branch_->id)->count());
            $response->setData($entrys);
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
