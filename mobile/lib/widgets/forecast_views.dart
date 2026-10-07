import 'dart:math' as math;

import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/format.dart';
import '../core/theme.dart';
import 'agro.dart';
import 'sneat.dart';

class ForecastTable extends StatefulWidget {
  const ForecastTable({
    super.key,
    required this.variables,
    required this.measured,
    required this.forecast,
    required this.titik,
    this.showLstm = true,
    this.pageSize = 50,
  });

  final List<Map> variables;
  final List<Map> measured;
  final List<Map> forecast;

  final int titik;
  final bool showLstm;
  final int pageSize;

  @override
  State<ForecastTable> createState() => _ForecastTableState();
}

class _ForecastTableState extends State<ForecastTable> {
  late int _limit = widget.pageSize;

  static const _wTime = 128.0, _wVar = 96.0, _wP = 78.0, _wKelas = 110.0, _wStatus = 200.0, _wReason = 250.0, _wConc = 230.0;

  static const _th = TextStyle(color: Agro.muted, fontSize: 10.5, fontWeight: FontWeight.w700, letterSpacing: .3);

  double get _width =>
      _wTime +
      widget.variables.length * _wVar +
      (widget.showLstm ? 3 * _wP + _wKelas : 0) +
      _wStatus +
      _wReason +
      _wConc;

  Widget _head(String title, double w, {String? sub}) => SizedBox(
        width: w,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title.toUpperCase(), style: _th),
            if (sub != null && sub.isNotEmpty) Text(sub, style: _th.copyWith(fontWeight: FontWeight.w400)),
          ]),
        ),
      );

  Widget _cell(Widget child, double w) =>
      SizedBox(width: w, child: Padding(padding: const EdgeInsets.symmetric(horizontal: 8), child: child));

  @override
  Widget build(BuildContext context) {
    final rows = [
      ...widget.measured.map((r) => (r, true)),
      ...widget.forecast.map((r) => (r, false)),
    ];
    final shown = rows.take(_limit).toList();
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Container(
        decoration: BoxDecoration(borderRadius: BorderRadius.circular(12), border: Border.all(color: Agro.line)),
        clipBehavior: Clip.antiAlias,
        child: SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: SizedBox(
            width: _width,
            child: Column(children: [
              Container(
                color: const Color(0xFFF8F9FB),
                padding: const EdgeInsets.symmetric(vertical: 10),
                child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                  _head('Waktu', _wTime),
                  for (final v in widget.variables) _head(v['label'].toString(), _wVar, sub: v['unit']?.toString()),
                  if (widget.showLstm) ...[
                    _head('P. Sehat', _wP, sub: '%'),
                    _head('P. Stres Sedang', _wP, sub: '%'),
                    _head('P. Stres Tinggi', _wP, sub: '%'),
                    _head('P. Kondisi', _wKelas, sub: '(Kelas LSTM)'),
                  ],
                  _head('Status', _wStatus),
                  _head('Penyebab', _wReason),
                  _head('Kesimpulan', _wConc),
                ]),
              ),
              for (final (i, (row, measured)) in shown.indexed) _row(row, measured, i, firstForecast: !measured && (i == 0 || shown[i - 1].$2)),
            ]),
          ),
        ),
      ),
      if (rows.length > _limit)
        TextButton.icon(
          onPressed: () => setState(() => _limit += widget.pageSize),
          icon: const Icon(Icons.expand_more),
          label: Text('Tampilkan lebih banyak (${rows.length - _limit} baris lagi)'),
        ),
    ]);
  }

  Widget _row(Map r, bool measured, int i, {required bool firstForecast}) {
    final health = r['health'] as Map?;
    final status = (r['status'] as List? ?? []).cast<Map>();
    final conclusion = r['conclusion'] as Map?;
    final (kelas, kelasColor) = lstmClass(health?['label']?.toString());
    String pct(String k) => health?[k] == null ? '-' : fmt(health![k], digits: 1);
    final num = const TextStyle(color: Agro.text, fontSize: 12.5);
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 9),
      decoration: BoxDecoration(
        color: measured ? const Color(0xFFF3FAF3) : Colors.white,
        border: Border(
            top: BorderSide(color: firstForecast && i > 0 ? const Color(0xFF9CCC9C) : Agro.line, width: firstForecast && i > 0 ? 2 : 1)),
      ),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _cell(
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(r['date'].toString(), style: const TextStyle(color: Agro.text, fontSize: 12)),
              const SizedBox(height: 3),
              LabelBadge(measured ? 'Terukur' : 'Prediksi', color: measured ? Sneat.success : Sneat.info),
            ]),
            _wTime),
        for (final v in widget.variables) _cell(Text(tableNum(r[v['key']], v['key'].toString()), style: num), _wVar),
        if (widget.showLstm) ...[
          _cell(Text(pct('p_healthy'), style: const TextStyle(color: Color(0xFF2E7D32), fontWeight: FontWeight.w700)), _wP),
          _cell(Text(pct('p_moderate'), style: const TextStyle(color: Color(0xFFE08A00), fontWeight: FontWeight.w700)), _wP),
          _cell(Text(pct('p_high'), style: const TextStyle(color: Color(0xFFD32F2F), fontWeight: FontWeight.w700)), _wP),
          _cell(health == null ? const Text('-') : Align(alignment: Alignment.centerLeft, child: LabelBadge(kelas, color: kelasColor)), _wKelas),
        ],
        _cell(
            status.isEmpty
                ? const Align(alignment: Alignment.centerLeft, child: LabelBadge('Normal', color: Sneat.success))
                : Wrap(runSpacing: 4, children: [for (final s in status) LabelBadge(s['label'].toString(), color: Sneat.warning)]),
            _wStatus),
        _cell(
            status.isEmpty
                ? const Text('-', style: TextStyle(color: Agro.muted))
                : Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    for (final s in status) Text(s['reason']?.toString() ?? '-', style: const TextStyle(fontSize: 12, color: Agro.text)),
                  ]),
            _wReason),
        _cell(
            conclusion == null
                ? const Text('-')
                : Align(
                    alignment: Alignment.centerLeft,
                    child: InkWell(
                      onTap: () => showRecommendationSheet(
                        context,
                        date: r['date'].toString(),
                        label: conclusion['label'].toString(),
                        level: conclusion['color'].toString(),
                        status: status,
                        petak: widget.titik > 0 ? 'Petak ${widget.titik}' : null,
                      ),
                      child: SolidBadge(conclusion['label'].toString(),
                          color: Sneat.fromType(conclusion['color']?.toString()), icon: Icons.info_outline),
                    ),
                  ),
            _wConc),
      ]),
    );
  }
}

class TrendChart extends StatefulWidget {
  const TrendChart({
    super.key,
    required this.variables,
    required this.measured,
    required this.history,
    required this.forecast,
  });

  final List<Map> variables;

  final List<Map> measured;

  final List<Map> history;
  final List<Map> forecast;

  @override
  State<TrendChart> createState() => _TrendChartState();
}

class _TrendChartState extends State<TrendChart> {
  String _key = '__health';

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Row(children: [
        const Text('Sensor:', style: TextStyle(color: Agro.muted, fontSize: 13)),
        const SizedBox(width: 8),
        Expanded(
          child: DropdownButtonFormField<String>(
            initialValue: _key,
            isExpanded: true,
            items: [
              const DropdownMenuItem(value: '__health', child: Text('Kesehatan Tanaman LSTM (%)')),
              for (final v in widget.variables)
                DropdownMenuItem(
                  value: v['key'].toString(),
                  child: Text('${v['label']}${(v['unit'] ?? '').toString().isNotEmpty ? ' (${v['unit']})' : ''}'),
                ),
            ],
            onChanged: (v) => setState(() => _key = v ?? '__health'),
          ),
        ),
      ]),
      const SizedBox(height: 12),
      SizedBox(height: 260, child: _key == '__health' ? _healthChart() : _sensorChart(_key)),
    ]);
  }

  static String _short(String date) {
    final t = parseTime(date);
    return t == null ? date : DateFormat('dd/MM\nHH:mm').format(t);
  }

  FlTitlesData _titles(List<String> labels, {double? interval}) => FlTitlesData(
        topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        leftTitles: AxisTitles(
          sideTitles: SideTitles(
            showTitles: true,
            reservedSize: 42,
            interval: interval,
            getTitlesWidget: (v, meta) => SideTitleWidget(
              meta: meta,
              child: Text(fmt(v, digits: v.abs() >= 100 ? 0 : 1), style: const TextStyle(fontSize: 10, color: Agro.muted)),
            ),
          ),
        ),
        bottomTitles: AxisTitles(
          sideTitles: SideTitles(
            showTitles: true,
            reservedSize: 32,
            interval: math.max(1, (labels.length / 4).floorToDouble()),
            getTitlesWidget: (v, meta) {
              final i = v.round();
              if (i < 0 || i >= labels.length || v != i.toDouble()) return const SizedBox.shrink();
              return SideTitleWidget(
                meta: meta,
                child: Text(_short(labels[i]), textAlign: TextAlign.center, style: const TextStyle(fontSize: 9.5, color: Agro.muted)),
              );
            },
          ),
        ),
      );

  Widget _legend(List<(String, Color, bool)> items) => Wrap(
        spacing: 12,
        runSpacing: 4,
        alignment: WrapAlignment.center,
        children: [
          for (final (name, color, dashed) in items)
            Row(mainAxisSize: MainAxisSize.min, children: [
              Container(width: 14, height: 3, color: dashed ? color.withValues(alpha: .55) : color),
              const SizedBox(width: 4),
              Text(name, style: const TextStyle(fontSize: 11, color: Agro.text)),
            ]),
        ],
      );

  Widget _healthChart() {
    final rows = [...widget.measured, ...widget.forecast];
    if (!rows.any((r) => r['health'] != null)) return const Center(child: EmptyText('Belum ada data LSTM'));
    final labels = rows.map((r) => r['date'].toString()).toList();
    LineChartBarData line(String key, Color c) => LineChartBarData(
          spots: [
            for (final (i, r) in rows.indexed)
              (r['health'] as Map?)?[key] == null ? FlSpot.nullSpot : FlSpot(i.toDouble(), toDouble((r['health'] as Map)[key])!),
          ],
          color: c,
          barWidth: 2.5,
          isCurved: true,
          preventCurveOverShooting: true,
          dotData: FlDotData(show: rows.length <= 40),
        );
    final firstForecast = widget.measured.length;
    return Column(children: [
      _legend(const [('P. Sehat (%)', Color(0xFF71DD37), false), ('P. Stres Sedang (%)', Color(0xFFFFAB00), false), ('P. Stres Tinggi (%)', Color(0xFFFF3E1D), false)]),
      const SizedBox(height: 8),
      Expanded(
        child: LineChart(LineChartData(
          minY: 0,
          maxY: 100,
          lineBarsData: [line('p_healthy', const Color(0xFF71DD37)), line('p_moderate', const Color(0xFFFFAB00)), line('p_high', const Color(0xFFFF3E1D))],
          titlesData: _titles(labels, interval: 20),
          gridData: const FlGridData(drawVerticalLine: false),
          borderData: FlBorderData(show: false),
          extraLinesData: widget.forecast.isEmpty || firstForecast == 0
              ? const ExtraLinesData()
              : ExtraLinesData(verticalLines: [
                  VerticalLine(
                    x: firstForecast.toDouble(),
                    color: Sneat.secondary,
                    strokeWidth: 1,
                    dashArray: [4, 4],
                    label: VerticalLineLabel(
                      show: true,
                      alignment: Alignment.topRight,
                      style: const TextStyle(fontSize: 10, color: Sneat.secondary, fontWeight: FontWeight.w600),
                      labelResolver: (_) => 'Mulai ramalan',
                    ),
                  ),
                ]),
        )),
      ),
    ]);
  }

  Widget _sensorChart(String key) {
    final hist = widget.history;
    final fc = widget.forecast;
    final labels = [...hist.map((r) => r['date'].toString()), ...fc.map((r) => r['date'].toString())];
    final measured = <FlSpot>[
      for (final (i, r) in hist.indexed) toDouble(r[key]) == null ? FlSpot.nullSpot : FlSpot(i.toDouble(), toDouble(r[key])!),
    ];
    final predicted = <FlSpot>[
      if (hist.isNotEmpty && toDouble(hist.last[key]) != null) FlSpot((hist.length - 1).toDouble(), toDouble(hist.last[key])!),
      for (final (i, r) in fc.indexed) toDouble(r[key]) == null ? FlSpot.nullSpot : FlSpot((hist.length + i).toDouble(), toDouble(r[key])!),
    ];
    final values = [...measured, ...predicted].where((s) => s != FlSpot.nullSpot).map((s) => s.y);
    if (values.isEmpty) return const Center(child: EmptyText('Belum ada data'));
    final lo = values.reduce(math.min), hi = values.reduce(math.max);
    final pad = (hi - lo).abs() < 1e-9 ? (hi.abs() * 0.05 + 1) : (hi - lo) * 0.12;
    final many = labels.length > 40;
    return Column(children: [
      _legend([('Terukur', const Color(0xFF008FFB), false), if (fc.isNotEmpty) ('Ramalan', const Color(0xFFFF4560), true)]),
      const SizedBox(height: 8),
      Expanded(
        child: LineChart(LineChartData(
          minY: lo - pad,
          maxY: hi + pad,
          lineBarsData: [
            LineChartBarData(spots: measured, color: const Color(0xFF008FFB), barWidth: 2.5, isCurved: true, preventCurveOverShooting: true, dotData: FlDotData(show: !many)),
            if (fc.isNotEmpty)
              LineChartBarData(
                  spots: predicted,
                  color: const Color(0xFFFF4560),
                  barWidth: 2.5,
                  isCurved: true,
                  preventCurveOverShooting: true,
                  dashArray: [6, 4],
                  dotData: FlDotData(show: !many)),
          ],
          titlesData: _titles(labels),
          gridData: const FlGridData(drawVerticalLine: false),
          borderData: FlBorderData(show: false),
        )),
      ),
    ]);
  }
}
