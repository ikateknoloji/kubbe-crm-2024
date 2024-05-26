<?php

namespace App\Http\Controllers\V1\Role;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Summary of RoleController
 * @author: Kube
 * @since: 2023-01-20
 * @method addRoleToUser(Request $request, User $user)
 * @param Request $request
 * @param User $user
 * 
 */
class RoleController extends Controller
{
    /**
     * Kullanıcı için rol ekleme
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\User $user
     * @return mixed|\Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @POST /user/123/addRole HTTP/1.1
     * { "role_id": 1 }
     * Authorization: Bearer your-auth-token
     * Content-Type: application/json
     */
    public function addRoleToUser(Request $request, User $user)
    {
        try {
            $request->validate([
                'role_id' => 'required|exists:roles,id', // Rol ID'si zorunlu ve roles tablosunda mevcut olmalı
            ], [
                'role_id.required' => 'Rol ID alanı gereklidir.',
                'role_id.exists' => 'Seçilen rol mevcut değil.',
            ]);
        
            // Kullanıcıya rol ekler
            $user->roles()->attach($request->role_id);
        
            return response()->json([
                'message' => 'Rol başarıyla eklendi.',
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Kullanıcı için rol silme
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\User $user
     * @return mixed|\Illuminate\Http\JsonResponse
     * Örnek İstek Yapısı
     * @DELETE /user/123/removeRole HTTP/1.1
     * { "role_id": 1 }
     * Authorization: Bearer your-auth-token
     * Content-Type: application/json
     */
    public function removeRoleFromUser(Request $request, User $user)
    {
      try {
            $request->validate([
                'role_id' => 'required|exists:roles,id', // Rol ID'si zorunlu ve roles tablosunda mevcut olmalı
            ], [
                'role_id.required' => 'Rol ID alanı gereklidir.',
                'role_id.exists' => 'Seçilen rol mevcut değil.',
            ]);

            // Kullanıcıdan rolü siler
            $user->roles()->detach($request->role_id);

            return response()->json([
                'message' => 'Rol başarıyla silindi.',
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        }
    }
}