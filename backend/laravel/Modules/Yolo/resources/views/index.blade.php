@extends('layouts.dashboard')

@section('content')
@php($judulPetak = $titik === 0 ? 'Gabungan Petak 1 & 2' : ($titikList[$titik]['label'] ?? ''))
@php($q = fn (array $extra = []) => url('/cnn') . '?' . http_build_query(array_merge(['titik' => $titik, 'days' => $days], $extra)))
<div class="row justify-content-center cnn-page">

    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-white"><i class="bx bx-camera me-2"></i>{{ __('label.riwayat_yolo') }} &middot; {{ $judulPetak }}</h5>
                <div class="d-flex flex-wrap gap-2">
                    <div class="btn-group badge bg-white btn-group-sm" title="{{ __('label.selectPlot') }}">
                        <a href="{{ url('/cnn') . '?titik=0&days=' . $days }}" class="btn btn-outline-info @if($titik === 0) active @endif">Gabungan</a>
                        @foreach ($titikList as $no => $info)
                            <a href="{{ url('/cnn') . '?titik=' . $no . '&days=' . $days }}" class="btn btn-outline-info @if($titik == $no) active @endif">{{ $info['label'] }}</a>
                        @endforeach
                    </div>
                    <div class="btn-group badge bg-white btn-group-sm">
                        @foreach ([1, 3, 7] as $d)
                            <a href="{{ url('/cnn') . '?titik=' . $titik . '&days=' . $d }}" class="btn btn-outline-info @if($days == $d) active @endif">{{ $d }}d</a>
                        @endforeach
                    </div>
                    <form method="GET" action="{{ url('/cnn') }}" class="cnn-days-form @if(! in_array($days, [1, 3, 7], true)) is-active @endif" title="Rentang riwayat (1–30 hari)">
                        <input type="hidden" name="titik" value="{{ $titik }}">
                        <input type="number" name="days" min="1" max="30" step="1" required value="{{ $days }}" class="form-control form-control-sm" aria-label="Rentang riwayat (hari)">
                        <span class="cnn-days-unit">{{ __('label.daysUnit') }}</span>
                        <button type="submit" class="btn btn-info btn-sm"><i class="bx bx-check"></i><span class="d-none d-sm-inline"> {{ __('label.apply') }}</span></button>
                    </form>
                    <a href="{{ route('export.data.yolo', ['titik' => $titik, 'days' => $days]) }}" class="btn btn-sm btn-light d-flex align-items-center">
                        <i class="bx bx-download me-1"></i> {{ __('label.export') }}
                    </a>
                </div>
            </div>

            <div class="card-body pt-3">
                @if ($last)
                    <small class="text-muted d-block mb-3"><i class="bx bx-time-five"></i> Deteksi {{ $days }} hari terakhir, sampai {{ $last }}</small>
                @endif

                <div class="cnn-stats mb-4">
                    <div class="cnn-stat tone-blue">
                        <span class="cnn-stat-ico"><i class="bx bx-scan"></i></span>
                        <div><span class="cnn-stat-label">Total deteksi</span><b>{{ $summary['total'] }}</b></div>
                    </div>
                    <div class="cnn-stat tone-green">
                        <span class="cnn-stat-ico"><i class="bx bx-check-shield"></i></span>
                        <div><span class="cnn-stat-label">Sehat</span><b>{{ $summary['healthy'] }}</b></div>
                    </div>
                    <div class="cnn-stat tone-red">
                        <span class="cnn-stat-ico"><i class="bx bx-bug"></i></span>
                        <div><span class="cnn-stat-label">Perlu tindakan</span><b>{{ $summary['sick'] }}</b></div>
                    </div>
                    <div class="cnn-stat tone-amber">
                        <span class="cnn-stat-ico"><i class="bx bx-error"></i></span>
                        <div><span class="cnn-stat-label">Paling sering</span><b class="cnn-stat-text">{{ $summary['top'] ?? '-' }}</b></div>
                    </div>
                    <div class="cnn-stat tone-teal">
                        <span class="cnn-stat-ico"><i class="bx bx-target-lock"></i></span>
                        <div><span class="cnn-stat-label">Rata-rata skor deteksi</span><b>{{ $summary['avg'] !== null ? number_format($summary['avg'], 1) . '%' : '-' }}</b></div>
                    </div>
                </div>

                @if ($data->isEmpty())
                    <div class="text-center text-muted py-5"><i class="bx bx-image fs-1 d-block mb-2"></i>{{ __('label.noData') }}</div>
                @else
                    <div class="row g-3">
                        @foreach ($data as $item)
                            <div class="col-sm-6 col-lg-4 col-xxl-3">
                                <div class="cnn-card {{ $item['healthy'] ? 'is-healthy' : 'is-sick' }}">
                                    <a class="cnn-media" @if($item['image']) href="{{ $item['image'] }}" target="_blank" rel="noopener" title="Klik untuk memperbesar" @endif>
                                        @if ($item['image'])
                                            <img src="{{ $item['image'] }}" alt="{{ $item['penyakit'] }}" loading="lazy" onerror="this.remove()">
                                        @endif
                                        <span class="cnn-empty"><i class="bx bx-image"></i></span>
                                        <span class="cnn-tag"><i class="bx bx-map-pin"></i> Petak {{ $item['titik'] }}</span>
                                        <span class="cnn-state">
                                            <i class="bx {{ $item['healthy'] ? 'bx-check-circle' : 'bx-error-circle' }}"></i>
                                            {{ $item['healthy'] ? 'Sehat' : 'Perlu tindakan' }}
                                        </span>
                                        <span class="cnn-zoom"><i class="bx bx-expand-alt"></i></span>
                                    </a>
                                    <div class="cnn-body">
                                        <button type="button" class="btn btn-sm {{ $item['healthy'] ? 'btn-outline-success' : 'btn-outline-danger' }} rec-open cnn-rec-btn"
                                                data-date="{{ $item['timestamp'] }}" data-petak="Petak {{ $item['titik'] }}"
                                                data-label="{{ $item['healthy'] ? 'Sehat' : 'Perlu tindakan' }}" data-color="{{ $item['healthy'] ? 'success' : 'danger' }}"
                                                data-status='@json($item['healthy'] ? [] : [['label' => 'Kamera mendeteksi ' . $item['penyakit'], 'reason' => 'Skor deteksi ' . ($item['probabilitas'] ?? '-') . '%']])'
                                                data-ok-text="Kamera tidak mendeteksi hama/penyakit.">
                                            <i class="bx bx-list-check"></i> Lihat aksi rekomendasi
                                        </button>
                                        <div class="cnn-disease"><i class="bx {{ $item['healthy'] ? 'bx-check-shield' : 'bx-bug' }}"></i> {{ $item['penyakit'] }}</div>
                                        <div class="cnn-meter">
                                            <span>Skor Deteksi Hama (CNN)</span><b>{{ $item['probabilitas'] !== null ? number_format($item['probabilitas'], 1) . '%' : '-' }}</b>
                                        </div>
                                        <div class="progress cnn-progress"><div class="progress-bar {{ $item['healthy'] ? 'bg-success' : 'bg-danger' }}" style="width: {{ $item['probabilitas'] ?? 0 }}%"></div></div>
                                        @if ($item['advice'])
                                            <div class="cnn-advice"><i class="bx bx-bulb"></i> {{ $item['advice'] }}</div>
                                        @endif
                                        <div class="cnn-time"><i class="bx bx-time-five"></i> {{ $item['timestamp'] }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($totalPages > 1)
                        <nav class="mt-4 d-flex justify-content-center">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item @if($currentPage <= 1) disabled @endif"><a class="page-link" href="{{ $q(['page' => $currentPage - 1]) }}">&laquo; Sebelumnya</a></li>
                                @for ($i = 1; $i <= $totalPages; $i++)
                                    <li class="page-item @if($i == $currentPage) active @endif"><a class="page-link" href="{{ $q(['page' => $i]) }}">{{ $i }}</a></li>
                                @endfor
                                <li class="page-item @if($currentPage >= $totalPages) disabled @endif"><a class="page-link" href="{{ $q(['page' => $currentPage + 1]) }}">Selanjutnya &raquo;</a></li>
                            </ul>
                        </nav>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <div class="col-12 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bx bx-bar-chart-alt-2 me-2"></i> Distribusi Deteksi &middot; {{ $judulPetak }}</h5>
            </div>
            <div class="card-body">
                @if ($distribution->isEmpty())
                    <p class="text-muted small mb-0">{{ __('label.noData') }}</p>
                @else
                    <div id="cnnDistributionChart" style="min-height: 280px;"></div>
                @endif
            </div>
        </div>
    </div>
</div>
@include('dashboard::partials.rec-modal')
@endsection

@push('style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}">
<style>
    .cnn-days-form { display: inline-flex; align-items: center; gap: 6px; padding: 4px 4px 4px 8px; border-radius: .375rem; background: #fff; border: 2px solid transparent; }
    .cnn-days-form.is-active { border-color: #0e7490; }
    .cnn-days-form .form-control { width: 64px; height: 30px; padding: 2px 6px; text-align: center; font-weight: 600; }
    .cnn-days-unit { font-size: .78rem; color: #03c3ec; font-weight: 600; }
    .cnn-days-form .btn { height: 30px; display: inline-flex; align-items: center; }

    .cnn-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
    .cnn-stat { display: flex; align-items: center; gap: 12px; padding: 14px; border-radius: 14px; border: 1px solid #edf0f4;
                background: linear-gradient(160deg, #fff 55%, var(--soft) 140%); }
    .cnn-stat-ico { width: 42px; height: 42px; flex: 0 0 42px; display: grid; place-items: center; border-radius: 12px; font-size: 1.3rem; color: var(--tone); background: var(--soft); }
    .cnn-stat-label { display: block; font-size: .74rem; color: #8a96a8; }
    .cnn-stat b { font-size: 1.35rem; color: #33435a; line-height: 1.2; }
    .cnn-stat b.cnn-stat-text { font-size: .92rem; display: block; }
    .tone-blue { --tone: #1e88e5; --soft: #e8f2ff; } .tone-green { --tone: #2e7d32; --soft: #e8f5e9; }
    .tone-red { --tone: #d32f2f; --soft: #ffebee; } .tone-amber { --tone: #f59e0b; --soft: #fff6dc; } .tone-teal { --tone: #00897b; --soft: #e3f5f2; }

    .cnn-card { height: 100%; display: flex; flex-direction: column; border-radius: 14px; overflow: hidden; background: #fff;
                border: 1px solid #edf0f4; border-top: 4px solid #cfd8dc; transition: transform .2s ease, box-shadow .2s ease; }
    .cnn-card:hover { transform: translateY(-3px); box-shadow: 0 12px 26px rgba(51, 67, 90, .12); }
    .cnn-card.is-healthy { border-top-color: #43a047; } .cnn-card.is-sick { border-top-color: #e53935; }
    .cnn-media { position: relative; display: block; aspect-ratio: 4 / 3; background: #f4f7f4; overflow: hidden; }
    .cnn-media[href] { cursor: zoom-in; }
    .cnn-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 1; transition: transform .35s ease; }
    .cnn-media[href]:hover img { transform: scale(1.06); }
    .cnn-empty { position: absolute; inset: 0; display: grid; place-items: center; font-size: 2rem; color: #b0bac6; }
    .cnn-tag, .cnn-state, .cnn-zoom { position: absolute; z-index: 2; padding: 3px 9px; border-radius: 999px; font-size: .72rem; font-weight: 600; color: #fff; }
    .cnn-tag { top: 8px; left: 8px; background: rgba(15, 25, 20, .55); }
    .cnn-state { top: 8px; right: 8px; }
    .is-healthy .cnn-state { background: rgba(46, 125, 50, .9); } .is-sick .cnn-state { background: rgba(211, 47, 47, .9); }
    .cnn-zoom { bottom: 8px; right: 8px; padding: 4px 7px; background: rgba(15, 25, 20, .55); opacity: 0; transition: opacity .2s; }
    .cnn-media[href]:hover .cnn-zoom { opacity: 1; }
    .cnn-body { flex: 1; display: flex; flex-direction: column; gap: 6px; padding: 12px 14px; }
    .cnn-disease { font-weight: 700; font-size: .98rem; line-height: 1.3; }
    .is-healthy .cnn-disease { color: #2e7d32; } .is-sick .cnn-disease { color: #d32f2f; }
    .cnn-meter { display: flex; justify-content: space-between; font-size: .74rem; color: #8a96a8; } .cnn-meter b { color: #33435a; }
    .cnn-progress { height: 6px; border-radius: 6px; background: #e6eaef; }
    .cnn-advice { font-size: .78rem; color: #566a7f; background: #f7f9fb; border-radius: 10px; padding: 8px 10px; line-height: 1.4; }
    .cnn-advice i { color: #f59e0b; }
    .cnn-time { margin-top: auto; font-size: .72rem; color: #8a96a8; }
    .cnn-rec-btn { align-self: flex-start; order: 99; margin-top: 4px; }
</style>
@endpush

@push('script')
<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
@if ($distribution->isNotEmpty())
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const dist = @json($distribution);
        const petak = @json(array_keys($titik === 0 ? $titikList : [$titik => true]));
        const labels = Object.keys(dist);
        const colors = { 1: '#008FFB', 2: '#ff9f43' };
        new ApexCharts(document.querySelector('#cnnDistributionChart'), {
            chart: { type: 'bar', height: Math.max(260, 60 + labels.length * 46), toolbar: { show: false } },
            series: petak.map(no => ({ name: 'Petak ' + no, data: labels.map(l => (dist[l] && dist[l][no]) || 0) })),
            colors: petak.map(no => colors[no] || '#8592a3'),
            plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
            xaxis: { categories: labels, title: { text: 'Jumlah deteksi' }, tickAmount: Math.min(10, Math.max(1, ...Object.values(dist).flatMap(o => Object.values(o)))),
                     labels: { formatter: v => Math.round(v) } },
            dataLabels: { enabled: true },
            legend: { position: 'top' },
            tooltip: { y: { formatter: v => v + ' deteksi' } },
        }).render();
    });
</script>
@endif
@endpush
