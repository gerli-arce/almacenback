<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\{Branch, People, Response, ViewPeople, ViewModels, ViewStock, User, SalesProducts,};
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
            $count = People::where('type', 'TECHNICAL')
                ->where('status', 1)
                ->count();
            $response->setStatus(200);
            $response->setMessage('Operación correcta.');
            $response->setData(['count' => $count]);
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
            $count = People::where('type', 'PROVIDER')
                ->where('status', 1)
                ->count();
            $response->setStatus(200);
            $response->setMessage('Operación correcta.');
            $response->setData(['count' => $count]);
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
            $count = People::where('type', 'EJECUTIVE')
                ->where('status', 1)
                ->count();
            $response->setStatus(200);
            $response->setMessage('Operación correcta.');
            $response->setData(['count' => $count]);
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
            $count = People::where('type', 'CLIENT')
                ->where('status', 1)
                ->count();
            $response->setStatus(200);
            $response->setMessage('Operación correcta.');
            $response->setData(['count' => $count]);
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

    public function countUsers(Request $request)
    {
        $response = new Response();
        try {
            // Se utiliza la clase gValidate para obtener datos del Request y validar permisos
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            // Se verifica si el rol tiene permisos para leer los proveedores (técnicos)
            if (!gValidate::check($role->permissions, $branch, 'providers', 'read')) {
                throw new Exception('No tienes permisos para listar técnicos');
            }

            // Se realiza la consulta para obtener la cantidad de usuarios con status igual a 1
            $count = User::where('status', 1)->count();

            // Se configuran los datos de respuesta con la cantidad de usuarios
            $response->setStatus(200);
            $response->setMessage('Operación correcta.');
            $response->setData(['count' => $count]);
        } catch (\Throwable $th) {
            // En caso de que ocurra una excepción, se configura la respuesta de error
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            // Se devuelve la respuesta como una respuesta HTTP
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function countInstallations(Request $request)
    {
        $response = new Response();
        try {
            // Se utiliza la clase gValidate para obtener datos del Request y validar permisos
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            // Se verifica si el rol tiene permisos para leer los proveedores (técnicos)
            if (!gValidate::check($role->permissions, $branch, 'providers', 'read')) {
                throw new Exception('No tienes permisos para listar técnicos');
            }

            // Se realiza la consulta para obtener la cantidad de usuarios con status igual a 1
            $count = SalesProducts::where('_type_operation', 5)->where('status_sale', 'CULMINADA')->count();

            // Se configuran los datos de respuesta con la cantidad de usuarios
            $response->setStatus(200);
            $response->setMessage('Operación correcta.');
            $response->setData(['count' => $count]);
        } catch (\Throwable $th) {
            // En caso de que ocurra una excepción, se configura la respuesta de error
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            // Se devuelve la respuesta como una respuesta HTTP
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function getModelsStar(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'models', 'read')) {
                throw new Exception('No tienes permisos para leer modelos.');
            }

            $modelsJpa = ViewModels::select(['*'])->whereNotNull('status')->where('star', '1')->get();

            $models = array();
            foreach ($modelsJpa as $modelJpa) {
                $viewStockJpa = ViewStock::where('model__id', $modelJpa['id'])->get();
                $stockModel = array();
                foreach ($viewStockJpa as $stocks) {
                    $stocksJpa = gJSON::restore($stocks->toArray(), '__');
                    $stockModel[] = $stocksJpa;
                }
                $model = gJSON::restore($modelJpa->toArray(), '__');
                $model['details'] = $stockModel;
                $models[] = $model;
            }

            $response->setData($models);
            $response->setStatus(200);
            $response->setMessage('Operación correcta');
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

    public function getProductsMin(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);

            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'products', 'read')) {
                throw new Exception('No tienes permisos para listar productos');
            }

            $productsJpa = ViewStock::orderBy('mount_new', 'asc')->where('star', '1')->take(10)->get();

            $products = array();
            foreach ($productsJpa as $productJpa) {
                $product = gJSON::restore($productJpa->toArray(), '__');
                $products[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($products);
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
