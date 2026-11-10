import 'package:flutter/material.dart';

/// Shared civic visual language for Android and iOS.
/// Blue/white/gold; consistent with the SmartCikoko public website mockup.
abstract final class CivicColors {
  static const navy = Color(0xFF0C3159);
  static const blue = Color(0xFF086EAF);
  static const sky = Color(0xFF29B9E5);
  static const gold = Color(0xFFE0B56E);
  static const background = Color(0xFFF4F8FC);
  static const ink = Color(0xFF143451);
  static const muted = Color(0xFF69849B);
  static const border = Color(0xFFDDE9F3);
  static const success = Color(0xFF178661);
}

ThemeData smartTheme() {
  final colors = ColorScheme.fromSeed(
    seedColor: CivicColors.blue,
    primary: CivicColors.blue,
    secondary: CivicColors.sky,
    surface: Colors.white,
    brightness: Brightness.light,
  );
  return ThemeData(
    useMaterial3: true,
    colorScheme: colors,
    fontFamily: 'Roboto',
    scaffoldBackgroundColor: CivicColors.background,
    appBarTheme: const AppBarTheme(
      backgroundColor: Colors.white,
      foregroundColor: CivicColors.ink,
      centerTitle: false,
      scrolledUnderElevation: 0,
      elevation: 0,
    ),
    cardTheme: CardThemeData(
      color: Colors.white,
      margin: EdgeInsets.zero,
      elevation: 0,
      shape: RoundedRectangleBorder(
        side: const BorderSide(color: CivicColors.border),
        borderRadius: BorderRadius.circular(16),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: const Color(0xFFFBFDFF),
      contentPadding: const EdgeInsets.symmetric(horizontal: 15, vertical: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: CivicColors.border),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: CivicColors.border),
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: CivicColors.blue,
        foregroundColor: Colors.white,
        minimumSize: const Size(0, 48),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        textStyle: const TextStyle(fontWeight: FontWeight.w800),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: Colors.white,
      indicatorColor: const Color(0xFFE4F3FD),
      labelTextStyle: WidgetStateProperty.all(
        const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
      ),
      height: 72,
    ),
  );
}

class CivicLogo extends StatelessWidget {
  final double size;
  const CivicLogo({super.key, this.size = 42});

  @override
  Widget build(BuildContext context) => Semantics(
        label: 'Lambang visual SmartCikoko',
        child: Container(
          width: size,
          height: size * 1.08,
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [Color(0xFF145A90), CivicColors.navy],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: const BorderRadius.only(
              topLeft: Radius.circular(8),
              topRight: Radius.circular(8),
              bottomLeft: Radius.circular(16),
              bottomRight: Radius.circular(16),
            ),
            border: Border.all(color: CivicColors.gold, width: 2),
          ),
          alignment: Alignment.center,
          child: Text(
            'KC',
            style: TextStyle(
              color: const Color(0xFFFBE0A6),
              fontSize: size * .30,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
      );
}

class CivicPageTitle extends StatelessWidget {
  final String title;
  final String? subtitle;
  const CivicPageTitle({super.key, required this.title, this.subtitle});

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title,
              style: const TextStyle(
                  fontSize: 23,
                  fontWeight: FontWeight.w900,
                  height: 1.2,
                  color: CivicColors.ink)),
          if (subtitle != null) ...[
            const SizedBox(height: 6),
            Text(subtitle!,
                style: const TextStyle(
                    color: CivicColors.muted, fontSize: 12, height: 1.5)),
          ],
        ],
      );
}

class CivicStatus extends StatelessWidget {
  final String status;
  const CivicStatus({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    const labels = <String, String>{
      'draf': 'Draf',
      'diajukan': 'Diajukan',
      'ditinjau': 'Ditinjau',
      'perbaikan': 'Perlu perbaikan',
      'revisi': 'Perlu perbaikan',
      'ditutup': 'Selesai administratif',
      'disetujui': 'Disetujui',
      'ditolak': 'Ditolak',
    };
    final active = status == 'diajukan' || status == 'ditinjau';
    final problem = status == 'ditolak' || status == 'perbaikan' || status == 'revisi';
    final bg = problem
        ? const Color(0xFFFFEEE9)
        : active
            ? const Color(0xFFE7F3FC)
            : const Color(0xFFEAF7EE);
    final foreground = problem
        ? const Color(0xFFA44336)
        : active
            ? CivicColors.blue
            : CivicColors.success;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(24)),
      child: Text(labels[status] ?? status,
          style: TextStyle(color: foreground, fontWeight: FontWeight.w800, fontSize: 10)),
    );
  }
}

class CivicEmpty extends StatelessWidget {
  final String text;
  final IconData icon;
  const CivicEmpty(this.text, {super.key, this.icon = Icons.inbox_outlined});

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 35, horizontal: 18),
        child: Column(children: [
          Icon(icon, color: CivicColors.muted, size: 40),
          const SizedBox(height: 11),
          Text(text,
              textAlign: TextAlign.center,
              style: const TextStyle(color: CivicColors.muted, fontSize: 13)),
        ]),
      );
}
