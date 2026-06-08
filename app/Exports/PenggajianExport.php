<?php

namespace App\Exports;

use App\Models\Penggajian;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Carbon\Carbon;

class PenggajianExport implements
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
        $this->bulan     = $bulan;
        $this->tahun     = $tahun;
        $this->posisi    = $posisi;
        $this->data      = $this->getData();
        $this->totalData = $this->data->count();
    }

    private function getData()
    {
        $query = Penggajian::with('karyawan')
            ->whereMonth('gajian_bulan', $this->bulan)
            ->whereYear('gajian_bulan', $this->tahun);

        if ($this->posisi) {
            $query->whereHas('karyawan', fn($q) => $q->where('posisi', $this->posisi));
        }

        return $query->orderBy('created_at')->get();
    }

    public function title(): string
    {
        $nama = $this->getMonthName($this->bulan);
        $title = "Penggajian {$nama} {$this->tahun}";
        if ($this->posisi) {
            $title .= ' - ' . ucfirst($this->posisi);
        }
        return substr($title, 0, 31);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet     = $event->sheet->getDelegate();
                $headerRow = 5;
                $startData = 6;
                $namaBulan = $this->getMonthName($this->bulan);

                // ── Info perusahaan ────────────────────────────────────────
                $sheet->setCellValue('A1', 'PT SURYA TAMADO MANDIRI');
                $sheet->mergeCells('A1:O1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 16, 'name' => 'Arial'],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(30);

                $sheet->setCellValue('A2', 'UNIT PT. SARI INCOFOOD CORPORATION ');
                $sheet->mergeCells('A2:O2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 14, 'name' => 'Arial'],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(2)->setRowHeight(25);

                $periodeText = "Periode: {$namaBulan} {$this->tahun}";
                if ($this->posisi) {
                    $periodeText .= ' | Posisi: ' . strtoupper(str_replace('_', ' ', $this->posisi));
                }
                $sheet->setCellValue('A3', $periodeText);
                $sheet->mergeCells('A3:O3');
                $sheet->getStyle('A3')->applyFromArray([
                    'font'      => ['size' => 12, 'name' => 'Arial'],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(3)->setRowHeight(20);

                $sheet->setCellValue('A4', 'Tanggal Cetak: ' . now()->format('d/m/Y H:i:s'));
                $sheet->mergeCells('A4:O4');
                $sheet->getStyle('A4')->applyFromArray([
                    'font'      => ['size' => 10, 'italic' => true, 'name' => 'Arial'],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(4)->setRowHeight(18);

                // ── Header tabel ───────────────────────────────────────────
                $headers = [
                    'No', 'No Rekening BRI', 'NIK', 'Nama', 'Bagian',
                    'Jumlah Penghasilan Kotor', 'BPJS Kesehatan 1%', 'BPJS JHT 2%', 'BPJS JP 1%',
                    'THR', 'QTY', 'SATUAN', 'LEMBUR & HARI BESAR',
                    'Upah Kotor Karyawan', 'Upah yang Diterima',
                ];
                $col = 'A';
                foreach ($headers as $header) {
                    $sheet->setCellValue("{$col}{$headerRow}", $header);
                    $col++;
                }

                $sheet->getStyle("A{$headerRow}:O{$headerRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9, 'name' => 'Arial'],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                ]);
                $sheet->getRowDimension($headerRow)->setRowHeight(30);

                // ── Lebar kolom ────────────────────────────────────────────
                $widths = [
                    'A' => 5,  'B' => 16, 'C' => 18, 'D' => 20, 'E' => 13,
                    'F' => 18, 'G' => 14, 'H' => 12, 'I' => 12, 'J' => 12,
                    'K' => 8,  'L' => 14, 'M' => 16, 'N' => 18, 'O' => 18,
                ];
                foreach ($widths as $c => $w) {
                    $sheet->getColumnDimension($c)->setWidth($w);
                }

                // ── Tulis data ─────────────────────────────────────────────
                if ($this->totalData > 0) {
                    $row = $startData;

                    foreach ($this->data as $index => $penggajian) {
                        $k = $penggajian->karyawan;

                        $sheet->setCellValue("A{$row}", $index + 1);
                        $sheet->setCellValueExplicit("B{$row}", optional($k)->no_rek_bri ?? '-', DataType::TYPE_STRING);
                        $sheet->setCellValueExplicit("C{$row}", optional($k)->nik ?? '-', DataType::TYPE_STRING);
                        $sheet->setCellValue("D{$row}", optional($k)->nama_lengkap ?? '-');
                        $sheet->setCellValue("E{$row}", $this->getPosisiLabel(optional($k)->posisi));
                        $sheet->setCellValue("F{$row}", $penggajian->jumlah_penghasilan_kotor ?? 0);
                        // Ambil BPJS langsung dari DB agar konsisten dengan hitungan controller
                        $sheet->setCellValue("G{$row}", $penggajian->bpjs_kesehatan ?? 0);
                        $sheet->setCellValue("H{$row}", $penggajian->bpjs_jht ?? 0);
                        $sheet->setCellValue("I{$row}", $penggajian->bpjs_jp ?? 0);
                        $sheet->setCellValue("J{$row}", $penggajian->uang_thr ?? 0);
                        $sheet->setCellValue("K{$row}", $penggajian->jumlah_hari_kerja ?? 0);
                        $sheet->setCellValue("L{$row}", $penggajian->gaji_harian ?? 0);
                        $sheet->setCellValue("M{$row}", $penggajian->jumlah_lembur ?? 0);
                        // Ambil upah kotor dan upah diterima langsung dari DB
                        $sheet->setCellValue("N{$row}", $penggajian->upah_kotor_karyawan ?? 0);
                        $sheet->setCellValue("O{$row}", $penggajian->upah_diterima ?? 0);

                        $row++;
                    }

                    $endData = $row - 1;

                    // Border & zebra striping
                    $sheet->getStyle("A{$startData}:O{$endData}")->applyFromArray([
                        'font'      => ['size' => 9, 'name' => 'Arial'],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    for ($r = $startData; $r <= $endData; $r++) {
                        if ($r % 2 === 0) {
                            $sheet->getStyle("A{$r}:O{$r}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setRGB('F2F2F2');
                        }
                    }

                    // Format angka
                    $currencyCols = ['F', 'G', 'H', 'I', 'J', 'L', 'M', 'N', 'O'];
                    foreach ($currencyCols as $c) {
                        $sheet->getStyle("{$c}{$startData}:{$c}{$endData}")->getNumberFormat()->setFormatCode('#,##0');
                        $sheet->getStyle("{$c}{$startData}:{$c}{$endData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                    $sheet->getStyle("K{$startData}:K{$endData}")->getNumberFormat()->setFormatCode('0.0');
                    $sheet->getStyle("K{$startData}:K{$endData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("A{$startData}:A{$endData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Baris TOTAL
                    $totalRow = $endData + 1;
                    $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                    $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
                    $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    foreach (['F', 'G', 'H', 'I', 'J', 'N', 'O'] as $c) {
                        $sheet->setCellValue("{$c}{$totalRow}", "=SUM({$c}{$startData}:{$c}{$endData})");
                        $sheet->getStyle("{$c}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                        $sheet->getStyle("{$c}{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    $sheet->getStyle("A{$totalRow}:O{$totalRow}")->applyFromArray([
                        'font'    => ['bold' => true, 'size' => 11],
                        'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                    ]);

                } else {
                    $sheet->setCellValue("A{$startData}", 'TIDAK ADA DATA');
                    $sheet->mergeCells("A{$startData}:O{$startData}");
                    $sheet->getStyle("A{$startData}")->applyFromArray([
                        'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FF0000']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFEB9C']],
                    ]);
                    $sheet->getRowDimension($startData)->setRowHeight(40);
                }

                // ── Page setup ─────────────────────────────────────────────
                $lastRow = $this->totalData > 0 ? ($startData + $this->totalData + 3) : $startData + 3;
                $sheet->getPageSetup()->setPrintArea("A1:O{$lastRow}");
                $sheet->getPageSetup()
                    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
                    ->setHorizontalCentered(true)
                    ->setFitToPage(false);

                $sheet->getPageSetup()->setScale(55);
                $sheet->getPageMargins()->setTop(0.3)->setRight(0.2)->setLeft(0.2)->setBottom(0.3);
                $sheet->getHeaderFooter()
                    ->setOddFooter('&L&D &T&C&"Arial"Page &P of &N&R');

                // ── Page breaks (setiap 40 data) ──────────────────────────
                if ($this->totalData > 40) {
                    $rowsPerPage = 40;
                    for ($i = $rowsPerPage; $i < $this->totalData; $i += $rowsPerPage) {
                        $breakRow = $startData + $i - 1;
                        $sheet->setBreak(
                            "A{$breakRow}",
                            \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_ROW
                        );
                    }
                }
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
        return $labels[$posisi] ?? ucfirst((string) $posisi);
    }

    private function getMonthName(int|string $bulan): string
    {
        $names = [
            1 => 'Januari',   2 => 'Februari', 3 => 'Maret',    4 => 'April',
            5 => 'Mei',       6 => 'Juni',     7 => 'Juli',      8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        return $names[(int) $bulan] ?? '';
    }
}
