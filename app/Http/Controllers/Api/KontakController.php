<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KontakResource;
use App\Models\Kontak;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;

class KontakController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse|AnonymousResourceCollection
    {
        try {
            $kontak = Kontak::latest()->paginate(10);
            
            return KontakResource::collection($kontak)->additional([
                'message' => 'Data kontak berhasil diambil'
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
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'perusahaan' => 'nullable|string|max:255',
            'subjek' => 'required|string|max:255',
            'isi' => 'required|string',
        ]);

        if ($validator->fails()) {
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
                'perusahaan' => $request->perusahaan,
                'subjek' => $request->subjek,
                'isi' => $request->isi,
                'status_dibaca' => 'pending'
            ]);

            return (new KontakResource($kontak))
                ->additional(['message' => 'Pesan berhasil dikirim'])
                ->response()
                ->setStatusCode(201);
                
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data ke database',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses pesan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse|KontakResource
    {
        try {
            $kontak = Kontak::findOrFail($id);

            return (new KontakResource($kontak))
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
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse|KontakResource
    {
        try {
            $kontak = Kontak::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'perusahaan' => 'nullable|string|max:255',
                'subjek' => 'required|string|max:255',
                'isi' => 'required|string',
                'status_dibaca' => 'nullable|in:pending,dibaca',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $kontak->update($request->only([
                'nama',
                'email',
                'perusahaan',
                'subjek',
                'isi',
                'status_dibaca'
            ]));

            return (new KontakResource($kontak))
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
     * Remove the specified resource from storage.
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
     * Update status dibaca
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

            $kontak->update([
                'status_dibaca' => $request->status_dibaca,
            ]);

            return (new KontakResource($kontak))
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
