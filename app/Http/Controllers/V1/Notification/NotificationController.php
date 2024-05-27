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

class NotificationController extends Controller
{
    /**
     * Display a paginated listing of admin notifications, ordered by creation date and unread status.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAdminNotifications(Request $request)
    {
        $notifications = AdminNotification::orderBy('created_at', 'desc')
                                          ->paginate(10);

        $unreadCount = AdminNotification::where('is_read', 0)->count();
        
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
        $notifications = CourierNotification::orderBy('created_at', 'desc')
                                            ->paginate(10);

        $unreadCount = CourierNotification::where('is_read', 0)->count();
        
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
        $notifications = DesingerNotification::orderBy('created_at', 'desc')
                                             ->paginate(10);
        
        $unreadCount = DesingerNotification::where('is_read', 0)->count();

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

        $notifications = UserNotification::where('user_id', $user_id)
                                         ->orderBy('created_at', 'desc')
                                         ->paginate(10);
                                         
        $unreadCount = UserNotification::where('user_id', $user_id)->where('is_read', 0)->count();
        
        $response = $notifications->toArray();
        $response['unread_count'] = $unreadCount;

        return response()->json($response);
    }

    /**
     * Display a paginated listing of user notifications, ordered by creation date and unread status.
     *
     * @return \Illuminate\Http\Response
     */
    public function getManufacturerNotifications(Request $request)
    {
        $user_id = Auth::id();

        $notifications = ManufacturerNotification::where('user_id', $user_id)
                                         ->orderBy('created_at', 'desc')
                                         ->paginate(10);
                                         
        $unreadCount = ManufacturerNotification::where('user_id', $user_id)->where('is_read', 0)->count();
        
        $response = $notifications->toArray();
        $response['unread_count'] = $unreadCount;

        return response()->json($response);
    }
}