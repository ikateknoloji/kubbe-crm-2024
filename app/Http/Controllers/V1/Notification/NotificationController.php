<?php

namespace App\Http\Controllers\V1\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\AdminNotification;
use App\Models\CourierNotification;
use App\Models\DesingerNotification;
use App\Models\ManufacturerNotification;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class NotificationController extends Controller
{
    /**
     * Display a paginated listing of admin notifications, ordered by creation date and unread status.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAdminNotifications(Request $request)
    {
        $oneWeekAgo = Carbon::now()->subWeek();
    
        // Okunmamış bildirimleri önceleyerek sırala
        $notifications = AdminNotification::where('created_at', '>=', $oneWeekAgo)
                                          ->orderBy('is_read', 'asc')
                                          ->orderBy('created_at', 'desc')
                                          ->paginate(10);
    
        // Okunmamış bildirim sayısını hesapla
        $unreadCount = AdminNotification::where('is_read', false)->where('created_at', '>=', $oneWeekAgo)->count();
    
        // Yanıtı hazırla
        $response = $notifications->toArray();
        $response['unread_count'] = $unreadCount;
    
        return response()->json($response);
    }

    /**
     * Display a paginated listing of courier notifications, ordered by creation date and unread status.
     *
     * @return \Illuminate\Http\Response
     */
    public function getCourierNotifications(Request $request)
    {
        $oneWeekAgo = Carbon::now()->subWeek();

        // Okunmamış bildirimleri önceleyerek sırala
        $notifications = CourierNotification::where('created_at', '>=', $oneWeekAgo)
                                            ->orderBy('is_read', 'asc')
                                            ->orderBy('created_at', 'desc')
                                            ->paginate(10);

        // Okunmamış bildirim sayısını hesapla
        $unreadCount = CourierNotification::where('is_read', false)->where('created_at', '>=', $oneWeekAgo)->count();

        // Yanıtı hazırla
        $response = $notifications->toArray();
        $response['unread_count'] = $unreadCount;

        return response()->json($response);
    }

    
    /**
     * Display a paginated listing of designer notifications, ordered by creation date and unread status.
     *
     * @return \Illuminate\Http\Response
     */
    public function getDesignerNotifications(Request $request)
    {
        $oneWeekAgo = Carbon::now()->subWeek();

        $notifications = DesingerNotification::where('created_at', '>=', $oneWeekAgo)
                                            ->orderBy('is_read', 'asc') // Okunmamış bildirimler önce
                                            ->orderBy('created_at', 'desc') // Sonra oluşturulma 
                                            ->paginate(10);

        $unreadCount = DesingerNotification::where('is_read', 'false')->where('created_at', '>=', $oneWeekAgo)->count();

        $response = $notifications->toArray();
        $response['unread_count'] = $unreadCount;

        return response()->json($response);
    }

    /**
     * Display a paginated listing of user notifications, ordered by creation date and unread status.
     *
     * @return \Illuminate\Http\Response
     */
    public function getUserNotifications(Request $request)
    {
        $user_id = Auth::id();
        $oneWeekAgo = Carbon::now()->subWeek();
    
        // Kullanıcının okunmamış bildirimlerini önceleyerek sırala
        $notifications = UserNotification::where('user_id', $user_id)
                                         ->where('created_at', '>=', $oneWeekAgo)
                                         ->orderBy('is_read', 'asc') // Okunmamış bildirimler önce
                                         ->orderBy('created_at', 'desc') // Sonra oluşturulma tarihine göre azalan sırada
                                         ->paginate(10);
    
        // Kullanıcının okunmamış bildirim sayısını hesapla
        $unreadCount = UserNotification::where('user_id', $user_id)
                                       ->where('created_at', '>=', $oneWeekAgo)
                                       ->where('is_read', false) // 'false' yerine false kullan
                                       ->count();
    
        // Yanıtı hazırla
        $response = $notifications->toArray();
        $response['unread_count'] = $unreadCount;
    
        return response()->json($response);
    }

    /**
     * Display a paginated listing of manufacturer notifications, ordered by creation date and unread status.
     *
     * @return \Illuminate\Http\Response
     */
    public function getManufacturerNotifications(Request $request)
    {
        $user_id = Auth::id();
        $oneWeekAgo = Carbon::now()->subWeek();
    
        // Üretici bildirimlerini okunmamış olanları önceleyerek sırala
        $notifications = ManufacturerNotification::where('user_id', $user_id)
                                                 ->where('created_at', '>=', $oneWeekAgo)
                                                 ->orderBy('is_read', 'asc') // Okunmamış bildirimler önce
                                                 ->orderBy('created_at', 'desc') // Sonra oluşturulma tarihine göre azalan  sırada
                                                 ->paginate(10);
    
        // Üreticinin okunmamış bildirim sayısını hesapla
        $unreadCount = ManufacturerNotification::where('user_id', $user_id)
                                                ->where('created_at', '>=', $oneWeekAgo)
                                               ->where('is_read', false) // 'false' yerine false kullan
                                               ->count();
    
        // Yanıtı hazırla
        $response = $notifications->toArray();
        $response['unread_count'] = $unreadCount;
    
        return response()->json($response);
    }


}