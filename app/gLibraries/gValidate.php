<?php

namespace App\gLibraries;

use App\gLibraries\gjson;
use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;

class gValidate
{

    public static function get(Request $request): array
    {
        $role = new Role();
        $status = 200;
        $message = 'Operación correcta';
        $userid = null;
        try {
            if ($request->header('Auth-Token') == null || $request->header('Auth-User') == null || $request->header('Auth-Branch') == null) {
                $status = 401;
                throw new Exception('Error: Datos de cabecera deben ser enviados');
            }

            $userJpa = User::select([
                'users.id',
                'roles.id AS role.id',
                'roles.priority AS role.priority',
                'roles.permissions AS role.permissions',
                'roles.status AS role.status',
            ])
                ->where('auth_token', $request->header('Auth-Token'))
                ->where('username', $request->header('Auth-User'))
                ->leftjoin('roles', 'users._role', '=', 'roles.id')
                ->first();

            if (!$userJpa) {
                $status = 403;
                throw new Exception('La sesión ha expirado o has iniciado sesión en otro dispositivo');
            }

            $user = gJSON::restore($userJpa->toArray());
            $userid = $user['id'];
            $role->id = $user['role']['id'];
            $role->priority = $user['role']['priority'];
            $role->permissions = gJSON::parse($user['role']['permissions']);
            $role->status = $user['role']['status'];

            $branch = $request->header('Auth-Branch');

            if (!$role->status) {
                $status = 400;
                throw new Exception('Tu rol se encuentra deshabilitado');
            }
        } catch (\Throwable$th) {
            $status = 400;
            $message = $th->getMessage();
            $role = null;
            $branch = null;
        }

        return [$branch, $status, $message, $role, $userid];
    }

    public static function check(array $permissions, string $branch, String $view, String $permission)
    {
        $status = false;
        $root = false;
        $admin = false;
        try {
            if (isset($permissions['root'])) {
                $root = true;
            }

            if(isset($permissions['admin'])){
                $admin = true;
                if($view == 'system'){
                    $admin = false;
                }
            }

            foreach ($permissions as $branch_) {
                if (array_key_exists($branch, $branch_)) {
                    foreach ($branch_[$branch] as $views_) {
                        if (array_key_exists($view, $views_)) {
                            $canReadUsers = $views_[$view][$permission];
                            break;
                        }
                    }
                }
            }

            if ($canReadUsers) {
                $status = true;
            } else {
                $status = false;
            }
        } catch (\Throwable$th) {
            $status = false;
        } finally {
            return $status || $root || $admin;
        }

    }

    public static function cleanPermissions(array $permissions, array $before, array $toset): array
    {
        $ok = true;
        $message = 'Operación correcta';

        $after = array();
        try {
            $before = gJSON::flatten($before);
            $toset = gJSON::flatten($toset);

            foreach ($toset as $key => $value) {
                [$view, $permission] = explode('.', $key);
                if (gValidate::check($permissions, $view, $permission) || $before[$key]) {
                    $after[$key] = true;
                }
            }
        } catch (\Throwable$th) {
            $ok = false;
            $message = $th->getMessage();
        }

        return [$ok, $message, gJSON::restore($after)];
    }
}
