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

                $sheet->setCellValue('A2', 'LAPORAN PENGGAJIAN KARYAWAN');
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
                    'Jumlah Penghasilan Kotor', 'BPJS Kesehatan', 'BPJS JHT', 'BPJS JP',
                    'THR', 'Jumlah Hari Kerja', 'Satuan (Gaji Harian)', 'Lembur',
                    'Upah Kotor Karyawan', 'Upah yang Diterima',
                ];
                $col = 'A';
                foreach ($headers as $header) {
                    $sheet->setCellValue("{$col}{$headerRow}", $header);
                    $col++;
                }

                $sheet->getStyle("A{$headerRow}:O{$headerRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11, 'name' => 'Arial'],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                ]);
                $sheet->getRowDimension($headerRow)->setRowHeight(30);

                // ── Lebar kolom ────────────────────────────────────────────
                $widths = [
                    'A' => 6,  'B' => 18, 'C' => 20, 'D' => 25, 'E' => 15,
                    'F' => 22, 'G' => 18, 'H' => 15, 'I' => 15, 'J' => 15,
                    'K' => 18, 'L' => 15, 'M' => 20, 'N' => 22, 'O' => 22,
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
                        // G: BPJS Kesehatan = 1% penghasilan kotor, 0 jika hari kerja < 7
                        $sheet->setCellValue("G{$row}", "=IF(K{$row}<7,0,F{$row}*0.01)");
                        // H: BPJS JHT = 2% penghasilan kotor, 0 jika hari kerja < 7
                        $sheet->setCellValue("H{$row}", "=IF(K{$row}<7,0,F{$row}*0.02)");
                        // I: BPJS JP = 1% penghasilan kotor, 0 jika hari kerja < 7
                        $sheet->setCellValue("I{$row}", "=IF(K{$row}<7,0,F{$row}*0.01)");
                        $sheet->setCellValue("J{$row}", $penggajian->uang_thr ?? 0);
                        $sheet->setCellValue("K{$row}", $penggajian->jumlah_hari_kerja ?? 0);
                        $sheet->setCellValue("L{$row}", $penggajian->gaji_harian ?? 0);
                        $sheet->setCellValue("M{$row}", $penggajian->jumlah_lembur ?? 0);
                        // N: Upah Kotor = (Gaji Harian × Hari Kerja) + Lembur + THR
                        $sheet->setCellValue("N{$row}", "=(L{$row}*K{$row})+M{$row}+J{$row}");
                        // O: Upah Diterima = Upah Kotor - Total BPJS (G+H+I)
                        $sheet->setCellValue("O{$row}", "=N{$row}-(G{$row}+H{$row}+I{$row})");

                        $row++;
                    }

                    $endData = $row - 1;

                    // Border & zebra striping
                    $sheet->getStyle("A{$startData}:O{$endData}")->applyFromArray([
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
                $sheet->getPageSetup()
                    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
                    ->setHorizontalCentered(true)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);

                $sheet->getPageMargins()->setTop(0.5)->setRight(0.5)->setLeft(0.5)->setBottom(0.5);
                $sheet->getHeaderFooter()
                    ->setOddHeader('&C&"Arial,Bold"PT SURYA TAMADO MANDIRI - Laporan Penggajian');
                $sheet->getHeaderFooter()
                    ->setOddFooter('&L&D &T&C&"Arial"Page &P of &N&R');
            },
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function getPosisiLabel(?string $posisi): string
    {
        $labels = [
            'jasa'             => 'Jasa',
            'supir'            => 'Supir',
            'keamanan'         => 'Keamanan',
            'cleaning_service' => 'Cleaning Service',
            'operator'         => 'Operator',
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
