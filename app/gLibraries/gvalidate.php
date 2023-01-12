<?php

namespace App\gLibraries;

use App\Models\User;
use App\gLibraries\gjson;
use Illuminate\Http\Request;
use App\Models\Role;
use Exception;

class gValidate
{

    public static function get(Request $request): array
    {
        $role = new Role();
        $status = 200;
        $message = 'Operación correcta';
        $userid = null;
        try {
            if ($request->header('SoDe-Auth-Token') == null || $request->header('SoDe-Auth-User') == null) {
                $status = 401;
                throw new Exception('Error: Datos de cabecera deben ser enviados');
            }

            $userJpa = User::select([
                'users.id',
                'roles.id AS role.id',
                'roles.priority AS role.priority',
                'roles.permissions AS role.permissions',
                'roles.status AS role.status'
            ])
                ->where('auth_token', $request->header('SoDe-Auth-Token'))
                ->where('username', $request->header('SoDe-Auth-User'))
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

            if (!$role->status) {
                $status = 400;
                throw new Exception('Tu rol se encuentra deshabilitado');
            }
        } catch (\Throwable $th) {
            $status = 400;
            $message = $th->getMessage();
            $role = null;
        }

        return [$status, $message, $role, $userid];
    }

    public static function check(array $permissions, String $view, String $permission): bool
    {
        $permissions = gJSON::flatten($permissions);
        if (
            isset($permissions["isRoot"]) ||
            isset($permissions["isAdmin"]) ||
            isset($permissions["$view.all"]) ||
            isset($permissions["$view.$permission"])
        ) {
            return true;
        }
        return false;
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
        } catch (\Throwable $th) {
            $ok = false;
            $message = $th->getMessage();
        }

        return [$ok, $message, gJSON::restore($after)];
    }
}
