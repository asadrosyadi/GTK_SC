@php($historyMode = $historyMode ?? false)
@if ($sensorForecast)
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <label class="small text-muted mb-0" for="{{ $chartId }}-var">Sensor:</label>
        <select id="{{ $chartId }}-var" class="form-select form-select-sm w-auto">
            <option value="__health" selected>Kesehatan Tanaman LSTM (%)</option>
            @foreach ($sensorForecast['variables'] as $var)
                <option value="{{ $var['key'] }}">{{ $var['label'] }}{{ $var['unit'] ? ' (' . $var['unit'] . ')' : '' }}</option>
            @endforeach
        </select>
    </div>
    <div id="{{ $chartId }}" style="min-height: 300px;"></div>

    @push('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const fc = @json($sensorForecast);
            const vars = Object.fromEntries(fc.variables.map(v => [v.key, v]));
            const labels = fc.history.map(r => r.date).concat(fc.forecast.map(r => r.date));
            let chart = null;
            function draw(options) {
                if (chart) { chart.destroy(); }
                chart = new ApexCharts(document.querySelector('#' + @json($chartId)), options);
                chart.render();
            }

            function renderHealth() {
                const rows = (fc.measured || []).concat(fc.forecast);
                const dates = rows.map(r => r.date);
                const firstForecast = (fc.forecast[0] || {}).date;
                const classes = [['p_healthy', 'P. Sehat', '#71dd37'], ['p_moderate', 'P. Stres Sedang', '#ffab00'], ['p_high', 'P. Stres Tinggi', '#ff3e1d']];
                let series = [], colors = [], dash = [];
                classes.forEach(([f, name, color]) => {
                    series.push({ name: `${name} (%)`, data: rows.map(r => (r.health && r.health[f] !== undefined) ? r.health[f] : null) });
                    colors.push(color); dash.push(0);
                });
                const options = {
                    series: series,
                    chart: { type: 'line', height: 300, toolbar: { show: true }, zoom: { enabled: true } },
                    stroke: { width: 3, curve: 'smooth', dashArray: dash },
                    markers: { size: rows.length > 40 ? 0 : 3 },
                    colors: colors,
                    labels: rows.map(r => r.date),
                    xaxis: { type: 'category', tickAmount: 8, labels: { rotate: -30, style: { colors: '#6c757d' } } },
                    yaxis: { min: 0, max: 100, tickAmount: 5, title: { text: 'Peluang kelas (%)' },
                             labels: { formatter: val => (val === null || val === undefined) ? '' : Number(val).toFixed(0) } },
                    annotations: firstForecast ? { xaxis: [{ x: firstForecast, borderColor: '#8592a3', strokeDashArray: 4,
                        label: { text: 'Mulai ramalan', orientation: 'horizontal', style: { color: '#fff', background: '#8592a3' } } }] } : {},
                    tooltip: { shared: true, y: { formatter: val => (val === null || val === undefined) ? '-' : Number(val).toFixed(1) + ' %' } },
                    legend: { position: 'top' },
                    noData: { text: 'Belum ada data LSTM' },
                };
                draw(options);
            }

            function render(key) {
                if (key === '__health') { return renderHealth(); }
                const v = vars[key] || {};
                const nh = fc.history.length;
                const measured = fc.history.map(r => r[key]).concat(fc.forecast.map(() => null));
                const predicted = fc.history.map((r, i) => (i === nh - 1 ? r[key] : null)).concat(fc.forecast.map(r => r[key]));
                const unit = v.unit ? ' ' + v.unit : '';

                const options = {
                    series: fc.forecast.length ? [{ name: 'Terukur', data: measured }, { name: 'Ramalan', data: predicted }]
                                               : [{ name: 'Terukur', data: measured }],
                    chart: { type: 'line', height: 300, toolbar: { show: true }, zoom: { enabled: true } },
                    stroke: { width: [3, 3], curve: 'smooth', dashArray: [0, 6] },
                    colors: ['#008FFB', '#FF4560'],
                    labels: labels,
                    xaxis: { type: 'category', tickAmount: 8, labels: { rotate: -30, style: { colors: '#6c757d' } } },
                    yaxis: { title: { text: (v.label || key) + unit }, labels: { formatter: val => (val === null || val === undefined) ? '' : Number(val).toFixed(2) } },
                    tooltip: { shared: true, y: { formatter: val => (val === null || val === undefined) ? '-' : Number(val).toFixed(2) + unit } },
                    legend: { position: 'top' },
                    noData: { text: 'Belum ada data' },
                };
                draw(options);
            }

            document.getElementById(@json($chartId) + '-var').addEventListener('change', e => render(e.target.value));
            render('__health');
        });
    </script>
    @endpush
@else
    <p class="text-muted small mb-0">
        Belum ada ramalan sensor: layanan peramalan tidak tersedia atau petak ini belum memiliki data sensor.
    </p>
@endif
