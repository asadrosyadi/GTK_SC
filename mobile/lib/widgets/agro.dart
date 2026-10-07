import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../core/format.dart';
import '../core/session.dart';
import '../core/theme.dart';
import 'sneat.dart';

class StatusPill extends StatelessWidget {
  const StatusPill({super.key, required this.label, required this.level, this.dense = false});

  StatusPill.json(Map? s, {super.key, this.dense = false})
      : label = s?['label']?.toString() ?? '-',
        level = s?['level']?.toString();

  final String label;
  final String? level;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    final (bg, fg) = Agro.pill(level);
    return Container(
      padding: EdgeInsets.symmetric(horizontal: dense ? 7 : 9, vertical: dense ? 2 : 3),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(width: 6, height: 6, decoration: BoxDecoration(color: fg, shape: BoxShape.circle)),
          const SizedBox(width: 4),
          Flexible(
            child: Text(label, style: TextStyle(color: fg, fontSize: dense ? 10.5 : 11, fontWeight: FontWeight.w600)),
          ),
        ],
      ),
    );
  }
}

class SolidBadge extends StatelessWidget {
  const SolidBadge(this.text, {super.key, required this.color, this.icon});
  final String text;
  final Color color;
  final IconData? icon;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(4)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Flexible(
            child: Text(text.toUpperCase(),
                style: const TextStyle(color: Colors.white, fontSize: 10.5, fontWeight: FontWeight.w700)),
          ),
          if (icon != null) ...[const SizedBox(width: 4), Icon(icon, size: 13, color: Colors.white)],
        ]),
      );
}

class PanelHeader extends StatelessWidget {
  const PanelHeader({super.key, required this.icon, required this.title, this.color, this.gradient, this.trailing});
  final IconData icon;
  final String title;
  final Color? color;
  final Gradient? gradient;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final onColor = color != null || gradient != null;
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 14, 12, 14),
      decoration: BoxDecoration(
        color: color ?? (gradient == null ? Colors.white : null),
        gradient: gradient,
        border: onColor ? null : const Border(bottom: BorderSide(color: Agro.line)),
      ),
      child: Row(children: [
        Icon(icon, size: 20, color: onColor ? Colors.white : Agro.green),
        const SizedBox(width: 8),
        Expanded(
          child: Text(title,
              style: TextStyle(
                  color: onColor ? Colors.white : Agro.text, fontWeight: FontWeight.w700, fontSize: 15.5)),
        ),
        ?trailing,
      ]),
    );
  }
}

class AgroPanel extends StatelessWidget {
  const AgroPanel({super.key, required this.header, required this.child, this.topBorder = false, this.padding});
  final Widget header;
  final Widget child;
  final bool topBorder;
  final EdgeInsets? padding;

  @override
  Widget build(BuildContext context) => Container(
        margin: const EdgeInsets.only(bottom: 16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: Agro.line),
          boxShadow: const [BoxShadow(color: Color(0x0F33435A), blurRadius: 18, offset: Offset(0, 4))],
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          if (topBorder) Container(height: 3, color: Agro.green),
          header,
          Padding(padding: padding ?? const EdgeInsets.all(14), child: child),
        ]),
      );
}

class PlotDaysPicker extends StatefulWidget {
  const PlotDaysPicker({
    super.key,
    required this.titik,
    required this.days,
    required this.onChanged,
    this.dayOptions = const [1, 3, 7, 14],
    this.maxDays = 30,
    this.plots = const {1: 'Petak 1', 2: 'Petak 2'},
  });

  final int titik;
  final int days;
  final List<int> dayOptions;
  final int maxDays;
  final Map<int, String> plots;
  final void Function(int titik, int days) onChanged;

  @override
  State<PlotDaysPicker> createState() => _PlotDaysPickerState();
}

class _PlotDaysPickerState extends State<PlotDaysPicker> {
  late final TextEditingController _days = TextEditingController(text: '${widget.days}');

  @override
  void didUpdateWidget(covariant PlotDaysPicker old) {
    super.didUpdateWidget(old);
    if (old.days != widget.days) _days.text = '${widget.days}';
  }

  @override
  void dispose() {
    _days.dispose();
    super.dispose();
  }

  void _apply() {
    final d = int.tryParse(_days.text.trim());
    if (d == null) return;
    FocusScope.of(context).unfocus();
    widget.onChanged(widget.titik, d.clamp(1, widget.maxDays));
  }

  Widget _seg<T>(Map<T, String> options, T selected, ValueChanged<T> onTap) => Container(
        padding: const EdgeInsets.all(3),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(6)),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: options.entries.map((e) {
            final active = e.key == selected;
            return Padding(
              padding: const EdgeInsets.symmetric(horizontal: 1.5),
              child: InkWell(
                onTap: () => onTap(e.key),
                borderRadius: BorderRadius.circular(4),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: active ? const Color(0xFF0397B8) : Colors.white,
                    border: Border.all(color: Sneat.info),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Text(e.value.toUpperCase(),
                      style: TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w600,
                          color: active ? Colors.white : Sneat.info)),
                ),
              ),
            );
          }).toList(),
        ),
      );

  @override
  Widget build(BuildContext context) {
    final custom = !widget.dayOptions.contains(widget.days);
    return Wrap(spacing: 8, runSpacing: 8, crossAxisAlignment: WrapCrossAlignment.center, children: [
      _seg<int>({0: 'Gabungan', ...widget.plots}, widget.titik, (t) => widget.onChanged(t, widget.days)),
      _seg<int>({for (final d in widget.dayOptions) d: '${d}d'}, widget.days, (d) => widget.onChanged(widget.titik, d)),
      Container(
        padding: const EdgeInsets.fromLTRB(8, 3, 3, 3),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(6),
          border: Border.all(color: custom ? const Color(0xFF0E7490) : Colors.transparent, width: 2),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          SizedBox(
            width: 48,
            height: 32,
            child: TextField(
              controller: _days,
              keyboardType: TextInputType.number,
              textAlign: TextAlign.center,
              style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
              decoration: const InputDecoration(contentPadding: EdgeInsets.zero),
              onSubmitted: (_) => _apply(),
            ),
          ),
          const SizedBox(width: 6),
          const Text('hari', style: TextStyle(color: Sneat.info, fontWeight: FontWeight.w600, fontSize: 12.5)),
          const SizedBox(width: 6),
          SizedBox(
            height: 32,
            child: FilledButton.icon(
              style: FilledButton.styleFrom(
                  backgroundColor: Sneat.info, padding: const EdgeInsets.symmetric(horizontal: 10)),
              onPressed: _apply,
              icon: const Icon(Icons.check, size: 16),
              label: const Text('Terapkan', style: TextStyle(fontSize: 12)),
            ),
          ),
        ]),
      ),
    ]);
  }
}

class AiText extends StatelessWidget {
  const AiText(this.text, {super.key, this.color, this.size = 14});
  final String text;
  final Color? color;
  final double size;

  List<TextSpan> _spans(String line, TextStyle base) {
    final out = <TextSpan>[];
    final re = RegExp(r'\*\*(.+?)\*\*');
    var last = 0;
    for (final m in re.allMatches(line)) {
      if (m.start > last) out.add(TextSpan(text: line.substring(last, m.start), style: base));
      out.add(TextSpan(text: m.group(1), style: base.copyWith(fontWeight: FontWeight.w700)));
      last = m.end;
    }
    if (last < line.length) out.add(TextSpan(text: line.substring(last), style: base));
    return out;
  }

  @override
  Widget build(BuildContext context) {
    final base = TextStyle(color: color ?? Agro.text, fontSize: size, height: 1.5);
    final bullet = RegExp(r'^\s*(?:[-•*]|\d+[.)])\s+(.*)$');
    final children = <Widget>[];
    for (final raw in text.split(RegExp(r'\r?\n'))) {
      if (raw.trim().isEmpty) continue;
      final m = bullet.firstMatch(raw);
      if (m != null) {
        children.add(Padding(
          padding: const EdgeInsets.only(left: 4, top: 2),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('•  ', style: base),
            Expanded(child: Text.rich(TextSpan(children: _spans(m.group(1)!, base)))),
          ]),
        ));
      } else {
        children.add(Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Text.rich(TextSpan(children: _spans(raw, base))),
        ));
      }
    }
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: children);
  }
}

class LoadState<T> {
  T? data;
  String? error;
  bool loading = false;
}

class RecommendationCard extends StatelessWidget {
  const RecommendationCard(this.r, {super.key});
  final Map r;

  @override
  Widget build(BuildContext context) {
    final color = Sneat.fromType(r['type']?.toString());
    final prob = toDouble(r['probability']);
    final reasons = (r['reasons_by_petak'] as Map? ?? {}).entries.where((e) => (e.value as List).isNotEmpty);
    return AlertBox(
      color: color,
      icon: iconFromBoxicon(r['icon']?.toString()),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text.rich(TextSpan(children: [
          TextSpan(text: r['title']?.toString() ?? '-', style: TextStyle(color: color, fontWeight: FontWeight.w700)),
          if (prob != null)
            TextSpan(text: '  (${(prob * 100).round()}%)', style: const TextStyle(color: Sneat.muted, fontWeight: FontWeight.w600)),
        ])),
        const SizedBox(height: 4),
        Wrap(spacing: 4, children: [for (final p in (r['petak'] as List? ?? [])) LabelBadge(p.toString(), color: Sneat.dark)]),
        const SizedBox(height: 6),
        Text(r['message']?.toString() ?? '', style: TextStyle(color: color, fontSize: 13, height: 1.45)),
        for (final e in reasons)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text.rich(TextSpan(style: TextStyle(color: color, fontSize: 12.5), children: [
              TextSpan(text: '• ${e.key}: ', style: const TextStyle(fontWeight: FontWeight.w700)),
              TextSpan(text: (e.value as List).join('; ')),
            ])),
          ),
      ]),
    );
  }
}

Future<void> showRecommendationSheet(
  BuildContext context, {
  required String date,
  required String label,
  required String level,
  List<Map> status = const [],
  String? petak,
  String okText = 'Normal, tidak ada nilai sensor yang melewati batas.',
}) {
  final api = context.read<Session>().api;
  return showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (ctx) => DraggableScrollableSheet(
      initialChildSize: 0.85,
      maxChildSize: 0.95,
      minChildSize: 0.4,
      builder: (ctx, scroll) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(children: [
          PanelHeader(
            icon: Icons.notifications_none,
            title: 'Aksi Rekomendasi',
            color: Sneat.warning,
            trailing: Row(mainAxisSize: MainAxisSize.min, children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(4)),
                child: const Text('MODEL RANDOM FOREST',
                    style: TextStyle(color: Sneat.warning, fontSize: 10, fontWeight: FontWeight.w700)),
              ),
              IconButton(
                onPressed: () => Navigator.pop(ctx),
                icon: const Icon(Icons.close, color: Colors.white),
                visualDensity: VisualDensity.compact,
              ),
            ]),
          ),
          Expanded(
            child: FutureBuilder<Map<String, dynamic>>(
              future: api.recommendations(),
              builder: (ctx, snap) {
                final petakSet = petak != null
                    ? {petak}
                    : status
                        .map((s) => RegExp(r'^(Petak \d+)').firstMatch(s['label'].toString())?.group(1))
                        .whereType<String>()
                        .toSet();
                return ListView(controller: scroll, padding: const EdgeInsets.all(16), children: [
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Agro.panelBg,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Agro.line),
                    ),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Wrap(spacing: 8, crossAxisAlignment: WrapCrossAlignment.center, children: [
                        const Icon(Icons.schedule, size: 15, color: Sneat.muted),
                        Text(date, style: const TextStyle(color: Sneat.muted, fontSize: 12.5)),
                        SolidBadge(label, color: Sneat.fromType(level)),
                      ]),
                      const SizedBox(height: 8),
                      if (status.isEmpty)
                        Row(children: [
                          const Icon(Icons.check_circle_outline, size: 16, color: Sneat.success),
                          const SizedBox(width: 6),
                          Expanded(child: Text(okText, style: const TextStyle(color: Sneat.success, fontSize: 13))),
                        ])
                      else
                        for (final s in status)
                          Padding(
                            padding: const EdgeInsets.only(top: 2),
                            child: Text.rich(TextSpan(style: const TextStyle(fontSize: 13, color: Agro.text), children: [
                              TextSpan(text: '• ${s['label']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                              if (s['reason'] != null) TextSpan(text: ' — ${s['reason']}'),
                            ])),
                          ),
                    ]),
                  ),
                  const SizedBox(height: 14),
                  Text('Aksi rekomendasi${petakSet.isNotEmpty ? ' · ${petakSet.join(', ')}' : ''}',
                      style: const TextStyle(fontWeight: FontWeight.w700, color: Agro.text)),
                  const SizedBox(height: 8),
                  if (snap.connectionState != ConnectionState.done)
                    const Padding(
                      padding: EdgeInsets.all(24),
                      child: Center(child: CircularProgressIndicator(color: Sneat.warning)),
                    )
                  else if (snap.hasError)
                    ErrorBox(message: snap.error is ApiException ? snap.error.toString() : 'Gagal memuat aksi rekomendasi.')
                  else ...() {
                    final items = (snap.data!['items'] as List)
                        .cast<Map>()
                        .where((r) => petakSet.isEmpty || (r['petak'] as List).any((p) => petakSet.contains(p)))
                        .toList();
                    return items.isEmpty
                        ? [const EmptyText('Tidak ada aksi rekomendasi untuk petak ini saat ini.')]
                        : items.map((r) => RecommendationCard(r)).toList();
                  }(),
                  const SizedBox(height: 4),
                  const EmptyText('Aksi rekomendasi berdasarkan kondisi sensor & kamera terbaru.'),
                ]);
              },
            ),
          ),
        ]),
      ),
    ),
  );
}

class AccentBox extends StatelessWidget {
  const AccentBox({
    super.key,
    required this.accent,
    required this.child,
    this.top = false,
    this.width = 4,
    this.radius = 14,
    this.color,
    this.margin,
    this.padding,
    this.clipBehavior = Clip.antiAlias,
  });

  final Color accent;
  final bool top;
  final double width;
  final double radius;
  final Color? color;
  final EdgeInsetsGeometry? margin;
  final EdgeInsetsGeometry? padding;
  final Clip clipBehavior;
  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
        margin: margin,
        clipBehavior: clipBehavior,
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(radius),
          border: Border.all(color: Agro.line),
        ),
        child: Container(
          padding: padding,
          decoration: BoxDecoration(
            border: top
                ? Border(top: BorderSide(color: accent, width: width))
                : Border(left: BorderSide(color: accent, width: width)),
          ),
          child: child,
        ),
      );
}
