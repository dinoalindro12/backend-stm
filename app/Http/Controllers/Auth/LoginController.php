<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LoginController extends Controller
{
    /**
     * Jumlah percobaan bebas sebelum lockout mulai berlaku.
     */
    private const FREE_ATTEMPTS = 3;

    /**
     * Durasi lockout pertama (menit).
     */
    private const BASE_LOCKOUT_MINUTES = 5;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ], [
            'email.required'    => 'Email wajib diisi',
            'email.email'       => 'Format email tidak valid',
            'password.required' => 'Password wajib diisi',
        ]);

        $key = $this->throttleKey($request);

        // Cek apakah sedang dalam masa lockout
        if ($this->isLockedOut($key)) {
            $seconds = $this->lockoutSecondsRemaining($key);
            $minutes = ceil($seconds / 60);

            return response()->json([
                'success'            => false,
                'message'            => "Terlalu banyak percobaan login. Coba lagi dalam {$minutes} menit.",
                'retry_after_seconds'=> $seconds,
            ], 429);
        }

        if (!auth()->attempt($request->only('email', 'password'))) {
            $this->incrementFailures($key);

            $attempts = $this->getFailureCount($key);

            // Jika sudah melebihi percobaan bebas, terapkan lockout
            if ($attempts > self::FREE_ATTEMPTS) {
                $lockoutMinutes = $this->calculateLockoutMinutes($attempts);
                $this->applyLockout($key, $lockoutMinutes);

                return response()->json([
                    'success'            => false,
                    'message'            => "Data yang anda masukan tidak benar. Akun dikunci selama {$lockoutMinutes} menit.",
                    'retry_after_seconds'=> $lockoutMinutes * 60,
                ], 429);
            }

            $remaining = self::FREE_ATTEMPTS - $attempts;

            return response()->json([
                'success'           => false,
                'message'           => 'Data yang anda masukan tidak benar, coba perbaiki lagi',
                'attempts_remaining'=> max(0, $remaining),
            ], 401);
        }

        // Login berhasil — reset semua hitungan
        $this->clearFailures($key);

        $user  = auth()->user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return (new UserResource($user))
            ->additional([
                'success' => true,
                'message' => 'Anda berhasil login',
                'token'   => $token,
            ]);
    }

    /**
     * Key unik per kombinasi IP + email.
     */
    private function throttleKey(Request $request): string
    {
        return 'login_failures:' . sha1(strtolower($request->email) . '|' . $request->ip());
    }

    /**
     * Ambil jumlah kegagalan saat ini.
     */
    private function getFailureCount(string $key): int
    {
        return (int) Cache::get("{$key}:count", 0);
    }

    /**
     * Tambah hitungan kegagalan. Simpan selama 24 jam.
     */
    private function incrementFailures(string $key): void
    {
        $count = $this->getFailureCount($key) + 1;
        Cache::put("{$key}:count", $count, now()->addHours(24));
    }

    /**
     * Reset semua data kegagalan setelah login berhasil.
     */
    private function clearFailures(string $key): void
    {
        Cache::forget("{$key}:count");
        Cache::forget("{$key}:locked_until");
    }

    /**
     * Cek apakah sedang dalam masa lockout.
     */
    private function isLockedOut(string $key): bool
    {
        $lockedUntil = Cache::get("{$key}:locked_until");
        return $lockedUntil && now()->lessThan($lockedUntil);
    }

    /**
     * Sisa detik masa lockout.
     */
    private function lockoutSecondsRemaining(string $key): int
    {
        $lockedUntil = Cache::get("{$key}:locked_until");
        return $lockedUntil ? max(0, (int) now()->diffInSeconds($lockedUntil)) : 0;
    }

    /**
     * Terapkan lockout dengan durasi tertentu.
     */
    private function applyLockout(string $key, int $minutes): void
    {
        Cache::put("{$key}:locked_until", now()->addMinutes($minutes), now()->addHours(24));
    }

    /**
     * Hitung durasi lockout berdasarkan jumlah percobaan gagal.
     *
     * Percobaan ke-4 → 5 menit
     * Percobaan ke-5 → 10 menit
     * Percobaan ke-6 → 20 menit
     * Percobaan ke-7 → 40 menit
     * dst (lipat dua setiap kali)
     */
    private function calculateLockoutMinutes(int $attempts): int
    {
        $extra = $attempts - self::FREE_ATTEMPTS; // 1, 2, 3, ...
        return self::BASE_LOCKOUT_MINUTES * (int) pow(2, $extra - 1);
    }
}
