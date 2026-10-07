import 'dart:ui';

import 'package:intl/intl.dart';

import 'theme.dart';

class StatusBadge {
  const StatusBadge(this.text, this.color);
  final String text;
  final Color color;

  static StatusBadge? fromJson(dynamic s) {
    if (s is! Map) return null;
    return StatusBadge(s['label']?.toString() ?? '-', Sneat.fromType(s['level']?.toString()));
  }
}

double? toDouble(dynamic v) {
  if (v == null) return null;
  if (v is num) return v.toDouble();
  return double.tryParse(v.toString());
}

String fmt(dynamic v, {int digits = 1, String unit = ''}) {
  final d = toDouble(v);
  if (d == null) return '-';
  var text = d.toStringAsFixed(digits);
  if (text.contains('.')) text = text.replaceFirst(RegExp(r'\.?0+$'), '');
  return unit.isEmpty ? text : '$text $unit';
}

String tableNum(dynamic v, String key) {
  final d = toDouble(v);
  if (d == null) return '-';
  const whole = {'pressure', 'light_intensity', 'ec', 'tds'};
  return NumberFormat(whole.contains(key) ? '#,##0' : '#,##0.00', 'en').format(d);
}

DateTime? parseTime(dynamic s) {
  if (s == null) return null;
  return DateTime.tryParse(s.toString().replaceFirst(' ', 'T'))?.toLocal();
}

String relativeTime(dynamic s) {
  final t = parseTime(s);
  if (t == null) return '-';
  final diff = DateTime.now().difference(t);
  if (diff.inSeconds < 60) return 'Sekarang';
  if (diff.inDays > 0) return '${diff.inDays} Hari yang lalu';
  if (diff.inHours > 0) return '${diff.inHours} Jam yang lalu';
  return '${diff.inMinutes} Menit yang lalu';
}

String dateTime(dynamic s) {
  final t = parseTime(s);
  return t == null ? '-' : DateFormat('yyyy-MM-dd HH:mm').format(t);
}

(String, Color) lstmClass(String? label) => switch (label) {
      'Healthy' => ('Sehat', Sneat.success),
      'Moderate Stress' => ('Stres Sedang', Sneat.warning),
      'High Stress' => ('Stres Tinggi', Sneat.danger),
      _ => (label ?? '-', Sneat.secondary),
    };
