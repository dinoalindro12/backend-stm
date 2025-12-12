<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email', 
            'password' => 'required'
        ]);
        
        if (!auth()->attempt($credentials)) {
            return response()->json(['message' => 'Data yang anda masukan tidak benar, coba perbaiki lagi'], 401);
        }
        
        $user = auth()->user();
        $token = $user->createToken('auth_token')->plainTextToken;
        
        return response()->json([
            'message' => 'Anda berhasil login',
            'user' => $user,
            'token' => $token
        ], 200);
    }
}
