<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ChangePasswordController extends Controller
{
    /**
     * Handle password change request.
     */
    public function __invoke(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
            'new_password_confirmation' => 'required'
        ], [
            'current_password.required' => 'Password saat ini wajib diisi',
            'new_password.required' => 'Password baru wajib diisi',
            'new_password.min' => 'Password baru minimal 8 karakter',
            'new_password.confirmed' => 'Konfirmasi password baru tidak cocok',
            'new_password_confirmation.required' => 'Konfirmasi password baru wajib diisi'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan validasi',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Ambil user yang sedang login
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses'
                ], 401);
            }

            // Cek password saat ini
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password saat ini salah'
                ], 400);
            }

            // Cek apakah password baru sama dengan password lama
            if (Hash::check($request->new_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password baru tidak boleh sama dengan password lama'
                ], 400);
            }

            // Update password
            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Password berhasil diubah'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah password: ' . $e->getMessage()
            ], 500);
        }
    }
}