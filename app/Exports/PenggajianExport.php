<?php

namespace App\Exports;

use App\Models\Penggajian;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Font;
use Carbon\Carbon;

class PenggajianExport implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithStyles,
    WithColumnWidths,
    WithTitle,
    ShouldAutoSize,
    WithEvents
{
    protected $bulan;
    protected $tahun;
    protected $posisi;
    protected $data;
    protected $totalData = 0;

    public function __construct($bulan, $tahun, $posisi = null)
    {
        $this->bulan = $bulan;
        $this->tahun = $tahun;
        $this->posisi = $posisi;
        $this->data = $this->getData();
        $this->totalData = $this->data->count();
    }

    /**
     * Get data
     */
    private function getData()
    {
        $query = Penggajian::with('karyawan')
            ->whereMonth('gajian_bulan', $this->bulan)
            ->whereYear('gajian_bulan', $this->tahun);

        if ($this->posisi) {
            $query->where('posisi', $this->posisi);
        }

        return $query->orderBy('nama')->get();
    }

    /**
     * Return collection of data
     */
    public function collection()
    {
        return $this->data;
    }

    /**
     * Define headings - 16 kolom (A-P)
     */
    public function headings(): array
    {
        return [
            'No',                       // A
            'No Rekening BRI',          // B
            'NIK',                      // C
            'Nama',                     // D
            'Bagian',                   // E
            'Jumlah Penghasilan Kotor', // F
            'BPJS Kesehatan',           // G
            'BPJS JHT',                 // H
            'BPJS JP',                  // I
            'Jumlah Iuran BPJS',        // J
            'THR',                      // K
            'Jumlah Hari Kerja',        // L
            'Satuan',                   // M (Gaji Harian)
            'Lembur Hari Besar',        // N
            'Upah Kotor Karyawan',      // O
            'Upah yang diterima'        // P
        ];
    }

    /**
     * Map each row - 16 kolom (A-P)
     */
    public function map($penggajian): array
    {
        return [
            '', // No akan diisi otomatis nanti
            $penggajian->no_rek_bri ?? '-',
            $penggajian->nik,
            $penggajian->nama,
            $this->getPosisiLabel($penggajian->posisi),
            $penggajian->jumlah_penghasilan_kotor,
            $penggajian->bpjs_kesehatan,
            $penggajian->bpjs_jht,
            $penggajian->bpjs_jp,
            $penggajian->total_bpjs,
            $penggajian->uang_thr ?? 0,
            $penggajian->jumlah_hari_kerja,
            $penggajian->gaji_harian,
            $penggajian->jumlah_lembur,
            $penggajian->upah_kotor_karyawan,
            $penggajian->upah_diterima
        ];
    }

    /**
     * Get posisi label
     */
    private function getPosisiLabel($posisi)
    {
        $labels = [
            'jasa' => 'Jasa',
            'supur' => 'Supir', // Perbaiki typo jika ada
            'supir' => 'Supir',
            'keamanan' => 'Keamanan',
            'cleaning_service' => 'Cleaning Service',
            'operator' => 'Operator'
        ];
        
        return $labels[$posisi] ?? $posisi;
    }

    /**
     * Apply styles to worksheet
     */
    public function styles(Worksheet $sheet)
    {
        $bulanNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        $namaBulan = $bulanNames[$this->bulan] ?? 'Bulan';
        $startDataRow = 6; // Data dimulai dari row 6
        $headerRow = 5; // Header tabel di row 5

        // ========== HEADER INFORMASI PERUSAHAAN ==========
        
        // Nama Perusahaan
        $sheet->setCellValue('A1', 'PT SURYA TAMADO MANDIRI');
        $sheet->mergeCells('A1:P1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'name' => 'Arial'
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Judul Laporan
        $sheet->setCellValue('A2', 'LAPORAN PENGGAJIAN KARYAWAN');
        $sheet->mergeCells('A2:P2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'name' => 'Arial'
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Periode
        $periodeText = "Periode: {$namaBulan} {$this->tahun}";
        if ($this->posisi) {
            $periodeText .= " | Posisi: " . strtoupper($this->posisi);
        }
        
        $sheet->setCellValue('A3', $periodeText);
        $sheet->mergeCells('A3:P3');
        $sheet->getStyle('A3')->applyFromArray([
            'font' => [
                'size' => 12,
                'name' => 'Arial'
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Tanggal Cetak
        $sheet->setCellValue('A4', 'Tanggal Cetak: ' . now()->format('d/m/Y H:i:s'));
        $sheet->mergeCells('A4:P4');
        $sheet->getStyle('A4')->applyFromArray([
            'font' => [
                'size' => 10,
                'italic' => true,
                'name' => 'Arial'
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Set height untuk header informasi
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(25);
        $sheet->getRowDimension(3)->setRowHeight(20);
        $sheet->getRowDimension(4)->setRowHeight(18);

        // ========== HEADER TABEL ==========
        $sheet->getStyle("A{$headerRow}:P{$headerRow}")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
                'name' => 'Arial'
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'] // Biru Microsoft
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        $sheet->getRowDimension($headerRow)->setRowHeight(30);

        // Auto size semua kolom
        foreach (range('A', 'P') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return [];
    }

    /**
     * Set column widths (fallback jika auto size kurang)
     */
    public function columnWidths(): array
    {
        return [
            'A' => 6,   // No
            'B' => 18,  // No Rek BRI
            'C' => 18,  // NIK
            'D' => 25,  // Nama
            'E' => 15,  // Bagian
            'F' => 22,  // Jml Penghasilan Kotor
            'G' => 18,  // BPJS Kesehatan
            'H' => 15,  // BPJS JHT
            'I' => 15,  // BPJS JP
            'J' => 20,  // Jml Iuran BPJS
            'K' => 15,  // THR
            'L' => 18,  // Jml Hari Kerja
            'M' => 15,  // Satuan
            'N' => 20,  // Lembur Hari Besar
            'O' => 22,  // Upah Kotor Karyawan
            'P' => 22   // Upah yang diterima
        ];
    }

    /**
     * Set sheet title
     */
    public function title(): string
    {
        $bulanNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $namaBulan = $bulanNames[$this->bulan] ?? 'Bulan';
        
        if ($this->posisi) {
            return "Penggajian {$namaBulan} {$this->tahun} - " . ucfirst($this->posisi);
        }
        
        return "Penggajian {$namaBulan} {$this->tahun}";
    }

    /**
     * Register events - INI YANG MENULIS DATA
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $startDataRow = 6;
                $headerRow = 5;
                
                // Tulis header
                $headers = $this->headings();
                $sheet->fromArray($headers, null, "A{$headerRow}");
                
                // Tulis data jika ada
                if ($this->totalData > 0) {
                    $row = $startDataRow;
                    
                    foreach ($this->data as $index => $penggajian) {
                        $no = $index + 1;
                        
                        // Tulis data per kolom
                        $sheet->setCellValue("A{$row}", $no);
                        $sheet->setCellValue("B{$row}", $penggajian->no_rek_bri ?? '-');
                        $sheet->setCellValue("C{$row}", $penggajian->nik);
                        $sheet->setCellValue("D{$row}", $penggajian->nama);
                        $sheet->setCellValue("E{$row}", $this->getPosisiLabel($penggajian->posisi));
                        $sheet->setCellValue("F{$row}", $penggajian->jumlah_penghasilan_kotor);
                        $sheet->setCellValue("G{$row}", $penggajian->bpjs_kesehatan);
                        $sheet->setCellValue("H{$row}", $penggajian->bpjs_jht);
                        $sheet->setCellValue("I{$row}", $penggajian->bpjs_jp);
                        $sheet->setCellValue("J{$row}", $penggajian->total_bpjs);
                        $sheet->setCellValue("K{$row}", $penggajian->uang_thr ?? 0);
                        $sheet->setCellValue("L{$row}", $penggajian->jumlah_hari_kerja);
                        $sheet->setCellValue("M{$row}", $penggajian->gaji_harian);
                        $sheet->setCellValue("N{$row}", $penggajian->jumlah_lembur);
                        $sheet->setCellValue("O{$row}", $penggajian->upah_kotor_karyawan);
                        $sheet->setCellValue("P{$row}", $penggajian->upah_diterima);
                        
                        $row++;
                    }
                    
                    $endDataRow = $row - 1;
                    
                    // ========== APPLY STYLES TO DATA ==========
                    
                    // Style untuk data rows
                    $sheet->getStyle("A{$startDataRow}:P{$endDataRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'CCCCCC']
                            ]
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER
                        ]
                    ]);

                    // Zebra striping
                    for ($r = $startDataRow; $r <= $endDataRow; $r++) {
                        if ($r % 2 == 0) {
                            $sheet->getStyle("A{$r}:P{$r}")->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'F2F2F2']
                                ]
                            ]);
                        }
                    }

                    // ========== NUMBER FORMATTING ==========
                    $currencyColumns = ['F', 'G', 'H', 'I', 'J', 'M', 'N', 'O', 'P'];
                    foreach ($currencyColumns as $col) {
                        $sheet->getStyle("{$col}{$startDataRow}:{$col}{$endDataRow}")
                            ->getNumberFormat()
                            ->setFormatCode('#,##0');
                    }

                    // Kolom jumlah hari kerja
                    $sheet->getStyle("L{$startDataRow}:L{$endDataRow}")
                        ->getNumberFormat()
                        ->setFormatCode('0.0');

                    // Center alignment untuk kolom No
                    $sheet->getStyle("A{$startDataRow}:A{$endDataRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Right alignment untuk kolom angka
                    foreach ($currencyColumns as $col) {
                        $sheet->getStyle("{$col}{$startDataRow}:{$col}{$endDataRow}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    $sheet->getStyle("L{$startDataRow}:L{$endDataRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // ========== TOTAL ROW ==========
                    $totalRow = $endDataRow + 1;
                    
                    // Label TOTAL
                    $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                    $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
                    $sheet->getStyle("A{$totalRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    
                    // Formulas untuk total
                    $sheet->setCellValue("F{$totalRow}", "=SUM(F{$startDataRow}:F{$endDataRow})");
                    $sheet->setCellValue("J{$totalRow}", "=SUM(J{$startDataRow}:J{$endDataRow})");
                    $sheet->setCellValue("O{$totalRow}", "=SUM(O{$startDataRow}:O{$endDataRow})");
                    $sheet->setCellValue("P{$totalRow}", "=SUM(P{$startDataRow}:P{$endDataRow})");

                    // Style untuk TOTAL row
                    $totalRowStyle = [
                        'font' => [
                            'bold' => true,
                            'size' => 11,
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'E2EFDA']
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => '000000']
                            ]
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER
                        ]
                    ];

                    $sheet->getStyle("A{$totalRow}:P{$totalRow}")->applyFromArray($totalRowStyle);

                    // Format angka untuk TOTAL row
                    foreach ($currencyColumns as $col) {
                        $sheet->getStyle("{$col}{$totalRow}")
                            ->getNumberFormat()
                            ->setFormatCode('#,##0');
                        
                        $sheet->getStyle("{$col}{$totalRow}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    // ========== RATA-RATA ROW ==========
                    $avgRow = $totalRow + 1;
                    $sheet->setCellValue("A{$avgRow}", 'RATA-RATA');
                    $sheet->mergeCells("A{$avgRow}:E{$avgRow}");
                    $sheet->getStyle("A{$avgRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    if ($this->totalData > 0) {
                        $sheet->setCellValue("F{$avgRow}", "=F{$totalRow}/{$this->totalData}");
                        $sheet->setCellValue("P{$avgRow}", "=P{$totalRow}/{$this->totalData}");
                    }

                    // Style untuk RATA-RATA row
                    $avgRowStyle = [
                        'font' => [
                            'bold' => true,
                            'italic' => true,
                            'size' => 10,
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFF2CC']
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'CCCCCC']
                            ]
                        ]
                    ];

                    $sheet->getStyle("A{$avgRow}:P{$avgRow}")->applyFromArray($avgRowStyle);

                    // Format angka untuk RATA-RATA
                    foreach (['F', 'P'] as $col) {
                        $sheet->getStyle("{$col}{$avgRow}")
                            ->getNumberFormat()
                            ->setFormatCode('#,##0');
                        
                        $sheet->getStyle("{$col}{$avgRow}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                } else {
                    // Jika tidak ada data
                    $sheet->setCellValue("A{$startDataRow}", 'TIDAK ADA DATA');
                    $sheet->mergeCells("A{$startDataRow}:P{$startDataRow}");
                    $sheet->getStyle("A{$startDataRow}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 12,
                            'color' => ['rgb' => 'FF0000']
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFEB9C']
                        ]
                    ]);
                }
                
                // ========== PAGE SETUP ==========
                
                // Set print area
                $sheet->getPageSetup()->setPrintArea('A1:P100');
                
                // Set orientation to landscape
                $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                
                // Set paper size to A4
                $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
                
                // Set margins
                $sheet->getPageMargins()->setTop(0.5);
                $sheet->getPageMargins()->setRight(0.5);
                $sheet->getPageMargins()->setLeft(0.5);
                $sheet->getPageMargins()->setBottom(0.5);
                
                // Center horizontally
                $sheet->getPageSetup()->setHorizontalCentered(true);
                
                // Set fit to page
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                
                // Add header for print
                $sheet->getHeaderFooter()
                    ->setOddHeader('&C&"Arial,Bold"PT SURYA TAMADO MANDIRI - Laporan Penggajian');
                
                // Add footer with page number
                $sheet->getHeaderFooter()
                    ->setOddFooter('&L&D &T&C&"Arial"Page &P of &N&R');
            },
        ];
    }
}