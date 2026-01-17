<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LowonganKerjaResource;
use App\Models\LowonganKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LowonganKerjaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = LowonganKerja::query();

        // Filter berdasarkan status
        if ($request->has('status')) {
            $query->where('status_lowongan', $request->status);
        }

        // Filter berdasarkan posisi
        if ($request->has('posisi')) {
            $query->where('posisi', $request->posisi);
        }

        // Filter berdasarkan jenis kerja
        if ($request->has('jenis_kerja')) {
            $query->where('jenis_kerja', $request->jenis_kerja);
        }

        // Hanya lowongan aktif
        if ($request->has('aktif') && $request->aktif == true) {
            $query->aktif();
        }

        $lowongan = $query->latest()->paginate($request->per_page ?? 10);

        return LowonganKerjaResource::collection($lowongan);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'posisi' => 'required|in:Cleaning Service,Supir,Keamanan,Operator,Jasa',
            'lokasi_kerja' => 'required|string|max:255',
            'jenis_kerja' => 'required|in:Full Time,Part Time',
            'catatan' => 'nullable|string',
            'range_gaji' => 'required|string|max:255',
            'deadline_lowongan' => 'required|date|after:today',
            'status_lowongan' => 'nullable|in:aktif,tidak_aktif',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $lowongan = LowonganKerja::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Lowongan kerja berhasil dibuat',
            'data' => new LowonganKerjaResource($lowongan)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $lowongan = LowonganKerja::find($id);

        if (!$lowongan) {
            return response()->json([
                'success' => false,
                'message' => 'Lowongan kerja tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new LowonganKerjaResource($lowongan)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $lowongan = LowonganKerja::find($id);

        if (!$lowongan) {
            return response()->json([
                'success' => false,
                'message' => 'Lowongan kerja tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'posisi' => 'sometimes|required|in:Cleaning Service,Supir,Keamanan,Operator,Jasa',
            'lokasi_kerja' => 'sometimes|required|string|max:255',
            'jenis_kerja' => 'sometimes|required|in:Full Time,Part Time',
            'catatan' => 'nullable|string',
            'range_gaji' => 'sometimes|required|string|max:255',
            'deadline_lowongan' => 'sometimes|required|date',
            'status_lowongan' => 'sometimes|required|in:aktif,tidak_aktif',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $lowongan->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Lowongan kerja berhasil diupdate',
            'data' => new LowonganKerjaResource($lowongan)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $lowongan = LowonganKerja::find($id);

        if (!$lowongan) {
            return response()->json([
                'success' => false,
                'message' => 'Lowongan kerja tidak ditemukan'
            ], 404);
        }

        $lowongan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lowongan kerja berhasil dihapus'
        ]);
    }

    /**
     * Get pelamar untuk lowongan tertentu
     */
    public function pelamar(string $id)
    {
        $lowongan = LowonganKerja::with('rekruitmen')->find($id);

        if (!$lowongan) {
            return response()->json([
                'success' => false,
                'message' => 'Lowongan kerja tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'lowongan' => new LowonganKerjaResource($lowongan),
                'pelamar' => \App\Http\Resources\RekruitmenResource::collection($lowongan->rekruitmen)
            ]
        ]);
    }
    /**
 * Get lowongan aktif untuk pelamar (public access)
 */
public function getLowonganAktif(Request $request)
{
    $query = LowonganKerja::query()->aktif();

    // Filter berdasarkan posisi
    if ($request->has('posisi')) {
        $query->where('posisi', $request->posisi);
    }

    // Filter berdasarkan jenis kerja
    if ($request->has('jenis_kerja')) {
        $query->where('jenis_kerja', $request->jenis_kerja);
    }

    // Filter berdasarkan lokasi
    if ($request->has('lokasi')) {
        $query->where('lokasi_kerja', 'like', '%' . $request->lokasi . '%');
    }

    // Search
    if ($request->has('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('posisi', 'like', '%' . $search . '%')
              ->orWhere('lokasi_kerja', 'like', '%' . $search . '%')
              ->orWhere('range_gaji', 'like', '%' . $search . '%');
        });
    }

    $lowongan = $query->latest()->paginate($request->per_page ?? 10);

    return response()->json([
        'success' => true,
        'message' => 'Lowongan kerja aktif berhasil diambil',
        'data' => LowonganKerjaResource::collection($lowongan),
        'meta' => [
            'current_page' => $lowongan->currentPage(),
            'last_page' => $lowongan->lastPage(),
            'per_page' => $lowongan->perPage(),
            'total' => $lowongan->total(),
        ]
    ]);
}

/**
 * Get detail lowongan untuk pelamar
 */
public function detailLowongan(string $id)
{
    $lowongan = LowonganKerja::where('id', $id)
                            ->where('status_lowongan', 'aktif')
                            ->where('deadline_lowongan', '>=', now())
                            ->first();

    if (!$lowongan) {
        return response()->json([
            'success' => false,
            'message' => 'Lowongan kerja tidak ditemukan atau sudah tidak aktif'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'message' => 'Detail lowongan berhasil diambil',
        'data' => new LowonganKerjaResource($lowongan)
    ]);
}

/**
 * Get statistik lowongan (untuk dashboard pelamar)
 */
public function statistik()
{
    $data = [
        'total_lowongan_aktif' => LowonganKerja::aktif()->count(),
        'lowongan_per_posisi' => LowonganKerja::aktif()
            ->selectRaw('posisi, COUNT(*) as total')
            ->groupBy('posisi')
            ->get(),
        'lowongan_per_jenis' => LowonganKerja::aktif()
            ->selectRaw('jenis_kerja, COUNT(*) as total')
            ->groupBy('jenis_kerja')
            ->get(),
    ];

    return response()->json([
        'success' => true,
        'message' => 'Statistik lowongan berhasil diambil',
        'data' => $data
    ]);
}
}