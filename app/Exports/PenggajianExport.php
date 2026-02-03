<?php

namespace App\Exports;

use App\Models\Penggajian;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PenggajianExport implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithStyles, 
    WithColumnWidths, 
    WithTitle,
    WithEvents
{
    protected $bulan;
    protected $tahun;
    protected $posisi;
    protected $data;
    protected $totalData;

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
     * Define headings - 15 kolom (A-O) - Kolom J (Jumlah Iuran BPJS) dihapus
     */
    public function headings(): array
    {
        return [
            'No',                           // A
            'No Rekening BRI',              // B
            'NIK',                          // C
            'Nama',                         // D
            'Bagian',                       // E
            'Jumlah Penghasilan Kotor',     // F
            'BPJS Kesehatan',               // G
            'BPJS JHT',                     // H
            'BPJS JP',                      // I
            'THR',                          // J (sebelumnya K)
            'Jumlah Hari Kerja',            // K (sebelumnya L)
            'Satuan',                       // L (sebelumnya M) - Gaji Harian
            'Lembur Hari Besar',            // M (sebelumnya N)
            'Upah Kotor Karyawan',          // N (sebelumnya O)
            'Upah yang diterima'            // O (sebelumnya P)
        ];
    }

    /**
     * Map each row - 15 kolom (A-O)
     */
    public function map($penggajian): array
    {
        return [
            '',                                         // No akan diisi otomatis nanti
            $penggajian->no_rek_bri ?? '-',
            $penggajian->nik,                           // NIK akan diformat di registerEvents
            $penggajian->nama,
            $this->getPosisiLabel($penggajian->posisi),
            $penggajian->jumlah_penghasilan_kotor,
            $penggajian->bpjs_kesehatan,
            $penggajian->bpjs_jht,
            $penggajian->bpjs_jp,
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
            'supur' => 'Supir',
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
        $startDataRow = 6;
        $headerRow = 5;

        // ========== HEADER INFORMASI PERUSAHAAN ==========
        // Nama Perusahaan
        $sheet->setCellValue('A1', 'PT SURYA TAMADO MANDIRI');
        $sheet->mergeCells('A1:O1');
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
        $sheet->mergeCells('A2:O2');
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
        $sheet->mergeCells('A3:O3');
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
        $sheet->mergeCells('A4:O4');
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
        $sheet->getStyle("A{$headerRow}:O{$headerRow}")->applyFromArray([
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

        // Auto size semua kolom
        foreach (range('A', 'O') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return [];
    }

    /**
     * Set column widths
     */
    public function columnWidths(): array
    {
        return [
            'A' => 6,       // No
            'B' => 18,      // No Rek BRI
            'C' => 20,      // NIK (diperlebar untuk menampung angka panjang)
            'D' => 25,      // Nama
            'E' => 15,      // Bagian
            'F' => 22,      // Jml Penghasilan Kotor
            'G' => 18,      // BPJS Kesehatan
            'H' => 15,      // BPJS JHT
            'I' => 15,      // BPJS JP
            'J' => 15,      // THR
            'K' => 18,      // Jml Hari Kerja
            'L' => 15,      // Satuan
            'M' => 20,      // Lembur Hari Besar
            'N' => 22,      // Upah Kotor Karyawan
            'O' => 22       // Upah yang diterima
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
                        
                        // *** FORMAT NO REKENING BRI SEBAGAI TEXT AGAR TIDAK JADI NOTASI ILMIAH ***
                        $noRekBri = $penggajian->no_rek_bri ?? '-';
                        if ($noRekBri !== '-') {
                            $sheet->setCellValueExplicit("B{$row}", $noRekBri, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        } else {
                            $sheet->setCellValue("B{$row}", $noRekBri);
                        }
                        
                        // *** FORMAT NIK SEBAGAI TEXT AGAR TIDAK JADI NOTASI ILMIAH ***
                        $sheet->setCellValueExplicit("C{$row}", $penggajian->nik, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        
                        $sheet->setCellValue("D{$row}", $penggajian->nama);
                        $sheet->setCellValue("E{$row}", $this->getPosisiLabel($penggajian->posisi));
                        $sheet->setCellValue("F{$row}", $penggajian->jumlah_penghasilan_kotor);
                        $sheet->setCellValue("G{$row}", $penggajian->bpjs_kesehatan);
                        $sheet->setCellValue("H{$row}", $penggajian->bpjs_jht);
                        $sheet->setCellValue("I{$row}", $penggajian->bpjs_jp);
                        $sheet->setCellValue("J{$row}", $penggajian->uang_thr ?? 0);
                        $sheet->setCellValue("K{$row}", $penggajian->jumlah_hari_kerja);
                        $sheet->setCellValue("L{$row}", $penggajian->gaji_harian);
                        $sheet->setCellValue("M{$row}", $penggajian->jumlah_lembur);
                        $sheet->setCellValue("N{$row}", $penggajian->upah_kotor_karyawan);
                        $sheet->setCellValue("O{$row}", $penggajian->upah_diterima);

                        $row++;
                    }

                    $endDataRow = $row - 1;

                    // ========== APPLY STYLES TO DATA ==========
                    $sheet->getStyle("A{$startDataRow}:O{$endDataRow}")->applyFromArray([
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
                            $sheet->getStyle("A{$r}:O{$r}")->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'F2F2F2']
                                ]
                            ]);
                        }
                    }

                    // ========== NUMBER FORMATTING ==========
                    $currencyColumns = ['F', 'G', 'H', 'I', 'L', 'M', 'N', 'O'];
                    foreach ($currencyColumns as $col) {
                        $sheet->getStyle("{$col}{$startDataRow}:{$col}{$endDataRow}")
                            ->getNumberFormat()
                            ->setFormatCode('#,##0');
                    }

                    // Kolom jumlah hari kerja
                    $sheet->getStyle("K{$startDataRow}:K{$endDataRow}")
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

                    $sheet->getStyle("K{$startDataRow}:K{$endDataRow}")
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
                    $sheet->setCellValue("N{$totalRow}", "=SUM(N{$startDataRow}:N{$endDataRow})");
                    $sheet->setCellValue("O{$totalRow}", "=SUM(O{$startDataRow}:O{$endDataRow})");

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

                    $sheet->getStyle("A{$totalRow}:O{$totalRow}")->applyFromArray($totalRowStyle);

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
                        $sheet->setCellValue("O{$avgRow}", "=O{$totalRow}/{$this->totalData}");
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

                    $sheet->getStyle("A{$avgRow}:O{$avgRow}")->applyFromArray($avgRowStyle);

                    // Format angka untuk RATA-RATA
                    foreach (['F', 'O'] as $col) {
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
                    $sheet->mergeCells("A{$startDataRow}:O{$startDataRow}");
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
                $sheet->getPageSetup()->setPrintArea('A1:O100');
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
                    ->setOddHeader('&C&"Arial,Bold"PT SURYA TAMADO MANDIRI - Laporan Penggajian');
                $sheet->getHeaderFooter()
                    ->setOddFooter('&L&D &T&C&"Arial"Page &P of &N&R');
            },
        ];
    }
}
