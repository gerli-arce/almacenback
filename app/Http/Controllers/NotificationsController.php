<?php
namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\Notifications;
use App\Models\Branch;
use App\Models\Stock;
use App\Models\Response;
use App\Models\ViewLendsForNotification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationsController extends Controller
{
    public function setNotificationByLends(Request $request, $date){
        $response = new Response();
        try {

            $ViewLendsForNotificationJpa = ViewLendsForNotification::where('type', 'LEND')->where('sale__tipe_installation', 'PRESTAMO')
            ->where('sale__date_sale','<=', $date)
            ->get();

            $lends = array();
            foreach ($ViewLendsForNotificationJpa as $lendJpa) {
                $lend = gJSON::restore($lendJpa->toArray(), '__');
                $NotificationsVerifi = Notifications::where('_view', 34)->where("_sale",$lend['sale']['id'])->where('_stock', $lend['id'])->first();
                if(!$NotificationsVerifi){
                    $NotificationsJpa = new Notifications();
                    $NotificationsJpa->title = "Pendiente devolucion de productos";
                    $NotificationsJpa->name = $lend['technical']['name'].' '.$lend['technical']['lastname'];
                    $NotificationsJpa->description = $lend['description'];
                    $NotificationsJpa->creation_date = gTrace::getDate('mysql');
                    $NotificationsJpa->_view = 34;
                    $NotificationsJpa->_sale = $lend['sale']['id'];
                    $NotificationsJpa->_stock = $lend['id'];
                    $NotificationsJpa->status = 1;
                    $NotificationsJpa->save();
                }
               
                $lends[] = $lend;
            }



            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($lends);
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
