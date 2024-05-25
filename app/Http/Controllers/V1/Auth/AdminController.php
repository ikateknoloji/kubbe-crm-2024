<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function deleteUser($id)
    {
        // Kullanıcıyı bulun
        $user = User::find($id);
    
        // Kullanıcıyı silin
        if ($user) {
            $user->delete();
            return response()->json([
                'message' => 'Kullanıcı başarıyla silindi.'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Kullanıcı bulunamadı.'
            ], 404);
        }
    }
}