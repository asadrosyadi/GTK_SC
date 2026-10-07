<?php

namespace Modules\Yolo\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use ZipArchive;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use App\Http\Controllers\RecommendationService;
use App\Services\DetectionService;
use App\Services\MultiTitikService;
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
use Illuminate\Support\Facades\Storage;
use Modules\Yolo\Models\HistoryYolo;

class YoloController extends Controller
{
    private function selection(Request $request, MultiTitikService $multiTitik): array
    {
        $titik = (int) $request->input('titik', 0);
        $titik = $titik === 0 || array_key_exists($titik, $multiTitik->titikList()) ? $titik : 0;

        return [$titik, max(1, min(30, (int) $request->input('days', 7)))];
    }

    public function index(Request $request): View
    {
        $multiTitik = app(MultiTitikService::class);
        [$titik, $days] = $this->selection($request, $multiTitik);
        $detection = app(DetectionService::class);
        [$rows, $last] = $detection->detections(auth()->user()->iot_id, $titik, $days);

        $summary = $detection->summary($rows);
        $distribution = $detection->distribution($rows);

        $perPage = 12;
        $totalPages = max(1, (int) ceil($rows->count() / $perPage));
        $page = max(1, min($totalPages, (int) $request->input('page', 1)));

        return view('yolo::index', [
            'user' => $request->user(),
            'titik' => $titik,
            'titikList' => $multiTitik->titikList(),
            'days' => $days,
            'last' => $last?->format('Y-m-d H:i'),
            'summary' => $summary,
            'distribution' => $distribution,
            'data' => $rows->forPage($page, $perPage)->values(),
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    public function exportDataYolo(Request $request)
    {
        $multiTitik = app(MultiTitikService::class);
        [$titik, $days] = $this->selection($request, $multiTitik);
        [$rows] = app(DetectionService::class)->detections($request->attributes->get('api_iot_id', auth()->user()->iot_id), $titik, $days);
        $rows = $rows->reverse()->values();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Deteksi');
        $sheet->fromArray(['Waktu', 'Petak', 'Penyakit / Hama', 'Skor Deteksi (%)', 'Status', 'Saran Penanganan', 'Foto'], null, 'A1');
        $r = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([$row['timestamp'], 'Petak ' . $row['titik'], $row['penyakit'], $row['probabilitas'],
                $row['healthy'] ? 'Sehat' : 'Perlu tindakan', $row['advice'] ?? '-', $row['image'] ?? '-'], null, "A{$r}", true);
            if ($row['image']) {
                $sheet->getCell("G{$r}")->getHyperlink()->setUrl($row['image']);
            }
            $sheet->getStyle("E{$r}")->getFont()->setBold(true)->getColor()->setRGB($row['healthy'] ? '2E7D32' : 'C62828');
            $r++;
        }
        $lastRow = max(2, $r - 1);
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '03C3EC']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A1:G{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9DEE3');
        $sheet->getStyle("A2:G{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        foreach (['A' => 17, 'B' => 9, 'C' => 30, 'D' => 15, 'E' => 15, 'F' => 60, 'G' => 40] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }
        $sheet->freezePane('A2');

        $chartSheet = $spreadsheet->createSheet()->setTitle('Grafik');
        $petakNos = $titik > 0 ? [$titik] : array_keys($multiTitik->titikList());
        $diseases = $rows->pluck('penyakit')->unique()->values();
        $chartSheet->fromArray(array_merge(['Penyakit / Hama'], array_map(fn ($no) => 'Petak ' . $no, $petakNos)), null, 'A1');
        foreach ($diseases as $i => $d) {
            $line = [$d];
            foreach ($petakNos as $no) {
                $line[] = $rows->where('penyakit', $d)->where('titik', $no)->count();
            }
            $chartSheet->fromArray($line, null, 'A' . ($i + 2), true);
        }
        $chartSheet->getStyle('A1:' . Coordinate::stringFromColumnIndex(count($petakNos) + 1) . '1')->getFont()->setBold(true);
        $chartSheet->getColumnDimension('A')->setWidth(32);
        if ($diseases->isNotEmpty()) {
            $n = $diseases->count();
            $labels = $values = [];
            $colors = ['008FFB', 'FF9F43', '71DD37'];
            foreach ($petakNos as $k => $no) {
                $c = Coordinate::stringFromColumnIndex($k + 2);
                $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Grafik'!\${$c}\$1", null, 1);
                $v = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Grafik'!\${$c}\$2:\${$c}\$" . ($n + 1), null, $n);
                $v->setFillColor($colors[$k % 3]);
                $values[] = $v;
            }
            $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Grafik'!\$A\$2:\$A\$" . ($n + 1), null, $n)];
            $ds = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, range(0, count($values) - 1),
                $labels, $categories, $values);
            $ds->setPlotDirection(DataSeries::DIRECTION_BAR);
            $chart = new Chart('distribusi', new Title('Jumlah Deteksi per Penyakit/Hama'), new Legend(Legend::POSITION_TOP, null, false),
                new PlotArea(null, [$ds]), true, DataSeries::EMPTY_AS_GAP, null, new Title('Jumlah deteksi'));
            $chart->setTopLeftPosition('E2');
            $chart->setBottomRightPosition('O22');
            $chartSheet->addChart($chart);
        }
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $writer->setIncludeCharts(true);
        $slug = $titik === 0 ? 'Gabungan' : 'Petak_' . $titik;
        $filename = Carbon::now()->format('Y-m-d_H-i-s') . "_Riwayat_Deteksi_CNN_{$slug}_{$days}hari.xlsx";

        return response()->stream(fn () => $writer->save('php://output'), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
