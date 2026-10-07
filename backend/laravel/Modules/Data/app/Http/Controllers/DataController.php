<?php

namespace Modules\Data\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\History\Models\Historyiot;
use Illuminate\View\View;
use Modules\Dashboard\Models\SensorIot;
use Modules\Dashboard\Models\SensorKamera;
use App\Http\Controllers\RecommendationService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Modules\Yolo\Models\HistoryYolo;
use Illuminate\Support\Facades\Http;

class DataController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::all();

        return view('data::index', compact('users'))->with([
            'user' => $request->user(),
        ]);
    }

    public function monitoring(Request $request, $iot_id): View
    {
        $iot_idUser = User::where('iot_id', $iot_id)->first();

        abort_if(!$iot_idUser, 404, 'User not found');

        $days = in_array((int) $request->input('days', 3), [1, 3, 7, 14], true) ? (int) $request->input('days', 3) : 3;

        $forecastData_first = null;
        $forecastNow = $this->getForecastData($iot_idUser, 1);
        if (isset($forecastNow['forecast']) && is_array($forecastNow['forecast'])) {
            $forecastData_first = collect($forecastNow['forecast'])->first();
        }

        $sensorForecast = app(\App\Services\MultiTitikService::class)
            ->sensorForecast($iot_idUser->iot_id, (int) config('monitoring.weather_titik', 1), $days);

        $sensorData = SensorIot::where('iot_id', $iot_id)
            ->where('titik', config('monitoring.weather_titik', 1))
            ->orderBy('id', 'desc')
            ->take(1)
            ->get();
        $sensorData2 = SensorKamera::where('iot_id', $iot_id)
            ->where('titik', config('monitoring.weather_titik', 1))
            ->orderBy('id', 'desc')
            ->take(1)
            ->get();

        $sensorData->first()?->setAttribute('rainfall', app(\App\Services\MultiTitikService::class)->rainfall24h($iot_id));

        $recommendationService = new RecommendationService();
        $recommendations = $recommendationService->getRecommendations($sensorData, $sensorData2);

        return view('data::monitoring', compact('iot_idUser'))->with([
            'user' => $request->user(),
            'recommendations' => $recommendations,
            'iot_id' => $iot_idUser,
            'days' => $days,
            'forecastData_first' => $forecastData_first,
            'sensorForecast' => $sensorForecast,
        ]);
    }

    public function Historyiot(Request $request, $iot_id): View
    {
        $iot_idUser = User::where('iot_id', $iot_id)->first();
        abort_if(!$iot_idUser, 404, 'User not found');

        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $skip = ($page - 1) * $limit;

        $sensorData = Historyiot::where('iot_id', $iot_id)
            ->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        $data = $sensorData->map(function ($item) {
            return [
                'timestamp' => isset($item->created_at) ? $item->created_at->format('Y-m-d H:i:s') : '-',
                'titik' => data_get($item, 'titik', '-'),
                'temperature' => $item->temperature ?? '-',
                'humidity' => $item->humidity ?? '-',
                'windspeed' => $item->windspeed ?? '-',
                'rainfall' => $item->rainfall ?? '-',
                'light_intensity' => $item->light_intensity ?? '-',
                'ph' => $item->ph ?? '-',
                'soil_moisture' => $item->soil_moisture ?? '-',
                'ec' => $item->ec ?? '-',
                'tds' => $item->tds ?? '-',
                'soil_temp' => $item->soil_temp ?? '-',
                'pressure' => $item->pressure ?? '-',
                'feromon' => $item->feromon ?? '-',
                'battery_level' => $item->battery_level ?? '-',
                'signal_strength' => $item->signal_strength ?? '-',
                'Nitrogen_Level' => $item->Nitrogen_Level ?? '-',
                'Phosphorus_Level' => $item->Phosphorus_Level ?? '-',
                'Potassium_Level' => $item->Potassium_Level ?? '-',
            ];
        });

        $totalRecords = Historyiot::where('iot_id', $iot_id)->count();
        $totalPages = ceil($totalRecords / $limit);

        return view('data::historyiot', compact('iot_idUser'))->with([
            'user' => $request->user(),
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'data' => $data,
            'sensorData' => $sensorData,
        ]);
    }

    public function historyyolo(Request $request, $iot_id): View
    {
        $iot_idUser = User::where('iot_id', $iot_id)->first();
        abort_if(!$iot_idUser, 404, 'User not found');

        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $skip = ($page - 1) * $limit;

        $sensorData = HistoryYolo::where('iot_id', $iot_idUser->iot_id)
            ->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        $data = $sensorData->map(function ($item) {
            return [
                'timestamp' => isset($item->created_at) ? $item->created_at->format('Y-m-d H:i:s') : '-',
                'titik' => data_get($item, 'titik', '-'),
                'penyakit' => $item->penyakit ?? '-',
                'probabilitas' => $item->probabilitas ?? '-',
                'image' => $item->image ?? '-',
            ];
        });

        $totalRecords = HistoryYolo::where('iot_id', $iot_idUser->iot_id)->count();
        $totalPages = ceil($totalRecords / $limit);

        return view('data::historyyolo', compact('iot_idUser'))->with([
            'user' => $request->user(),
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'data' => $data,
            'sensorData' => $sensorData,
        ]);
    }

    private function getForecastData($iot_idUser, $days)
    {
        try {
            $response = Http::timeout(30)->get(config('services.rnn.url') . "{$days}");
        } catch (\Throwable $e) {
            report($e);
            $response = null;
        }

        $data = ($response && $response->successful()) ? $response->json() : null;

        if (isset($data['iot_id'], $data['forecast']) && $data['iot_id'] === $iot_idUser->iot_id) {
            return $data;
        }

        return [
            "iot_id" => $iot_idUser->iot_id,
            "titik" => 1,
            "days_forecasted" => $days,
            "available" => false,
            "forecast" => [],
            "forecast_generated" => now(),
        ];
    }

    public function getData(Request $request)
    {
        $iot_id = $request->input('iot_id');

        if (!$iot_id) {
            $iot_id = auth()->user()->iot_id;
        }

        $sensorData = SensorIot::where('iot_id', $iot_id)
            ->where('titik', $request->input('titik', config('monitoring.weather_titik', 1)))
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        $data = $sensorData->map(function ($item) {
            if (is_array($item)) {
                return $item;
            }

            return [
                'temperature' => $item->temperature,
                'humidity' => $item->humidity,
                'windspeed' => $item->windspeed,
                'rainfall' => $item->rainfall,
                'light_intensity' => $item->light_intensity,
                'ph' => $item->ph,
                'soil_moisture' => $item->soil_moisture,
                'ec' => $item->ec,
                'tds' => $item->tds,
                'soil_temp' => $item->soil_temp,
                'pressure' => $item->pressure,
                'feromon' => $item->feromon,
                'battery_level' => $item->battery_level,
                'signal_strength' => $item->signal_strength,
                'nitrogen' => $item->Nitrogen_Level,
                'fosfor' => $item->Phosphorus_Level,
                'potassium' => $item->Potassium_Level,
                'created_at' => $item->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $data,
            'total_records' => $sensorData->count(),
            'rainfall_24h' => app(\App\Services\MultiTitikService::class)
                ->rainfall24h($iot_id, (int) $request->input('titik', config('monitoring.weather_titik', 1))),
        ]);
    }

    public function getKamera(Request $request)
    {

        $iot_id = $request->input('iot_id');

        if (!$iot_id) {
            $iot_id = auth()->user()->iot_id;
        }

        $kameraData = SensorKamera::where('iot_id', $iot_id)
            ->where('titik', $request->input('titik', config('monitoring.weather_titik', 1)))
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        $data = $kameraData->map(function ($item) {
            return [
                'image_url' => $item->image,
                'penyakit' => $item->penyakit,
                'probabilitas' => $item->probabilitas,
                'created_at' => $item->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $data,
            'total_records' => $kameraData->count(),
        ]);
    }

    public function exportDataUser(Request $request, $iot_id)
    {
        $iot_idUser = User::where('iot_id', $iot_id)->first();

        if (!$iot_idUser) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $data = Historyiot::where('iot_id', $iot_idUser->iot_id)->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Timestamp')
            ->setCellValue('B1', 'Temperature (°C)')
            ->setCellValue('C1', 'Humidity (%)')
            ->setCellValue('D1', 'Wind Speed (m/s)')
            ->setCellValue('E1', 'Rainfall (mm/15 min)')
            ->setCellValue('F1', 'Light Intensity (lux)')
            ->setCellValue('G1', 'pH Level')
            ->setCellValue('H1', 'Soil Moisture (%)')
            ->setCellValue('I1', 'EC (µS/cm)')
            ->setCellValue('J1', 'TDS (ppm)')
            ->setCellValue('K1', 'Soil Temp (°C)')
            ->setCellValue('L1', 'Pressure (Pa)')
            ->setCellValue('M1', 'Feromon')
            ->setCellValue('N1', 'Nitrogen Level (mg/kg)')
            ->setCellValue('O1', 'Phosphorus Level (mg/kg)')
            ->setCellValue('P1', 'Potassium Level (mg/kg)')
            ->setCellValue('Q1', 'Titik');

        $sheet->getStyle('A1:P1')->getFont()->setBold(true);
        $sheet->getStyle('A1:P1')->getFont()->setSize(12);
        $sheet->getStyle('A1:P1')->getFont()->setName('Calibri');
        $sheet->getStyle('A1:P1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:P1')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1:P1')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        $row = 2;
        foreach ($data as $item) {
            $sheet->setCellValue('A' . $row, $item->created_at->format('Y-m-d H:i:s'))
                ->setCellValue('B' . $row, $item->temperature)
                ->setCellValue('C' . $row, $item->humidity)
                ->setCellValue('D' . $row, $item->windspeed)
                ->setCellValue('E' . $row, $item->rainfall)
                ->setCellValue('F' . $row, $item->light_intensity)
                ->setCellValue('G' . $row, $item->ph)
                ->setCellValue('H' . $row, $item->soil_moisture)
                ->setCellValue('I' . $row, $item->ec)
                ->setCellValue('J' . $row, $item->tds)
                ->setCellValue('K' . $row, $item->soil_temp)
                ->setCellValue('L' . $row, $item->pressure)
                ->setCellValue('M' . $row, $item->feromon)
                ->setCellValue('N' . $row, $item->Nitrogen_Level)
                ->setCellValue('O' . $row, $item->Phosphorus_Level)
                ->setCellValue('P' . $row, $item->Potassium_Level)
                ->setCellValue('Q' . $row, $item->titik);

            $row++;
        }

        foreach (range('A', 'P') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $sheet->getStyle('A2:P' . $row)->getFont()->setSize(11);
        $sheet->getStyle('A2:P' . $row)->getFont()->setName('Calibri');

        $writer = new Xlsx($spreadsheet);
        $currentDateTime = Carbon::now()->format('Y-m-d_H-i-s');
        $userName = $iot_idUser->name;

        $filename = "{$currentDateTime}_Riwayat_Sensor_{$userName}.xlsx";

        return response()->stream(
            function () use ($writer) {
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]
        );
    }

    public function exportDataYoloUser(Request $request, $iot_id)
    {
        $iot_idUser = User::where('iot_id', $iot_id)->first();

        if (!$iot_idUser) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $data = HistoryYolo::where('iot_id', $iot_idUser->iot_id)->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Timestamp')
            ->setCellValue('B1', 'Disease')
            ->setCellValue('C1', 'Probability (%)')
            ->setCellValue('D1', 'Titik');

        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getStyle('A1:C1')->getFont()->setSize(12);
        $sheet->getStyle('A1:C1')->getFont()->setName('Calibri');
        $sheet->getStyle('A1:C1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:C1')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1:C1')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        $row = 2;
        foreach ($data as $item) {
            $sheet->setCellValue("A$row", $item->created_at->format('Y-m-d H:i:s'))
                ->setCellValue("B$row", $item->penyakit)
                ->setCellValue("C$row", $item->probabilitas)
                ->setCellValue("D$row", $item->titik);
            $row++;
        }

        foreach (range('A', 'C') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $sheet->getStyle('A2:C' . $row)->getFont()->setSize(11);
        $sheet->getStyle('A2:C' . $row)->getFont()->setName('Calibri');
        $sheet->getStyle('A2:C' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2:C' . $row)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        $writer = new Xlsx($spreadsheet);

        $currentDateTime = Carbon::now()->format('Y-m-d_H-i-s');
        $userName = $iot_idUser->name;
        $excelFilePath = storage_path("app/public/{$currentDateTime}_Riwayat_Yolo_{$userName}.xlsx");

        $writer->save($excelFilePath);
        return response()->download($excelFilePath)->deleteFileAfterSend(true);
    }
}
