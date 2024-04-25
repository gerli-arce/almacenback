<?php
namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\Notifications;
use App\Models\ViewNotifications;
use App\Models\ViewPeople;
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
                    $NotificationsJpa->description = 'Se le presto: <strong>'.$lend['model']['model'].'</strong> El monto de: <strong>'.$lend['mount_new'].'</strong> = Nuevos, <strong>'.$lend['mount_second'].'</strong> = Segunda en la fecha: <strong>'.$lend['sale']["date_sale"].'</strong>';
                    $NotificationsJpa->_person = $lend['technical']['id'];
                    $NotificationsJpa->creation_date = gTrace::getDate('mysql');
                    $NotificationsJpa->_view = 34;
                    $NotificationsJpa->_sale = $lend['sale']['id'];
                    $NotificationsJpa->_stock = $lend['id'];
                    $NotificationsJpa->status = 1;
                    $NotificationsJpa->save();
                }else{
                    $NotificationsVerifi->_person = $lend['technical']['id'];
                    $NotificationsVerifi->description = 'Se le presto: <strong>'.$lend['model']['model'].'</strong> El monto de: <strong>'.$lend['mount_new'].'</strong> = Nuevos, <strong>'.$lend['mount_second'].'</strong> = Segunda en la fecha: <strong>'.$lend['sale']["date_sale"].'</strong>';
                    $NotificationsVerifi->save();
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

    public function getNotifications(Request $request){
        $response = new Response();
        try {
            $ViewNotifications = ViewNotifications::whereNotNull('status')->get();

            $notifications = array();
            foreach ($ViewNotifications as $nitificationJpa) {
                $notifi = gJSON::restore($nitificationJpa->toArray(), '__');
                if($notifi['view']['id'] == 34){
                    $PeopleJpa = ViewPeople::find($notifi['_person']);
                    if($PeopleJpa){
                        $notifi['person'] =gJSON::restore($PeopleJpa->toArray(), '__');
                    }
                }
                $notifications[] = $notifi;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($notifications);
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
