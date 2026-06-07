<?php

namespace App\Exports;

use App\Models\TagihanPerusahaan;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Carbon\Carbon;

class TagihanPerusahaanExport implements
    WithTitle,
    ShouldAutoSize,
    WithEvents
{
    protected $gajianBulan;
    protected $posisi;
    protected $data;
    protected $totalData = 0;

    public function __construct($gajianBulan, $posisi = null)
    {
        $this->gajianBulan = $gajianBulan;
        $this->posisi      = $posisi;
        $this->data        = $this->getData();
        $this->totalData   = $this->data->count();
    }

    /**
     * Ambil data berdasarkan tagihan_bulan (bukan gajian_bulan)
     */
    private function getData()
    {
        $bulan = Carbon::parse($this->gajianBulan)->startOfMonth();

        $query = TagihanPerusahaan::with('karyawan')
            ->whereYear('tagihan_bulan', $bulan->year)
            ->whereMonth('tagihan_bulan', $bulan->month);

        if ($this->posisi) {
            $query->whereHas('karyawan', fn($q) => $q->where('posisi', $this->posisi));
        }

        return $query->orderBy('created_at')->get();
    }

    /**
     * Judul sheet
     */
    public function title(): string
    {
        $bulan = Carbon::parse($this->gajianBulan);
        $title = "Tagihan " . $bulan->format('Y-m');

        if ($this->posisi) {
            $title .= ' - ' . ucfirst($this->posisi);
        }

        // Maks 31 karakter untuk nama sheet Excel
        return substr($title, 0, 31);
    }

    /**
     * Register events — semua penulisan dilakukan di sini
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet       = $event->sheet->getDelegate();
                $headerRow   = 5;
                $startData   = 6;
                $bulan       = Carbon::parse($this->gajianBulan);

                // ── Baris info perusahaan ──────────────────────────────────
                $sheet->setCellValue('A1', 'PT SURYA TAMADO MANDIRI');
                $sheet->mergeCells('A1:R1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 16, 'name' => 'Arial'],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(30);

                $sheet->setCellValue('A2', 'LAPORAN TAGIHAN PERUSAHAAN');
                $sheet->mergeCells('A2:R2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 14, 'name' => 'Arial'],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(2)->setRowHeight(25);

                $periodeText = 'Bulan: ' . $this->getBulanIndonesia($bulan->format('n')) . ' ' . $bulan->format('Y');
                if ($this->posisi) {
                    $periodeText .= ' | Posisi: ' . strtoupper(str_replace('_', ' ', $this->posisi));
                }
                $sheet->setCellValue('A3', $periodeText);
                $sheet->mergeCells('A3:R3');
                $sheet->getStyle('A3')->applyFromArray([
                    'font'      => ['size' => 12, 'name' => 'Arial'],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(3)->setRowHeight(20);

                $sheet->setCellValue('A4', 'Tanggal Cetak: ' . now()->format('d/m/Y H:i:s'));
                $sheet->mergeCells('A4:R4');
                $sheet->getStyle('A4')->applyFromArray([
                    'font'      => ['size' => 10, 'italic' => true, 'name' => 'Arial'],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(4)->setRowHeight(18);

                // ── Header tabel ───────────────────────────────────────────
                $headers = [
                    'No', 'No Induk', 'NIK', 'Nama', 'Bagian',
                    'BPJS Kesehatan', 'JKK', 'JKM', 'JHT', 'JP',
                    'Seragam CS & Keamanan', 'Fee Manajemen', 'THR',
                    'Jml Hari Kerja', 'Gaji Harian', 'Lembur',
                    'Upah Diterima Pekerja', 'Total Tagihan',
                ];
                $col = 'A';
                foreach ($headers as $header) {
                    $sheet->setCellValue("{$col}{$headerRow}", $header);
                    $col++;
                }

                $sheet->getStyle("A{$headerRow}:R{$headerRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11, 'name' => 'Arial'],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                ]);
                $sheet->getRowDimension($headerRow)->setRowHeight(30);

                // ── Lebar kolom ────────────────────────────────────────────
                $widths = [
                    'A' => 6,  'B' => 15, 'C' => 20, 'D' => 25, 'E' => 20,
                    'F' => 18, 'G' => 12, 'H' => 12, 'I' => 15, 'J' => 12,
                    'K' => 22, 'L' => 18, 'M' => 15, 'N' => 15, 'O' => 15,
                    'P' => 15, 'Q' => 22, 'R' => 20,
                ];
                foreach ($widths as $c => $w) {
                    $sheet->getColumnDimension($c)->setWidth($w);
                }

                // ── Tulis data ─────────────────────────────────────────────
                if ($this->totalData > 0) {
                    $row = $startData;

                    foreach ($this->data as $index => $tagihan) {
                        $k = $tagihan->karyawan;

                        $sheet->setCellValue("A{$row}", $index + 1);
                        $sheet->setCellValue("B{$row}", optional($k)->nomor_induk ?? '-');
                        $sheet->setCellValueExplicit("C{$row}", optional($k)->nik ?? '-', DataType::TYPE_STRING);
                        $sheet->setCellValue("D{$row}", optional($k)->nama_lengkap ?? '-');
                        $sheet->setCellValue("E{$row}", $this->getPosisiLabel(optional($k)->posisi));

                        $sheet->setCellValue("F{$row}", $tagihan->bpjs_kesehatan ?? 0);
                        $sheet->setCellValue("G{$row}", $tagihan->jkk ?? 0);
                        $sheet->setCellValue("H{$row}", $tagihan->jkm ?? 0);
                        $sheet->setCellValue("I{$row}", $tagihan->jht ?? 0);
                        $sheet->setCellValue("J{$row}", $tagihan->jp ?? 0);
                        $sheet->setCellValue("K{$row}", $tagihan->seragam_cs_dan_keamanan ?? 0);
                        $sheet->setCellValue("L{$row}", $tagihan->fee_manajemen ?? 0);
                        $sheet->setCellValue("M{$row}", $tagihan->thr ?? 0);
                        $sheet->setCellValue("N{$row}", $tagihan->jumlah_hari_kerja ?? 0);
                        $sheet->setCellValue("O{$row}", $tagihan->gaji_harian ?? 0);
                        $sheet->setCellValue("P{$row}", $tagihan->jlh_lembur ?? 0);
                        // Q: Upah Diterima Pekerja = (Gaji Harian × Hari Kerja) + Lembur + THR
                        $sheet->setCellValue("Q{$row}", "=(O{$row}*N{$row})+P{$row}+M{$row}");
                        // R: Total Tagihan = Upah Diterima Pekerja + semua iuran & fee perusahaan
                        $sheet->setCellValue("R{$row}", "=Q{$row}-F{$row}+G{$row}+H{$row}+I{$row}+J{$row}+K{$row}+L{$row}");

                        $row++;
                    }

                    $endData = $row - 1;

                    // Border & zebra striping
                    $sheet->getStyle("A{$startData}:R{$endData}")->applyFromArray([
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    for ($r = $startData; $r <= $endData; $r++) {
                        if ($r % 2 === 0) {
                            $sheet->getStyle("A{$r}:R{$r}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setRGB('F2F2F2');
                        }
                    }

                    // Format angka
                    $currencyCols = ['F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'O', 'P', 'Q', 'R'];
                    foreach ($currencyCols as $c) {
                        $sheet->getStyle("{$c}{$startData}:{$c}{$endData}")->getNumberFormat()->setFormatCode('#,##0');
                        $sheet->getStyle("{$c}{$startData}:{$c}{$endData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                    $sheet->getStyle("N{$startData}:N{$endData}")->getNumberFormat()->setFormatCode('0.0');
                    $sheet->getStyle("N{$startData}:N{$endData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("A{$startData}:B{$endData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Baris TOTAL
                    $totalRow = $endData + 1;
                    $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                    $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
                    $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    foreach (['F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'P', 'Q', 'R'] as $c) {
                        $sheet->setCellValue("{$c}{$totalRow}", "=SUM({$c}{$startData}:{$c}{$endData})");
                        $sheet->getStyle("{$c}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                        $sheet->getStyle("{$c}{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    $sheet->getStyle("A{$totalRow}:R{$totalRow}")->applyFromArray([
                        'font'    => ['bold' => true, 'size' => 11],
                        'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                    ]);

                } else {
                    // Tidak ada data
                    $sheet->setCellValue("A{$startData}", 'TIDAK ADA DATA');
                    $sheet->mergeCells("A{$startData}:R{$startData}");
                    $sheet->getStyle("A{$startData}")->applyFromArray([
                        'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FF0000']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFEB9C']],
                    ]);
                    $sheet->getRowDimension($startData)->setRowHeight(40);
                }

                // ── Page setup ─────────────────────────────────────────────
                $sheet->getPageSetup()
                    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
                    ->setHorizontalCentered(true)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);

                $sheet->getPageMargins()->setTop(0.5)->setRight(0.5)->setLeft(0.5)->setBottom(0.5);
                $sheet->getHeaderFooter()->setOddFooter('&L&D &T&C&"Arial"Page &P of &N&R');
            },
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function getPosisiLabel(?string $posisi): string
    {
        $labels = [
            'jasa'             => 'JASA',
            'supir'            => 'SUPIR',
            'keamanan'         => 'KEAMANAN',
            'cleaning_service' => 'CLEANING SERVICE',
            'operator'         => 'OPERATOR',
        ];
        return $labels[$posisi] ?? strtoupper((string) $posisi);
    }

    private function getBulanIndonesia(int|string $bulan): string
    {
        $names = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',    4 => 'April',
            5 => 'Mei',     6 => 'Juni',     7 => 'Juli',      8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        return $names[(int) $bulan] ?? '';
    }
}
