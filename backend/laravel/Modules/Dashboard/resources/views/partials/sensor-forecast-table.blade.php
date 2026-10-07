@php($showLstm = $showLstm ?? true)
@if ($sensorForecast)
    @php($vars = collect($sensorForecast['variables'])->keyBy('key'))
    @php($kelasLstm = ['Healthy' => ['Sehat', 'success'], 'Moderate Stress' => ['Stres Sedang', 'warning'], 'High Stress' => ['Stres Tinggi', 'danger']])
    <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
        <table class="table table-hover table-sm small align-middle">
            <thead class="table-light" style="position: sticky; top: 0;">
                <tr>
                    <th class="text-nowrap">Waktu</th>
                    @foreach ($vars as $var)
                        <th class="text-nowrap" title="{{ $var['method_label'] ?? '' }}">
                            {{ $var['label'] }}@if ($var['unit'])<br><span class="text-muted fw-normal">{{ $var['unit'] }}</span>@endif
                        </th>
                    @endforeach
                    @if ($showLstm)
                    <th class="text-nowrap" title="Peluang kelas Healthy">P. Sehat<br><span class="text-muted fw-normal">%</span></th>
                    <th class="text-nowrap" title="Peluang kelas Moderate Stress">P. Stres Sedang<br><span class="text-muted fw-normal">%</span></th>
                    <th class="text-nowrap" title="Peluang kelas High Stress">P. Stres Tinggi<br><span class="text-muted fw-normal">%</span></th>
                    <th class="text-nowrap" title="Kelas dengan peluang terbesar">P. Kondisi<br><span class="text-muted fw-normal">(Kelas LSTM)</span></th>
                    @endif
                    <th>Status</th>
                    <th class="text-nowrap">Penyebab</th>
                    <th class="text-nowrap" title="Gabungan Status (diutamakan) dan Kelas LSTM">Kesimpulan</th>
                </tr>
            </thead>
            <tbody>
                @php($rows = array_merge(
                    array_map(fn ($r) => $r + ['_measured' => true], $sensorForecast['measured'] ?? []),
                    $sensorForecast['forecast']
                ))
                @foreach ($rows as $row)
                    @php($measured = ! empty($row['_measured']))
                    <tr @class(['agro-measured' => $measured, 'agro-first-forecast' => ! $measured && $loop->index === count($sensorForecast['measured'] ?? [])])>
                        <td class="text-nowrap">
                            {{ $row['date'] }}<br>
                            <span class="badge {{ $measured ? 'bg-label-success' : 'bg-label-info' }}">{{ $measured ? 'Terukur' : 'Prediksi' }}</span>
                        </td>
                        @foreach ($vars as $key => $var)
                            <td>{{ isset($row[$key]) ? number_format($row[$key], in_array($key, ['pressure', 'light_intensity', 'ec', 'tds']) ? 0 : 2) : '-' }}</td>
                        @endforeach
                        @if ($showLstm)
                        <td class="fw-semibold text-success">{{ isset($row['health']['p_healthy']) ? number_format($row['health']['p_healthy'], 1) : '-' }}</td>
                        <td class="fw-semibold text-warning">{{ isset($row['health']['p_moderate']) ? number_format($row['health']['p_moderate'], 1) : '-' }}</td>
                        <td class="fw-semibold text-danger">{{ isset($row['health']['p_high']) ? number_format($row['health']['p_high'], 1) : '-' }}</td>
                        <td class="text-nowrap">
                            @if (isset($row['health']['label']))
                                @php($k = $kelasLstm[$row['health']['label']] ?? [$row['health']['label'], 'secondary'])
                                <span class="badge bg-label-{{ $k[1] }}">{{ $k[0] }}</span>
                            @else
                                -
                            @endif
                        </td>
                        @endif
                        <td>
                            @forelse ($row['status'] ?? [] as $status)
                                <span class="badge bg-label-warning mb-1">{{ $status['label'] }}</span>
                            @empty
                                <span class="badge bg-label-success">Normal</span>
                            @endforelse
                        </td>
                        <td class="text-nowrap">
                            @forelse ($row['status'] ?? [] as $status)
                                <div>{{ $status['reason'] ?? '-' }}</div>
                            @empty
                                <span class="text-muted">-</span>
                            @endforelse
                        </td>
                        <td class="text-nowrap">
                            @isset($row['conclusion'])
                                <button type="button" class="badge bg-{{ $row['conclusion']['color'] }} border-0 rec-open" title="Lihat aksi rekomendasi"
                                        data-date="{{ $row['date'] }}" data-petak="{{ ($sensorForecast['titik'] ?? 0) > 0 ? 'Petak ' . $sensorForecast['titik'] : '' }}" data-label="{{ $row['conclusion']['label'] }}" data-color="{{ $row['conclusion']['color'] }}"
                                        data-status='@json(array_map(fn ($st) => ['label' => $st['label'], 'reason' => $st['reason'] ?? null], $row['status'] ?? []))'>
                                    {{ $row['conclusion']['label'] }} <i class="bx bx-info-circle"></i>
                                </button>
                            @else
                                -
                            @endisset
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @include('dashboard::partials.rec-modal')

    <style>
        .agro-measured td { background: #f3faf3 !important; }
        .agro-first-forecast td { border-top: 2px solid #9ccc9c; }
    </style>
@else
    <p class="text-muted small mb-0">
        Belum ada ramalan sensor: layanan peramalan tidak tersedia atau petak ini belum memiliki data sensor.
    </p>
@endif
