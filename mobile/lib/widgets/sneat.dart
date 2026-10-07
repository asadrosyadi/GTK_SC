import 'package:flutter/material.dart';

import '../core/format.dart';
import '../core/theme.dart';

class SneatCard extends StatelessWidget {
  const SneatCard({
    super.key,
    this.title,
    this.icon,
    this.headerColor,
    this.trailing,
    required this.child,
    this.padding = const EdgeInsets.all(16),
  });

  final String? title;
  final IconData? icon;
  final Color? headerColor;
  final Widget? trailing;
  final Widget child;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    final onHeader = headerColor == null ? Sneat.heading : Colors.white;
    return Container(
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
          if (title != null)
            Container(
              color: headerColor,
              padding: const EdgeInsets.fromLTRB(16, 14, 12, 14),
              decoration: headerColor == null
                  ? const BoxDecoration(
                      border: Border(
                        bottom: BorderSide(color: Color(0xFFEFF1F4)),
                      ),
                    )
                  : null,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      if (icon != null) ...[
                        Icon(icon, color: onHeader, size: 20),
                        const SizedBox(width: 8),
                      ],
                      Expanded(
                        child: Text(
                          title!,
                          style: TextStyle(
                            color: onHeader,
                            fontSize: 15,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ],
                  ),
                  if (trailing != null) ...[
                    const SizedBox(height: 6),
                    Padding(
                      padding: EdgeInsets.only(left: icon != null ? 28 : 0),
                      child: trailing,
                    ),
                  ],
                ],
              ),
            ),
          Padding(padding: padding, child: child),
        ],
      ),
    );
  }
}

class LabelBadge extends StatelessWidget {
  const LabelBadge(
    this.text, {
    super.key,
    this.color = Sneat.primary,
    this.solid = false,
  });

  LabelBadge.status(StatusBadge b, {super.key})
    : text = b.text,
      color = b.color,
      solid = true;

  final String text;
  final Color color;
  final bool solid;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: solid ? color : color.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: solid ? Colors.white : color,
          fontSize: 11,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class InfoRow extends StatelessWidget {
  const InfoRow({
    super.key,
    required this.icon,
    required this.label,
    required this.value,
    this.badge,
  });

  final IconData icon;
  final String label;
  final String value;
  final StatusBadge? badge;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(
        children: [
          Icon(icon, size: 18, color: Sneat.secondary),
          const SizedBox(width: 10),
          Expanded(
            child: Text(label, style: const TextStyle(color: Sneat.body)),
          ),
          Text(
            value,
            style: const TextStyle(
              color: Sneat.heading,
              fontWeight: FontWeight.w600,
            ),
          ),
          if (badge != null) ...[
            const SizedBox(width: 6),
            LabelBadge.status(badge!),
          ],
        ],
      ),
    );
  }
}

class SectionTitle extends StatelessWidget {
  const SectionTitle({
    super.key,
    required this.icon,
    required this.color,
    required this.title,
    this.subtitle,
  });

  final IconData icon;
  final Color color;
  final String title;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.16),
              borderRadius: BorderRadius.circular(6),
            ),
            child: Icon(icon, color: color, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: Sneat.heading,
                    fontWeight: FontWeight.w600,
                    fontSize: 15,
                  ),
                ),
                if (subtitle != null)
                  Text(
                    subtitle!,
                    style: const TextStyle(color: Sneat.muted, fontSize: 12),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class ChoiceChips<T> extends StatelessWidget {
  const ChoiceChips({
    super.key,
    required this.options,
    required this.selected,
    required this.onSelected,
  });

  final Map<T, String> options;
  final T selected;
  final ValueChanged<T> onSelected;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: options.entries.map((e) {
        final active = e.key == selected;
        return ChoiceChip(
          label: Text(e.value),
          selected: active,
          showCheckmark: false,
          onSelected: (_) => onSelected(e.key),
          selectedColor: Sneat.primary,
          backgroundColor: Colors.white,
          side: BorderSide(color: active ? Sneat.primary : Sneat.border),
          labelStyle: TextStyle(
            color: active ? Colors.white : Sneat.body,
            fontWeight: FontWeight.w500,
          ),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
          visualDensity: VisualDensity.compact,
        );
      }).toList(),
    );
  }
}

class EmptyText extends StatelessWidget {
  const EmptyText(this.text, {super.key});
  final String text;

  @override
  Widget build(BuildContext context) =>
      Text(text, style: const TextStyle(color: Sneat.muted, fontSize: 13));
}

class ErrorBox extends StatelessWidget {
  const ErrorBox({super.key, required this.message, this.onRetry});
  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      margin: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(
        color: Sneat.danger.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline, color: Sneat.danger),
          const SizedBox(width: 10),
          Expanded(
            child: Text(message, style: const TextStyle(color: Sneat.danger)),
          ),
          if (onRetry != null)
            TextButton(onPressed: onRetry, child: const Text('Muat ulang')),
        ],
      ),
    );
  }
}

class AlertBox extends StatelessWidget {
  const AlertBox({
    super.key,
    required this.color,
    required this.icon,
    required this.child,
  });
  final Color color;
  final IconData icon;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 22),
          const SizedBox(width: 10),
          Expanded(child: child),
        ],
      ),
    );
  }
}

IconData iconFromBoxicon(String? name) {
  final n = name ?? '';
  const map = <String, IconData>{
    'trash': Icons.delete_outline,
    'broadcast': Icons.campaign_outlined,
    'water': Icons.water_drop_outlined,
    'droplet': Icons.water_drop_outlined,
    'cloud-rain': Icons.thunderstorm_outlined,
    'bug': Icons.bug_report_outlined,
    'leaf': Icons.eco_outlined,
    'sun': Icons.wb_sunny_outlined,
    'wind': Icons.air,
    'test-tube': Icons.science_outlined,
    'error': Icons.warning_amber_rounded,
    'shield': Icons.shield_outlined,
    'cut': Icons.content_cut,
    'spray': Icons.sanitizer_outlined,
    'check': Icons.check_circle_outline,
    'cog': Icons.settings_outlined,
    'info': Icons.info_outline,
    'thermometer': Icons.thermostat,
    'bell': Icons.notifications_none,
  };
  for (final e in map.entries) {
    if (n.contains(e.key)) return e.value;
  }
  return Icons.info_outline;
}
