<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class ProfileController extends Controller
{
    /**
     * Profil fotoğrafı yükleme
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "profile_photo": "image.jpg"
     *  }
     * POST /user/123/uploadProfilePhoto HTTP/1.1
     * Host: yourwebsite.com
     * Authorization: Bearer your-auth-token
     * Content-Type: multipart/form-data
     */
    public function uploadProfilePhoto(Request $request)
    {
        try {
            $request->validate([
                'profile_photo' => 'required|image|max:2048',
            ], [
                'profile_photo.required' => 'Profil fotoğrafı alanı gereklidir.',
                'profile_photo.image' => 'Profil fotoğrafı dosyası olmalıdır.',
                'profile_photo.max' => 'Profil fotoğrafı en fazla 2048 kB olmalıdır.',
            ]);
        
            $user = $request->user();
        
            if ($request->hasFile('profile_photo')) {
                $file = $request->file('profile_photo');
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('public/profile_photos', $filename);
            
                $user->profile_photo = 'storage/profile_photos/' . $filename;
                $user->save();
            }
        
            return response()->json([
                'message' => 'Profil fotoğrafı başarıyla yüklendi.',
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Profil fotoğrafını güncelleme
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\User $user
     * @return mixed|\Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @PUT 
     * {
     *   "profile_photo": "image.jpg"
     *  }
     * PUT /user/123/updateProfilePhoto HTTP/1.1
     * Host: yourwebsite.com
     * Authorization: Bearer your-auth-token
     * Content-Type: multipart/form-data
     */
    public function updateProfilePhoto(Request $request, User $user)
    {
        try {
            $request->validate([
                'profile_photo' => 'nullable|image|max:2048',
            ], [
                'profile_photo.image' => 'Profil fotoğrafı bir resim dosyası olmalıdır.',
                'profile_photo.max' => 'Profil fotoğrafı en fazla 2048 kilobayt olmalıdır.',
            ]);

            if ($request->hasFile('profile_photo')) {
                // Eski profil fotoğrafını sil
                if ($user->profile_photo) {
                    Storage::delete('public/' . $user->profile_photo);
                }

                // Yeni profil fotoğrafını yükle
                $file = $request->file('profile_photo');
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('public/profile_photos', $filename);

                $user->profile_photo = 'profile_photos/' . $filename;
                $user->save();
            }

            return response()->json([
                'message' => 'Profil fotoğrafı başarıyla güncellendi.',
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Profil fotoğrafı yükleme
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\User $user
     * @return void
     * Örnek İstek Yapısı
     * @POST 
     * {
     *   "profile_photo": "image.jpg"
     *  }
     */
    public function uploadPP(Request $request, User $user)
    {
        $file = $request->file('profile_photo');
        $filename = time() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('public/profile_photos', $filename);

        $user->profile_photo = 'storage/profile_photos/' . $filename;
        $user->save();
    }
}