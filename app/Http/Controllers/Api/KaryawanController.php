<?php

namespace App\Http\Controllers\Api;

use App\Models\Karyawan;
use App\Http\Controllers\Controller;
use App\Http\Resources\KaryawanResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class KaryawanController extends Controller
{
    /**
     * Menampilkan semua karyawan
     */
    public function index()
    {
        $karyawan = Karyawan::latest()->paginate(10);
        return new KaryawanResource(true, 'List Data Karyawan', $karyawan);
    }

    /**
     * Menyimpan data karyawan baru
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nomor_induk'   => 'required|unique:karyawans,nomor_induk',
            'nik'           => 'required|unique:karyawans,nik',
            'no_rek_bri'    => 'required|unique:karyawans,no_rek_bri',
            'nama_lengkap'  => 'required|string|max:100', // Diubah dari 'nama' ke 'nama_lengkap'
            'posisi'        => 'required|string|in:jasa,supir,keamanan,cleaning_service,operator',
            'email'         => 'nullable|email|unique:karyawans,email', // Diubah jadi nullable
            'alamat'        => 'required|string',
            'no_wa'         => 'nullable|string', // Diubah dari 'telepon' ke 'no_wa'
            'image'         => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Diubah jadi nullable
            'tanggal_masuk' => 'required|date',
            'tanggal_keluar'=> 'nullable|date|after:tanggal_masuk',
            'status_aktif'  => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = [
            'nomor_induk'   => $request->nomor_induk,
            'no_rek_bri'    => $request->no_rek_bri,
            'nik'           => $request->nik,
            'nama_lengkap'  => $request->nama_lengkap, // Sesuai dengan field di database
            'posisi'        => $request->posisi,
            'email'         => $request->email,
            'alamat'        => $request->alamat,
            'no_wa'         => $request->no_wa, // Sesuai dengan field di database
            'tanggal_masuk' => $request->tanggal_masuk,
            'tanggal_keluar'=> $request->tanggal_keluar,
            'status_aktif'  => $request->status_aktif,
        ];

        // Upload foto jika ada
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $image->storeAs('karyawans', $image->hashName());
            $data['image'] = $image->hashName();
        }

        // Simpan data
        $karyawan = Karyawan::create($data);

        return new KaryawanResource(true, 'Data Karyawan Berhasil Ditambahkan!', $karyawan);
    }

    /**
     * Menampilkan detail 1 karyawan
     */
    public function show($id)
    {
        $karyawan = Karyawan::find($id);

        if (!$karyawan) {
            return new KaryawanResource(false, 'Data Karyawan Tidak Ditemukan', null);
        }

        return new KaryawanResource(true, 'Detail Data Karyawan', $karyawan);
    }

    /**
     * Update data karyawan
     */
    public function update(Request $request, $id)
    {
        $karyawan = Karyawan::find($id);

        if (!$karyawan) {
            return new KaryawanResource(false, 'Data Karyawan Tidak Ditemukan', null);
        }

        $validator = Validator::make($request->all(), [
            'nomor_induk'   => 'required|unique:karyawans,nomor_induk,' . $karyawan->id,
            'nik'           => 'required|unique:karyawans,nik,' . $karyawan->id,
            'no_rek_bri'    => 'required|unique:karyawans,no_rek_bri,' . $karyawan->id,
            'nama_lengkap'  => 'required|string|max:100', // Diubah dari 'nama' ke 'nama_lengkap'
            'posisi'        => 'required|string|in:jasa,supir,keamanan,cleaning_service,operator',
            'email'         => 'nullable|email|unique:karyawans,email,' . $karyawan->id,
            'alamat'        => 'required|string',
            'no_wa'         => 'nullable|string', // Diubah dari 'telepon' ke 'no_wa'
            'image'         => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'tanggal_masuk' => 'required|date',
            'tanggal_keluar'=> 'nullable|date|after:tanggal_masuk',
            'status_aktif'  => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = [
            'nomor_induk'   => $request->nomor_induk,
            'nik'           => $request->nik,
            'no_rek_bri'    => $request->no_rek_bri,
            'nama_lengkap'  => $request->nama_lengkap,
            'posisi'        => $request->posisi,
            'email'         => $request->email,
            'alamat'        => $request->alamat,
            'no_wa'         => $request->no_wa,
            'tanggal_masuk' => $request->tanggal_masuk,
            'tanggal_keluar'=> $request->tanggal_keluar,
            'status_aktif'  => $request->status_aktif,
        ];

        // Jika ada foto baru
        if ($request->hasFile('image')) {
            // Hapus foto lama jika ada
            if ($karyawan->image) {
                Storage::delete('karyawans/' . basename($karyawan->image));
            }

            $image = $request->file('image');
            $image->storeAs('karyawans', $image->hashName());
            $data['image'] = $image->hashName();
        }

        // Update data
        $karyawan->update($data);

        return new KaryawanResource(true, 'Data Karyawan Berhasil Diupdate!', $karyawan);
    }

    /**
     * Menghapus karyawan
     */
    public function destroy($id)
    {
        $karyawan = Karyawan::find($id);

        if (!$karyawan) {
            return new KaryawanResource(false, 'Data Karyawan Tidak Ditemukan', null);
        }

        // Hapus foto jika ada
        if ($karyawan->image) {
            Storage::delete('karyawans/' . basename($karyawan->image));
        }
        
        $karyawan->delete();

        return new KaryawanResource(true, 'Data Karyawan Berhasil Dihapus!', null);
    }
}