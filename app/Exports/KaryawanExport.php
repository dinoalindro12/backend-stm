<?php

namespace App\Exports;

use App\Models\Karyawan;
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

class KaryawanExport implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithStyles, 
    WithColumnWidths, 
    WithTitle,
    WithEvents
{
    protected $filters;
    protected $data;
    protected $totalData;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
        $this->data = $this->getData();
        $this->totalData = $this->data->count();
    }

    /**
     * Get data dengan filter
     */
    private function getData()
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
                  ->orWhere('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('no_wa', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('nama_lengkap')->get();
    }

    /**
     * Return collection of data
     */
    public function collection()
    {
        return $this->data;
    }

    /**
     * Define headings - 12 kolom (A-L)
     */
    public function headings(): array
    {
        return [
            'No',                           // A
            'Nomor Induk',                  // B
            'NIK',                          // C
            'No. Rekening BRI',             // D
            'Nama Lengkap',                 // E
            'Posisi',                       // F
            'Email',                        // G
            'No. WhatsApp',                 // H
            'Alamat',                       // I
            'Tanggal Masuk',                // J
            'Tanggal Keluar',               // K
            'Status',                       // L
        ];
    }

    /**
     * Map each row
     */
    public function map($karyawan): array
    {
        return [
            '', // No akan diisi otomatis nanti
            $karyawan->nomor_induk,
            $karyawan->nik,
            $karyawan->no_rek_bri,
            $karyawan->nama_lengkap,
            $this->getPosisiLabel($karyawan->posisi),
            $karyawan->email ?? '-',
            $karyawan->no_wa ?? '-',
            $karyawan->alamat,
            $karyawan->tanggal_masuk ? $karyawan->tanggal_masuk->format('d/m/Y') : '-',
            $karyawan->tanggal_keluar ? $karyawan->tanggal_keluar->format('d/m/Y') : '-',
            $karyawan->status_aktif ? 'Aktif' : 'Tidak Aktif',
        ];
    }

    /**
     * Get posisi label
     */
    private function getPosisiLabel($posisi)
    {
        $labels = [
            'jasa' => 'Jasa',
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
        $startDataRow = 6;
        $headerRow = 5;

        // ========== HEADER INFORMASI PERUSAHAAN ==========
        // Nama Perusahaan
        $sheet->setCellValue('A1', 'PT SURYA TAMADO MANDIRI');
        $sheet->mergeCells('A1:L1');
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
        $sheet->setCellValue('A2', 'DATA KARYAWAN');
        $sheet->mergeCells('A2:L2');
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

        // Filter Info
        $filterText = "Data Karyawan";
        
        if (isset($this->filters['status_aktif'])) {
            $statusText = $this->filters['status_aktif'] ? 'Aktif' : 'Tidak Aktif';
            $filterText .= " | Status: " . $statusText;
        }
        
        if (isset($this->filters['posisi'])) {
            $filterText .= " | Posisi: " . strtoupper($this->filters['posisi']);
        }
        
        if (isset($this->filters['search'])) {
            $filterText .= " | Pencarian: '" . $this->filters['search'] . "'";
        }
        
        $sheet->setCellValue('A3', $filterText);
        $sheet->mergeCells('A3:L3');
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
        $sheet->mergeCells('A4:L4');
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
        $sheet->getStyle("A{$headerRow}:L{$headerRow}")->applyFromArray([
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
        foreach (range('A', 'L') as $column) {
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
            'B' => 15,      // Nomor Induk
            'C' => 20,      // NIK
            'D' => 20,      // No Rek BRI
            'E' => 25,      // Nama Lengkap
            'F' => 15,      // Posisi
            'G' => 25,      // Email
            'H' => 18,      // No. WhatsApp
            'I' => 35,      // Alamat
            'J' => 15,      // Tanggal Masuk
            'K' => 15,      // Tanggal Keluar
            'L' => 12,      // Status
        ];
    }

    /**
     * Set sheet title
     */
    public function title(): string
    {
        $title = "Data Karyawan";
        
        if (isset($this->filters['posisi'])) {
            $title .= " - " . ucfirst($this->filters['posisi']);
        }
        
        if (isset($this->filters['status_aktif'])) {
            $status = $this->filters['status_aktif'] ? 'Aktif' : 'Tidak Aktif';
            $title .= " ($status)";
        }
        
        return $title;
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
                    foreach ($this->data as $index => $karyawan) {
                        $no = $index + 1;

                        // Tulis data per kolom
                        $sheet->setCellValue("A{$row}", $no);
                        $sheet->setCellValue("B{$row}", $karyawan->nomor_induk);
                        
                        // Format NIK sebagai text (mencegah notasi ilmiah)
                        $sheet->setCellValueExplicit(
                            "C{$row}", 
                            $karyawan->nik, 
                            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                        );
                        
                        // Format No Rek BRI sebagai text
                        $sheet->setCellValueExplicit(
                            "D{$row}", 
                            $karyawan->no_rek_bri, 
                            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                        );
                        
                        $sheet->setCellValue("E{$row}", $karyawan->nama_lengkap);
                        $sheet->setCellValue("F{$row}", $this->getPosisiLabel($karyawan->posisi));
                        $sheet->setCellValue("G{$row}", $karyawan->email ?? '-');
                        $sheet->setCellValue("H{$row}", $karyawan->no_wa ?? '-');
                        $sheet->setCellValue("I{$row}", $karyawan->alamat);
                        $sheet->setCellValue("J{$row}", 
                            $karyawan->tanggal_masuk ? $karyawan->tanggal_masuk->format('d/m/Y') : '-'
                        );
                        $sheet->setCellValue("K{$row}", 
                            $karyawan->tanggal_keluar ? $karyawan->tanggal_keluar->format('d/m/Y') : '-'
                        );
                        
                        // Status dengan warna conditional
                        $statusCell = $sheet->getCell("L{$row}");
                        $statusCell->setValue($karyawan->status_aktif ? 'Aktif' : 'Tidak Aktif');
                        
                        // Apply style untuk status
                        $statusStyle = [
                            'font' => [
                                'bold' => true,
                                'color' => ['rgb' => $karyawan->status_aktif ? '107C41' : 'E81123']
                            ],
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_CENTER
                            ]
                        ];
                        
                        $sheet->getStyle("L{$row}")->applyFromArray($statusStyle);

                        $row++;
                    }

                    $endDataRow = $row - 1;

                    // ========== APPLY STYLES TO DATA ==========
                    $sheet->getStyle("A{$startDataRow}:L{$endDataRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'CCCCCC']
                            ]
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true
                        ]
                    ]);

                    // Zebra striping
                    for ($r = $startDataRow; $r <= $endDataRow; $r++) {
                        if ($r % 2 == 0) {
                            $sheet->getStyle("A{$r}:L{$r}")->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'F2F2F2']
                                ]
                            ]);
                        }
                    }

                    // Alignment untuk kolom tertentu
                    $centerColumns = ['A', 'F', 'J', 'K', 'L']; // No, Posisi, Tanggal, Status
                    foreach ($centerColumns as $col) {
                        $sheet->getStyle("{$col}{$startDataRow}:{$col}{$endDataRow}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }

                    // Right alignment untuk kolom angka
                    $rightColumns = []; // Tidak ada kolom angka di data karyawan
                    foreach ($rightColumns as $col) {
                        $sheet->getStyle("{$col}{$startDataRow}:{$col}{$endDataRow}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    // Left alignment untuk kolom teks
                    $leftColumns = ['B', 'C', 'D', 'E', 'G', 'H', 'I'];
                    foreach ($leftColumns as $col) {
                        $sheet->getStyle("{$col}{$startDataRow}:{$col}{$endDataRow}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    }

                    // Wrap text untuk alamat
                    $sheet->getStyle("I{$startDataRow}:I{$endDataRow}")
                        ->getAlignment()
                        ->setWrapText(true);

                    // ========== SUMMARY ROW ==========
                    $summaryRow = $endDataRow + 2;
                    
                    // Jumlah Karyawan
                    $sheet->setCellValue("A{$summaryRow}", 'JUMLAH KARYAWAN:');
                    $sheet->setCellValue("B{$summaryRow}", $this->totalData);
                    $sheet->getStyle("A{$summaryRow}:B{$summaryRow}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'E2EFDA']
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => '000000']
                            ]
                        ]
                    ]);

                    // Status Summary
                    $activeCount = $this->data->where('status_aktif', true)->count();
                    $inactiveCount = $this->totalData - $activeCount;
                    
                    $sheet->setCellValue("D{$summaryRow}", 'AKTIF:');
                    $sheet->setCellValue("E{$summaryRow}", $activeCount);
                    $sheet->setCellValue("G{$summaryRow}", 'TIDAK AKTIF:');
                    $sheet->setCellValue("H{$summaryRow}", $inactiveCount);
                    
                    $sheet->mergeCells("D{$summaryRow}:E{$summaryRow}");
                    $sheet->mergeCells("G{$summaryRow}:H{$summaryRow}");
                    
                    $sheet->getStyle("D{$summaryRow}:H{$summaryRow}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFF2CC']
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => '000000']
                            ]
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER
                        ]
                    ]);

                    // Posisi Distribution
                    $distributionRow = $summaryRow + 1;
                    $posisiGroups = $this->data->groupBy('posisi');
                    
                    $sheet->setCellValue("A{$distributionRow}", 'DISTRIBUSI POSISI:');
                    $sheet->getStyle("A{$distributionRow}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'D9E1F2']
                        ]
                    ]);
                    
                    $colIndex = 'B';
                    foreach ($posisiGroups as $posisi => $group) {
                        $posisiLabel = $this->getPosisiLabel($posisi);
                        $sheet->setCellValue("{$colIndex}{$distributionRow}", $posisiLabel . ': ' . $group->count());
                        $colIndex++;
                    }

                } else {
                    // Jika tidak ada data
                    $sheet->setCellValue("A{$startDataRow}", 'TIDAK ADA DATA KARYAWAN');
                    $sheet->mergeCells("A{$startDataRow}:L{$startDataRow}");
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
                $sheet->getPageSetup()->setPrintArea('A1:L100');
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
                    ->setOddHeader('&C&"Arial,Bold"PT SURYA TAMADO MANDIRI');
                $sheet->getHeaderFooter()
                    ->setOddFooter('&L&D &T&C&"Arial"Page &P of &N&R');

                // ========== FREEZE PANES ==========
                $sheet->freezePane("A{$startDataRow}");
            },
        ];
    }
}