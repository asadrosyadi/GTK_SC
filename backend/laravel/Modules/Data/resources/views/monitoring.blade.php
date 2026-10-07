@extends('layouts.dashboard')

@section('content')
<link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
<div class="row justify-content-center">
    <div class="col-lg-12 mb-4 order-0">
        <div class="card sensor-dashboard">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-white"><i class="fa-regular fa-user me-3"></i>      {{ $iot_idUser->name }}</h5>
                <div class="badge bg-white text-primary">{{ $iot_idUser->iot_id }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-7 mb-4 order-0">
        <div class="card sensor-dashboard">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-white"><div class="spinner-grow text-danger small-spinner" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>   {{ __('label.realTimeEnvironmentalMonitoring') }}</h5>
                <div class="badge bg-white text-primary">{{ __('label.liveData') }}</div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="sensor-card weather-card h-100">
                            <div class="sensor-header d-flex align-items-center mb-3">
                                <i class='bx bx-cloud fs-2 text-info me-3'></i>
                                <h6 class="mb-0">{{ __('label.weatherConditions') }}</h6>
                            </div>
                            <div class="sensor-body">
                                <div class="d-flex justify-content-between align-items-center mb-2 py-1">
                                    <span class="d-flex align-items-center"><i class="fa fa-temperature-quarter me-3"></i> {{ __('label.temperature') }}:</span>
                                    <span id="temperature" class="fw-bold">Loading...</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2 py-1">
                                    <span class="d-flex align-items-center"><i class='bx bx-droplet me-2'></i>{{ __('label.humidity_1') }}:</span>
                                    <span id="humidity" class="fw-bold">Loading...</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2 py-1">
                                    <span class="d-flex align-items-center"><i class='bx bx-cloud me-2'></i>VPD:</span>
                                    <span id="vpd" class="fw-bold">Loading...</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2 py-1">
                                    <span class="d-flex align-items-center"><i class='bx bx-wind me-2'></i>{{ __('label.windSpeed') }}:</span>
                                    <span id="windspeed" class="fw-bold">Loading...</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2 py-1">
                                    <span class="d-flex align-items-center"><i class='bx bx-cloud-rain me-2'></i>{{ __('label.rainfall_1') }} 24 jam:</span>
                                    <span id="rainfall" class="fw-bold">Loading...</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-1">
                                    <span class="d-flex align-items-center"><i class='bx bx-sun me-2'></i>{{ __('label.lightIntensity') }}:</span>
                                    <span id="light_intensity" class="fw-bold">Loading...</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2 py-1">
                                    <span class="d-flex align-items-center"><i class="bx bxs-thermometer" style="color: #000;"></i></i>{{ __('label.pressure_1') }}:</span>
                                    <span id="pressure" class="fw-bold">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="sensor-card soil-card h-100">
                            <div class="sensor-header d-flex align-items-center mb-3">
                                <i class='bx bx-leaf fs-2 text-success me-3'></i>
                                <h6 class="mb-0">{{ __('label.soilConditions') }}</h6>
                            </div>
                            <div class="sensor-body">
                                <div class="d-flex justify-content-between align-items-center mb-2 py-1">
                                    <span class="d-flex align-items-center"><i class='bx bx-test-tube me-2'></i>{{ __('label.phLevel') }}:</span>
                                    <span id="ph" class="fw-bold">Loading...</span>

                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2 py-1">
                                    <span class="d-flex align-items-center"><i class='bx bx-line-chart me-2'></i>{{ __('label.soilMoisture') }}:</span>
                                    <span id="soil_moisture" class="fw-bold">Loading...</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2 py-1">
                                    <span class="d-flex align-items-center"><i class='bx bx-atom me-2'></i>{{ __('label.ec_1') }}:</span>
                                    <span id="ec" class="fw-bold">Loading...</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2 py-1">
                                    <span class="d-flex align-items-center"><i class="bx bx-droplet"></i>{{ __('label.tds') }}:</span>
                                    <span id="tds" class="fw-bold">Loading...</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2 py-1">
                                    <span class="d-flex align-items-center"><i class='bx bx-layer me-2'></i>{{ __('label.soilTemp') }}:</span>
                                    <span id="soil_temp" class="fw-bold">Loading...</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2 py-1">
                                    <span class="d-flex align-items-center"><i class="bx bx-badge-check me-2"></i></i>N:</span>
                                    <span id="nitrogen" class="fw-bold">Loading...</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2 py-1">
                                    <span class="d-flex align-items-center"><i class='bx  bx-test-tube me-2'></i>P:</span>
                                    <span id="phosphorus" class="fw-bold">Loading...</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2 py-1">
                                    <span class="d-flex align-items-center"><i class="bx bx-gas-pump me-2"></i></i>K:</span>
                                    <span id="potassium" class="fw-bold">Loading...</span>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="sensor-card air-card h-100">
                            <div class="sensor-header d-flex align-items-center mb-3">
                                <i class='bx bx-bug fs-2 text-primary me-3'></i>
                                <h6 class="mb-0">{{ __('label.pest') }}</h6>
                            </div>
                         <div class="sensor-body">
                                <div class="d-flex justify-content-between align-items-center mb-2 py-1" >
                                    <span class="d-flex align-items-center"><i class='bx bx-gas-pump me-2'></i>{{ __('label.feromon') }}:</span>
                                    <span id="feromon" class="fw-bold">Loading...</span>
                                </div>
                        </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="sensor-card status-card h-100">
                            <div class="sensor-header d-flex align-items-center mb-3">
                                <i class='bx bx-cog fs-2 text-warning me-3'></i>
                                <h6 class="mb-0">{{ __('label.systemStatus') }}</h6>
                            </div>
                            <div class="sensor-body">
                                <div class="d-flex justify-content-between align-items-center mb-2 py-1">
                                    <span class="d-flex align-items-center"><i class="fa-solid fa-battery-three-quarters me-2"></i> {{ __('label.batteryLevel') }}:</span>
                                    <span id="battery_level" class="fw-bold">Loading...</span>
                                </div>
                                <div class="status-item mb-3">
                                    <span id="battery_levelbar" class="fw-bold"></span>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2 py-1">
                                    <span class="d-flex align-items-center"><i class='bx bx-signal-5 me-2'></i>{{ __('label.signalStrength') }}:</span>
                                    <span id="signal_strength" class="fw-bold">Loading...</span>
                                </div>
                                <div class="status-item mb-3">
                                    <span id="signal_strengthbar" class="fw-bold"></span>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2 py-1">
                                    <span class="d-flex align-items-center"><i class='bx bx-time me-2'></i>{{ __('label.lastUpdate') }}:</span>
                                    <span id="last_update" class="fw-bold">Loading...</span>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-5 order-1">
        <div class="row g-3">

            <div class="col-12">
                <div class="card detection-card h-100">
                    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                         <h5 class="card-title mb-0 text-white"><i class="bx bx-camera me-2"></i>{{ __('label.yoloPestDetection') }}</h5>
                    </div>

                    <div class="card-body">
                        <div class="detection-image mb-3">
                            <div id="loading-spinner-container" class="d-flex justify-content-center align-items-center" >
                                <div id="loading-spinner" class="spinner-border spinner-border-lg text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>

                            <img id="image" src="" class="img-fluid rounded d-block mx-auto" width="300" style="display: none;">

                            <div class="detection-overlay">
                                <span id="penyakit_up" class="badge bg-danger">Loading...</span>
                                <span id="probabilitas_up" class="badge bg-warning">Loading...</span>
                            </div>
                        </div>
                        <div class="detection-details">
                            <h6 class="fw-semibold mb-2">{{ __('label._detectionSummary') }}:</h6>
                            <ul class="detection-list list-unstyled mb-3">
                                <li class="text-danger mb-1 d-flex align-items-center" id="penyakit"> Loading...</li>
                                <li class="text-warning mb-1 d-flex align-items-center" id="probabilitas">Loading...</li>
                            </ul>
                            <div class="health-score">
                                @if(isset($forecastData_first['confidence']['Healthy']))
                                    @php($conf = $forecastData_first['confidence'])
                                    @php($kelasHs = array_search(max($conf), $conf))
                                    @php([$namaHs, $warnaHs] = ['Healthy' => ['Sehat', 'success'], 'Moderate Stress' => ['Stres Sedang', 'warning'], 'High Stress' => ['Stres Tinggi', 'danger']][$kelasHs] ?? [$kelasHs, 'secondary'])
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-success">
                                            {{ __('label.plant_health_score') }}:
                                        </span>
                                        <span class="fw-bold text-end" id="planthealth">
                                            {{ number_format($conf['Healthy'] * 100, 1) }}% <span class="text-{{ $warnaHs }}">· {{ $namaHs }}</span>
                                        </span>
                                    </div>

                                <div class="status-item mb-3">
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-{{ $warnaHs }}" style="width: {{ number_format($conf['Healthy'] * 100, 1) }}%"></div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8 order-2 order-lg-3 mb-4 mt-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bx bx-line-chart me-2"></i>{{ __('label.trendprediction') }}</h5>

            </div>
            <div class="card-body">
                @include('dashboard::partials.sensor-forecast-chart', ['sensorForecast' => $sensorForecast, 'chartId' => 'adminSensorTrendChart'])
            </div>
        </div>
    </div>
    <div class="col-lg-4 order-3 order-lg-2 mb-4 mt-4">
        <div class="card h-100">
            <div class="card-header bg-warning text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-white"><i class="bx bx-bell me-2"></i>{{ __('label.rekomendasi') }}</h5>
            </div>
            <div class="card-body recommendations-scroll">
                @foreach ($recommendations as $recommendation)
                    <div class="alert alert-{{ $recommendation['type'] }} d-flex align-items-start p-3 mb-3">
                        <i class="{{ $recommendation['icon'] }} fs-4 me-3"></i>
                        <div>
                            <strong class="d-block">{{ $recommendation['title'] }}</strong>
                            <p class="mb-0 small">{{ $recommendation['message'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-12 mb-4 order-4">
        <div class="card sensor-dashboard">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-white"><i class="fa-solid fa-wand-magic-sparkles"></i>
                    {{ __('label.prediction') }}</h5>
                    <div class="btn-group badge bg-white btn-group-sm me-2">
                        <a href="{{ url('/data/monitoring/'.$iot_idUser->iot_id.'?days=1') }}" class="btn btn-outline-info @if($days == 1) active @endif">1d</a>
                        <a href="{{ url('/data/monitoring/'.$iot_idUser->iot_id.'?days=3') }}" class="btn btn-outline-info @if($days == 3) active @endif">3d</a>
                        <a href="{{ url('/data/monitoring/'.$iot_idUser->iot_id.'?days=7') }}" class="btn btn-outline-info @if($days == 7) active @endif">7d</a>
                        <a href="{{ url('/data/monitoring/'.$iot_idUser->iot_id.'?days=14') }}" class="btn btn-outline-info @if($days == 14) active @endif">14d</a>
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
        max-height: 440px;
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
</style>
@endpush

@push('script')
<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>


<script>
    const MON_IOT_ID = window.location.pathname.split('/').pop();
    const MON_NA = 'Data tidak tersedia';
    const MON_ELEVASI = @json((float) config('monitoring.elevasi_stasiun', 0));
    const MON_L = {
        high: @json(__('label.high')), low: @json(__('label.low')), normal: @json(__('label.normal')),
        medium: @json(__('label.medium')), night: @json(__('label.night')),
        speed: @json(__('label.speed')), basa: @json(__('label.basa')), asam: @json(__('label.asam')),
        netral: @json(__('label.netral')), kering: @json(__('label.kering')), ganti: @json(__('label.ganti')),
        now: @json(__('label.now')), day: @json(__('label.day')), hour: @json(__('label.hour')),
        minute: @json(__('label.minute')), diseases: @json(__('label.diseases')), probability: @json(__('label.probability')),
    };

    function monHas(v) { return v !== null && v !== undefined && v !== '' && !isNaN(parseFloat(v)); }
    function monBadge(cls, text) { return `<span class="badge bg-${cls} ms-2">${text}</span>`; }
    function monText(v, unit, badgeFn) {
        if (!monHas(v)) return '-';
        return v + unit + (badgeFn ? badgeFn(parseFloat(v)) : '');
    }
    function monRelative(dateString) {
        if (!dateString) return '-';
        const diff = Math.floor((new Date() - new Date(String(dateString).replace(' ', 'T'))) / 1000);
        if (isNaN(diff)) return '-';
        if (diff < 60) return MON_L.now;
        const m = Math.floor(diff / 60), h = Math.floor(m / 60), d = Math.floor(h / 24);
        return d > 0 ? d + ' ' + MON_L.day : (h > 0 ? h + ' ' + MON_L.hour : m + ' ' + MON_L.minute);
    }

    function fetchSensorMonitoring() {
        $.get(@json(url('/monitor_iot')) + '?iot_id=' + MON_IOT_ID, function(res) {
            const d = (res.data || [])[0] || {};
            const t = parseFloat(d.temperature), rh = parseFloat(d.humidity);
            const vpd = (monHas(d.temperature) && monHas(d.humidity))
                ? 0.6108 * Math.exp((17.27 * t) / (t + 237.3)) * (1 - rh / 100) : null;

            $('#temperature').html(monText(d.temperature, ' °C', v => v >= 35 ? monBadge('danger', MON_L.high) : monBadge('success', MON_L.normal)));
            $('#humidity').html(monText(d.humidity, '%', v => v >= 90 ? monBadge('warning', MON_L.high) : monBadge('success', MON_L.normal)));
            $('#vpd').html(vpd === null ? '-' : vpd.toFixed(2) + ' kPa');
            $('#windspeed').html(monText(d.windspeed, ' m/s', v => v >= 8 ? monBadge('danger', MON_L.speed) : monBadge('success', MON_L.normal)));
            $('#rainfall').html(monText((res.data || []).length ? res.rainfall_24h : null, ' mm/hari', v => v >= 50 ? monBadge('danger', MON_L.high) : (v >= 20 ? monBadge('warning', MON_L.medium) : monBadge('success', MON_L.normal))));
            $('#light_intensity').html(monText(d.light_intensity, ' lux', v => v <= 0 ? monBadge('secondary', MON_L.night) : (v < 32400 ? monBadge('warning', MON_L.low) : monBadge('success', MON_L.normal))));
            $('#pressure').html(monText(d.pressure, ' Pa', v => {
                const h = MON_ELEVASI, t = monHas(d.temperature) ? parseFloat(d.temperature) : 27;
                const slp = h > 0 ? (v / 100) * Math.pow(1 - (0.0065 * h) / (t + 0.0065 * h + 273.15), -5.257) : v / 100;
                return slp <= 1000 ? monBadge('warning', MON_L.low) : monBadge('success', MON_L.normal);
            }));
            $('#ph').html(monText(d.ph, '', v => v > 7.5 ? monBadge('danger', MON_L.basa) : (v <= 5.5 ? monBadge('warning', MON_L.asam) : monBadge('success', MON_L.netral))));
            $('#soil_moisture').html(monText(d.soil_moisture, ' %', v => v < 35 ? monBadge('warning', MON_L.kering) : monBadge('success', MON_L.normal)));
            $('#ec').html(monText(d.ec, ' µS/cm', v => v >= 3000 ? monBadge('danger', MON_L.high) : monBadge('success', MON_L.normal)));
            $('#tds').html(monText(d.tds, ' ppm', v => v >= 1920 ? monBadge('danger', MON_L.high) : monBadge('success', MON_L.normal)));
            $('#soil_temp').html(monText(d.soil_temp, ' °C', v => v > 34 ? monBadge('danger', MON_L.high) : (v <= 17.5 ? monBadge('warning', MON_L.low) : monBadge('success', MON_L.normal))));
            $('#nitrogen').html(monText(d.nitrogen, ' mg/kg', v =>
                v < 80 ? monBadge('warning', MON_L.low) : (v <= 200 ? monBadge('success', MON_L.medium) : monBadge('warning', MON_L.high))));
            $('#phosphorus').html(monText(d.fosfor, ' mg/kg', v => v < 6 ? monBadge('warning', MON_L.low) : monBadge('success', MON_L.normal)));
            $('#potassium').html(monText(d.potassium, ' mg/kg', v => v < 78 ? monBadge('warning', MON_L.low) : monBadge('success', MON_L.normal)));
            $('#feromon').html(monHas(d.feromon) ? (parseFloat(d.feromon) < 1000 ? monBadge('warning', MON_L.ganti) : monBadge('success', MON_L.normal)) : '-');
            $('#battery_level').html(monText(d.battery_level, ' %'));
            $('#signal_strength').html(monHas(d.signal_strength) ? d.signal_strength : '-');
            $('#last_update').html(monRelative(d.created_at));
            const bar = (id, value, cls) => $(id).html(`<div class="progress" style="height: 8px;"><div class="progress-bar ${cls}" style="width: ${monHas(value) ? value : 0}%"></div></div>`);
            bar('#battery_levelbar', d.battery_level, 'bg-success');
            bar('#signal_strengthbar', d.signal_strength, 'bg-info');
        }).fail(function() {
            ['#temperature', '#humidity', '#vpd', '#windspeed', '#rainfall', '#light_intensity', '#ph', '#soil_moisture',
             '#ec', '#tds', '#soil_temp', '#pressure', '#feromon', '#battery_level', '#signal_strength', '#last_update']
                .forEach(id => $(id).html(MON_NA));
        });
    }

    function fetchKameraMonitoring() {
        $.get(@json(url('/monitor_kamera')) + '?iot_id=' + MON_IOT_ID, function(res) {
            const cam = (res.data || [])[0];
            $('#loading-spinner').hide();
            if (!cam) {
                $('#penyakit, #penyakit_up').html('-');
                $('#probabilitas, #probabilitas_up').html('-');
                return;
            }
            const img = $('#image');
            img.off('load error').on('load', () => img.show()).on('error', () => img.hide())
               .attr('src', "{{ asset('img/hama_padi') }}/" + encodeURIComponent(cam.image_url || ''));
            $('#penyakit').html('<i class="bx bx-bug me-2"></i> ' + MON_L.diseases + ': ' + $('<div>').text(cam.penyakit).html());
            $('#probabilitas').html('<i class="bx bx-leaf me-2"></i> ' + MON_L.probability + ': ' + cam.probabilitas + '%');
            $('#penyakit_up').text(cam.penyakit);
            $('#probabilitas_up').text(cam.probabilitas + '%');
        }).fail(function() {
            $('#loading-spinner').hide();
        });
    }

    $(document).ready(function() {
        fetchSensorMonitoring();
        fetchKameraMonitoring();
        setInterval(fetchSensorMonitoring, 15000);
        setInterval(fetchKameraMonitoring, 60000);
    });
</script>

@endpush
