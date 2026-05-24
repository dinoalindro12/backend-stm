<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RekruitmenResource;
use App\Http\Resources\StatusTerimaResource;
use App\Models\LowonganKerja;
use App\Models\Rekruitmen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;


class RekruitmenController extends Controller
{
    /**
     * Display a listing of the resource.
     */
public function index(Request $request)
    {
        $query = Rekruitmen::with('lowonganKerja');

        // Filter berdasarkan status
        if ($request->has('status_terima')) {
            $query->where('status_terima', $request->status_terima);
        }

        // Filter berdasarkan lowongan
        if ($request->has('lowongan_kerja_id')) {
            $query->where('lowongan_kerja_id', $request->lowongan_kerja_id);
        }

        // Filter berdasarkan posisi
        if ($request->has('posisi_dilamar')) {
            $query->where('posisi_dilamar', $request->posisi_dilamar);
        }

        $rekruitmen = $query->latest()->paginate($request->per_page ?? 10);

        // Cek jika data tidak ditemukan
        if ($rekruitmen->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Sejauh ini belum ada yang mengajukan lamaran',
                'data' => []
            ], 200);
        }

        return RekruitmenResource::collection($rekruitmen);
    }

    /**
     * Store a newly created resource in storage.
     */
public function store(Request $request): JsonResponse
{
    // Ambil IP user
    $ipAddress = $request->ip();

    // Key limiter
    $key = 'rekruitmen-create:' . $ipAddress;

    // Maksimal 1 request per menit
    if (RateLimiter::tooManyAttempts($key, 1)) {
        $seconds = RateLimiter::availableIn($key);

        return response()->json([
            'success' => false,
            'message' => 'Terlalu banyak permintaan. Silakan coba lagi setelah ' . $seconds . ' detik.',
            'errors' => [
                'rate_limit' => [
                    'Terlalu banyak permintaan. Silakan coba lagi beberapa saat lagi.'
                ]
            ]
        ], 429);
    }

    // Hit limiter selama 60 detik
    RateLimiter::hit($key, 60);

    $validator = Validator::make($request->all(), [
        'lowongan_kerja_id' => 'required|exists:lowongan_kerja,id',

        'nik' => [
            'required',
            'regex:/^[0-9]+$/',
            'max:16',
            'string',
            'max:16',
            function ($attribute, $value, $fail) use ($request) {
                $exists = Rekruitmen::where('nik', $value)
                    ->where('lowongan_kerja_id', $request->lowongan_kerja_id)
                    ->exists();

                if ($exists) {
                    $fail('Anda sudah pernah melamar di lowongan yang sama sebelumnya.');
                }
            },
        ],

        'nama' => 'required|string|max:255',
        'nama_lengkap' => 'required|string|max:255',
        'posisi_dilamar' => 'required|string|max:255',
        'no_wa' => 'required|regex:/^[0-9]+$/|string|max:16',
        'alamat' => 'nullable|string',

        'foto_ktp' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        'foto_kk' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        'foto_skck' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        'pas_foto' => 'required|image|mimes:jpeg,png,jpg|max:2048',

        'surat_sehat' => 'required|file|mimes:pdf,jpeg,png,jpg|max:2048',
        'surat_anti_narkoba' => 'required|file|mimes:pdf,jpeg,png,jpg|max:2048',

        'surat_lamaran' => 'required|file|mimes:pdf|max:2048',
        'cv' => 'required|file|mimes:pdf|max:2048',
    ],[
        'nik.regex' => 'NIK harus berupa angka saja.',
        'no_wa.regex' => 'Nomor WhatsApp harus berupa angka saja.',
        'nik.max' => 'NIK tidak boleh lebih dari 16 karakter.',
        'nik.string' => 'NIK harus berupa string.',
        'nik.unique' => 'Anda sudah pernah melamar di lowongan yang sama sebelumnya.',
        'foto_ktp.required' => 'Foto KTP wajib diunggah.',
        'foto_kk.required' => 'Foto KK wajib diunggah.',
        'foto_skck.required' => 'Foto SKCK wajib diunggah.',
        'pas_foto.required' => 'Pas foto wajib diunggah.',
        'surat_sehat.required' => 'Surat sehat wajib diunggah.',
        'surat_anti_narkoba.required' => 'Surat anti narkoba wajib diunggah.',
        'surat_lamaran.required' => 'Surat lamaran wajib diunggah.',
        'cv.required' => 'CV wajib diunggah.',
    ]);

    // Jika validasi gagal
    if ($validator->fails()) {

        // Hapus limiter supaya user bisa coba lagi
        RateLimiter::clear($key);

        return response()->json([
            'success' => false,
            'message' => 'Validation error',
            'errors' => $validator->errors()
        ], 422);
    }

    try {

        // Cek lowongan aktif
        $lowongan = LowonganKerja::findOrFail($request->lowongan_kerja_id);

        if (
            $lowongan->status_lowongan !== 'aktif' ||
            $lowongan->deadline_lowongan < now()
        ) {

            RateLimiter::clear($key);

            return response()->json([
                'success' => false,
                'message' => 'Lowongan kerja sudah tidak aktif atau melewati deadline'
            ], 400);
        }

        // Upload files
        $data = $request->except([
            'foto_ktp',
            'foto_kk',
            'foto_skck',
            'pas_foto',
            'surat_sehat',
            'surat_anti_narkoba',
            'surat_lamaran',
            'cv'
        ]);

        $files = [
            'foto_ktp',
            'foto_kk',
            'foto_skck',
            'pas_foto',
            'surat_sehat',
            'surat_anti_narkoba',
            'surat_lamaran',
            'cv'
        ];

        foreach ($files as $file) {
            if ($request->hasFile($file)) {
                $data[$file] = $request
                    ->file($file)
                    ->store('rekruitmen', 'public');
            }
        }

        // Generate token
        $data['token_pendaftaran'] = (string) Str::uuid();

        $rekruitmen = Rekruitmen::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran berhasil',
            'data' => new RekruitmenResource($rekruitmen)
        ], 201);

    } catch (\Exception $e) {

        // Hapus limiter jika gagal
        RateLimiter::clear($key);

        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan saat pendaftaran',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $rekruitmen = Rekruitmen::with('lowonganKerja')->find($id);

        if (!$rekruitmen) {
            return response()->json([
                'success' => false,
                'message' => 'Data rekruitmen tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new RekruitmenResource($rekruitmen)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $rekruitmen = Rekruitmen::find($id);

        if (!$rekruitmen) {
            return response()->json([
                'success' => false,
                'message' => 'Data rekruitmen tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'lowongan_kerja_id' => 'sometimes|required|exists:lowongan_kerja,id',
            'nik' => 'sometimes|required|max:16|unique:rekruitmen,nik,' . $id,
            'nama' => 'sometimes|required|string|max:255',
            'nama_lengkap' => 'sometimes|required|string|max:255',
            'posisi_dilamar' => 'sometimes|required|string|max:255',
            'no_wa' => 'sometimes|regex:/^[0-9]+$/|string|max:16',
            'alamat' => 'nullable|string',
            'status_terima' => 'sometimes|required|in:pending,diterima,ditolak',
            'catatan' => 'nullable|string',
            // File dokumen opsional saat update
            'foto_ktp' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'foto_kk' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'foto_skck' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'pas_foto' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'surat_sehat' => 'sometimes|file|mimes:pdf,jpeg,png,jpg|max:2048',
            'surat_anti_narkoba' => 'sometimes|file|mimes:pdf,jpeg,png,jpg|max:2048',
            'surat_lamaran' => 'sometimes|file|mimes:pdf|max:2048',
            'cv' => 'sometimes|file|mimes:pdf|max:2048',
        ],
        [
            'nik.regex' => 'NIK harus berupa angka saja.',
            'no_wa.regex' => 'Nomor WhatsApp harus berupa angka saja.',
            'nik.max' => 'NIK tidak boleh lebih dari 16 karakter.',
            'nik.unique' => 'NIK sudah digunakan oleh pelamar lain.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['foto_ktp', 'foto_kk', 'foto_skck', 'pas_foto', 'surat_sehat', 'surat_anti_narkoba', 'surat_lamaran', 'cv']);

        // Handle file upload — hapus file lama jika ada file baru
        $files = ['foto_ktp', 'foto_kk', 'foto_skck', 'pas_foto', 'surat_sehat', 'surat_anti_narkoba', 'surat_lamaran', 'cv'];
        foreach ($files as $file) {
            if ($request->hasFile($file)) {
                // Hapus file lama
                if ($rekruitmen->$file && Storage::disk('public')->exists($rekruitmen->$file)) {
                    Storage::disk('public')->delete($rekruitmen->$file);
                }
                $data[$file] = $request->file($file)->store('rekruitmen', 'public');
            }
        }

        $rekruitmen->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Data rekruitmen berhasil diupdate',
            'data' => new RekruitmenResource($rekruitmen->load('lowonganKerja'))
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $rekruitmen = Rekruitmen::find($id);

        if (!$rekruitmen) {
            return response()->json([
                'success' => false,
                'message' => 'Data rekruitmen tidak ditemukan'
            ], 404);
        }

        // Hapus semua file
        $files = ['foto_ktp', 'foto_kk', 'foto_skck', 'pas_foto', 'surat_sehat', 'surat_anti_narkoba', 'surat_lamaran', 'cv'];
        
        foreach ($files as $file) {
            if ($rekruitmen->$file && Storage::disk('public')->exists($rekruitmen->$file)) {
                Storage::disk('public')->delete($rekruitmen->$file);
            }
        }

        $rekruitmen->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data rekruitmen berhasil dihapus'
        ]);
    }

    /**
     * Update status terima
     */
    public function updateStatus(Request $request, string $id)
    {
        $rekruitmen = Rekruitmen::find($id);

        if (!$rekruitmen) {
            return response()->json([
                'success' => false,
                'message' => 'Data rekruitmen tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status_terima' => 'required|in:pending,diterima,ditolak',
            'catatan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $rekruitmen->update($request->only(['status_terima', 'catatan']));

        return response()->json([
            'success' => true,
            'message' => 'Status berhasil diupdate',
            'data' => new RekruitmenResource($rekruitmen->load('lowonganKerja'))
        ]);
    }

    /**
     * Cari berdasarkan token
     */
    
    public function checkStatusByToken(Request $request)
        {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $rekruitmen = Rekruitmen::where('token_pendaftaran', $request->token)->first();
            
            if (!$rekruitmen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak valid atau tidak ditemukan'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Status rekruitmen berhasil ditemukan',
                'data' => new StatusTerimaResource($rekruitmen)
            ]);
        }
    }