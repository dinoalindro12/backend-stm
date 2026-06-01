<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ], [
            'email.required'    => 'Email wajib diisi',
            'email.email'       => 'Format email tidak valid',
            'password.required' => 'Password wajib diisi',
        ]);

        if (!auth()->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Data yang anda masukan tidak benar, coba perbaiki lagi'
            ], 401);
        }

        $user  = auth()->user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return (new UserResource($user))
            ->additional([
                'success' => true,
                'message' => 'Anda berhasil login',
                'token'   => $token,
            ]);
    }
}
