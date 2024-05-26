<?php

namespace App\Http\Controllers\V1\Role;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class GetUserInfoController extends Controller
{
    /**
     * Rol ismine göre kullanıcıları getir.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
public function getUsersByRole(Request $request)
{
    // 'roleName' parametresini doğrula
    $request->validate([
        'roleName' => 'required|string|exists:roles,name', // roles tablosunda böyle bir role adı var mı kontrol et
    ]);

    // Role modeli üzerinden sorgu yapılır ve ilişkili kullanıcılar alınır
    $role = Role::where('name', $request->input('roleName'))->first();
    if ($role) {
        $usersWithRole = $role->users;
    } else {
        // Eğer role bulunamazsa boş bir koleksiyon döndürülür
        $usersWithRole = collect();
    }

    // Kullanıcılar JSON olarak döndürülür
    return response()->json($usersWithRole);
}

}