<?php

namespace App\Http\Controllers;

use App\gLibraries\gValidate;
use App\Models\People;
use App\Models\User;
use App\Models\Response;
use Illuminate\Http\Request;
use Exception;

class ProfileController extends Controller
{

    public function profile($relative_id, $size)
    {
        $response = new Response();
        $content = null;
        $type = null;
        try {
            if ($size != 'full') {
                $size = 'mini';
            }
            if (
                !isset($relative_id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $userJpa = User::select([
                "users.image_$size as image_content",
                'users.image_type'

            ])
                ->where('relative_id', $relative_id)
                ->first();

            if (!$userJpa) {
                throw new Exception('No se encontraron datos');
            }
            if (!$userJpa->image_content) {
                throw new Exception('No existe imagen');
            }
            $content = $userJpa->image_content;
            $type = $userJpa->image_type;
            $response->setStatus(200);
        } catch (\Throwable $th) {
            $ruta = '../storage/images/user_not_found.svg';
            $fp = fopen($ruta, 'r');
            $datos_image = fread($fp, filesize($ruta));
            $datos_image = addslashes($datos_image);
            fclose($fp);
            $content = stripslashes($datos_image);
            $type = 'image/svg+xml';
            $response->setStatus(400);
        } finally {
            return response(
                $content,
                $response->getStatus()
            )->header('Content-Type', $type);
        }
    }

    public function cover($relative_id, $size)
    {
        $response = new Response();
        $content = null;
        $type = null;
        try {
            if ($size != 'full') {
                $size = 'mini';
            }
            if (
                !isset($relative_id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $userJpa = User::select([
                "users.cover_$size as cover_content",
                'users.cover_type'

            ])
                ->where('relative_id', $relative_id)
                ->first();

            if (!$userJpa) {
                throw new Exception('No se encontraron datos');
            }
            if (!$userJpa->cover_content) {
                throw new Exception('No existe portada');
            }
            $content = $userJpa->cover_content;
            $type = $userJpa->cover_type;
            $response->setStatus(200);
        } catch (\Throwable $th) {
            $ruta = '../storage/covers/cover_default.svg';
            $fp = fopen($ruta, 'r');
            $datos_cover = fread($fp, filesize($ruta));
            $datos_cover = addslashes($datos_cover);
            fclose($fp);
            $content = stripslashes($datos_cover);
            $type = 'image/svg+xml';
            $response->setStatus(400);
        } finally {
            return response(
                $content,
                $response->getStatus()
            )->header('Content-Type', $type);
        }
    }

    public function account(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (
                !isset($request->username) &&
                !isset($request->password)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            if (strlen($request->username) < 4) {
                throw new Exception('El nombre de usuario debe contener entre 4 y 16 caracteres');
            }

            if (!ctype_alnum($request->username)) {
                throw new Exception('El nombre de usuario debe contener solo letras y números');
            }

            $userJpa = User::find($userid);
            if (!$userJpa) {
                throw new Exception("Este usuario no existe");
            }

            if (!password_verify($request->password, $userJpa->password)) {
                throw new Exception('Error: Contraseña incorrecta');
            }

            if (
                isset($request->image_type) &&
                isset($request->image_mini) &&
                isset($request->image_full)
            ) {
                if (
                    $request->image_type != 'none' &&
                    $request->image_mini != 'none' &&
                    $request->image_full != 'none'
                ) {
                    $userJpa->image_type = $request->image_type;
                    $userJpa->image_mini = base64_decode($request->image_mini);
                    $userJpa->image_full = base64_decode($request->image_full);
                } else {
                    $userJpa->image_type = null;
                    $userJpa->image_mini = null;
                    $userJpa->image_full = null;
                }
            }

            if ($request->username != $userJpa->username) {
                $userJpa->username = $request->username;
                $userJpa->auth_token = null;
            }

            $userJpa->save();

            $response->setStatus(200);
            $response->setMessage('Usuario actualizado correctamente');
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

    public function password(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (
                !isset($request->password_new) &&
                !isset($request->password_confirm) &&
                !isset($request->password)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            if ($request->password_new != $request->password_confirm) {
                throw new Exception('Las contraseñas deben ser iguales');
            }

            if (strlen($request->password_new) < 4) {
                throw new Exception('La contraseña debe contener 4 caracteres como mínimo. Recuerda: Si quieres tener una cuenta segura, debes crear una contraseña segura');
            }

            $userJpa = User::find($userid);
            if (!$userJpa) {
                throw new Exception("Este usuario no existe");
            }

            if (!password_verify($request->password, $userJpa->password)) {
                throw new Exception('Error: Contraseña de confirmación incorrecta');
            }

            if (password_verify($request->password_new, $userJpa->password)) {
                throw new Exception('La contraseña nueva debe ser diferente a la anterior');
            }

            $userJpa->password = password_hash($request->password_new, PASSWORD_DEFAULT);
            $userJpa->auth_token = null;

            $userJpa->save();

            $response->setStatus(200);
            $response->setMessage('Contraseña actualizada correctamente');
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

    public function personal(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (
                !isset($request->lastname) &&
                !isset($request->name)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $userJpa = User::find($userid);
            if (!$userJpa) {
                throw new Exception("Este usuario no existe");
            }

            if (!password_verify($request->password, $userJpa->password)) {
                throw new Exception('Error: Contraseña de confirmación incorrecta');
            }

            $PeopleJpa = People::find($request->person_id);
            $PeopleJpa->lastname = $request->lastname;
            $PeopleJpa->name = $request->name;
            $PeopleJpa->phone = $request->phone_number;

            if (isset($request->email)) {
                $PeopleJpa->email = $request->email;
            }

            $PeopleJpa->save();

            $response->setStatus(200);
            $response->setMessage('Usuario actualizado correctamente');
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
