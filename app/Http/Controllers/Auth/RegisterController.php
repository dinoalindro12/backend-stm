<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RegisterController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        // Cek jumlah user yang sudah terdaftar
        $userCount = User::count();
        
        // Jika sudah mencapai 4 akun, tolak registrasi
        if ($userCount >= 10) {
            return response()->json([
                'message' => 'Maaf, batas maksimal registrasi (4 akun) telah tercapai.'
            ], 403); // 403 Forbidden
        }
        
        // Validasi input
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);
        
        // Buat user baru
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);
        
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201); // 201 Created
    }
}