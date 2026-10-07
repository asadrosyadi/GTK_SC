import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

class Sneat {
  static const primary = Color(0xFF696CFF);
  static const secondary = Color(0xFF8592A3);
  static const success = Color(0xFF71DD37);
  static const info = Color(0xFF03C3EC);
  static const warning = Color(0xFFFFAB00);
  static const danger = Color(0xFFFF3E1D);
  static const dark = Color(0xFF233446);

  static const bodyBg = Color(0xFFF5F5F9);
  static const heading = Color(0xFF566A7F);
  static const body = Color(0xFF697A8D);
  static const muted = Color(0xFFA1ACB8);
  static const border = Color(0xFFD9DEE3);

  static const radius = 8.0;

  static List<BoxShadow> get cardShadow => const [
    BoxShadow(color: Color(0x1F435971), blurRadius: 6, offset: Offset(0, 2)),
  ];

  static Color fromType(String? type) {
    switch (type) {
      case 'success':
        return success;
      case 'warning':
        return warning;
      case 'danger':
        return danger;
      case 'primary':
        return primary;
      case 'dark':
        return dark;
      case 'secondary':
        return secondary;
      default:
        return info;
    }
  }

  static ThemeData theme() {
    final scheme = ColorScheme.fromSeed(
      seedColor: primary,
      primary: primary,
      secondary: secondary,
      error: danger,
      surface: Colors.white,
    );
    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: bodyBg,
      appBarTheme: AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: heading,
        elevation: 0,
        scrolledUnderElevation: 1,
        centerTitle: false,
        titleTextStyle: Typography.material2021(platform: defaultTargetPlatform)
            .black
            .titleLarge!
            .copyWith(
              color: heading,
              fontSize: 18,
              fontWeight: FontWeight.w600,
            ),
      ),
      textTheme: const TextTheme(
        titleLarge: TextStyle(color: heading, fontWeight: FontWeight.w600),
        titleMedium: TextStyle(color: heading, fontWeight: FontWeight.w600),
        titleSmall: TextStyle(color: heading, fontWeight: FontWeight.w600),
        bodyLarge: TextStyle(color: body),
        bodyMedium: TextStyle(color: body),
        bodySmall: TextStyle(color: body),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        isDense: true,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 14,
          vertical: 14,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(6),
          borderSide: const BorderSide(color: border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(6),
          borderSide: const BorderSide(color: border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(6),
          borderSide: const BorderSide(color: primary, width: 1.5),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: primary,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: Colors.white,
        indicatorColor: primary.withValues(alpha: 0.16),
        iconTheme: WidgetStateProperty.resolveWith(
          (s) => IconThemeData(
            color: s.contains(WidgetState.selected) ? primary : secondary,
          ),
        ),
        labelTextStyle: WidgetStateProperty.resolveWith(
          (s) => TextStyle(
            fontSize: 12,
            color: s.contains(WidgetState.selected) ? primary : secondary,
            fontWeight: s.contains(WidgetState.selected)
                ? FontWeight.w600
                : FontWeight.w400,
          ),
        ),
      ),
    );
  }
}

class Agro {
  static const green = Color(0xFF2E7D32);
  static const greenSoft = Color(0xFFE8F5E9);
  static const text = Color(0xFF33435A);
  static const muted = Color(0xFF8A96A8);
  static const line = Color(0xFFEDF0F4);
  static const panelBg = Color(0xFFF7F9FB);

  static const tones = <String, (Color, Color)>{
    'vpd': (Color(0xFF00897B), Color(0xFFE3F5F2)),
    'humidity': (Color(0xFF1E88E5), Color(0xFFE8F2FF)),
    'temperature': (Color(0xFFF4511E), Color(0xFFFFF1E8)),
    'windspeed': (Color(0xFF039BE5), Color(0xFFE3F5FD)),
    'rainfall': (Color(0xFF5E5CE6), Color(0xFFEEEDFF)),
    'light_intensity': (Color(0xFFF9A825), Color(0xFFFFF6DC)),
    'pressure': (Color(0xFF607D8B), Color(0xFFEEF2F5)),
  };

  static const heroGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF2EC4B0), Color(0xFF00897B)],
  );

  static const aiGradient = LinearGradient(colors: [Color(0xFF696CFF), Color(0xFF03C3EC)]);

  static (Color, Color) pill(String? level) => switch (level) {
        'success' => (const Color(0xFFE8F5E9), const Color(0xFF2E7D32)),
        'warning' => (const Color(0xFFFFF1D6), const Color(0xFFB45F00)),
        'danger' => (const Color(0xFFFFE5E5), const Color(0xFFC62828)),
        'info' => (const Color(0xFFE0F7FC), const Color(0xFF0277BD)),
        _ => (const Color(0xFFEEF1F5), const Color(0xFF607D8B)),
      };
}
