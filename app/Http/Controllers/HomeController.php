<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\People;
use App\Models\Response;
use App\Models\ViewPeople;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{

    public function countTechnicals(Request $request)
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
            $countsJpa = People::select([
                'status',
                'type',
                DB::raw('COUNT(id) AS quantity')
            ])
                ->where('type', 'TECHNICAL')
                ->where('_branch', $branch_->id)
                ->groupBy('status', 'type','_branch')
                ->get();
            $counts = $countsJpa->toArray();
            $response->setStatus(200);
            $response->setMessage('Operación correcta.');
            $response->setData($counts);
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

    public function countProviders(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'providers', 'read')) {
                throw new Exception('No tienes permisos para listar técnicos');
            }
            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();
            $countsJpa = People::select([
                'status',
                'type',
                DB::raw('COUNT(id) AS quantity')
            ])
                ->where('type', 'PROVIDER')
                ->where('_branch', $branch_->id)
                ->groupBy('status', 'type','_branch')
                ->get();
            $counts = $countsJpa->toArray();
            $response->setStatus(200);
            $response->setMessage('Operación correcta.');
            $response->setData($counts);
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

    public function countEjecutives(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'providers', 'read')) {
                throw new Exception('No tienes permisos para listar técnicos');
            }
            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();
            $countsJpa = People::select([
                'status',
                'type',
                DB::raw('COUNT(id) AS quantity')
            ])
                ->where('type', 'EJECUTIVE')
                ->where('_branch', $branch_->id)
                ->groupBy('status', 'type','_branch')
                ->get();
            $counts = $countsJpa->toArray();
            $response->setStatus(200);
            $response->setMessage('Operación correcta.');
            $response->setData($counts);
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

    public function countClients(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'providers', 'read')) {
                throw new Exception('No tienes permisos para listar técnicos');
            }
            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();
            $countsJpa = People::select([
                'status',
                'type',
                DB::raw('COUNT(id) AS quantity')
            ])
                ->where('type', 'PERSON')
                ->where('_branch', $branch_->id)
                ->groupBy('status', 'type','_branch')
                ->get();
            $counts = $countsJpa->toArray();
            $response->setStatus(200);
            $response->setMessage('Operación correcta.');
            $response->setData($counts);
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
