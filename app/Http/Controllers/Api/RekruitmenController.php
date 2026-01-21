<?php

namespace App\Http\Controllers\Api;

use App\Models\Rekruitmen;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\LowonganKerja;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\RekruitmenResource;
use App\Http\Resources\StatusTerimaResource;

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
                'success' => false,
                'message' => 'Sejauh ini belum ada yang mengajukan lamaran'
            ], 200);
        }

        return RekruitmenResource::collection($rekruitmen);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lowongan_kerja_id' => 'required|exists:lowongan_kerja,id',
            'nik' => 'required|string|unique:rekruitmen,nik|max:16',
            'nama' => 'required|string|max:255',
            'nama_lengkap' => 'required|string|max:255',
            'posisi_dilamar' => 'required|string|max:255',
            'no_wa' => 'required|string|max:20',
            'alamat' => 'nullable|string',
            'foto_ktp' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'foto_kk' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'foto_skck' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'pas_foto' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'surat_sehat' => 'required|file|mimes:pdf,jpeg,png,jpg|max:2048',
            'surat_anti_narkoba' => 'required|file|mimes:pdf,jpeg,png,jpg|max:2048',
            'surat_lamaran' => 'required|file|mimes:pdf|max:2048',
            'cv' => 'required|file|mimes:pdf|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek apakah lowongan masih aktif
        $lowongan = LowonganKerja::find($request->lowongan_kerja_id);
        if ($lowongan->status_lowongan !== 'aktif' || $lowongan->deadline_lowongan < now()) {
            return response()->json([
                'success' => false,
                'message' => 'Lowongan kerja sudah tidak aktif atau melewati deadline'
            ], 400);
        }

        // Upload files
        $data = $request->except(['foto_ktp', 'foto_kk', 'foto_skck', 'pas_foto', 'surat_sehat', 'surat_anti_narkoba', 'surat_lamaran', 'cv']);
        
        $files = ['foto_ktp', 'foto_kk', 'foto_skck', 'pas_foto', 'surat_sehat', 'surat_anti_narkoba', 'surat_lamaran', 'cv'];
        
        foreach ($files as $file) {
            if ($request->hasFile($file)) {
                $data[$file] = $request->file($file)->store('rekruitmen', 'public');
            }
        }

        // Generate token pendaftaran
        $data['token_pendaftaran'] = Str::random(32);

        $rekruitmen = Rekruitmen::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran berhasil',
            'data' => new RekruitmenResource($rekruitmen->load('lowonganKerja'))
        ], 201);
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
            'nik' => 'sometimes|required|string|max:16|unique:rekruitmen,nik,' . $id,
            'nama' => 'sometimes|required|string|max:255',
            'nama_lengkap' => 'sometimes|required|string|max:255',
            'posisi_dilamar' => 'sometimes|required|string|max:255',
            'no_wa' => 'sometimes|required|string|max:20',
            'alamat' => 'nullable|string',
            'status_terima' => 'sometimes|required|in:pending,diterima,ditolak',
            'catatan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $rekruitmen->update($request->all());

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