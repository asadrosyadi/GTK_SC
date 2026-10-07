import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../core/format.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/agro.dart';
import '../widgets/sneat.dart';
import 'cnn_screen.dart' show showPhoto;

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  Map<String, dynamic>? _dash;
  Map<String, dynamic>? _rec;
  String? _error;
  String? _recError;
  bool _loading = true;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _load();
    _timer = Timer.periodic(const Duration(seconds: 30), (_) => _load(silent: true));
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    final api = context.read<Session>().api;
    if (!silent) setState(() => _loading = true);
    Future<void> dash() async {
      try {
        _dash = await api.dashboard();
        _error = null;
      } on ApiException catch (e) {
        _error = e.message;
      }
    }

    Future<void> rec() async {
      try {
        _rec = await api.recommendations();
        _recError = null;
      } on ApiException catch (e) {
        _recError = e.message;
      }
    }

    await Future.wait([dash(), rec()]);
    if (mounted) setState(() => _loading = false);
  }

  @override
  Widget build(BuildContext context) {
    final dash = _dash;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 24),
        children: [
          if (_error != null && dash == null) ErrorBox(message: _error!, onRetry: _load),
          if (_loading && dash == null)
            const Padding(padding: EdgeInsets.all(40), child: Center(child: CircularProgressIndicator())),
          if (dash != null) ...[
            _monitoringPanel(dash),
            _cnnPanel(dash),
          ],
          _recommendationPanel(),
          _overallPanel(),
        ],
      ),
    );
  }

  Widget _monitoringPanel(Map<String, dynamic> d) {
    final plots = (d['plots'] as List).cast<Map>();
    final weather = d['weather'] as Map?;
    return AgroPanel(
      topBorder: true,
      header: PanelHeader(
        icon: Icons.circle,
        title: 'Pemantauan Lingkungan Waktu Nyata',
        trailing: Container(
          padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
          decoration: BoxDecoration(color: Agro.greenSoft, borderRadius: BorderRadius.circular(999)),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.location_on_outlined, size: 13, color: Agro.green),
            const SizedBox(width: 3),
            Text('${plots.length} Titik',
                style: const TextStyle(color: Agro.green, fontSize: 11.5, fontWeight: FontWeight.w600)),
          ]),
        ),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        _sectionTitle(Icons.cloud_outlined, 'Kondisi Cuaca',
            trailing: weather?['recorded_at'] != null ? 'Diperbarui ${relativeTime(weather!['recorded_at'])}' : null),
        if (weather == null) const EmptyText('Data tidak tersedia') else _weatherGrid(weather['items'] as List),
        const SizedBox(height: 18),
        _sectionTitle(Icons.eco_outlined, 'Kondisi Tanah'),
        _soilTable(plots),
        const SizedBox(height: 18),
        _sectionTitle(Icons.memory, 'Status Sistem'),
        for (final p in plots) _deviceCard(p),
      ]),
    );
  }

  Widget _sectionTitle(IconData icon, String title, {String? trailing}) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Row(children: [
          Icon(icon, size: 18, color: Agro.green),
          const SizedBox(width: 6),
          Expanded(
            child: Text(title,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w700, color: Agro.text, fontSize: 14)),
          ),
          if (trailing != null)
            Row(children: [
              const Icon(Icons.schedule, size: 13, color: Agro.muted),
              const SizedBox(width: 3),
              Text(trailing, style: const TextStyle(color: Agro.muted, fontSize: 11.5)),
            ]),
        ]),
      );

  static const _weatherIcons = {
    'temperature': Icons.thermostat,
    'humidity': Icons.water_drop_outlined,
    'vpd': Icons.waves,
    'windspeed': Icons.air,
    'rainfall': Icons.thunderstorm_outlined,
    'light_intensity': Icons.wb_sunny_outlined,
    'pressure': Icons.speed,
  };

  Widget _weatherGrid(List items) {
    final byKey = {for (final i in items.cast<Map>()) i['key']: i};
    const order = ['humidity', 'temperature', 'windspeed', 'rainfall', 'light_intensity', 'pressure'];
    return Column(children: [
      if (byKey['vpd'] != null) _heroVpd(byKey['vpd']!),
      const SizedBox(height: 10),
      GridView.count(
        crossAxisCount: 2,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        childAspectRatio: 1.55,
        children: [for (final k in order) if (byKey[k] != null) _kpi(byKey[k]!)],
      ),
    ]);
  }

  Widget _heroVpd(Map i) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          gradient: Agro.heroGradient,
          borderRadius: BorderRadius.circular(14),
          boxShadow: const [BoxShadow(color: Color(0x40008970), blurRadius: 20, offset: Offset(0, 8))],
        ),
        child: Stack(children: [
          const Positioned(right: -10, bottom: -18, child: Icon(Icons.waves, size: 110, color: Color(0x24FFFFFF))),
          Row(children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(color: const Color(0x38FFFFFF), borderRadius: BorderRadius.circular(12)),
              child: const Icon(Icons.waves, color: Colors.white, size: 24),
            ),
            const SizedBox(width: 14),
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(i['label'], style: const TextStyle(color: Color(0xD9FFFFFF), fontSize: 13)),
              Text.rich(TextSpan(children: [
                TextSpan(
                    text: fmt(i['value'], digits: 2),
                    style: const TextStyle(color: Colors.white, fontSize: 34, fontWeight: FontWeight.w700)),
                TextSpan(text: ' ${i['unit']}', style: const TextStyle(color: Color(0xD9FFFFFF), fontSize: 15)),
              ])),
              if (i['status'] != null)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 2),
                  decoration: BoxDecoration(color: const Color(0x3DFFFFFF), borderRadius: BorderRadius.circular(999)),
                  child: Text('● ${i['status']['label']}',
                      style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
                ),
            ]),
          ]),
        ]),
      );

  Widget _kpi(Map i) {
    final (tone, soft) = Agro.tones[i['key']] ?? (Agro.green, Agro.greenSoft);
    final digits = i['key'] == 'light_intensity' ? 0 : (i['key'] == 'vpd' ? 2 : 1);
    return Container(
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Agro.line),
        gradient: LinearGradient(
            begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [Colors.white, Colors.white, soft]),
      ),
      child: Row(children: [
        Container(width: 3, height: 44, decoration: BoxDecoration(color: tone, borderRadius: BorderRadius.circular(3))),
        const SizedBox(width: 8),
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(color: soft, borderRadius: BorderRadius.circular(10)),
          child: Icon(_weatherIcons[i['key']], color: tone, size: 19),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
            Text(i['label'], maxLines: 1, overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Agro.muted, fontSize: 11)),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text.rich(TextSpan(children: [
                TextSpan(
                    text: fmt(i['value'], digits: digits),
                    style: const TextStyle(color: Agro.text, fontSize: 18, fontWeight: FontWeight.w700)),
                TextSpan(text: ' ${i['unit']}', style: const TextStyle(color: Agro.muted, fontSize: 11)),
              ])),
            ),
            if (i['status'] != null) ...[const SizedBox(height: 3), StatusPill.json(i['status'] as Map, dense: true)],
          ]),
        ),
      ]),
    );
  }

  static const _soilIcons = {
    'ph': Icons.science_outlined,
    'soil_moisture': Icons.show_chart,
    'ec': Icons.bubble_chart_outlined,
    'tds': Icons.water_drop_outlined,
    'soil_temp': Icons.layers_outlined,
  };
  static const _soilSymbols = {'Nitrogen_Level': 'N', 'Phosphorus_Level': 'P', 'Potassium_Level': 'K'};

  Widget _soilTable(List<Map> plots) {
    final rows = (plots.first['soil'] as List).cast<Map>();
    Widget cell(Map? item) => item == null || item['value'] == null
        ? const Text('-', style: TextStyle(color: Agro.muted))
        : Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text(fmt(item['value'], digits: 2),
                style: const TextStyle(fontWeight: FontWeight.w700, color: Agro.text, fontSize: 14)),
            if (item['status'] != null) ...[const SizedBox(height: 3), StatusPill.json(item['status'] as Map, dense: true)],
          ]);
    return Container(
      decoration: BoxDecoration(borderRadius: BorderRadius.circular(14), border: Border.all(color: Agro.line)),
      clipBehavior: Clip.antiAlias,
      child: Column(children: [
        Container(
          color: const Color(0xFFF7FAF7),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          child: Row(children: [
            const Expanded(flex: 5, child: Text('PARAMETER', style: _th)),
            for (final p in plots)
              Expanded(
                  flex: 4,
                  child: Text(p['label'].toString().toUpperCase(), textAlign: TextAlign.right, style: _th)),
          ]),
        ),
        for (final (idx, r) in rows.indexed)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
            decoration: BoxDecoration(border: idx == 0 ? null : const Border(top: BorderSide(color: Agro.line))),
            child: Row(children: [
              Expanded(
                flex: 5,
                child: Row(children: [
                  Container(
                    width: 28,
                    height: 28,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(color: Agro.greenSoft, borderRadius: BorderRadius.circular(8)),
                    child: _soilSymbols[r['key']] != null
                        ? Text(_soilSymbols[r['key']]!,
                            style: const TextStyle(color: Agro.green, fontWeight: FontWeight.w800))
                        : Icon(_soilIcons[r['key']] ?? Icons.grass, size: 16, color: Agro.green),
                  ),
                  const SizedBox(width: 8),
                  Flexible(
                    child: Text.rich(TextSpan(children: [
                      TextSpan(text: r['label'], style: const TextStyle(color: Agro.text, fontSize: 13)),
                      if ((r['unit'] as String).isNotEmpty)
                        TextSpan(text: '  ${r['unit']}', style: const TextStyle(color: Agro.muted, fontSize: 10.5)),
                    ])),
                  ),
                ]),
              ),
              for (final p in plots)
                Expanded(
                  flex: 4,
                  child: Align(
                    alignment: Alignment.centerRight,
                    child: cell((p['soil'] as List).cast<Map>().where((x) => x['key'] == r['key']).firstOrNull),
                  ),
                ),
            ]),
          ),
      ]),
    );
  }

  static const _th = TextStyle(color: Agro.muted, fontSize: 10.5, fontWeight: FontWeight.w700, letterSpacing: .4);

  Widget _deviceCard(Map p) {
    final dev = p['device'] as Map;
    final online = dev['online'] == true;
    final bat = toDouble(dev['battery_level']);
    final sig = toDouble(dev['signal_strength']);
    final batColor = bat == null ? Sneat.secondary : (bat < 20 ? Sneat.danger : (bat < 50 ? Sneat.warning : Sneat.success));
    final sigColor = sig == null ? Sneat.secondary : (sig < 30 ? Sneat.danger : (sig < 60 ? Sneat.warning : const Color(0xFF43A047)));
    final bars = sig == null ? 0 : (sig / 25).ceil().clamp(1, 4);
    return AccentBox(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      color: Colors.white,
      accent: online ? const Color(0xFF43A047) : const Color(0xFFE53935),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(color: Agro.greenSoft, borderRadius: BorderRadius.circular(8)),
            child: const Icon(Icons.sensors, size: 18, color: Agro.green),
          ),
          const SizedBox(width: 8),
          Text(p['label'], style: const TextStyle(fontWeight: FontWeight.w700, color: Agro.text)),
          const Spacer(),
          StatusPill(label: online ? 'Aktif' : 'Tidak aktif', level: online ? 'success' : 'danger'),
        ]),
        const SizedBox(height: 10),
        Row(children: [
          Expanded(
            child: _devItem(Icons.battery_std, 'Baterai', bat == null ? '-' : fmt(bat, digits: 0), '%',
                bar: LinearProgressIndicator(
                  value: (bat ?? 0) / 100,
                  minHeight: 6,
                  borderRadius: BorderRadius.circular(6),
                  color: batColor,
                  backgroundColor: const Color(0xFFE6EAEF),
                )),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: _devItem(Icons.wifi, 'Sinyal', sig == null ? '-' : fmt(sig, digits: 0), '%',
                trailing: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                  for (var b = 0; b < 4; b++)
                    Container(
                      width: 4,
                      height: 4.0 * (b + 1),
                      margin: const EdgeInsets.only(left: 2),
                      decoration: BoxDecoration(
                          color: b < bars ? sigColor : const Color(0xFFDDE3EA), borderRadius: BorderRadius.circular(2)),
                    ),
                ])),
          ),
        ]),
        const SizedBox(height: 8),
        Row(children: [
          const Icon(Icons.schedule, size: 13, color: Agro.muted),
          const SizedBox(width: 4),
          Text('Pembaruan: ${relativeTime(dev['last_seen_at'])}', style: const TextStyle(color: Agro.muted, fontSize: 11.5)),
        ]),
      ]),
    );
  }

  Widget _devItem(IconData icon, String label, String value, String unit, {Widget? bar, Widget? trailing}) => Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(color: const Color(0xFFF6F8FA), borderRadius: BorderRadius.circular(11)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Icon(icon, size: 15, color: Agro.muted),
            const SizedBox(width: 4),
            Text(label, style: const TextStyle(color: Agro.muted, fontSize: 11.5)),
          ]),
          const SizedBox(height: 2),
          Row(children: [
            Text.rich(TextSpan(children: [
              TextSpan(text: value, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 19, color: Agro.text)),
              TextSpan(text: ' $unit', style: const TextStyle(color: Agro.muted, fontSize: 11)),
            ])),
            const Spacer(),
            ?trailing,
          ]),
          if (bar != null) ...[const SizedBox(height: 6), bar],
        ]),
      );

  Widget _cnnPanel(Map<String, dynamic> d) {
    final plots = (d['plots'] as List).cast<Map>();
    return AgroPanel(
      topBorder: true,
      header: const PanelHeader(icon: Icons.photo_camera_outlined, title: 'Deteksi Hama CNN per Petak'),
      child: Column(children: [for (final p in plots) _cnnCard(p)]),
    );
  }

  Widget _cnnCard(Map p) {
    final cam = p['camera'] as Map?;
    final healthy = cam?['healthy'] == true;
    final health = p['health'] as Map?;
    final edge = cam == null ? const Color(0xFFCFD8DC) : (healthy ? const Color(0xFF43A047) : const Color(0xFFE53935));
    final skor = toDouble(cam?['skor_deteksi']);
    final pSehat = toDouble(health?['p_healthy']);
    return AccentBox(
      margin: const EdgeInsets.only(bottom: 12),
      color: Colors.white,
      accent: edge,
      child: IntrinsicHeight(
        child: Row(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          GestureDetector(
            onTap: cam?['image_url'] == null ? null : () => showPhoto(context, cam!['image_url'], cam['penyakit']),
            child: SizedBox(
              width: 100,
              child: cam?['image_url'] == null
                  ? Container(
                      color: const Color(0xFFF4F7F4),
                      child: const Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                        Icon(Icons.image_outlined, color: Agro.muted),
                        Text('Belum ada data', style: TextStyle(color: Agro.muted, fontSize: 11)),
                      ]),
                    )
                  : Image.network(cam!['image_url'], fit: BoxFit.cover,
                      errorBuilder: (_, _, _) => const Icon(Icons.broken_image_outlined, color: Agro.muted)),
            ),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(9),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  const Icon(Icons.location_on_outlined, size: 15, color: Agro.green),
                  const SizedBox(width: 3),
                  Text(p['label'], style: const TextStyle(fontWeight: FontWeight.w700, color: Agro.text, fontSize: 13)),
                  const Spacer(),
                  if (cam != null) StatusPill.json(cam['status'] as Map, dense: true),
                ]),
                const SizedBox(height: 6),
                Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Icon(cam == null ? Icons.info_outline : (healthy ? Icons.verified_user_outlined : Icons.bug_report_outlined),
                      size: 17, color: cam == null ? Agro.muted : (healthy ? Agro.green : const Color(0xFFD32F2F))),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(cam?['penyakit'] ?? 'Belum ada data',
                        style: TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 14,
                            color: cam == null ? Agro.muted : (healthy ? Agro.green : const Color(0xFFD32F2F)))),
                  ),
                ]),
                const SizedBox(height: 8),
                _meter('Deteksi Hama (CNN)', skor, healthy ? Sneat.success : Sneat.danger),
                const SizedBox(height: 6),
                _meter(
                  'Kesehatan (LSTM)',
                  pSehat,
                  Sneat.fromType(health?['level']),
                  suffix: health == null ? null : ' · ${health['label']}',
                ),
                const SizedBox(height: 6),
                Row(children: [
                  const Icon(Icons.schedule, size: 12, color: Agro.muted),
                  const SizedBox(width: 3),
                  Expanded(
                    child: Text(cam == null ? '-' : relativeTime(cam['detected_at']),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: Agro.muted, fontSize: 10.5)),
                  ),
                  const SizedBox(width: 4),
                  if (cam != null)
                    InkWell(
                      onTap: () => showRecommendationSheet(
                        context,
                        date: dateTime(cam['detected_at']),
                        label: healthy ? 'Sehat' : 'Perlu tindakan',
                        level: healthy ? 'success' : 'danger',
                        petak: p['label'],
                        status: healthy
                            ? const []
                            : [
                                {'label': 'Kamera mendeteksi ${cam['penyakit']}', 'reason': 'Skor deteksi ${fmt(skor)}%'}
                              ],
                        okText: 'Kamera tidak mendeteksi hama/penyakit.',
                      ),
                      child: const Text('Rekomendasi ›',
                          style: TextStyle(color: Sneat.primary, fontSize: 11, fontWeight: FontWeight.w600)),
                    ),
                ]),
              ]),
            ),
          ),
        ]),
      ),
    );
  }

  Widget _meter(String label, double? value, Color color, {String? suffix}) => Column(children: [
        Row(children: [
          Expanded(
              child: Text(label,
                  maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Agro.muted, fontSize: 11))),
          const SizedBox(width: 4),
          Text.rich(TextSpan(children: [
            TextSpan(
                text: value == null ? '-' : '${fmt(value)}%',
                style: const TextStyle(color: Agro.text, fontWeight: FontWeight.w700, fontSize: 11)),
            if (suffix != null)
              TextSpan(text: suffix, style: TextStyle(color: color, fontSize: 10.5, fontWeight: FontWeight.w600)),
          ])),
        ]),
        const SizedBox(height: 3),
        LinearProgressIndicator(
          value: (value ?? 0) / 100,
          minHeight: 6,
          borderRadius: BorderRadius.circular(6),
          color: color,
          backgroundColor: const Color(0xFFE6EAEF),
        ),
      ]);

  Widget _recommendationPanel() {
    final rec = _rec;
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(Sneat.radius), boxShadow: Sneat.cardShadow),
      clipBehavior: Clip.antiAlias,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        PanelHeader(
          icon: Icons.notifications_none,
          title: 'Aksi Rekomendasi',
          color: Sneat.warning,
          trailing: rec == null
              ? null
              : Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(4)),
                  child: Text(rec['model']['label'].toString().toUpperCase(),
                      style: const TextStyle(color: Sneat.warning, fontSize: 10, fontWeight: FontWeight.w700)),
                ),
        ),
        Padding(
          padding: const EdgeInsets.all(14),
          child: rec == null
              ? (_recError != null ? ErrorBox(message: _recError!, onRetry: _load) : const Center(child: CircularProgressIndicator()))
              : Column(children: [
                  for (final r in (rec['items'] as List).cast<Map>()) RecommendationCard(r),
                  if ((rec['items'] as List).isEmpty) const EmptyText('Belum ada rekomendasi'),
                ]),
        ),
      ]),
    );
  }

  static const _levelLabel = {'danger': 'Mendesak', 'warning': 'Perlu dilakukan', 'info': 'Pantau', 'success': 'Aman'};

  Widget _overallPanel() {
    final rec = _rec;
    if (rec == null) return const SizedBox.shrink();
    final o = rec['overall'] as Map;
    final level = o['level'].toString();
    final color = Sneat.fromType(level);
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(Sneat.radius), boxShadow: Sneat.cardShadow),
      clipBehavior: Clip.antiAlias,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const PanelHeader(icon: Icons.checklist, title: 'Aksi Rekomendasi Keseluruhan', color: Sneat.primary),
        Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: color.withValues(alpha: .14), borderRadius: BorderRadius.circular(8)),
              child: Row(children: [
                Icon(level == 'success' ? Icons.check_circle_outline : Icons.warning_amber_rounded, color: color, size: 28),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(o['headline'], style: TextStyle(color: color, fontWeight: FontWeight.w700)),
                    Text('${o['total']} aksi untuk ${(rec['plots'] as List).length} petak · Mendesak ${o['urgent']} · Perlu dilakukan ${o['todo']}',
                        style: TextStyle(color: color, fontSize: 12)),
                  ]),
                ),
              ]),
            ),
            const SizedBox(height: 12),
            for (final p in (rec['plots'] as List).cast<Map>()) _overallPlot(p),
          ]),
        ),
      ]),
    );
  }

  Widget _overallPlot(Map p) {
    final level = p['level'].toString();
    final actions = (p['actions'] as List).cast<Map>();
    return AccentBox(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      radius: 12,
      accent: Sneat.fromType(level),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          LabelBadge(p['label'], color: Sneat.dark),
          const SizedBox(width: 6),
          LabelBadge(p['fase'] == null ? 'Fase belum diatur' : '${p['fase']}${p['hst'] != null ? ' · HST ${p['hst']}' : ''}',
              color: Sneat.success),
          const Spacer(),
          SolidBadge(p['level_label'], color: Sneat.fromType(level)),
        ]),
        const SizedBox(height: 10),
        if (actions.isEmpty)
          const Row(children: [
            Icon(Icons.check_circle_outline, size: 16, color: Sneat.success),
            SizedBox(width: 6),
            Text('Tidak ada aksi, kondisi aman.', style: TextStyle(color: Sneat.muted, fontSize: 13)),
          ])
        else
          for (final (i, a) in actions.indexed)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Container(
                  width: 24,
                  height: 24,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(color: Sneat.fromType(a['type']).withValues(alpha: .16), shape: BoxShape.circle),
                  child: Text('${i + 1}',
                      style: TextStyle(color: Sneat.fromType(a['type']), fontWeight: FontWeight.w700, fontSize: 12)),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Wrap(spacing: 6, runSpacing: 4, crossAxisAlignment: WrapCrossAlignment.center, children: [
                      Text(a['title'], style: const TextStyle(fontWeight: FontWeight.w700, color: Sneat.heading)),
                      LabelBadge(_levelLabel[a['type']] ?? a['level_label'], color: Sneat.fromType(a['type'])),
                    ]),
                    if ((a['reasons'] as List).isNotEmpty)
                      Text((a['reasons'] as List).join('; '), style: const TextStyle(color: Sneat.muted, fontSize: 12)),
                  ]),
                ),
              ]),
            ),
      ]),
    );
  }
}
