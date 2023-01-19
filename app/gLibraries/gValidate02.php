<?php

// namespace App\gLibraries;

use App\Generated\Address;
use App\Generated\Contact;
use App\Generated\Document;
use App\Generated\Person;
use App\Generated\User;
use Illuminate\Http\Request;
use App\Models\ViewUsers;
use Exception;

class gValidate
{
    public static function session(Request $request): array
    {
        $ok = false;
        $status = 500;
        $message = 'Error de autenticación inesperado';
        $session = null;

        try {
            if ($request->header('Auth-Token') == null || $request->header('Auth-User') == null) {
                throw new Exception('Error: Datos de cabecera deben ser enviados');
            }

            $userJpa = ViewUsers::select()
                ->where('auth_token', $request->header('SoDe-Auth-Token'))
                ->where('username', $request->header('SoDe-Auth-User'))
                ->first();

            if (!$userJpa) {
                throw new Exception('No existe un registro de usuario que coincida con las credenciales enviadas', 404);
            }

            $session = new User();
            $session->setId($userJpa->id);
            $session->setRelativeId($userJpa->relative_id);
            $session->setUsername($userJpa->username);
            $session->setPassword($userJpa->password);
            $session->setAuthToken($userJpa->auth_token);
            $session->setRecoveryEmail($userJpa->recovery_email);

            $person = new Person();
            $person->setId($userJpa->person__id);
            $person->setName($userJpa->person__name);
            $person->setLastname($userJpa->person__lastname);

            $document = new Document();
            $document->setType($userJpa->person__document__type);
            $document->setNumber($userJpa->person__document__number);

            $person->setDocument($document);
            $person->setBirth($userJpa->person__birthdate);
            $person->setGender($userJpa->person__gender);

            $contact = new Contact();
            $contact->setEmail($userJpa->person__email);
            $contact->setPrefix($userJpa->person__phone__prefix);
            $contact->setPhone($userJpa->person__phone__number);
            $contact->setPhonefull($userJpa->person__phone__full);

            $person->setContact($contact);

            $address = new Address();
            $address->setUbigeo($userJpa->person__ubigeo);
            $address->setAddress($userJpa->person__address);

            $person->setAddress($address);
            $person->setStatus($userJpa->person__status);

            $session->setPerson($person);
            $session->setStatus($userJpa->status);

            if (!$session->getStatus()) {
                throw new Exception('Este usuario se encuentra inactivo en SoDe', 403);
            }

            if (!$session->getPerson()->getStatus()) {
                throw new Exception('Esta persona se encuentra inactiva en SoDe', 403);
            }

            $ok = true;
            $status = 200;
            $message = 'Existe una sesión activa';
        } catch (\Throwable $th) {
            $ok = false;
            $status = gStatus::get($th->getCode());
            // $message = $th->getMessage();
            $message = $th->getMessage() . ' ' . $th->getFile() . ' Ln' . $th->getLine();
            $session = null;
        } finally {
            return [$ok, $status, $message, $session];
        }
    }

    public static function nullOrEmpty(?string $string): bool
    {
        if (!isset($string) || $string == null || trim($string) == '') {
            return false;
        }
        return true;
    }
}
