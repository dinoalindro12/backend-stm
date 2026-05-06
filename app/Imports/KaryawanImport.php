<?php

namespace App\Imports;

use App\Models\Karyawan;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\Importable;

use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;
use Illuminate\Validation\Rule;

class KaryawanImport implements 
    ToModel, 
    WithHeadingRow, 
    WithValidation, 
    WithChunkReading, 
    WithBatchInserts,
    WithCalculatedFormulas
{
    use Importable, SkipsFailures, RemembersRowNumber;

    protected $successCount = 0;
    protected $totalRows = 0;
    protected $importedIds = [];
    protected $processedNomorInduk = [];

    public function model(array $row)
    {
        $this->totalRows++;
        
        // Konversi nilai numeric ke string untuk field tertentu
        $row['nik'] = $this->convertToString($row['nik'] ?? '');
        $row['nomor_induk'] = $this->convertToString($row['nomor_induk'] ?? '');
        $row['no_rek_bri'] = $this->convertToString($row['no_rek_bri'] ?? '');
        $row['no_wa'] = $this->convertToStringOrNull($row['no_wa'] ?? null);
        
        // Skip jika nomor induk sudah diproses dalam batch ini
        if (in_array($row['nomor_induk'], $this->processedNomorInduk)) {
            Log::warning("Duplicate nomor_induk in import file", [
                'nomor_induk' => $row['nomor_induk'],
                'row' => $this->getRowNumber()
            ]);
            return null;
        }
        
        $this->processedNomorInduk[] = $row['nomor_induk'];
        
        // Cek apakah data sudah ada - FIXED: gunakan primary key saja
        $existingKaryawan = Karyawan::where('nomor_induk', $row['nomor_induk'])->first();

        if ($existingKaryawan) {
            // Update data yang sudah ada
            $existingKaryawan->update([
                'nik' => $row['nik'],
                'no_rek_bri' => $row['no_rek_bri'],
                'nama_lengkap' => $row['nama_lengkap'],
                'posisi' => $row['posisi'],
                'email' => $row['email'] ?? null,
                'alamat' => $row['alamat'],
                'no_wa' => $row['no_wa'],
                'tanggal_masuk' => $this->transformDate($row['tanggal_masuk']),
                'tanggal_keluar' => isset($row['tanggal_keluar']) && !empty($row['tanggal_keluar']) 
                    ? $this->transformDate($row['tanggal_keluar']) 
                    : null,
                'status_aktif' => $this->transformStatusAktif($row['status_aktif'] ?? 'aktif'),
            ]);
            
            $this->importedIds[] = $existingKaryawan->id;
            $this->successCount++;
            
            return null;
        }

        // Buat data baru
        $karyawan = Karyawan::create([
            'nomor_induk'   => $row['nomor_induk'],
            'nik'           => $row['nik'],
            'no_rek_bri'    => $row['no_rek_bri'],
            'nama_lengkap'  => $row['nama_lengkap'],
            'posisi'        => $row['posisi'],
            'email'         => $row['email'] ?? null,
            'alamat'        => $row['alamat'],
            'no_wa'         => $row['no_wa'],
            'tanggal_masuk' => $this->transformDate($row['tanggal_masuk']),
            'tanggal_keluar'=> isset($row['tanggal_keluar']) && !empty($row['tanggal_keluar']) 
                ? $this->transformDate($row['tanggal_keluar']) 
                : null,
            'status_aktif'  => $this->transformStatusAktif($row['status_aktif'] ?? 'aktif'),
        ]);
        
        $this->importedIds[] = $karyawan->id;
        $this->successCount++;
        
        return $karyawan;
    }

    private function convertToString($value)
    {
        if (is_null($value) || $value === '') {
            return '';
        }
        
        if (is_numeric($value)) {
            return number_format($value, 0, '', '');
        }
        
        return (string) trim($value);
    }
    
    private function convertToStringOrNull($value)
    {
        if (is_null($value) || $value === '') {
            return null;
        }
        
        if (is_numeric($value)) {
            return number_format($value, 0, '', '');
        }
        
        return (string) trim($value);
    }

    private function transformDate($value)
    {
        if (empty($value)) {
            return null;
        }
        
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Exception $e) {
                Log::error('Excel date conversion failed', ['value' => $value, 'error' => $e->getMessage()]);
                return null;
            }
        }
        
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::error('Parsing tanggal gagal', ['value' => $value, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function transformStatusAktif($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_numeric($value)) {
            return (bool) $value;
        }
        
        $value = strtolower(trim($value));
        return in_array($value, ['aktif', 'active', 'yes', 'y', '1', 'true']);
    }

    public function rules(): array
    {
        return [
            'nomor_induk' => [
                'required',
                'max:12',
                // Unique dalam database, kecuali untuk update
                Rule::unique('karyawans', 'nomor_induk')->ignore($this->getNomorIndukIfExists())
            ],
            'nik' => 'required|max:20',
            'no_rek_bri' => 'required|max:30',
            'nama_lengkap' => 'required|string|max:100',
            'posisi' => 'required|string|in:jasa,supir,keamanan,cleaning_service,operator',
            'email' => 'nullable|email|max:100',
            'alamat' => 'required|string',
            'no_wa' => 'nullable|max:15',
            'tanggal_masuk' => 'required',
            'tanggal_keluar' => 'nullable',
            'status_aktif' => 'nullable',
        ];
    }
    
    private function getNomorIndukIfExists()
    {
        // Helper untuk unique validation - return ID jika record exist
        return null; // Simplified - bisa dikembangkan lebih lanjut
    }

    public function customValidationMessages()
    {
        return [
            'nomor_induk.required' => 'Nomor induk wajib diisi',
            'nomor_induk.unique' => 'Nomor induk sudah terdaftar',
            'nomor_induk.max' => 'Nomor induk maksimal 12 karakter',
            'nik.required' => 'NIK wajib diisi',
            'nik.max' => 'NIK maksimal 20 karakter',
            'no_rek_bri.required' => 'No Rekening BRI wajib diisi',
            'no_rek_bri.max' => 'No Rekening BRI maksimal 30 karakter',
            'nama_lengkap.required' => 'Nama lengkap wajib diisi',
            'posisi.required' => 'Posisi wajib diisi',
            'posisi.in' => 'Posisi harus salah satu: jasa, supir, keamanan, cleaning_service, operator',
            'email.email' => 'Format email tidak valid',
            'alamat.required' => 'Alamat wajib diisi',
            'tanggal_masuk.required' => 'Tanggal masuk wajib diisi',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 250;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getImportedIds(): array
    {
        return $this->importedIds;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }
}