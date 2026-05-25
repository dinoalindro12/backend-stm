<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KontakResource;
use App\Models\Kontak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class KontakController extends Controller
{
    /**
     * Tampilkan semua pesan kontak dengan filter opsional.
     */
    public function index(Request $request): JsonResponse|AnonymousResourceCollection
    {
        try {
            $query = Kontak::with('admin')->latest();

            // Filter berdasarkan status
            if ($request->has('status_dibaca')) {
                $query->where('status_dibaca', $request->status_dibaca);
            }

            $kontak = $query->paginate($request->per_page ?? 10);

            return KontakResource::collection($kontak)->additional([
                'message' => 'Data kontak berhasil diambil',
                'summary' => [
                    'total_pending' => Kontak::pending()->count(),
                    'total_dibaca' => Kontak::dibaca()->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kontak',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simpan pesan kontak baru dari publik.
     */
public function store(Request $request): JsonResponse
{
    // Dapatkan IP pengirim
    $ipAddress = $request->ip();
    $key = 'kontak-create:' . $ipAddress;
    
    // Cek limit: hanya 1 request per menit
    if (RateLimiter::tooManyAttempts($key, 1)) {
        $seconds = RateLimiter::availableIn($key);
        return response()->json([
            'success' => false,
            'message' => 'Terlalu banyak permintaan. Silakan coba lagi setelah ' . $seconds . ' detik.',
            'errors' => [
                'rate_limit' => ['Terlalu banyak permitaan. Silahkan coba lagi beberapa menit setelah ini.']
            ]
        ], 429); // HTTP 429 Too Many Requests
    }
    
    // Hitung attempt
    RateLimiter::hit($key, 60); // Lock selama 60 detik
    
    $validator = Validator::make($request->all(), [
        'nama' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'no_wa' => 'nullable|string|regex:/^[0-9]+$/|max:15',
        'perusahaan' => 'nullable|string|max:255',
        'subjek' => 'required|string|max:255',
        'isi' => 'required|string',          
    ], [
        'no_wa.max' => 'Maaf, nomor WhatsApp maksimal 15 digit',
        'no_wa.regex' => 'Maaf, nomor WhatsApp hanya boleh berisi angka',
    ]);

    if ($validator->fails()) {
        // Jangan lupa mengurangi attempt jika validasi gagal
        RateLimiter::clear($key);
        
        return response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $kontak = Kontak::create([
            'nama' => $request->nama,
            'email' => $request->email,
            'no_wa'=>  $request->no_wa,
            'perusahaan' => $request->perusahaan,
            'subjek' => $request->subjek,
            'isi' => $request->isi,
            'status_dibaca' => 'pending',
        ]);

        return (new KontakResource($kontak))
            ->additional(['message' => 'Pesan berhasil dikirim'])
            ->response()
            ->setStatusCode(201);

    } catch (\Illuminate\Database\QueryException $e) {
        // Hapus attempt jika gagal menyimpan
        RateLimiter::clear($key);
        
        return response()->json([
            'success' => false,
            'message' => 'Gagal menyimpan data ke database',
            'error' => $e->getMessage()
        ], 500);
    } catch (\Exception $e) {
        // Hapus attempt jika ada error
        RateLimiter::clear($key);
        
        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan saat memproses pesan',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Tampilkan detail pesan — otomatis tandai dibaca oleh admin yang login.
     */
    public function show(Request $request, string $id): JsonResponse|KontakResource
    {
        try {
            $kontak = Kontak::with('admin')->findOrFail($id);

            // Otomatis tandai dibaca saat admin membuka pesan
            $kontak->tandaiDibaca($request->user()->id);

            return (new KontakResource($kontak->fresh('admin')))
                ->additional(['message' => 'Data kontak berhasil diambil']);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kontak',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update data kontak (hanya field teks, bukan status).
     */
    public function update(Request $request, string $id): JsonResponse|KontakResource
    {
        try {
            $kontak = Kontak::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'nama' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|max:255',
                'no_wa' => 'sometimes|required|regex:/^[0-9]+$/|max:15',
                'perusahaan' => 'nullable|string|max:255',
                'subjek' => 'sometimes|required|string|max:255',
                'isi' => 'sometimes|required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $kontak->update($request->only(['nama', 'email','no_wa', 'perusahaan', 'subjek', 'isi']));

            return (new KontakResource($kontak->fresh('admin')))
                ->additional(['message' => 'Data kontak berhasil diperbarui']);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data ke database',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hapus pesan kontak (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $kontak = Kontak::findOrFail($id);
            $kontak->delete();

            return response()->json([
                'success' => true,
                'message' => 'Data kontak berhasil dihapus'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data dari database',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tandai status dibaca secara manual — mencatat admin yang melakukan aksi.
     */
    public function updateStatus(Request $request, string $id): JsonResponse|KontakResource
    {
        try {
            $kontak = Kontak::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'status_dibaca' => 'required|in:pending,dibaca',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($request->status_dibaca === 'dibaca') {
                // Catat admin yang menandai dan waktu dibaca
                $kontak->update([
                    'status_dibaca' => 'dibaca',
                    'dibaca_pada' => now(),
                    'admin_id' => $request->user()->id,
                ]);
            } else {
                // Reset ke pending — hapus catatan pembaca
                $kontak->update([
                    'status_dibaca' => 'pending',
                    'dibaca_pada' => null,
                    'admin_id' => null,
                ]);
            }

            return (new KontakResource($kontak->fresh('admin')))
                ->additional(['message' => 'Status kontak berhasil diperbarui']);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status ke database',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
