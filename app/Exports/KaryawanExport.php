<?php

namespace App\Exports;

use App\Models\Karyawan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class KaryawanExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, ShouldAutoSize
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = Karyawan::query();

        // Apply filters
        if (isset($this->filters['status_aktif'])) {
            $query->where('status_aktif', $this->filters['status_aktif']);
        }

        if (isset($this->filters['posisi'])) {
            $query->where('posisi', $this->filters['posisi']);
        }

        if (isset($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('nomor_induk', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('nama_lengkap', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('nama_lengkap')->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'No',
            'Nomor Induk',
            'NIK',
            'No. Rekening BRI',
            'Nama Lengkap',
            'Posisi',
            'Email',
            'No. WhatsApp',
            'Alamat',
            'Tanggal Masuk',
            'Tanggal Keluar',
            'Status',
        ];
    }

    /**
     * @var Karyawan $karyawan
     */
    public function map($karyawan): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $karyawan->nomor_induk,
            $karyawan->nik,
            $karyawan->no_rek_bri,
            $karyawan->nama_lengkap,
            ucfirst(str_replace('_', ' ', $karyawan->posisi)),
            $karyawan->email ?? '-',
            $karyawan->no_wa ?? '-',
            $karyawan->alamat,
            $karyawan->tanggal_masuk ? $karyawan->tanggal_masuk->format('d/m/Y') : '-',
            $karyawan->tanggal_keluar ? $karyawan->tanggal_keluar->format('d/m/Y') : '-',
            $karyawan->status_aktif ? 'Aktif' : 'Tidak Aktif',
        ];
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 15,
            'C' => 18,
            'D' => 20,
            'E' => 25,
            'F' => 20,
            'G' => 25,
            'H' => 18,
            'I' => 35,
            'J' => 15,
            'K' => 15,
            'L' => 12,
        ];
    }

    /**
     * @param Worksheet $sheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style header
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ],
        ];
    }
}