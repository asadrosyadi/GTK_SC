import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/agro.dart';
import '../widgets/forecast_views.dart';
import '../widgets/sneat.dart';

class PredictionScreen extends StatefulWidget {
  const PredictionScreen({super.key});

  @override
  State<PredictionScreen> createState() => _PredictionScreenState();
}

class _PredictionScreenState extends State<PredictionScreen> {
  int _titik = 0;
  int _days = 3;
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
      _data = await context.read<Session>().api.forecast(_titik, _days);
    } on ApiException catch (e) {
      _error = e.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  String get _judul => _titik == 0 ? 'Gabungan Petak 1 & 2' : 'Petak $_titik';

  @override
  Widget build(BuildContext context) {
    final d = _data;
    List<Map> list(String k) => (d?[k] as List? ?? []).cast<Map>();
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
                  const Icon(Icons.auto_fix_high, color: Colors.white, size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text('Prediksi · $_judul',
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 15.5)),
                  ),
                ]),
                const SizedBox(height: 12),
                PlotDaysPicker(
                  titik: _titik,
                  days: _days,
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
                        EmptyText('Menghitung ramalan sensor & LSTM…'),
                      ]),
                    )
                  : _error != null
                      ? ErrorBox(message: _error!, onRetry: _load)
                      : d == null
                          ? const EmptyText('Belum ada ramalan sensor.')
                          : ForecastTable(
                              variables: list('variables'),
                              measured: list('measured'),
                              forecast: list('forecast'),
                              titik: _titik,
                            ),
            ),
          ]),
        ),
        if (d != null && !_loading)
          AgroPanel(
            header: PanelHeader(icon: Icons.show_chart, title: 'Tren Prediksi · $_judul'),
            child: TrendChart(
              variables: list('variables'),
              measured: list('measured'),
              history: list('history'),
              forecast: list('forecast'),
            ),
          ),
      ]),
    );
  }
}
