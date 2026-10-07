import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/session.dart';
import '../core/theme.dart';
import 'account_screen.dart';
import 'ai_screen.dart';
import 'cnn_screen.dart';
import 'dashboard_screen.dart';
import 'history_screen.dart';
import 'prediction_screen.dart';

class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  final _visited = <int>{0};

  static const _titles = ['Beranda', 'Prediksi', 'Riwayat Sensor', 'Deteksi CNN', 'Asisten AI'];

  Widget _page(int i) => switch (i) {
        0 => const DashboardScreen(),
        1 => const PredictionScreen(),
        2 => const HistoryScreen(),
        3 => const CnnScreen(),
        _ => const AiScreen(),
      };

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final user = session.user ?? {};
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 14,
        title: Row(children: [
          const Icon(Icons.spa_outlined, color: Agro.green, size: 26),
          const SizedBox(width: 8),
          const Text('AgroGuard', style: TextStyle(fontWeight: FontWeight.w800, color: Sneat.heading, fontSize: 19)),
          const SizedBox(width: 10),
          Flexible(
            child: Text(_titles[_index],
                overflow: TextOverflow.ellipsis, style: const TextStyle(color: Sneat.muted, fontSize: 13, fontWeight: FontWeight.w500)),
          ),
        ]),
        actions: [
          if (session.iotId != null)
            Padding(
              padding: const EdgeInsets.only(right: 4),
              child: Chip(
                label: Text(session.iotId!, style: const TextStyle(fontSize: 11)),
                visualDensity: VisualDensity.compact,
                avatar: const Icon(Icons.devices_other, size: 14),
              ),
            ),
          GestureDetector(
            onTap: () => Navigator.of(context).push(MaterialPageRoute(
              builder: (_) => Scaffold(appBar: AppBar(title: const Text('Akun')), body: const AccountScreen()),
            )),
            child: Padding(
              padding: const EdgeInsets.only(right: 14),
              child: Stack(children: [
                CircleAvatar(
                  radius: 18,
                  backgroundColor: Sneat.primary.withValues(alpha: .15),
                  backgroundImage: user['image_url'] != null ? NetworkImage(user['image_url']) : null,
                  child: user['image_url'] == null ? const Icon(Icons.person, color: Sneat.primary) : null,
                ),
                Positioned(
                  right: 0,
                  bottom: 0,
                  child: Container(
                    width: 10,
                    height: 10,
                    decoration: BoxDecoration(
                      color: Sneat.success,
                      shape: BoxShape.circle,
                      border: Border.all(color: Colors.white, width: 2),
                    ),
                  ),
                ),
              ]),
            ),
          ),
        ],
      ),
      body: IndexedStack(
        index: _index,
        children: [for (var i = 0; i < 5; i++) _visited.contains(i) ? _page(i) : const SizedBox.shrink()],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() {
          _index = i;
          _visited.add(i);
        }),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'Beranda'),
          NavigationDestination(icon: Icon(Icons.auto_graph_outlined), selectedIcon: Icon(Icons.auto_graph), label: 'Prediksi'),
          NavigationDestination(icon: Icon(Icons.history_outlined), selectedIcon: Icon(Icons.history), label: 'Riwayat'),
          NavigationDestination(icon: Icon(Icons.photo_camera_outlined), selectedIcon: Icon(Icons.photo_camera), label: 'Deteksi CNN'),
          NavigationDestination(icon: Icon(Icons.smart_toy_outlined), selectedIcon: Icon(Icons.smart_toy), label: 'Asisten AI'),
        ],
      ),
    );
  }
}
