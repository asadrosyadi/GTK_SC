import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../core/export.dart';
import '../core/format.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/agro.dart';
import '../widgets/sneat.dart';

void showPhoto(BuildContext context, String url, String? title) {
  Navigator.of(context).push(
    MaterialPageRoute(
      builder: (_) => Scaffold(
        backgroundColor: Colors.black,
        appBar: AppBar(
          backgroundColor: Colors.black,
          foregroundColor: Colors.white,
          title: Text(title ?? 'Foto', style: const TextStyle(color: Colors.white, fontSize: 16)),
        ),
        body: Center(
          child: InteractiveViewer(
            maxScale: 5,
            child: Image.network(
              url,
              errorBuilder: (_, _, _) => const Icon(Icons.broken_image, color: Colors.white54, size: 60),
            ),
          ),
        ),
      ),
    ),
  );
}

class CnnScreen extends StatefulWidget {
  const CnnScreen({super.key});

  @override
  State<CnnScreen> createState() => _CnnScreenState();
}

class _CnnScreenState extends State<CnnScreen> {
  int _titik = 0;
  int _days = 7;
  Map<String, dynamic>? _data;
  String? _error;
  bool _loading = false;
  int _limit = 12;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
      _limit = 12;
    });
    try {
      _data = await context.read<Session>().api.detectionSummary(_titik, _days);
    } on ApiException catch (e) {
      _error = e.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  String get _judul => _titik == 0 ? 'Gabungan Petak 1 & 2' : 'Petak $_titik';

  @override
  Widget build(BuildContext context) {
    final d = _data;
    final api = context.read<Session>().api;
    final items = (d?['items'] as List? ?? []).cast<Map>();
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 24),
        children: [
          Container(
            margin: const EdgeInsets.only(bottom: 16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(Sneat.radius),
              boxShadow: Sneat.cardShadow,
            ),
            clipBehavior: Clip.antiAlias,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Container(
                  color: Sneat.info,
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.photo_camera_outlined, color: Colors.white, size: 20),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              'Riwayat Deteksi Hama CNN · $_judul',
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 15),
                            ),
                          ),
                          FilledButton.icon(
                            style: FilledButton.styleFrom(
                              backgroundColor: Colors.white,
                              foregroundColor: Sneat.heading,
                              padding: const EdgeInsets.symmetric(horizontal: 10),
                              visualDensity: VisualDensity.compact,
                            ),
                            onPressed: () => exportAndShare(context, api, '/detections/export', {
                              'titik': _titik,
                              'days': _days,
                            }, 'Riwayat_Deteksi_CNN.xlsx'),
                            icon: const Icon(Icons.download, size: 16),
                            label: const Text('Ekspor', style: TextStyle(fontSize: 12)),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      PlotDaysPicker(
                        titik: _titik,
                        days: _days,
                        dayOptions: const [1, 3, 7],
                        onChanged: (t, dd) {
                          setState(() {
                            _titik = t;
                            _days = dd;
                          });
                          _load();
                        },
                      ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(12),
                  child: _loading
                      ? const Padding(
                          padding: EdgeInsets.all(30),
                          child: Center(child: CircularProgressIndicator()),
                        )
                      : _error != null
                      ? ErrorBox(message: _error!, onRetry: _load)
                      : d == null
                      ? const SizedBox.shrink()
                      : Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            if (d['last_detection_at'] != null)
                              Padding(
                                padding: const EdgeInsets.only(bottom: 10),
                                child: Row(
                                  children: [
                                    const Icon(Icons.schedule, size: 14, color: Agro.muted),
                                    const SizedBox(width: 4),
                                    Expanded(
                                      child: Text(
                                        'Deteksi $_days hari terakhir, sampai ${dateTime(d['last_detection_at'])}',
                                        style: const TextStyle(color: Agro.muted, fontSize: 12),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            _stats(d['summary'] as Map),
                            const SizedBox(height: 14),
                            if (items.isEmpty)
                              const Padding(
                                padding: EdgeInsets.all(30),
                                child: Column(
                                  children: [
                                    Icon(Icons.image_outlined, size: 40, color: Agro.muted),
                                    EmptyText('Belum ada data'),
                                  ],
                                ),
                              )
                            else ...[
                              for (final it in items.take(_limit)) _card(it),
                              if (items.length > _limit)
                                TextButton.icon(
                                  onPressed: () => setState(() => _limit += 12),
                                  icon: const Icon(Icons.expand_more),
                                  label: Text('Tampilkan lebih banyak (${items.length - _limit} lagi)'),
                                ),
                            ],
                          ],
                        ),
                ),
              ],
            ),
          ),
          if (d != null && !_loading)
            AgroPanel(
              header: PanelHeader(icon: Icons.bar_chart, title: 'Distribusi Deteksi · $_judul'),
              child: _distribution((d['distribution'] as List).cast<Map>()),
            ),
        ],
      ),
    );
  }

  Widget _stats(Map s) {
    Widget stat(IconData icon, String label, String value, Color tone, Color soft, {bool text = false}) => Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Agro.line),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Colors.white, Colors.white, soft],
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(color: soft, borderRadius: BorderRadius.circular(12)),
            child: Icon(icon, color: tone, size: 21),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(label, style: const TextStyle(color: Agro.muted, fontSize: 11)),
                Text(
                  value,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: Agro.text, fontWeight: FontWeight.w700, fontSize: text ? 12.5 : 19),
                ),
              ],
            ),
          ),
        ],
      ),
    );
    final avg = toDouble(s['avg']);
    final cards = [
      stat(
        Icons.center_focus_strong,
        'Total deteksi',
        '${s['total']}',
        const Color(0xFF1E88E5),
        const Color(0xFFE8F2FF),
      ),
      stat(Icons.verified_user_outlined, 'Sehat', '${s['healthy']}', Agro.green, Agro.greenSoft),
      stat(
        Icons.bug_report_outlined,
        'Perlu tindakan',
        '${s['sick']}',
        const Color(0xFFD32F2F),
        const Color(0xFFFFEBEE),
      ),
      stat(
        Icons.gps_fixed,
        'Rata-rata skor deteksi',
        avg == null ? '-' : '${fmt(avg)}%',
        const Color(0xFF00897B),
        const Color(0xFFE3F5F2),
      ),
      stat(
        Icons.warning_amber_rounded,
        'Paling sering',
        s['top']?.toString() ?? '-',
        const Color(0xFFF59E0B),
        const Color(0xFFFFF6DC),
        text: true,
      ),
    ];
    return Column(
      children: [
        for (var i = 0; i < cards.length; i += 2)
          Padding(
            padding: EdgeInsets.only(bottom: i + 2 < cards.length ? 10 : 0),
            child: IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Expanded(child: cards[i]),
                  const SizedBox(width: 10),
                  Expanded(child: i + 1 < cards.length ? cards[i + 1] : const SizedBox.shrink()),
                ],
              ),
            ),
          ),
      ],
    );
  }

  Widget _card(Map it) {
    final healthy = it['healthy'] == true;
    final color = healthy ? const Color(0xFF2E7D32) : const Color(0xFFD32F2F);
    final prob = toDouble(it['probabilitas']);
    final img = (it['image_url'] ?? it['image'])?.toString();
    return AccentBox(
      margin: const EdgeInsets.only(bottom: 14),
      color: Colors.white,
      top: true,
      accent: healthy ? const Color(0xFF43A047) : const Color(0xFFE53935),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          GestureDetector(
            onTap: img == null ? null : () => showPhoto(context, img, it['penyakit']),
            child: AspectRatio(
              aspectRatio: 4 / 3,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  Container(
                    color: const Color(0xFFF4F7F4),
                    child: const Icon(Icons.image_outlined, color: Agro.muted, size: 34),
                  ),
                  if (img != null)
                    Image.network(img, fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox.shrink()),
                  Positioned(
                    top: 8,
                    left: 8,
                    child: _overlay(
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.location_on_outlined, size: 13, color: Colors.white),
                          Text(
                            ' Petak ${it['titik']}',
                            style: const TextStyle(color: Colors.white, fontSize: 11.5, fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                    ),
                  ),
                  Positioned(
                    top: 8,
                    right: 8,
                    child: _overlay(
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            healthy ? Icons.check_circle_outline : Icons.error_outline,
                            size: 13,
                            color: Colors.white,
                          ),
                          Text(
                            healthy ? ' Sehat' : ' Perlu tindakan',
                            style: const TextStyle(color: Colors.white, fontSize: 11.5, fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                      color: healthy ? const Color(0xE62E7D32) : const Color(0xE6D32F2F),
                    ),
                  ),
                  if (img != null)
                    Positioned(
                      bottom: 8,
                      right: 8,
                      child: _overlay(const Icon(Icons.zoom_out_map, size: 15, color: Colors.white)),
                    ),
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(healthy ? Icons.verified_user_outlined : Icons.bug_report_outlined, color: color, size: 19),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        it['penyakit'].toString(),
                        style: TextStyle(color: color, fontWeight: FontWeight.w700, fontSize: 15),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    const Expanded(
                      child: Text('Skor Deteksi Hama (CNN)', style: TextStyle(color: Agro.muted, fontSize: 12)),
                    ),
                    Text(
                      prob == null ? '-' : '${fmt(prob)}%',
                      style: const TextStyle(color: Agro.text, fontWeight: FontWeight.w700),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                LinearProgressIndicator(
                  value: (prob ?? 0) / 100,
                  minHeight: 6,
                  borderRadius: BorderRadius.circular(6),
                  color: healthy ? Sneat.success : Sneat.danger,
                  backgroundColor: const Color(0xFFE6EAEF),
                ),
                if (it['advice'] != null) ...[
                  const SizedBox(height: 10),
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(color: Agro.panelBg, borderRadius: BorderRadius.circular(10)),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(Icons.lightbulb_outline, size: 16, color: Color(0xFFF59E0B)),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            it['advice'].toString(),
                            style: const TextStyle(color: Color(0xFF566A7F), fontSize: 12.5, height: 1.4),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 10),
                Row(
                  children: [
                    const Icon(Icons.schedule, size: 13, color: Agro.muted),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        dateTime(it['timestamp']),
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: Agro.muted, fontSize: 11.5),
                      ),
                    ),
                    OutlinedButton.icon(
                      style: OutlinedButton.styleFrom(
                        foregroundColor: healthy ? Sneat.success : Sneat.danger,
                        side: BorderSide(color: healthy ? Sneat.success : Sneat.danger),
                        visualDensity: VisualDensity.compact,
                        padding: const EdgeInsets.symmetric(horizontal: 10),
                      ),
                      onPressed: () => showRecommendationSheet(
                        context,
                        date: dateTime(it['timestamp']),
                        label: healthy ? 'Sehat' : 'Perlu tindakan',
                        level: healthy ? 'success' : 'danger',
                        petak: 'Petak ${it['titik']}',
                        status: healthy
                            ? const []
                            : [
                                {
                                  'label': 'Kamera mendeteksi ${it['penyakit']}',
                                  'reason': 'Skor deteksi ${fmt(prob)}%',
                                },
                              ],
                        okText: 'Kamera tidak mendeteksi hama/penyakit.',
                      ),
                      icon: const Icon(Icons.checklist, size: 16),
                      label: const Text('Lihat aksi rekomendasi', style: TextStyle(fontSize: 12)),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _overlay(Widget child, {Color color = const Color(0x8C0F1914)}) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
    decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(999)),
    child: child,
  );

  Widget _distribution(List<Map> dist) {
    if (dist.isEmpty) return const EmptyText('Belum ada data');
    const colors = {'1': Color(0xFF008FFB), '2': Color(0xFFFF9F43)};
    final petak = _titik == 0 ? ['1', '2'] : ['$_titik'];
    final maxV = dist
        .expand((d) => (d['per_petak'] as Map).values)
        .map((v) => (v as num).toDouble())
        .fold<double>(1, (a, b) => b > a ? b : a);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Wrap(
          spacing: 14,
          alignment: WrapAlignment.center,
          children: [
            for (final p in petak)
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(width: 12, height: 12, color: colors[p] ?? Sneat.secondary),
                  Text(' Petak $p', style: const TextStyle(fontSize: 12, color: Agro.text)),
                ],
              ),
          ],
        ),
        const SizedBox(height: 12),
        for (final d in dist)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${d['penyakit']}  (${d['total']})',
                  style: const TextStyle(color: Agro.text, fontSize: 12.5, fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 4),
                for (final p in petak)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 3),
                    child: LayoutBuilder(
                      builder: (ctx, c) {
                        final v = ((d['per_petak'] as Map)[p] as num?)?.toDouble() ?? 0;
                        return Row(
                          children: [
                            Container(
                              width: (c.maxWidth - 30) * (v / maxV),
                              height: 14,
                              decoration: BoxDecoration(
                                color: colors[p] ?? Sneat.secondary,
                                borderRadius: BorderRadius.circular(4),
                              ),
                            ),
                            const SizedBox(width: 6),
                            Text(fmt(v, digits: 0), style: const TextStyle(fontSize: 11, color: Agro.text)),
                          ],
                        );
                      },
                    ),
                  ),
              ],
            ),
          ),
      ],
    );
  }
}
