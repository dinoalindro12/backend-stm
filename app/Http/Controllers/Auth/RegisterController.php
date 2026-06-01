<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

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
        if ($userCount >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Maaf, batas maksimal registrasi (5 akun) telah tercapai.'
            ], 403);
        }

        // Validasi input
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => [
                'required',
                Password::min(8)->letters()->numbers()->symbols()
            ],
        ]);

        // Buat user baru
        $user  = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => bcrypt($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return (new UserResource($user))
            ->additional([
                'success' => true,
                'message' => 'Registrasi berhasil',
                'token'   => $token,
            ])
            ->response()
            ->setStatusCode(201);
    }
}