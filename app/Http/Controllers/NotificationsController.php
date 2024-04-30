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
use Psy\Command\WhereamiCommand;

class NotificationsController extends Controller
{
    public function setNotificationByLends(Request $request, $date){
        $response = new Response();
        try {

            $dateSend = str_replace('T',' ', $date);

            $ViewLendsForNotificationJpa = ViewLendsForNotification::where('type', 'LEND')
            ->whereNotNull("date_return")
            ->orderBy('id', 'desc')
            ->where('date_return','<=', $dateSend)
            ->distinct()
            ->get();

            $lends = array();
            foreach ($ViewLendsForNotificationJpa as $lendJpa) {
                $lend = gJSON::restore($lendJpa->toArray(), '__');
                $NotificationsVerifi = Notifications::where('_view', 34)->where('_stock', $lend['id'])->first();
                if(!$NotificationsVerifi){
                    $NotificationsJpa = new Notifications();
                    $NotificationsJpa->title = "Pendiente devolucion de productos";
                    $NotificationsJpa->name = $lend['technical']['name'].' '.$lend['technical']['lastname'];
                    $NotificationsJpa->description = 'Se le presto: <strong>'.$lend['model']['model'].'</strong> El monto de: <strong>'.$lend['mount_new'].'</strong> = Nuevos, <strong>'.$lend['mount_second'].'</strong> = Segunda en la fecha: <strong>'.$lend["date_lend"].'</strong>';
                    $NotificationsJpa->_person = $lend['technical']['id'];
                    $NotificationsJpa->creation_date = gTrace::getDate('mysql');
                    $NotificationsJpa->_view = 34;
                    $NotificationsJpa->_stock = $lend['id'];
                    $NotificationsJpa->status = 1;
                    $NotificationsJpa->save();
                }else{
                    $NotificationsVerifi->_person = $lend['technical']['id'];
                    $NotificationsVerifi->description = 'Se le presto: <strong>'.$lend['model']['model'].'</strong> El monto de: <strong>'.$lend['mount_new'].'</strong> = Nuevos, <strong>'.$lend['mount_second'].'</strong> = Segunda en la fecha: <strong>'.$lend["date_lend"].'</strong>';
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
            $ViewNotifications = ViewNotifications::whereNotNull('status')->where('status', "!=", 0) ->orderBy('id', 'desc')->get();

            $notifications = array();
            foreach ($ViewNotifications as $nitificationJpa) {
                $notifi = gJSON::restore($nitificationJpa->toArray(), '__');
                $ViewLendsForNotificationJpa = ViewLendsForNotification::where('type', 'LEND')->whereNotNull('status')
                ->where('status', "!=", 0)
                ->where('id', $notifi['_stock'])
                ->first();
                if($ViewLendsForNotificationJpa){
                    if($notifi['view']['id'] == 34){
                        $PeopleJpa = ViewPeople::find($notifi['_person']);
                        if($PeopleJpa){
                            $notifi['person'] =gJSON::restore($PeopleJpa->toArray(), '__');
                        }
                    }
                    $notifications[] = $notifi;
                }else{
                    $ViewNotificationsVal = ViewNotifications::find($notifi['id']);
                    $ViewNotificationsVal->status = 0;
                    $ViewNotificationsVal->save();
                }
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
