import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../core/export.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/agro.dart';
import '../widgets/forecast_views.dart';
import '../widgets/sneat.dart';

class HistoryScreen extends StatefulWidget {
  const HistoryScreen({super.key});

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen> {
  int _titik = 0;
  int _days = 1;
  Map<String, dynamic>? _data;
  String? _error;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      _data = await context.read<Session>().api.history(_titik, _days);
    } on ApiException catch (e) {
      _error = e.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  String get _judul => _titik == 0 ? 'Gabungan Petak 1 & 2' : 'Petak $_titik';

  @override
  Widget build(BuildContext context) {
    final d = _data;
    final rows = (d?['rows'] as List? ?? []).cast<Map>();
    final variables = (d?['variables'] as List? ?? []).cast<Map>();
    final api = context.read<Session>().api;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(padding: const EdgeInsets.fromLTRB(14, 14, 14, 24), children: [
        Container(
          margin: const EdgeInsets.only(bottom: 16),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(Sneat.radius), boxShadow: Sneat.cardShadow),
          clipBehavior: Clip.antiAlias,
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Container(
              color: Sneat.info,
              padding: const EdgeInsets.all(14),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  const Icon(Icons.history, color: Colors.white, size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text('Riwayat Sensor IoT · $_judul',
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 15.5)),
                  ),
                  FilledButton.icon(
                    style: FilledButton.styleFrom(
                      backgroundColor: Colors.white,
                      foregroundColor: Sneat.heading,
                      padding: const EdgeInsets.symmetric(horizontal: 10),
                      visualDensity: VisualDensity.compact,
                    ),
                    onPressed: () => exportAndShare(context, api, '/history/export', {'titik': _titik, 'days': _days},
                        'Riwayat_Sensor.xlsx'),
                    icon: const Icon(Icons.download, size: 16),
                    label: const Text('Ekspor', style: TextStyle(fontSize: 12)),
                  ),
                ]),
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
              ]),
            ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: _loading
                  ? const Padding(
                      padding: EdgeInsets.all(30),
                      child: Column(children: [
                        CircularProgressIndicator(),
                        SizedBox(height: 10),
                        EmptyText('Memuat riwayat & kondisi LSTM tiap pembacaan…'),
                      ]),
                    )
                  : _error != null
                      ? ErrorBox(message: _error!, onRetry: _load)
                      : Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                          if (d?['last_observation'] != null)
                            Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: Row(children: [
                                const Icon(Icons.schedule, size: 14, color: Agro.muted),
                                const SizedBox(width: 4),
                                Expanded(
                                  child: Text('Data terukur $_days hari terakhir, sampai ${d!['last_observation']} · ${rows.length} pembacaan',
                                      style: const TextStyle(color: Agro.muted, fontSize: 12)),
                                ),
                              ]),
                            ),
                          if (rows.isEmpty)
                            const EmptyText('Belum ada data')
                          else
                            ForecastTable(variables: variables, measured: rows, forecast: const [], titik: _titik),
                        ]),
            ),
          ]),
        ),
        if (!_loading && rows.isNotEmpty)
          AgroPanel(
            header: PanelHeader(icon: Icons.show_chart, title: 'Tren Riwayat · $_judul'),
            child: TrendChart(
              variables: variables,
              measured: rows.reversed.toList(),
              history: rows.reversed.toList(),
              forecast: const [],
            ),
          ),
      ]),
    );
  }
}
