<?php

namespace App\Http\Controllers\Auth;

use Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class DeleteAccountController extends Controller
{
    /**
     * Handle account deletion request.
     */
    public function __invoke(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'password' => 'required',
            'confirmation' => 'required|in:YA,HAPUS' // Konfirmasi teks
        ], [
            'password.required' => 'Password wajib diisi untuk konfirmasi',
            'confirmation.required' => 'Konfirmasi penghapusan wajib diisi',
            'confirmation.in' => 'Tulis "YA" atau "HAPUS" untuk mengkonfirmasi'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Ambil user yang sedang login
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses'
                ], 401);
            }

            // Cek password untuk konfirmasi
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password salah. Penghapusan dibatalkan.'
                ], 400);
            }

            // Cek konfirmasi teks
            $confirmation = strtoupper($request->confirmation);
            if (!in_array($confirmation, ['YA', 'HAPUS'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Konfirmasi tidak valid. Tulis "YA" atau "HAPUS"'
                ], 400);
            }

            // Simpan informasi user sebelum dihapus (opsional, untuk audit)
            $userInfo = [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'deleted_at' => now()->toDateTimeString()
            ];

            // Hapus semua token (logout dari semua device)
            $user->tokens()->delete();

            // Hapus user (soft delete jika ada, atau hard delete)
            $deleteMethod = 'delete'; // default hard delete
            
            // Jika model menggunakan soft delete
            if (method_exists($user, 'softDelete')) {
                $deleteMethod = 'softDelete';
            } elseif (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($user))) {
                $deleteMethod = 'delete'; // Laravel soft delete otomatis
            }

            $user->$deleteMethod();

            // Commit transaction
            DB::commit();

            // Log aktivitas (opsional)
            \Log::info('User account deleted', $userInfo);

            return response()->json([
                'success' => true,
                'message' => 'Akun Anda berhasil dihapus. Semua data terkait telah dihapus.'
            ], 200);

        } catch (\Exception $e) {
            // Rollback transaction jika error
            DB::rollBack();
            
            \Log::error('Failed to delete user account: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus akun: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request untuk menghapus akun (verifikasi awal)
     */
    // public function requestDelete(Request $request)
    // {
    //     $user = auth()->user();
        
    //     // Generate deletion token (valid 24 jam)
    //     $token = hash('sha256', $user->email . now()->timestamp . env('APP_KEY'));
        
    //     // Simpan token di cache (24 jam)
    //     \Cache::put('delete_account_' . $user->id, $token, now()->addHours(24));
        
    //     // Kirim email konfirmasi (opsional)
    //     // $user->sendAccountDeletionConfirmation($token);
        
    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Permintaan penghapusan akun telah dikirim. Cek email Anda untuk konfirmasi.',
    //         'data' => [
    //             'confirmation_required' => true,
    //             'note' => 'Anda perlu mengkonfirmasi melalui email atau dengan mengirim password'
    //         ]
    //     ], 200);
    // }
}