<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rekruitmen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class RekruitmenController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $rekruitmen = Rekruitmen::paginate(10);
        
        return response()->json([
            'success' => true,
            'message' => 'Data rekruitmen berhasil diambil',
            'data' => $rekruitmen
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nik' => 'required|unique:rekruitmen|max:16',
            'nama' => 'required|string|max:100',
            'nama_lengkap' => 'required|string|max:255',
            'posisi_dilamar' => 'required|string|max:50',
            'foto_ktp' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'foto_kk' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'foto_skck' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'pas_foto' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'surat_sehat' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'surat_anti_narkoba' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'surat_lamaran' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'cv' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'no_wa' => 'required|string|max:15',
            'alamat' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tokenPendaftaran = Str::random(32);

            $fotoKtpPath = $request->file('foto_ktp')->store('rekruitmen/ktp', 'public');
            $fotoKkPath = $request->file('foto_kk')->store('rekruitmen/kk', 'public');
            $fotoSkckPath = $request->file('foto_skck')->store('rekruitmen/skck', 'public');
            $pasFotoPath = $request->file('pas_foto')->store('rekruitmen/pas_foto', 'public');
            $suratSehat = $request->file('surat_sehat')->store('rekruitmen/surat_sehat', 'public');
            $suratNarkoba = $request->file('surat_anti_narkoba')->store('rekruitmen/surat_anti_narkoba', 'public');
            $suratLamaran = $request->file('surat_lamaran')->store('rekruitmen/surat_lamaran', 'public');
            $cv = $request->file('cv')->store('rekruitmen/cv', 'public');

            $rekruitmen = Rekruitmen::create([
                'nik' => $request->nik,
                'nama' => $request->nama,
                'nama_lengkap' => $request->nama_lengkap,
                'posisi_dilamar' => $request->posisi_dilamar,
                'foto_ktp' => $fotoKtpPath,
                'foto_kk' => $fotoKkPath,
                'foto_skck' => $fotoSkckPath,
                'pas_foto' => $pasFotoPath,
                'surat_sehat' => $suratSehat,
                'surat_anti_narkoba' => $suratNarkoba,
                'surat_lamaran' => $suratLamaran,
                'cv' => $cv,
                'token_pendaftaran' => $tokenPendaftaran,
                'no_wa' => $request->no_wa,
                'alamat' => $request->alamat,
                'status_terima' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lamaran berhasil dikirim',
                'data' => [
                    'rekruitmen' => $rekruitmen,
                    'token_pendaftaran' => $tokenPendaftaran
                ]
            ], 201);
                
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $rekruitmen = Rekruitmen::find($id);
        
        if (!$rekruitmen) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data rekruitmen berhasil diambil',
            'data' => $rekruitmen
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $rekruitmen = Rekruitmen::find($id);
        
        if (!$rekruitmen) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nik' => 'required|max:16|unique:rekruitmen,nik,' . $id,
            'nama' => 'required|string|max:100',
            'nama_lengkap' => 'required|string|max:255',
            'posisi_dilamar' => 'required|string|max:50',
            'no_wa' => 'required|string|max:15',
            'alamat' => 'required|string|max:500',
            'foto_ktp' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'foto_kk' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'foto_skck' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'pas_foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'surat_sehat' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'surat_anti_narkoba' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'surat_lamaran' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'cv' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'status_terima' => 'nullable|in:pending,diterima,ditolak',
            'catatan' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = [
                'nik' => $request->nik,
                'nama' => $request->nama,
                'nama_lengkap' => $request->nama_lengkap,
                'posisi_dilamar' => $request->posisi_dilamar,
                'no_wa' => $request->no_wa,
                'alamat' => $request->alamat,
            ];

            if ($request->has('status_terima')) {
                $data['status_terima'] = $request->status_terima;
            }

            if ($request->has('catatan')) {
                $data['catatan'] = $request->catatan;
            }

            // Handle file uploads
            $fileFields = [
                'foto_ktp' => 'rekruitmen/ktp',
                'foto_kk' => 'rekruitmen/kk',
                'foto_skck' => 'rekruitmen/skck',
                'pas_foto' => 'rekruitmen/pas_foto',
                'surat_sehat' => 'rekruitmen/surat_sehat',
                'surat_anti_narkoba' => 'rekruitmen/surat_anti_narkoba',
                'surat_lamaran' => 'rekruitmen/surat_lamaran',
                'cv' => 'rekruitmen/cv',
            ];

            foreach ($fileFields as $field => $path) {
                if ($request->hasFile($field)) {
                    if ($rekruitmen->$field) {
                        Storage::disk('public')->delete($rekruitmen->$field);
                    }
                    $data[$field] = $request->file($field)->store($path, 'public');
                }
            }

            $rekruitmen->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Data rekruitmen berhasil diperbarui',
                'data' => $rekruitmen->fresh()
            ]);
                
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $rekruitmen = Rekruitmen::find($id);
        
        if (!$rekruitmen) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        try {
            Storage::disk('public')->delete([
                $rekruitmen->foto_ktp,
                $rekruitmen->foto_kk,
                $rekruitmen->foto_skck,
                $rekruitmen->pas_foto,
                $rekruitmen->surat_sehat,
                $rekruitmen->surat_anti_narkoba,
                $rekruitmen->surat_lamaran,
                $rekruitmen->cv,
            ]);

            $rekruitmen->delete();

            return response()->json([
                'success' => true,
                'message' => 'Data rekruitmen berhasil dihapus'
            ]);
                
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update status penerimaan
     */
    public function updateStatus(Request $request, $id)
    {
        $rekruitmen = Rekruitmen::find($id);
        
        if (!$rekruitmen) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status_terima' => 'required|in:pending,diterima,ditolak',
            'catatan' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $rekruitmen->update([
            'status_terima' => $request->status_terima,
            'catatan' => $request->catatan,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status rekruitmen berhasil diperbarui',
            'data' => $rekruitmen->fresh()
        ]);
    }

    /**
     * Cek status by token pendaftaran
     */
    public function checkByToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token_pendaftaran' => 'required|string',
            'nama_lengkap' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $rekruitmen = Rekruitmen::where('token_pendaftaran', $request->token_pendaftaran)
            ->where('nama_lengkap', $request->nama_lengkap)
            ->first();

        if (!$rekruitmen) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan. Pastikan token dan nama lengkap sesuai.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data rekruitmen berhasil ditemukan',
            'data' => $rekruitmen
        ]);
    }
}
