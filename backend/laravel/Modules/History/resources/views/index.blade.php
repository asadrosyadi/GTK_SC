@extends('layouts.dashboard')

@section('content')
@php($judulPetak = $titik === 0 ? 'Gabungan Petak 1 & 2' : ($titikList[$titik]['label'] ?? ''))
<div class="row justify-content-center">

    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-white"><i class="bx bx-history me-2"></i>{{ __('label.riwayat_data') }} &middot; {{ $judulPetak }}</h5>
                <div class="d-flex flex-wrap gap-2">
                    <div class="btn-group badge bg-white btn-group-sm" title="{{ __('label.selectPlot') }}">
                        <a href="{{ url('/history?titik=0&days=' . $days) }}" class="btn btn-outline-info @if($titik === 0) active @endif">Gabungan</a>
                        @foreach ($titikList as $no => $info)
                            <a href="{{ url('/history?titik=' . $no . '&days=' . $days) }}" class="btn btn-outline-info @if($titik == $no) active @endif">{{ $info['label'] }}</a>
                        @endforeach
                    </div>
                    <div class="btn-group badge bg-white btn-group-sm">
                        @foreach ([1, 3, 7] as $d)
                            <a href="{{ url('/history?titik=' . $titik . '&days=' . $d) }}" class="btn btn-outline-info @if($days == $d) active @endif">{{ $d }}d</a>
                        @endforeach
                    </div>
                    <form method="GET" action="{{ url('/history') }}" class="history-days-form @if(! in_array($days, [1, 3, 7], true)) is-active @endif" title="Rentang riwayat (1–30 hari)">
                        <input type="hidden" name="titik" value="{{ $titik }}">
                        <input type="number" name="days" min="1" max="30" step="1" required value="{{ $days }}" class="form-control form-control-sm" aria-label="Rentang riwayat (hari)">
                        <span class="history-days-unit">{{ __('label.daysUnit') }}</span>
                        <button type="submit" class="btn btn-info btn-sm"><i class="bx bx-check"></i><span class="d-none d-sm-inline"> {{ __('label.apply') }}</span></button>
                    </form>
                    <a href="{{ route('export.data', ['titik' => $titik, 'days' => $days]) }}" class="btn btn-sm btn-light d-flex align-items-center">
                        <i class="bx bx-download me-1"></i> {{ __('label.export') }}
                    </a>
                </div>
            </div>
            <div class="card-body pt-3">
                @if ($history)
                    <small class="text-muted d-block mb-2"><i class="bx bx-time-five"></i> Data terukur {{ $days }} hari terakhir, sampai {{ $history['last_observation'] ?? '-' }}
                        &middot; {{ count($history['measured']) }} pembacaan</small>
                @endif
                @include('dashboard::partials.sensor-forecast-table', [
                    'sensorForecast' => $history ? array_merge($history, ['measured' => array_reverse($history['measured']), 'forecast' => []]) : null,
                    'showLstm' => true,
                ])
            </div>
        </div>
    </div>

    <div class="col-12 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bx bx-line-chart me-2"></i> Tren Riwayat &middot; {{ $judulPetak }}</h5>
            </div>
            <div class="card-body">
                @include('dashboard::partials.sensor-forecast-chart', ['sensorForecast' => $history, 'chartId' => 'historyTrendChart', 'historyMode' => true])
            </div>
        </div>
    </div>

</div>
@endsection

@push('style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}">
<style>
    .history-days-form {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 4px 4px 8px;
        border-radius: 0.375rem;
        background: #fff;
        border: 2px solid transparent;
    }
    .history-days-form.is-active { border-color: #0e7490; }
    .history-days-form .form-control { width: 64px; height: 30px; padding: 2px 6px; text-align: center; font-weight: 600; }
    .history-days-unit { font-size: 0.78rem; color: #03c3ec; font-weight: 600; }
    .history-days-form .btn { height: 30px; display: inline-flex; align-items: center; }
</style>
@endpush

@push('script')
<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
@endpush
