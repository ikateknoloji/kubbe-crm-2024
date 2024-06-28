<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\V1\Auth\ProfileController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $profileController;

    /**
     * Profile Controller ile ilgili bir obje oluşturma
     * @param \App\Http\Controllers\V1\Auth\ProfileController $profileController
     */
    public function __construct(ProfileController $profileController)
    {
        $this->profileController = $profileController;
    }


    /**
     * Summary of register
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * {
     *   "name": "John Doe",
     *   "email": "johndoe@example.com",
     *   "password": "password123",
     *   "password_confirmation": "password123",
     *   "roles": [1, 2]
     *  }
     */
    public function register(Request $request)
    {
        try {
            // Gelen verilerin doğrulaması
            $request->validate([
                'name' => 'required|max:255', // İsim alanı zorunlu ve en fazla 255 karakter olmalı
                'email' => 'required|email|unique:users', // E-posta alanı zorunlu, geçerli bir e-posta adresi olmalı ve benzersiz olmalı
                'password' => 'required|min:8|confirmed', // Şifre alanı zorunlu, en az 8 karakter olmalı ve şifre tekrarı ile eşleşmeli
                'roles' => 'required|array', // Roller alanı zorunlu ve bir dizi olmalı
                'roles.*' => 'exists:roles,id', // Roller dizisi içindeki her bir rol, roles tablosunda mevcut olmalı
                'profile_photo' => 'nullable|image|max:2048', // Profil fotoğrafı alanı opsiyonel, bir resim dosyası olmalı ve en fazla 2048 kilobayt olmalı
            ], 
            [
                // Doğrulama hataları için özel hata mesajları
                'name.required' => 'İsim alanı gereklidir.',
                'name.max' => 'İsim alanı en fazla 255 karakter olmalıdır.',
                'email.required' => 'E-posta alanı gereklidir.',
                'email.email' => 'Geçerli bir e-posta adresi girin.',
                'email.unique' => 'Bu e-posta adresi zaten kullanımda.',
                'password.required' => 'Şifre alanı gereklidir.',
                'password.min' => 'Şifre en az 8 karakter olmalıdır.',
                'password.confirmed' => 'Şifreler eşleşmiyor.',
                'roles.required' => 'En az bir rol seçmelisiniz.',
                'roles.array' => 'Roller bir dizi olmalıdır.',
                'roles.*.exists' => 'Seçilen rol mevcut değil.',
                'profile_photo.image' => 'Profil fotoğrafı bir resim dosyası olmalıdır.',
                'profile_photo.max' => 'Profil fotoğrafı en fazla 2048 kilobayt olmalıdır.',
            ]);

            // Yeni kullanıcı oluşturma
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password), // Şifrenin hashlenmesi
            ]);

            // Kullanıcının rollerini ayarlama
            $user->roles()->sync($request->roles);

            // Profil fotoğrafını yükleme
            if ($request->hasFile('profile_photo')) {
              $this->profileController->uploadPP($request, $user);
            }

            // İstemci adının belirtilip belirtilmediğini kontrol edin
            $deviceName = $request->device_name ?? 'unknown device';

            // Yanıtı döndürme
            return response()->json([
                'user' => $user,
                'roles' => $user->roles->pluck('name'),
                'token' => $user->createToken($deviceName)->plainTextToken, // Token oluşturma
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Doğrulama hatalarını yakalama ve döndürme
            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Giriş yapma
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * {
     *   "email": "johndoe@example.com",
     *   "password": "password123"
     *   "device_name": "iPhone 12"
     *  }
     */
    public function login(Request $request)
    {
        try {
            // Gelen verilerin doğrulaması
            $request->validate([
                'email' => 'required|email', // E-posta alanı zorunlu ve geçerli bir e-posta adresi olmalı
                'password' => 'required', // Şifre alanı zorunlu
                'device_name' => 'nullable|string', // Cihaz adı alanı opsiyonel ve bir string olmalı
            ], [
                // Doğrulama hataları için özel hata mesajları
                'email.required' => 'E-posta alanı gereklidir.',
                'email.email' => 'Geçerli bir e-posta adresi girin.',
                'password.required' => 'Şifre alanı gereklidir.',
            ]);
        
            // Kullanıcının e-posta adresine göre arama yapma
            $user = User::where('email', $request->email)->first();
        
            // Kullanıcının var olup olmadığını ve şifresinin doğru olup olmadığını kontrol etme
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Kullanıcı adı veya şifre hatalı.',
                ], 401);
            }
        
            // İstemci adının belirtilip belirtilmediğini kontrol edin
            $deviceName = $request->device_name ?? 'unknown device';

            // Kullanıcının rollerini al
            $roles = $user->roles->pluck('name');
            // Token oluşturma
            $token = $user->createToken($deviceName)->plainTextToken;
        
            // Yanıtı döndürme
            return response()->json([
                'user' => $user,
                'roles' => $roles,
                'token' => $token,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Doğrulama hatalarını yakalama ve döndürme
            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        }
    }
    
    /**
     * Oturum Kapatma
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Kullanıcının belirli bir token'ını siler
        $request->user()->token()->revoke();

        // Çıkış yapıldı mesajını döndürme
        return response()->json('Çıkış yapıldı', 200);
    }

}