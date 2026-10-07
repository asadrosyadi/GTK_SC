@extends('layouts.dashboard')

@section('content')
<link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
@php $agroCss = base_path('../html/assets/css/dashboard-agro.css'); @endphp
<link rel="stylesheet" href="{{ asset('assets/css/dashboard-agro.css') }}?v={{ is_file($agroCss) ? filemtime($agroCss) : '1' }}">
@php
    $soilRows = [
        'ph' => ['icon' => 'bx bx-test-tube', 'label' => __('label.phLevel'), 'unit' => ''],
        'soil_moisture' => ['icon' => 'bx bx-line-chart', 'label' => __('label.soilMoisture'), 'unit' => '%'],
        'ec' => ['icon' => 'bx bx-atom', 'label' => __('label.ec_1'), 'unit' => 'µS/cm'],
        'tds' => ['icon' => 'bx bx-droplet', 'label' => __('label.tds'), 'unit' => 'ppm'],
        'soil_temp' => ['icon' => 'bx bx-layer', 'label' => __('label.soilTemp'), 'unit' => '°C'],
        'Nitrogen_Level' => ['symbol' => 'N', 'label' => \Illuminate\Support\Str::before(__('label.nitrogen1'), ' ('), 'unit' => 'mg/kg'],
        'Phosphorus_Level' => ['symbol' => 'P', 'label' => \Illuminate\Support\Str::before(__('label.phosphorus1'), ' ('), 'unit' => 'mg/kg'],
        'Potassium_Level' => ['symbol' => 'K', 'label' => \Illuminate\Support\Str::before(__('label.potasium1'), ' ('), 'unit' => 'mg/kg'],
    ];
@endphp
@php
    $weatherTiles = [
        'vpd' => ['icon' => 'bx bx-water', 'label' => 'VPD', 'tone' => 'tone-teal'],
        'humidity' => ['icon' => 'bx bx-droplet', 'label' => __('label.humidity_1'), 'tone' => 'tone-blue'],
        'temperature' => ['icon' => 'bx bxs-thermometer', 'label' => __('label.temperature'), 'tone' => 'tone-orange'],
        'windspeed' => ['icon' => 'bx bx-wind', 'label' => __('label.windSpeed'), 'tone' => 'tone-sky'],
        'rainfall' => ['icon' => 'bx bx-cloud-rain', 'label' => __('label.rainfall_1') . ' 24 jam', 'tone' => 'tone-indigo'],
        'light_intensity' => ['icon' => 'bx bx-sun', 'label' => __('label.lightIntensity'), 'tone' => 'tone-amber'],
        'pressure' => ['icon' => 'bx bx-tachometer', 'label' => __('label.pressure_1'), 'tone' => 'tone-slate'],
    ];
@endphp
<div class="row g-4 mb-4 agro-dash">
    <div class="col-12">
        <div class="card agro-panel h-100">
            <div class="agro-panel-head">
                <div class="d-flex align-items-center gap-2">
                    <span class="agro-live"><span></span></span>
                    <h5 class="mb-0">{{ __('label.realTimeEnvironmentalMonitoring') }}</h5>
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-end">
                    <span class="agro-chip chip-green"><i class="bx bx-map-pin"></i> {{ count($titikList) }} {{ __('label.titik') }}</span>
                </div>
            </div>

            <div class="card-body pt-3">
                <div class="agro-section-title">
                    <h6 class="mb-0"><i class="bx bx-cloud"></i> {{ __('label.weatherConditions') }}</h6>
                    <small class="text-muted agro-updated" id="weather-updated"></small>
                </div>
                <div class="agro-kpi-grid mb-4">
                    @foreach ($weatherTiles as $id => $tile)
                        <div class="agro-kpi {{ $tile['tone'] }} {{ $loop->first ? 'agro-kpi--hero' : '' }}">
                            @if ($loop->first)<i class="{{ $tile['icon'] }} agro-kpi-watermark"></i>@endif
                            <span class="agro-kpi-ico"><i class="{{ $tile['icon'] }}"></i></span>
                            <div class="agro-kpi-text">
                                <span class="agro-kpi-label">{{ $tile['label'] }}</span>
                                <div class="agro-kpi-value" id="{{ $id }}"><span class="agro-skeleton"></span></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="agro-section-title">
                            <h6 class="mb-0"><i class="bx bx-leaf"></i> {{ __('label.soilConditions') }}</h6>
                        </div>
                        <div class="table-responsive agro-soil">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('label.parameter') }}</th>
                                        @foreach ($titikList as $no => $info)
                                            <th class="text-end"><i class="bx bx-map-pin"></i> {{ $info['label'] }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($soilRows as $field => $row)
                                        <tr>
                                            <td class="agro-soil-param">
                                                <span class="soil-ico">@isset($row['symbol']){{ $row['symbol'] }}@else<i class="{{ $row['icon'] }}"></i>@endisset</span>
                                                <span class="soil-name">{{ $row['label'] }}</span>@if ($row['unit'])<span class="soil-unit">{{ $row['unit'] }}</span>@endif
                                            </td>
                                            @foreach ($titikList as $no => $info)
                                                <td class="text-end" id="soil-{{ $field }}-{{ $no }}"><span class="agro-skeleton"></span></td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="agro-section-title">
                            <h6 class="mb-0"><i class="bx bx-chip"></i> {{ __('label.systemStatus') }}</h6>
                        </div>
                        <div class="d-grid gap-3">
                            @foreach ($titikList as $no => $info)
                                <div class="agro-status" id="node-{{ $no }}">
                                    <div class="agro-status-head">
                                        <span class="agro-status-name"><span class="agro-head-ico"><i class="bx bx-broadcast"></i></span>{{ $info['label'] }}</span>
                                        <span class="agro-chip chip-muted" id="online-{{ $no }}">-</span>
                                    </div>
                                    <div class="agro-status-grid">
                                        <div class="agro-status-item">
                                            <span class="agro-status-label"><i class="bx bx-battery" id="battery-ico-{{ $no }}"></i> {{ __('label.batteryLevel') }}</span>
                                            <span class="agro-status-val"><span id="battery-{{ $no }}">-</span></span>
                                            <div class="progress agro-progress"><div id="battery-bar-{{ $no }}" class="progress-bar bg-success" style="width: 0%"></div></div>
                                        </div>
                                        <div class="agro-status-item">
                                            <span class="agro-status-label"><i class="bx bx-wifi"></i> {{ __('label.signalStrength') }}</span>
                                            <span class="agro-status-val">
                                                <span id="signal-{{ $no }}">-</span>
                                                <span class="agro-signal" id="signal-bars-{{ $no }}"><i></i><i></i><i></i><i></i></span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="agro-status-foot"><i class="bx bx-time-five"></i> {{ __('label.lastUpdate') }}: <span id="last-update-{{ $no }}">-</span></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card agro-panel h-100">
            <div class="agro-panel-head">
                <div class="d-flex align-items-center gap-2">
                    <span class="agro-head-ico"><i class="bx bx-camera"></i></span>
                    <h5 class="mb-0">{{ __('label.cnnPerPlot') }}</h5>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach ($titikList as $no => $info)
                        <div class="col-md-6">
                            <div class="agro-cam" id="cam-card-{{ $no }}">
                                <a class="agro-cam-media" id="cam-link-{{ $no }}" target="_blank" rel="noopener" title="{{ __('label.clickToEnlarge') }}">
                                    <div id="cam-spinner-{{ $no }}" class="spinner-border spinner-border-sm text-success" role="status"><span class="visually-hidden">Loading...</span></div>
                                    <img id="cam-image-{{ $no }}" src="" alt="{{ $info['label'] }}" style="display: none;">
                                    <span id="cam-empty-{{ $no }}" class="agro-cam-empty" style="display: none;"><i class="bx bx-image"></i>{{ __('label.noData') }}</span>
                                    <span class="agro-cam-zoom"><i class="bx bx-expand-alt"></i></span>
                                </a>
                                <div class="agro-cam-body">
                                    <div class="agro-cam-top">
                                        <span class="agro-cam-plot"><i class="bx bx-map-pin"></i> {{ $info['label'] }}</span>
                                        <span class="agro-chip chip-muted" id="cam-state-{{ $no }}">-</span>
                                    </div>
                                    <div class="agro-disease" id="cam-penyakit-{{ $no }}">-</div>
                                    <div class="agro-meter-row" id="cam-prob-{{ $no }}">
                                        <span>{{ __('label.detectionConfidence') }}</span><b>-</b>
                                    </div>
                                    <div class="progress agro-progress mb-2"><div id="cam-prob-bar-{{ $no }}" class="progress-bar bg-success" style="width: 0%"></div></div>
                                    <div class="agro-meter-row">
                                        <span title="Peluang kelas Sehat (P. Sehat) dan kelas kondisi model LSTM">{{ __('label.plant_health_score') }} (LSTM)</span><b id="health-{{ $no }}">-</b>
                                    </div>
                                    <div class="progress agro-progress"><div id="health-bar-{{ $no }}" class="progress-bar bg-success" style="width: 0%"></div></div>
                                    <div class="agro-hybrid mt-2" id="hybrid-{{ $no }}" style="display:none"></div>
                                    <div class="agro-cam-time" id="cam-time-{{ $no }}" style="display:none"></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row justify-content-center">
    <div class="col-lg-12 order-5 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bx bx-line-chart me-2"></i> {{ __('label.trendprediction') }} &middot; {{ $titik === 0 ? 'Gabungan Petak 1 & 2' : ($titikList[$titik]['label'] ?? '') }}</h5>
            </div>
            <div class="card-body">
                @include('dashboard::partials.sensor-forecast-chart', ['sensorForecast' => $sensorForecast, 'chartId' => 'sensorTrendChart'])
            </div>
        </div>
    </div>

    <div class="col-lg-6 order-3 order-lg-2 mb-4 mt-4">
        <div class="card h-100">
            <div class="card-header bg-warning text-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-white"><i class="bx bx-bell me-2"></i> {{ __('label.rekomendasi') }}</h5>
                <span class="badge bg-white text-warning" id="rec-model">...</span>
            </div>
            <div class="card-body recommendations-scroll" id="recommendations">
            </div>
        </div>
    </div>

    <div class="col-lg-6 order-3 order-lg-2 mb-4 mt-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-white"><i class="bx bx-list-check me-2"></i> {{ __('label.actuationPerPlot') }}</h5>
            </div>
            <div class="card-body recommendations-scroll" id="actuation">
                <p class="text-muted small mb-0">Loading...</p>
            </div>
        </div>
    </div>


    <div class="col-12 order-3 order-lg-2 mb-4">
        <div class="card ai-card">
            <div class="card-header ai-head text-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-white"><i class="bx bx-bot me-2"></i> Asisten AI</h5>
                <span class="badge bg-white text-primary">{{ app(\App\Services\AiAssistantService::class)->providerName() }}</span>
            </div>
            <div class="card-body pt-3">
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0"><i class="bx bx-clipboard me-1 text-primary"></i> Kesimpulan Keadaan Sawah</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="aiSummaryRefresh" title="Muat ulang kesimpulan"><i class="bx bx-refresh"></i></button>
                        </div>
                        <div class="ai-summary" id="aiSummary">
                            <div class="d-flex align-items-center gap-2 text-muted small"><span class="spinner-border spinner-border-sm text-primary"></span> AI sedang merangkum data sawah...</div>
                        </div>
                        <small class="text-muted d-block mt-2" id="aiSummaryTime"></small>
                    </div>
                    <div class="col-lg-7">
                        <h6 class="mb-2"><i class="bx bx-chat me-1 text-primary"></i> Tanya Asisten</h6>
                        <div class="ai-chat" id="aiChat">
                            <div class="ai-msg ai-bot">Halo! Saya bisa menjawab pertanyaan tentang sawah Anda berdasarkan data sensor, kamera, dan rekomendasi saat ini. Silakan bertanya.</div>
                        </div>
                        <form id="aiForm" class="d-flex gap-2 mt-2">
                            <input type="text" id="aiInput" class="form-control" maxlength="1000" placeholder="Tulis pertanyaan, mis. bolehkah saya menyemprot obat besok?" autocomplete="off">
                            <button type="submit" class="btn btn-primary" id="aiSend"><i class="bx bx-send"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-12 mb-4 order-4">
        <div class="card sensor-dashboard">
            <div class="card-header bg-info text-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-white"><i class="fa-solid fa-wand-magic-sparkles"></i>
                    {{ __('label.prediction') }} &middot; {{ $titik === 0 ? 'Gabungan Petak 1 & 2' : ($titikList[$titik]['label'] ?? '') }}</h5>
                <div class="d-flex flex-wrap gap-2">
                    <div class="btn-group badge bg-white btn-group-sm" title="{{ __('label.selectPlot') }}">
                        <a href="{{ url('/dashboard?titik=0&days=' . $days) }}" class="btn btn-outline-info @if($titik === 0) active @endif">Gabungan</a>
                        @foreach ($titikList as $no => $info)
                            <a href="{{ url('/dashboard?titik=' . $no . '&days=' . $days) }}" class="btn btn-outline-info @if($titik == $no) active @endif">{{ $info['label'] }}</a>
                        @endforeach
                    </div>
                    <div class="btn-group badge bg-white btn-group-sm">
                        @foreach ([1, 3, 7, 14] as $d)
                            <a href="{{ url('/dashboard?titik=' . $titik . '&days=' . $d) }}" class="btn btn-outline-info @if($days == $d) active @endif">{{ $d }}d</a>
                        @endforeach
                    </div>
                    <form method="GET" action="{{ url('/dashboard') }}" class="agro-days-form @if(! in_array($days, [1, 3, 7, 14], true)) is-active @endif" title="{{ __('label.forecastDays', ['max' => $maxDays]) }}">
                        <input type="hidden" name="titik" value="{{ $titik }}">
                        <input type="number" name="days" min="1" max="{{ $maxDays }}" step="1" required value="{{ $days }}" class="form-control form-control-sm" aria-label="{{ __('label.forecastDays', ['max' => $maxDays]) }}">
                        <span class="agro-days-unit">{{ __('label.daysUnit') }}</span>
                        <button type="submit" class="btn btn-info btn-sm"><i class="bx bx-check"></i><span class="d-none d-sm-inline"> {{ __('label.apply') }}</span></button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                @include('dashboard::partials.sensor-forecast-table', ['sensorForecast' => $sensorForecast])
            </div>
        </div>
    </div>


</div>
@endsection

@push('style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}">
<style>
    .recommendations-scroll {
        max-height: 350px;
        overflow-y: auto;
    }

    .small-spinner {
    width: 1.2rem;
    height: 1.2rem;
    }

    .sensor-dashboard {
        border: none;
        border-radius: 10px;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .sensor-card {
        border-radius: 8px;
        border: none;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        padding: 1rem;
    }

    .sensor-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    }

    .weather-card {
        background: linear-gradient(135deg, rgba(246, 249, 252, 0.9) 0%, rgba(238, 242, 245, 0.9) 100%);
        border-left: 4px solid #0dcaf0;
    }

    .soil-card {
        background: linear-gradient(135deg, rgba(243, 244, 229, 0.9) 0%, rgba(232, 233, 213, 0.9) 100%);
        border-left: 4px solid #198754;
    }

    .air-card {
        background: linear-gradient(135deg, rgba(230, 247, 255, 0.9) 0%, rgba(208, 235, 255, 0.9) 100%);
        border-left: 4px solid #0d6efd;
    }

    .status-card {
        background: linear-gradient(135deg, rgba(248, 249, 250, 0.9) 0%, rgba(233, 236, 239, 0.9) 100%);
        border-left: 4px solid #ffc107;
    }

    .sensor-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        padding-bottom: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .detection-card {
        border: none;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .detection-image {
        position: relative;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .detection-overlay {
        position: absolute;
        top: 10px;
        left: 10px;
        right: 10px;
        display: flex;
        justify-content: space-between;
    }

    .detection-list li {
        padding: 0.25rem 0;
    }





    .stat-box {
        transition: all 0.3s ease;
    }

    .stat-box:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .sensor-item {
        transition: all 0.3s ease;
        border-radius: 6px;
        padding: 0.5rem;
    }

    .sensor-item:hover {
        background-color: rgba(13, 110, 253, 0.05);
    }

    .sensor-item.online {
        border-left: 3px solid #198754;
    }

    .sensor-item.warning {
        border-left: 3px solid #ffc107;
    }

    .sensor-item.offline {
        border-left: 3px solid #dc3545;
    }

    .alert {
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .alert:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .table-hover tbody tr {
        transition: all 0.3s ease;
    }

    .table-hover tbody tr:hover {
        background-color: rgba(13, 110, 253, 0.05);
        transform: translateX(3px);
    }

    .card-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .progress {
        border-radius: 4px;
    }

    .progress-bar {
        border-radius: 4px;
    }

    .ai-head { background: linear-gradient(120deg, #696cff 0%, #03c3ec 100%); }
    .ai-summary { min-height: 180px; max-height: 360px; overflow-y: auto; padding: 14px 16px; border-radius: 12px;
                  background: linear-gradient(160deg, #f5f5ff 0%, #eefbff 100%); border: 1px solid #e3e6ff; font-size: .9rem; line-height: 1.55; }
    .ai-chat { height: 300px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; padding: 12px;
               border-radius: 12px; border: 1px solid #edf0f4; background: #fafbfc; }
    .ai-msg { max-width: 85%; padding: 9px 13px; border-radius: 14px; font-size: .88rem; line-height: 1.5; white-space: normal; }
    .ai-bot { align-self: flex-start; background: #fff; border: 1px solid #e3e6ff; border-bottom-left-radius: 4px; }
    .ai-user { align-self: flex-end; background: #696cff; color: #fff; border-bottom-right-radius: 4px; }
    .ai-msg ul, .ai-summary ul { padding-left: 18px; margin: 4px 0; }
    .ai-error { color: #c62828; }

    .overall-petak { border: 1px solid #edf0f4; border-left: 4px solid #a1acb8; border-radius: 12px; padding: 12px 14px; margin-bottom: 12px; background: #fff; }
    .overall-petak.lv-danger { border-left-color: #ff3e1d; } .overall-petak.lv-warning { border-left-color: #ffab00; }
    .overall-petak.lv-info { border-left-color: #03c3ec; } .overall-petak.lv-success { border-left-color: #71dd37; }
    .overall-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; }
    .overall-item { display: flex; gap: 10px; align-items: flex-start; }
    .overall-no { width: 24px; height: 24px; flex: 0 0 24px; display: grid; place-items: center; border-radius: 50%; font-size: .75rem; font-weight: 700; }
    .overall-devices { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e3e7ec; }
    .overall-device { font-size: .78rem; padding: 3px 10px; border-radius: 999px; background: #eef2ff; color: #4f46e5; font-weight: 600; }

    .agro-days-form {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 4px 4px 8px;
        border-radius: 0.375rem;
        background: #fff;
        border: 2px solid transparent;
    }
    .agro-days-form.is-active { border-color: #0e7490; }
    .agro-days-form .form-control {
        width: 64px;
        height: 30px;
        padding: 2px 6px;
        text-align: center;
        font-weight: 600;
    }
    .agro-days-unit {
        font-size: 0.78rem;
        color: #03c3ec;
        font-weight: 600;
    }
    .agro-days-form .btn { height: 30px; display: inline-flex; align-items: center; }
</style>
@endpush

@push('script')
<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>



<script>
    const TITIK = @json(array_keys($titikList));
    const IMG_BASE = "{{ asset('img/hama_padi') }}/";
    const L = {
        high: @json(__('label.high')), low: @json(__('label.low')), normal: @json(__('label.normal')),
        night: @json(__('label.night')),
        medium: @json(__('label.medium')),
        speed: @json(__('label.speed')), basa: @json(__('label.basa')), asam: @json(__('label.asam')),
        netral: @json(__('label.netral')), basah: @json(__('label.basah')), kering: @json(__('label.kering')),
        ganti: @json(__('label.ganti')), now: @json(__('label.now')), day: @json(__('label.day')),
        hour: @json(__('label.hour')), minute: @json(__('label.minute')), second: @json(__('label.second')),
        diseases: @json(__('label.diseases')), probability: @json(__('label.probability')),
        noData: @json(__('label.noData')), rfModel: @json(__('label.rfModel')),
        ruleFallback: @json(__('label.ruleFallback')), reasons: @json(__('label.reasons')),
        phaseUnknown: @json(__('label.phaseUnknown')),
        updated: @json(__('label.updated')), healthy: @json(__('label.healthy')),
        online: @json(__('label.online')), offline: @json(__('label.offline')),
        needsAction: @json(__('label.needsAction')),
    };
    const NA = 'Data tidak tersedia';

    function esc(v) {
        return $('<div>').text(v === null || v === undefined ? '' : String(v)).html();
    }
    function has(v) {
        return v !== null && v !== undefined && v !== '' && !isNaN(parseFloat(v));
    }
    function badge(cls, text, title) {
        return `<span class="badge bg-${cls} ms-2"${title ? ` title="${esc(title)}"` : ''}>${text}</span>`;
    }
    function nAvailBadge(v) {
        const n = parseFloat(v);
        if (n < 80) return badge('warning', L.low);
        if (n <= 200) return badge('success', L.medium);
        return badge('warning', L.high);
    }
    function rainBadge(v) {
        const mm = parseFloat(v);
        return mm >= 50 ? badge('danger', L.high) : (mm >= 20 ? badge('warning', L.medium) : badge('success', L.normal));
    }
    function lightBadge(v) {
        const lux = parseFloat(v);
        if (lux <= 0) return badge('secondary', L.night);
        return lux < 32400 ? badge('warning', L.low) : badge('success', L.normal);
    }
    const ELEVASI = @json((float) config('monitoring.elevasi_stasiun', 0));
    function seaLevelHpa(pa, tempC) {
        const p = parseFloat(pa) / 100, t = has(tempC) ? parseFloat(tempC) : 27, h = ELEVASI;
        return h > 0 ? p * Math.pow(1 - (0.0065 * h) / (t + 0.0065 * h + 273.15), -5.257) : p;
    }
    function pressureBadge(pa, tempC) {
        return seaLevelHpa(pa, tempC) <= 1000 ? badge('warning', L.low) : badge('success', L.normal);
    }
    function level(value, low, high, lowText = L.low, highText = L.high) {
        if (!has(value)) return '';
        const v = parseFloat(value);
        if (high !== null && v >= high) return badge('danger', highText);
        if (low !== null && v <= low) return badge('warning', lowText);
        return badge('success', L.normal);
    }
    function relativeTime(dateString) {
        if (!dateString) return '-';
        const diff = Math.floor((new Date() - new Date(dateString.replace(' ', 'T'))) / 1000);
        if (isNaN(diff)) return '-';
        if (diff < 60) return L.now;
        const m = Math.floor(diff / 60), h = Math.floor(m / 60), d = Math.floor(h / 24);
        if (d > 0) return d + ' ' + L.day;
        if (h > 0) return h + ' ' + L.hour;
        return m + ' ' + L.minute;
    }

    const SOIL = {
        ph:              { unit: '',        badge: v => parseFloat(v) > 7.5 ? badge('danger', L.basa) : (parseFloat(v) <= 5.5 ? badge('warning', L.asam) : badge('success', L.netral)) },
        soil_moisture:   { unit: ' %',      badge: v => parseFloat(v) < 35 ? badge('warning', L.kering) : badge('success', L.normal) },
        ec:              { unit: ' µS/cm',  badge: v => parseFloat(v) >= 3000 ? badge('danger', L.high) : badge('success', L.normal) },
        tds:             { unit: ' ppm',    badge: v => parseFloat(v) >= 1920 ? badge('danger', L.high) : badge('success', L.normal) },
        soil_temp:       { unit: ' °C',     badge: v => parseFloat(v) > 34 ? badge('danger', L.high) : (parseFloat(v) <= 17.5 ? badge('warning', L.low) : badge('success', L.normal)) },
        Nitrogen_Level:  { unit: ' mg/kg',  badge: v => nAvailBadge(v) },
        Phosphorus_Level:{ unit: ' mg/kg',  badge: v => parseFloat(v) < 6 ? badge('warning', L.low) : badge('success', L.normal) },
        Potassium_Level: { unit: ' mg/kg',  badge: v => parseFloat(v) < 78 ? badge('warning', L.low) : badge('success', L.normal) },
    };

    function kpi(value, unit, status) {
        return `<span class="kpi-num">${esc(value)}</span><span class="kpi-unit">${esc(unit)}</span>` +
               (status ? `<span class="kpi-status">${status}</span>` : '');
    }
    const OFFLINE_AFTER_MIN = 35;
    function minutesSince(dateString) {
        if (!dateString) return null;
        const diff = (new Date() - new Date(dateString.replace(' ', 'T'))) / 60000;
        return isNaN(diff) ? null : diff;
    }
    function fmt(v, digits = 2) {
        const n = parseFloat(v);
        return Number.isInteger(n) ? String(n) : n.toFixed(digits).replace(/\.?0+$/, '');
    }
    function barTone(el, pct, warnBelow, dangerBelow) {
        el.removeClass('bg-success bg-warning bg-danger')
          .addClass(pct < dangerBelow ? 'bg-danger' : (pct < warnBelow ? 'bg-warning' : 'bg-success'));
    }

    function fetchSensorData() {
        $.get(@json(url('/datasensor')), function(res) {
            $('#weather-updated').html(res.weather_at ? '<i class="bx bx-time-five"></i> ' + L.updated + ' ' + relativeTime(res.weather_at) : '');
            const w = res.weather || {};
            if (has(w.temperature) && has(w.humidity)) {
                const t = parseFloat(w.temperature), rh = parseFloat(w.humidity);
                const svp = 0.6108 * Math.exp((17.27 * t) / (t + 237.3));
                const vpd = svp * (1 - rh / 100);
                $('#temperature').html(kpi(fmt(t, 1), '°C', t >= 35 ? badge('danger', L.high) : badge('success', L.normal)));
                $('#humidity').html(kpi(fmt(rh, 1), '%', rh >= 90 ? badge('warning', L.high) : badge('success', L.normal)));
                $('#vpd').html(kpi(vpd.toFixed(2), 'kPa', level(vpd, 0.5, 1.5)));
            } else {
                $('#temperature, #humidity, #vpd').html(`<span class="kpi-na">${NA}</span>`);
            }
            $('#windspeed').html(has(w.windspeed) ? kpi(fmt(w.windspeed, 1), 'm/s', parseFloat(w.windspeed) >= 8 ? badge('danger', L.speed) : badge('success', L.normal)) : `<span class="kpi-na">${NA}</span>`);
            $('#rainfall').html(has(w.rainfall) ? kpi(fmt(w.rainfall, 1), 'mm', rainBadge(w.rainfall)) : `<span class="kpi-na">${NA}</span>`);
            $('#light_intensity').html(has(w.light_intensity) ? kpi(fmt(w.light_intensity, 0), 'lux', lightBadge(w.light_intensity)) : `<span class="kpi-na">${NA}</span>`);
            $('#pressure').html(has(w.pressure) ? kpi((parseFloat(w.pressure) / 100).toFixed(1), 'hPa', pressureBadge(w.pressure, w.temperature)) : `<span class="kpi-na">${NA}</span>`);

            TITIK.forEach(function(no) {
                const node = (res.titik || {})[no] || {};
                const soil = node.soil || {};
                Object.keys(SOIL).forEach(function(field) {
                    const v = soil[field];
                    const status = has(v) ? SOIL[field].badge(v) : '';
                    $(`#soil-${field}-${no}`).html(has(v)
                            ? `<span class="soil-num">${esc(fmt(v))}</span>${status || '<span class="badge-space"></span>'}`
                            : '<span class="text-muted">-</span>');
                });

                const st = node.status || {};
                const bat = has(st.battery_level) ? Math.max(0, Math.min(100, parseFloat(st.battery_level))) : null;
                $(`#battery-${no}`).html(bat === null ? '-' : bat + '<small>%</small>');
                const bar = $(`#battery-bar-${no}`).css('width', (bat || 0) + '%');
                if (bat !== null) barTone(bar, bat, 50, 20);
                $(`#battery-ico-${no}`).attr('class', 'bx ' + (bat === null ? 'bx-battery' : (bat < 20 ? 'bxs-battery-low' : (bat < 50 ? 'bxs-battery' : 'bxs-battery-full'))));

                const sig = has(st.signal_strength) ? Math.max(0, Math.min(100, parseFloat(st.signal_strength))) : null;
                $(`#signal-${no}`).html(sig === null ? '-' : esc(fmt(sig, 0)) + '<small>%</small>');
                const level = sig === null ? 0 : Math.max(1, Math.ceil(sig / 25));
                $(`#signal-bars-${no}`)
                    .attr('class', 'agro-signal ' + (sig === null ? '' : (sig < 30 ? 'is-danger' : (sig < 60 ? 'is-warn' : 'is-ok'))))
                    .find('i').each((i, el) => $(el).toggleClass('on', i < level));

                const age = minutesSince(node.soil_at);
                const online = age !== null && age <= OFFLINE_AFTER_MIN;
                $(`#online-${no}`)
                    .attr('class', 'agro-chip ' + (age === null ? 'chip-muted' : (online ? 'chip-green' : 'chip-danger')))
                    .html(age === null ? '-' : `<i class="bx ${online ? 'bx-check-circle' : 'bx-error-circle'}"></i> ${online ? L.online : L.offline}`);
                $(`#node-${no}`).toggleClass('is-offline', age !== null && !online);
                $(`#last-update-${no}`).html(relativeTime(node.soil_at));
            });
        }).fail(function() {
            $('#temperature, #humidity, #vpd, #windspeed, #rainfall, #light_intensity, #pressure').html(`<span class="kpi-na">${NA}</span>`);
        });
    }

    function fetchKamera() {
        $.get(@json(url('/datayolo')), function(res) {
            TITIK.forEach(function(no) {
                const cam = (res.titik || {})[no];
                const img = $(`#cam-image-${no}`);
                const card = $(`#cam-card-${no}`).removeClass('is-healthy is-sick');
                $(`#cam-spinner-${no}`).hide();
                if (!cam) {
                    img.hide();
                    $(`#cam-empty-${no}`).show();
                    $(`#cam-penyakit-${no}`).html('<i class="bx bx-info-circle"></i> ' + L.noData);
                    $(`#cam-prob-${no} b`).text('-');
                    $(`#cam-prob-bar-${no}`).css('width', '0%');
                    $(`#cam-time-${no}`).text('').hide();
                    $(`#cam-link-${no}`).removeAttr('href');
                    $(`#cam-state-${no}`).attr('class', 'agro-chip chip-muted').text('-');
                    return;
                }
                $(`#cam-empty-${no}`).hide();
                const src = IMG_BASE + encodeURIComponent(cam.image_url || '');
                if (img.attr('src') !== src) {
                    img.off('load error')
                       .on('load', () => img.show())
                       .on('error', () => { img.hide(); $(`#cam-empty-${no}`).show(); })
                       .attr('src', src);
                    $(`#cam-link-${no}`).attr('href', src);
                }
                const healthy = /normal|sehat/i.test(cam.penyakit || '');
                card.addClass(healthy ? 'is-healthy' : 'is-sick');
                $(`#cam-penyakit-${no}`).html(`<i class="bx ${healthy ? 'bx-check-shield' : 'bx-bug'}"></i> ${esc(cam.penyakit)}`);
                const p = has(cam.probabilitas) ? parseFloat(cam.probabilitas) : null;
                $(`#cam-prob-${no} b`).text(p === null ? '-' : p.toFixed(1) + '%');
                $(`#cam-prob-bar-${no}`).css('width', (p || 0) + '%')
                    .removeClass('bg-success bg-danger').addClass(healthy ? 'bg-success' : 'bg-danger');
                $(`#cam-state-${no}`).attr('class', 'agro-chip ' + (healthy ? 'chip-green' : 'chip-danger'))
                    .html(`<i class="bx ${healthy ? 'bx-check-circle' : 'bx-error-circle'}"></i> ${healthy ? L.healthy : L.needsAction}`);
                $(`#cam-time-${no}`).html('<i class="bx bx-time-five"></i> ' + relativeTime(cam.created_at)).show();
            });
        }).fail(function() {
            TITIK.forEach(no => { $(`#cam-spinner-${no}`).hide(); $(`#cam-penyakit-${no}`).html(NA); });
        });
    }

    const REC_RANK = { danger: 0, warning: 1, info: 2, success: 3 };
    const REC_LEVEL = {
        danger: ['Mendesak', 'danger', 'bx-error'], warning: ['Perlu dilakukan', 'warning', 'bx-time-five'],
        info: ['Pantau', 'info', 'bx-info-circle'], success: ['Aman', 'success', 'bx-check-circle'],
    };
    const SHOW_DEVICES = false;
    const DEVICE_NAME = {
        irrigation_valve: ['bx-water', 'Katup irigasi dibuka otomatis'],
        ultrasonic_repeller: ['bx-broadcast', 'Pengusir hama ultrasonik dinyalakan'],
        uv_lamp: ['bx-bulb', 'Lampu UV perangkap hama dinyalakan'],
    };
    function renderOverall(recs, petakList) {
        const sorted = recs.slice().sort((a, b) => (REC_RANK[a.type] ?? 9) - (REC_RANK[b.type] ?? 9) || (b.probability || 0) - (a.probability || 0));
        const nDanger = sorted.filter(r => r.type === 'danger').length;
        const nWarn = sorted.filter(r => r.type === 'warning').length;
        const worst = nDanger ? 'danger' : (nWarn ? 'warning' : 'success');
        const headline = nDanger ? `${nDanger} aksi mendesak perlu segera dilakukan`
            : (nWarn ? `${nWarn} aksi perlu dilakukan` : 'Semua petak aman, lanjutkan pemantauan rutin');

        let html = `
            <div class="overall-head alert alert-${worst} d-flex align-items-center gap-3 p-3 mb-3">
                <i class="bx ${REC_LEVEL[worst][2]} fs-3"></i>
                <div>
                    <strong class="d-block">${headline}</strong>
                    <small>${sorted.length} aksi untuk ${petakList.length} petak &middot; Mendesak ${nDanger} &middot; Perlu dilakukan ${nWarn}</small>
                </div>
            </div>`;

        petakList.forEach(function (p) {
            const items = sorted.filter(r => (r.petak || []).includes(p.label));
            const level = items.length ? (items[0].type in REC_LEVEL ? items[0].type : 'info') : 'success';
            const fase = p.fase ? esc(p.fase) + (p.hst != null ? ' · HST ' + p.hst : '') : L.phaseUnknown;
            const rows = items.map(function (r, idx) {
                const lv = REC_LEVEL[r.type] || REC_LEVEL.info;
                const why = ((r.reasons_by_petak || {})[p.label] || []).map(esc).join('; ');
                return `
                    <li class="overall-item">
                        <span class="overall-no bg-label-${lv[1]}">${idx + 1}</span>
                        <div>
                            <strong>${esc(r.title)}</strong> <span class="badge bg-label-${lv[1]}">${lv[0]}</span>
                            ${why ? `<div class="small text-muted">${why}</div>` : ''}
                        </div>
                    </li>`;
            }).join('');
            const devices = (SHOW_DEVICES ? ((p.actuation || {}).actuators || []) : []).map(function (code) {
                const [icon, name] = DEVICE_NAME[code] || ['bx-chip', String(code).replace(/_/g, ' ')];
                return `<span class="overall-device"><i class="bx ${icon}"></i> ${esc(name)}</span>`;
            }).join('');
            html += `
                <div class="overall-petak lv-${level}">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="badge bg-label-dark">${esc(p.label)}</span>
                        <span class="badge bg-label-success">${fase}</span>
                        <span class="badge bg-${REC_LEVEL[level][1]} ms-auto">${REC_LEVEL[level][0]}</span>
                    </div>
                    ${items.length ? `<ol class="overall-list">${rows}</ol>`
                                   : '<p class="small text-muted mb-0"><i class="bx bx-check-circle text-success"></i> Tidak ada aksi, kondisi aman.</p>'}
                    ${devices ? `<div class="overall-devices">${devices}</div>` : ''}
                </div>`;
        });
        return html || `<p class="text-muted small mb-0">${L.noData}</p>`;
    }

    function fetchRecommendations() {
        $.get(@json(url('/getRecommendations')), function(res) {
            const model = res.model || {};
            $('#rec-model').text(model.source === 'random_forest'
                ? L.rfModel
                : L.ruleFallback);

            let html = '';
            (res.recommendations || []).forEach(function(r) {
                const petak = (r.petak || []).map(p => `<span class="badge bg-label-dark me-1">${esc(p)}</span>`).join('');
                const prob = has(r.probability) ? ` <span class="text-muted">(${(r.probability * 100).toFixed(0)}%)</span>` : '';
                let reasons = '';
                Object.entries(r.reasons_by_petak || {}).forEach(function([p, list]) {
                    if (list && list.length) reasons += `<li><strong>${esc(p)}:</strong> ${list.map(esc).join('; ')}</li>`;
                });
                html += `
                    <div class="alert alert-${esc(r.type)} d-flex align-items-start p-3 mb-3">
                        <i class="${esc(r.icon)} fs-4 me-3"></i>
                        <div>
                            <strong class="d-block">${esc(r.title)}${prob}</strong>
                            <div class="mb-1">${petak}</div>
                            <p class="mb-1 small">${esc(r.message)}</p>
                            ${reasons ? `<ul class="small mb-0 ps-3">${reasons}</ul>` : ''}
                        </div>
                    </div>`;
            });
            $('#recommendations').html(html || `<p class="text-muted small mb-0">${L.noData}</p>`);

            const petakInfo = [];
            TITIK.forEach(function(no) {
                const p = (res.petak || {})[no];
                const hi = (res.hybridInferences || {})[no];

                const h = p && p.health;
                if (h && has(h.Healthy)) {
                    const score = parseFloat(h.Healthy) * 100;
                    const kelas = Object.entries(h).sort((a, b) => b[1] - a[1])[0][0];
                    const [nama, warna] = ({ 'Healthy': ['Sehat', 'success'], 'Moderate Stress': ['Stres Sedang', 'warning'],
                                             'High Stress': ['Stres Tinggi', 'danger'] })[kelas] || [kelas, 'secondary'];
                    $(`#health-${no}`).html(`${score.toFixed(1)}% <span class="text-${warna}">· ${nama}</span>`);
                    $(`#health-bar-${no}`).css('width', score + '%')
                        .removeClass('bg-success bg-warning bg-danger bg-secondary').addClass('bg-' + warna);
                } else {
                    $(`#health-${no}`).text('-');
                    $(`#health-bar-${no}`).css('width', '0%');
                }

                if (hi) {
                    const vpdTxt = hi.vpd_kpa != null ? Number(hi.vpd_kpa).toFixed(3) + ' kPa' : '-';
                    $(`#hybrid-${no}`).html(`<strong><i class="bx bx-git-merge"></i> Hybrid CNN&ndash;LSTM</strong><br>` +
                        `Hama: ${esc(hi.pest_label || '-')} &middot; Kesehatan: ${esc(hi.health_status || '-')} &middot; VPD: ${vpdTxt}`).show();
                } else {
                    $(`#hybrid-${no}`).hide();
                }

                if (p) petakInfo.push(p);
            });
            $('#actuation').html(renderOverall(res.recommendations || [], petakInfo));
        }).fail(function() {
            $('#recommendations').html('<p>No recommendations available.</p>');
        });
    }

    function aiFormat(text) {
        const lines = esc(text || '').split(/\r?\n/);
        let html = '', inList = false;
        lines.forEach(function (raw) {
            const line = raw.replace(/\*\*(.+?)\*\*/g, '<b>$1</b>');
            const m = line.match(/^\s*(?:[-•*]|\d+[.)])\s+(.*)$/);
            if (m) { if (!inList) { html += '<ul>'; inList = true; } html += `<li>${m[1]}</li>`; return; }
            if (inList) { html += '</ul>'; inList = false; }
            if (line.trim()) html += `<div>${line}</div>`;
        });
        return html + (inList ? '</ul>' : '');
    }
    function loadAiSummary() {
        $('#aiSummary').html('<div class="d-flex align-items-center gap-2 text-muted small"><span class="spinner-border spinner-border-sm text-primary"></span> AI sedang merangkum data sawah...</div>');
        $.get(@json(route('ai.summary')), function (res) {
            if (res.error) { $('#aiSummary').html(`<div class="ai-error"><i class="bx bx-error-circle"></i> ${esc(res.error)}</div>`); return; }
            $('#aiSummary').html(aiFormat(res.text));
            $('#aiSummaryTime').text(res.generated_at ? 'Dibuat ' + res.generated_at : '');
        }).fail(function (xhr) {
            $('#aiSummary').html(`<div class="ai-error"><i class="bx bx-error-circle"></i> ${esc((xhr.responseJSON || {}).error || 'Gagal memuat kesimpulan AI.')}</div>`);
        });
    }
    const aiHistory = [];
    function aiAsk(question) {
        question = (question || '').trim();
        if (!question) return;
        const box = $('#aiChat');
        box.append(`<div class="ai-msg ai-user">${esc(question)}</div>`);
        const typing = $('<div class="ai-msg ai-bot text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Mengetik...</div>').appendTo(box);
        box.scrollTop(box[0].scrollHeight);
        $('#aiInput').val('').prop('disabled', true); $('#aiSend').prop('disabled', true);
        $.ajax({
            url: @json(route('ai.chat')), method: 'POST', contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': @json(csrf_token()) },
            data: JSON.stringify({ message: question, history: aiHistory.slice(-10) }),
        }).done(function (res) {
            if (res.error) { typing.removeClass('text-muted').addClass('ai-error').text(res.error); return; }
            typing.removeClass('text-muted').html(aiFormat(res.answer));
            aiHistory.push({ role: 'user', content: question }, { role: 'assistant', content: res.answer });
        }).fail(function (xhr) {
            const msg = xhr.status === 429 ? 'Terlalu banyak pertanyaan. Tunggu sebentar lalu coba lagi.'
                : ((xhr.responseJSON || {}).error || 'Asisten AI gagal menjawab.');
            typing.removeClass('text-muted').addClass('ai-error').text(msg);
        }).always(function () {
            $('#aiInput').prop('disabled', false).focus(); $('#aiSend').prop('disabled', false);
            box.scrollTop(box[0].scrollHeight);
        });
    }

    $(document).ready(function() {
        loadAiSummary();
        $('#aiSummaryRefresh').on('click', loadAiSummary);
        $('#aiForm').on('submit', function (e) { e.preventDefault(); aiAsk($('#aiInput').val()); });
        fetchSensorData();
        fetchKamera();
        fetchRecommendations();
        setInterval(fetchSensorData, 15000);
        setInterval(fetchKamera, 60000);
        setInterval(fetchRecommendations, 30000);
    });
</script>

@endpush
