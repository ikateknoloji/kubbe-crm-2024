<?php

namespace App\Http\Controllers\V1\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AdminNotification;
use App\Models\CourierNotification;
use App\Models\DesingerNotification;
use App\Models\ManufacturerNotification;
use App\Models\UserNotification;

class NotificationReadController extends Controller
{
     /**
     * Mark an admin notification as read.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAdminNotificationAsRead(Request $request, $id)
    {
        $notification = AdminNotification::findOrFail($id);
        $notification->is_read = true;
        $notification->save();

        return response()->json(['message' => 'Admin notification marked as read']);
    }

    /**
     * Mark a courier notification as read.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markCourierNotificationAsRead(Request $request, $id)
    {
        $notification = CourierNotification::findOrFail($id);
        $notification->is_read = true;
        $notification->save();

        return response()->json(['message' => 'Courier notification marked as read']);
    }

    /**
     * Mark a designer notification as read.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markDesignerNotificationAsRead(Request $request, $id)
    {
        $notification = DesingerNotification::findOrFail($id);
        $notification->is_read = true;
        $notification->save();

        return response()->json(['message' => 'Designer notification marked as read']);
    }

    /**
     * Mark a user notification as read.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markUserNotificationAsRead(Request $request, $id)
    {
        $notification = UserNotification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->is_read = true;
        $notification->save();

        return response()->json(['message' => 'User notification marked as read']);
    }   

        /**
     * Mark a user notification as read.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markManufacturerNotificationAsRead(Request $request, $id)
    {
        $notification = ManufacturerNotification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->is_read = true;
        $notification->save();

        return response()->json(['message' => 'User notification marked as read']);
    }   
}