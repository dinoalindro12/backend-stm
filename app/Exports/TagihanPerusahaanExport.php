<?php

namespace App\Exports;

use App\Models\TagihanPerusahaan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
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

class TagihanPerusahaanExport implements 
    FromCollection, 
    WithHeadings, 
    WithStyles,
    WithColumnWidths,
    WithTitle,
    ShouldAutoSize,
    WithEvents
{
    protected $periodeAwal;
    protected $periodeAkhir;
    protected $posisi;
    protected $data;
    protected $totalData = 0;
    protected $mappedData;

    public function __construct($periodeAwal, $periodeAkhir, $posisi = null)
    {
        $this->periodeAwal = $periodeAwal;
        $this->periodeAkhir = $periodeAkhir;
        $this->posisi = $posisi;
        $this->data = $this->getData();
        $this->totalData = $this->data->count();
        $this->mappedData = $this->mapData();
    }

    /**
     * Get data
     */
    private function getData()
    {
        $query = TagihanPerusahaan::with('karyawan')
            ->where('periode_awal', $this->periodeAwal)
            ->where('periode_akhir', $this->periodeAkhir);

        if ($this->posisi) {
            $query->where('posisi', $this->posisi);
        }

        return $query->orderBy('nama')->get();
    }

    /**
     * Map data untuk hanya mengambil kolom yang dibutuhkan
     */
    private function mapData()
    {
        return $this->data->map(function($tagihan) {
            return [
                // Kolom-kolom yang akan ditampilkan di Excel
                'no_induk' => $tagihan->no_induk,
                'nik' => $tagihan->nik,
                'nama' => $tagihan->nama,
                'posisi' => $tagihan->posisi,
                'bpjs_kesehatan' => $tagihan->bpjs_kesehatan ?? 0,
                'jkk' => $tagihan->jkk ?? 0,
                'jkm' => $tagihan->jkm ?? 0,
                'jht' => $tagihan->jht ?? 0,
                'jp' => $tagihan->jp ?? 0,
                'seragam_cs_dan_keamanan' => $tagihan->seragam_cs_dan_keamanan ?? 0,
                'fee_manajemen' => $tagihan->fee_manajemen ?? 0,
                'thr' => $tagihan->thr ?? 0,
                'jumlah_hari_kerja' => $tagihan->jumlah_hari_kerja ?? 0,
                'gaji_harian' => $tagihan->gaji_harian ?? 0,
                'lembur' => $tagihan->lembur ?? 0,
                'upah_yang_diterima_pekerja' => $tagihan->upah_yang_diterima_pekerja ?? 0,
                'total_diterima' => $tagihan->total_diterima ?? 0,
            ];
        });
    }

    /**
     * Return collection yang sudah dimapping
     */
    public function collection()
    {
        // Kembalikan data yang sudah dimapping, bukan model langsung
        return $this->mappedData;
    }

    /**
     * Define headings - 18 kolom
     */
    public function headings(): array
    {
        return [
            'No',                           // A
            'No Induk',                     // B
            'NIK',                          // C
            'NAMA',                         // D
            'Bagian',                       // E
            'BPJS Kesehatan',               // F
            'JKK',                          // G
            'JKM',                          // H
            'JHT',                          // I
            'JP',                           // J
            'Seragam CS dan Keamanan',      // K
            'Fee Manajemen',                // L
            'THR',                          // M
            'Jumlah Hari Kerja',            // N
            'Gaji Harian',                  // O
            'Lembur',                       // P
            'Upah yang Diterima Pekerja',   // Q
            'Total Diterima'                // R
        ];
    }

    /**
     * Apply styles to worksheet
     */
    public function styles(Worksheet $sheet)
    {
        $headerRow = 5;
        $startDataRow = 6;

        // ========== HEADER INFORMASI PERUSAHAAN ==========
        
        // Nama Perusahaan (18 kolom: A-R)
        $sheet->setCellValue('A1', 'PT SURYA TAMADO MANDIRI');
        $sheet->mergeCells('A1:R1');
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
        $sheet->setCellValue('A2', 'LAPORAN TAGIHAN PERUSAHAAN');
        $sheet->mergeCells('A2:R2');
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
        $periodeText = "Periode: " . date('d/m/Y', strtotime($this->periodeAwal)) . " - " . date('d/m/Y', strtotime($this->periodeAkhir));
        if ($this->posisi) {
            $periodeText .= " | Posisi: " . strtoupper(str_replace('_', ' ', $this->posisi));
        }
        
        $sheet->setCellValue('A3', $periodeText);
        $sheet->mergeCells('A3:R3');
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
        $sheet->mergeCells('A4:R4');
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
        $sheet->getStyle("A{$headerRow}:R{$headerRow}")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
                'name' => 'Arial'
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
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

        return [];
    }

    /**
     * Set column widths
     */
    public function columnWidths(): array
    {
        return [
            'A' => 6,   // No
            'B' => 15,  // No Induk
            'C' => 20,  // NIK (diperlebar untuk menampung angka panjang)
            'D' => 25,  // NAMA
            'E' => 20,  // Bagian
            'F' => 18,  // BPJS Kesehatan
            'G' => 12,  // JKK
            'H' => 12,  // JKM
            'I' => 15,  // JHT
            'J' => 12,  // JP
            'K' => 20,  // Seragam CS dan Keamanan
            'L' => 18,  // Fee Manajemen
            'M' => 15,  // THR
            'N' => 18,  // Jumlah Hari Kerja
            'O' => 15,  // Gaji Harian
            'P' => 15,  // Lembur
            'Q' => 22,  // Upah yang Diterima Pekerja
            'R' => 22   // Total Diterima
        ];
    }

    /**
     * Set sheet title
     */
    public function title(): string
    {
        $periode = date('d-m-Y', strtotime($this->periodeAwal));
        
        if ($this->posisi) {
            return "Tagihan {$periode} - " . ucfirst($this->posisi);
        }
        
        return "Tagihan {$periode}";
    }

    /**
     * Get posisi label
     */
    private function getPosisiLabel($posisi)
    {
        $labels = [
            'jasa' => 'JASA',
            'supir' => 'SUPIR',
            'keamanan' => 'KEAMANAN',
            'cleaning_service' => 'CLEANING SERVICE',
            'operator' => 'OPERATOR'
        ];
        
        return $labels[$posisi] ?? strtoupper($posisi);
    }

    /**
     * Register events
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
                    
                    // Gunakan data yang sudah dimapping
                    foreach ($this->mappedData as $index => $tagihan) {
                        $no = $index + 1;
                        
                        // Tulis data per kolom (18 kolom: A-R)
                        $sheet->setCellValue("A{$row}", $no);
                        $sheet->setCellValue("B{$row}", $tagihan['no_induk']);
                        
                        // *** FORMAT NIK SEBAGAI TEXT AGAR TIDAK JADI NOTASI ILMIAH ***
                        $sheet->setCellValueExplicit("C{$row}", $tagihan['nik'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        
                        $sheet->setCellValue("D{$row}", $tagihan['nama']);
                        $sheet->setCellValue("E{$row}", $this->getPosisiLabel($tagihan['posisi']));
                        
                        // *** TAMPILKAN HANYA JIKA TIDAK NOL ***
                        $sheet->setCellValue("F{$row}", $tagihan['bpjs_kesehatan'] != 0 ? $tagihan['bpjs_kesehatan'] : '');
                        $sheet->setCellValue("G{$row}", $tagihan['jkk'] != 0 ? $tagihan['jkk'] : '');
                        $sheet->setCellValue("H{$row}", $tagihan['jkm'] != 0 ? $tagihan['jkm'] : '');
                        $sheet->setCellValue("I{$row}", $tagihan['jht'] != 0 ? $tagihan['jht'] : '');
                        $sheet->setCellValue("J{$row}", $tagihan['jp'] != 0 ? $tagihan['jp'] : '');
                        $sheet->setCellValue("K{$row}", $tagihan['seragam_cs_dan_keamanan'] != 0 ? $tagihan['seragam_cs_dan_keamanan'] : '');
                        $sheet->setCellValue("L{$row}", $tagihan['fee_manajemen'] != 0 ? $tagihan['fee_manajemen'] : '');
                        
                        $sheet->setCellValue("M{$row}", $tagihan['thr']);
                        $sheet->setCellValue("N{$row}", $tagihan['jumlah_hari_kerja']);
                        $sheet->setCellValue("O{$row}", $tagihan['gaji_harian']);
                        $sheet->setCellValue("P{$row}", $tagihan['lembur']);
                        $sheet->setCellValue("Q{$row}", $tagihan['upah_yang_diterima_pekerja']);
                        $sheet->setCellValue("R{$row}", $tagihan['total_diterima']);
                        
                        $row++;
                    }
                    
                    $endDataRow = $row - 1;
                    
                    // ========== APPLY STYLES TO DATA ==========
                    
                    // Style untuk data rows
                    $sheet->getStyle("A{$startDataRow}:R{$endDataRow}")->applyFromArray([
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
                            $sheet->getStyle("A{$r}:R{$r}")->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'F2F2F2']
                                ]
                            ]);
                        }
                    }

                    // ========== NUMBER FORMATTING ==========
                    
                    // Format currency untuk kolom angka
                    $currencyColumns = ['F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'O', 'P', 'Q', 'R'];
                    foreach ($currencyColumns as $col) {
                        $sheet->getStyle("{$col}{$startDataRow}:{$col}{$endDataRow}")
                            ->getNumberFormat()
                            ->setFormatCode('#,##0');
                    }

                    // Format untuk jumlah hari kerja (dengan desimal)
                    $sheet->getStyle("N{$startDataRow}:N{$endDataRow}")
                        ->getNumberFormat()
                        ->setFormatCode('0.0');

                    // Center alignment untuk kolom No dan No Induk
                    $sheet->getStyle("A{$startDataRow}:B{$endDataRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Right alignment untuk kolom angka
                    foreach ($currencyColumns as $col) {
                        $sheet->getStyle("{$col}{$startDataRow}:{$col}{$endDataRow}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    $sheet->getStyle("N{$startDataRow}:N{$endDataRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // ========== TOTAL ROW ==========
                    $totalRow = $endDataRow + 1;
                    
                    // Label TOTAL
                    $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                    $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
                    $sheet->getStyle("A{$totalRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    
                    // Formulas untuk total (sesuai kolom yang ada)
                    $sheet->setCellValue("F{$totalRow}", "=SUM(F{$startDataRow}:F{$endDataRow})");
                    $sheet->setCellValue("G{$totalRow}", "=SUM(G{$startDataRow}:G{$endDataRow})");
                    $sheet->setCellValue("H{$totalRow}", "=SUM(H{$startDataRow}:H{$endDataRow})");
                    $sheet->setCellValue("I{$totalRow}", "=SUM(I{$startDataRow}:I{$endDataRow})");
                    $sheet->setCellValue("J{$totalRow}", "=SUM(J{$startDataRow}:J{$endDataRow})");
                    $sheet->setCellValue("K{$totalRow}", "=SUM(K{$startDataRow}:K{$endDataRow})");
                    $sheet->setCellValue("L{$totalRow}", "=SUM(L{$startDataRow}:L{$endDataRow})");
                    $sheet->setCellValue("M{$totalRow}", "=SUM(M{$startDataRow}:M{$endDataRow})");
                    $sheet->setCellValue("P{$totalRow}", "=SUM(P{$startDataRow}:P{$endDataRow})");
                    $sheet->setCellValue("Q{$totalRow}", "=SUM(Q{$startDataRow}:Q{$endDataRow})");
                    $sheet->setCellValue("R{$totalRow}", "=SUM(R{$startDataRow}:R{$endDataRow})");

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

                    $sheet->getStyle("A{$totalRow}:R{$totalRow}")->applyFromArray($totalRowStyle);

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
                        // Hanya kolom yang relevan untuk rata-rata
                        $sheet->setCellValue("Q{$avgRow}", "=Q{$totalRow}/{$this->totalData}");
                        $sheet->setCellValue("R{$avgRow}", "=R{$totalRow}/{$this->totalData}");
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

                    $sheet->getStyle("A{$avgRow}:R{$avgRow}")->applyFromArray($avgRowStyle);

                    // Format angka untuk RATA-RATA
                    foreach (['Q', 'R'] as $col) {
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
                    $sheet->mergeCells("A{$startDataRow}:R{$startDataRow}");
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
                    $sheet->getRowDimension($startDataRow)->setRowHeight(40);
                }
                
                // ========== PAGE SETUP ==========
                
                $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
                $sheet->getPageMargins()->setTop(0.5);
                $sheet->getPageMargins()->setRight(0.5);
                $sheet->getPageMargins()->setLeft(0.5);
                $sheet->getPageMargins()->setBottom(0.5);
                $sheet->getPageSetup()->setHorizontalCentered(true);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                
                $sheet->getHeaderFooter()
                    ->setOddHeader('&C&"Arial,Bold"PT SURYA TAMADO MANDIRI - Laporan Tagihan Perusahaan');
                
                $sheet->getHeaderFooter()
                    ->setOddFooter('&L&D &T&C&"Arial"Page &P of &N&R');
            },
        ];
    }
}