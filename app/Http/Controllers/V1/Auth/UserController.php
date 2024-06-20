<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Şifre sıfırlama kodu gönderir.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * istek url:
     * - /api/v1/auth/password/reset
     * istek alanları:
     * - email: Kullanıcının e-posta adresinin
     */
    public function resetPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Bu e-posta adresi ile bir kullanıcı bulunamadı.'], 404);
        }

        $newPassword = Str::random(10);
        $user->password = Hash::make($newPassword);
        $user->save();

        // Mail::to($request->email)->send(new PasswordResetMail($newPassword));

        return response()->json(['message' => 'Yeni şifre e-posta ile gönderildi.'], 200);
    }

    /**
     * Şifre güncelleme
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     * istek url:
     * - /api/v1/auth/password/update
     * istek alanları:
     * - current_password: Kullanıcının mevcut şifresi
     * - new_password: Yeni şifre
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Mevcut şifre hatalı.'], 401);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Şifre başarıyla güncellendi.'], 200);
    }

    /**
     * E-posta adresini güncelleme
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     * istek url:
     * - /api/v1/auth/email/update
     * istek alanları:
     * - current_password: Kullanıcının mevcut şifresi
     * - new_email: Yeni e-posta adresinin
     */
    public function updateEmail(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_email' => 'required|email|unique:users,email',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Mevcut şifre hatalı.'], 401);
        }

        $user->email = $request->new_email;
        $user->save();

        return response()->json(['message' => 'E-posta adresi başarıyla güncellendi.'], 200);
    }

    // Şifre güncelleme fonksiyonu
    public function updatePasswordAdmin(Request $request)
    {
        // Validasyon
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'new_password' => 'required|min:8|confirmed',
        ]);

        // Kullanıcıyı bul
        $user = User::find($request->user_id);

        // Şifreyi güncelle
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Şifre başarıyla güncellendi']);
    }
}