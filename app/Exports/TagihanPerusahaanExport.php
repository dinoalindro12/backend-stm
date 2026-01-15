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

    public function __construct($periodeAwal, $periodeAkhir, $posisi = null)
    {
        $this->periodeAwal = $periodeAwal;
        $this->periodeAkhir = $periodeAkhir;
        $this->posisi = $posisi;
        $this->data = $this->getData();
        $this->totalData = $this->data->count();
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
     * Return collection
     */
    public function collection()
    {
        return $this->data;
    }

    /**
     * Define headings - 17 kolom sesuai screenshot
     */
    public function headings(): array
    {
        return [
            'No',                           // A
            'No Induk',                     // B
            'NIK',                          // C
            'NAMA',                         // D
            'Bagian',                       // E
            'Jumlah Gaji Diterima',        // F
            'BPJS Kesehatan',              // G
            'JKK',                         // H
            'JKM',                         // I
            'JHT',                         // J
            'JP',                          // K
            'Seragam CS dan Keamanan',     // L
            'Fee Manajemen',               // M
            'Jumlah Iuran',                // N
            'THR',                         // O
            'Jumlah Hari Kerja',           // P
            'Gaji Harian',                 // Q
            'Lembur',                      // R
            'Upah yang Diterima Pekerja',   // S
            'Total Diterima'               // T
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
        
        // Nama Perusahaan
        $sheet->setCellValue('A1', 'PT SURYA TAMADO MANDIRI');
        $sheet->mergeCells('A1:T1');
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
        $sheet->mergeCells('A2:T2');
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
        $sheet->mergeCells('A3:T3');
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
        $sheet->mergeCells('A4:T4');
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
        $sheet->getStyle("A{$headerRow}:T{$headerRow}")->applyFromArray([
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
            'C' => 18,  // NIK
            'D' => 25,  // NAMA
            'E' => 20,  // Bagian
            'F' => 20,  // Jumlah Gaji Diterima
            'G' => 18,  // BPJS Kesehatan
            'H' => 12,  // JKK
            'I' => 12,  // JKM
            'J' => 15,  // JHT
            'K' => 12,  // JP
            'L' => 20,  // Seragam CS dan Keamanan
            'M' => 18,  // Fee Manajemen
            'N' => 18,  // Jumlah Iuran
            'O' => 15,  // THR
            'P' => 18,  // Jumlah Hari Kerja
            'Q' => 15,  // Gaji Harian
            'R' => 15,  // Lembur
            'S' => 22,  // Upa yang Diterima Pekerja
            'T' => 22   // Total Diterima
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
                    
                    foreach ($this->data as $index => $tagihan) {
                        $no = $index + 1;
                        
                        // Tulis data per kolom (20 kolom: A-T)
                        $sheet->setCellValue("A{$row}", $no);
                        $sheet->setCellValue("B{$row}", $tagihan->no_induk);
                        $sheet->setCellValue("C{$row}", $tagihan->nik);
                        $sheet->setCellValue("D{$row}", $tagihan->nama);
                        $sheet->setCellValue("E{$row}", $this->getPosisiLabel($tagihan->posisi));
                        $sheet->setCellValue("F{$row}", $tagihan->jumlah_gaji_diterima ?? 3732900);
                        $sheet->setCellValue("G{$row}", $tagihan->bpjs_kesehatan ?? 0);
                        $sheet->setCellValue("H{$row}", $tagihan->jkk ?? 0);
                        $sheet->setCellValue("I{$row}", $tagihan->jkm ?? 0);
                        $sheet->setCellValue("J{$row}", $tagihan->jht ?? 0);
                        $sheet->setCellValue("K{$row}", $tagihan->jp ?? 0);
                        $sheet->setCellValue("L{$row}", $tagihan->seragam_cs_dan_keamanan ?? 0);
                        $sheet->setCellValue("M{$row}", $tagihan->fee_manajemen ?? 0);
                        $sheet->setCellValue("N{$row}", $tagihan->jumlah_iuran_bpjs ?? 0);
                        $sheet->setCellValue("O{$row}", $tagihan->thr ?? 0);
                        $sheet->setCellValue("P{$row}", $tagihan->jumlah_hari_kerja ?? 0);
                        $sheet->setCellValue("Q{$row}", $tagihan->gaji_harian ?? 0);
                        $sheet->setCellValue("R{$row}", $tagihan->lembur ?? 0);
                        $sheet->setCellValue("S{$row}", $tagihan->upah_yang_diterima_pekerja ?? 0);
                        $sheet->setCellValue("T{$row}", $tagihan->total_diterima ?? 0);
                        
                        $row++;
                    }
                    
                    $endDataRow = $row - 1;
                    
                    // ========== APPLY STYLES TO DATA ==========
                    
                    // Style untuk data rows
                    $sheet->getStyle("A{$startDataRow}:T{$endDataRow}")->applyFromArray([
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
                            $sheet->getStyle("A{$r}:T{$r}")->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'F2F2F2']
                                ]
                            ]);
                        }
                    }

                    // ========== NUMBER FORMATTING ==========
                    
                    // Format currency untuk kolom angka
                    $currencyColumns = ['F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'Q', 'R', 'S', 'T'];
                    foreach ($currencyColumns as $col) {
                        $sheet->getStyle("{$col}{$startDataRow}:{$col}{$endDataRow}")
                            ->getNumberFormat()
                            ->setFormatCode('#,##0');
                    }

                    // Format untuk jumlah hari kerja (dengan desimal)
                    $sheet->getStyle("P{$startDataRow}:P{$endDataRow}")
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

                    $sheet->getStyle("P{$startDataRow}:P{$endDataRow}")
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
                    $sheet->setCellValue("G{$totalRow}", "=SUM(G{$startDataRow}:G{$endDataRow})");
                    $sheet->setCellValue("H{$totalRow}", "=SUM(H{$startDataRow}:H{$endDataRow})");
                    $sheet->setCellValue("I{$totalRow}", "=SUM(I{$startDataRow}:I{$endDataRow})");
                    $sheet->setCellValue("J{$totalRow}", "=SUM(J{$startDataRow}:J{$endDataRow})");
                    $sheet->setCellValue("K{$totalRow}", "=SUM(K{$startDataRow}:K{$endDataRow})");
                    $sheet->setCellValue("L{$totalRow}", "=SUM(L{$startDataRow}:L{$endDataRow})");
                    $sheet->setCellValue("M{$totalRow}", "=SUM(M{$startDataRow}:M{$endDataRow})");
                    $sheet->setCellValue("N{$totalRow}", "=SUM(N{$startDataRow}:N{$endDataRow})");
                    $sheet->setCellValue("O{$totalRow}", "=SUM(O{$startDataRow}:O{$endDataRow})");
                    $sheet->setCellValue("R{$totalRow}", "=SUM(R{$startDataRow}:R{$endDataRow})");
                    $sheet->setCellValue("S{$totalRow}", "=SUM(S{$startDataRow}:S{$endDataRow})");
                    $sheet->setCellValue("T{$totalRow}", "=SUM(T{$startDataRow}:T{$endDataRow})");

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

                    $sheet->getStyle("A{$totalRow}:T{$totalRow}")->applyFromArray($totalRowStyle);

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
                        $sheet->setCellValue("N{$avgRow}", "=N{$totalRow}/{$this->totalData}");
                        $sheet->setCellValue("S{$avgRow}", "=S{$totalRow}/{$this->totalData}");
                        $sheet->setCellValue("T{$avgRow}", "=T{$totalRow}/{$this->totalData}");
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

                    $sheet->getStyle("A{$avgRow}:T{$avgRow}")->applyFromArray($avgRowStyle);

                    // Format angka untuk RATA-RATA
                    foreach (['F', 'N', 'S', 'T'] as $col) {
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
                    $sheet->mergeCells("A{$startDataRow}:T{$startDataRow}");
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