<?php

namespace Modules\History\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\MultiTitikService;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class HistoryController extends Controller
{
    public function index(Request $request): View
    {
        $iot_id = auth()->user()->iot_id;
        $multiTitik = app(MultiTitikService::class);

        [$titik, $days] = $this->selection($request, $multiTitik);

        $history = $this->history($multiTitik, $iot_id, $titik, $days);

        return view('history::index', [
            'user' => $request->user(),
            'titik' => $titik,
            'titikList' => $multiTitik->titikList(),
            'days' => $days,
            'history' => $history,
        ]);
    }

    private function selection(Request $request, MultiTitikService $multiTitik): array
    {
        $titik = (int) $request->input('titik', 0);
        $titik = $titik === 0 || array_key_exists($titik, $multiTitik->titikList()) ? $titik : 0;

        return [$titik, max(1, min(30, (int) $request->input('days', 1)))];
    }

    private function history(MultiTitikService $multiTitik, string $iotId, int $titik, int $days): ?array
    {
        return $titik === 0
            ? $multiTitik->combineForecast(collect($multiTitik->titikList())
                ->mapWithKeys(fn ($info, $no) => [$no => $multiTitik->measuredHistory($iotId, $no, $days)])->all())
            : $multiTitik->measuredHistory($iotId, $titik, $days);
    }

    public function exportData(Request $request)
    {
        set_time_limit(300);
        $multiTitik = app(MultiTitikService::class);
        [$titik, $days] = $this->selection($request, $multiTitik);
        $history = $this->history($multiTitik, $request->attributes->get('api_iot_id', auth()->user()->iot_id), $titik, $days);
        $rows = $history['measured'] ?? [];
        $vars = MultiTitikService::SENSOR_VARIABLES;
        $petakLabel = $titik === 0 ? 'Gabungan Petak 1 & 2' : $multiTitik->label($titik);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Riwayat');

        $headers = ['Waktu'];
        foreach ($vars as $v) {
            $headers[] = $v['label'] . ($v['unit'] ? " ({$v['unit']})" : '');
        }
        array_push($headers, 'P. Sehat (%)', 'P. Stres Sedang (%)', 'P. Stres Tinggi (%)', 'P. Kondisi (Kelas LSTM)',
            'Status', 'Penyebab', 'Kesimpulan');
        $sheet->fromArray($headers, null, 'A1');
        $col = fn (int $i) => Coordinate::stringFromColumnIndex($i);
        $lastCol = $col(count($headers));
        $pSehatCol = count($vars) + 2;

        $kelas = ['Healthy' => 'Sehat', 'Moderate Stress' => 'Stres Sedang', 'High Stress' => 'Stres Tinggi'];
        $warna = ['success' => '2E7D32', 'warning' => 'B45F00', 'danger' => 'C62828', 'info' => '0277BD'];
        $r = 2;
        foreach ($rows as $row) {
            $line = [$row['date']];
            foreach ($vars as $v) {
                $line[] = $row[$v['key']] ?? null;
            }
            $h = $row['health'] ?? null;
            array_push($line, $h['p_healthy'] ?? null, $h['p_moderate'] ?? null, $h['p_high'] ?? null,
                $h ? ($kelas[$h['label']] ?? $h['label']) : null,
                collect($row['status'] ?? [])->pluck('label')->implode("\n") ?: 'Normal',
                collect($row['status'] ?? [])->pluck('reason')->implode("\n") ?: '-',
                $row['conclusion']['label'] ?? '-');
            $sheet->fromArray($line, null, "A{$r}", true);
            $sheet->getStyle($lastCol . $r)->getFont()->setBold(true)
                ->getColor()->setRGB($warna[$row['conclusion']['color'] ?? ''] ?? '333333');
            $r++;
        }
        $lastRow = max(2, $r - 1);

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '03C3EC']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getStyle("A1:{$lastCol}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setRGB('D9DEE3');
        $sheet->getStyle("A2:{$lastCol}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        foreach ([[0, '2E7D32'], [1, 'B45F00'], [2, 'C62828']] as [$off, $rgb]) {
            $sheet->getStyle($col($pSehatCol + $off) . '2:' . $col($pSehatCol + $off) . $lastRow)
                ->getFont()->setBold(true)->getColor()->setRGB($rgb);
        }
        for ($i = 1; $i <= count($headers); $i++) {
            $sheet->getColumnDimension($col($i))->setWidth($i === 1 ? 17 : ($i > count($headers) - 3 ? 34 : 14));
        }
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->freezePane('B2');

        $chartSheet = $spreadsheet->createSheet()->setTitle('Grafik');
        $chartSheet->setCellValue('A1', "Grafik Riwayat · {$petakLabel} · {$days} hari terakhir (s.d. " . ($history['last_observation'] ?? '-') . ')');
        $chartSheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $n = max(1, $lastRow - 1);
        $ref = fn (int $c, bool $header = false) => "'Riwayat'!\$" . $col($c) . ($header ? '$1' : '$2:$' . $col($c) . '$' . $lastRow);
        $makeChart = function (string $name, string $title, array $series, string $yTitle, string $from, string $to) use ($chartSheet, $ref, $n) {
            $labels = $values = [];
            foreach ($series as [$c, $rgb]) {
                $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $ref($c, true), null, 1);
                $v = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $ref($c), null, $n);
                $v->setPointMarker('none');
                $v->setLineColorProperties($rgb);
                $values[] = $v;
            }
            $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $ref(1), null, $n)];
            $ds = new DataSeries(DataSeries::TYPE_LINECHART, DataSeries::GROUPING_STANDARD, range(0, count($values) - 1),
                $labels, $categories, $values);
            $chart = new Chart($name, new Title($title), new Legend(Legend::POSITION_TOP, null, false),
                new PlotArea(null, [$ds]), true, DataSeries::EMPTY_AS_GAP, null, new Title($yTitle));
            $chart->setTopLeftPosition($from);
            $chart->setBottomRightPosition($to);
            $chartSheet->addChart($chart);
        };

        $makeChart('lstm', 'Kesehatan Tanaman LSTM (%)',
            [[$pSehatCol, '71DD37'], [$pSehatCol + 1, 'FFAB00'], [$pSehatCol + 2, 'FF3E1D']], 'Peluang kelas (%)', 'A3', 'P22');
        foreach ($vars as $i => $v) {
            $top = 24 + intdiv($i, 2) * 18;
            [$left, $right] = $i % 2 === 0 ? ['A', 'H'] : ['I', 'P'];
            $makeChart("s{$i}", $v['label'] . ($v['unit'] ? " ({$v['unit']})" : ''), [[$i + 2, '008FFB']],
                $v['unit'] ?: $v['label'], "{$left}{$top}", $right . ($top + 16));
        }
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $writer->setIncludeCharts(true);
        $slug = $titik === 0 ? 'Gabungan' : 'Petak_' . $titik;
        $filename = Carbon::now()->format('Y-m-d_H-i-s') . "_Riwayat_Sensor_{$slug}_{$days}hari.xlsx";

        return response()->stream(
            function () use ($writer) {
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }
}
