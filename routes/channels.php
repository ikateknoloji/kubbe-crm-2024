<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Illuminate\Support\Facades\Route;

    Broadcast::routes(['middleware' => ['auth:sanctum']]);

    // Kullanıcı kanalı, kullanıcı kimliğini doğrular
    Broadcast::channel('user.{id}', function ($user, $id) {
        return (int) $user->id === (int) $id;
    });

    // Admin kanalı, admin olup olmadığını kontrol eder
    Broadcast::channel('admin-notifications', function ($user) {
        return true; // Gerekirse yetkilendirme kontrollerini burada yapabilirsiniz
    });
    
    // Courier kanalı, sadece oturum açmış kullanıcılar için
    Broadcast::channel('courier-notifications', function ($user) {
        return Auth::check();
    });

    // Designer kanalı, sadece oturum açmış kullanıcılar için
    Broadcast::channel('designer-notifications', function ($user) {
        return Auth::check();
    });