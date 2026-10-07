import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:provider/provider.dart';

import 'core/session.dart';
import 'core/theme.dart';
import 'screens/home_shell.dart';
import 'screens/login_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('id');
  runApp(AgroGuardApp(session: Session()..load()));
}

class AgroGuardApp extends StatelessWidget {
  const AgroGuardApp({super.key, required this.session});

  final Session session;

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider.value(
      value: session,
      child: MaterialApp(
        title: 'AgroGuard',
        debugShowCheckedModeBanner: false,
        theme: Sneat.theme(),
        locale: const Locale('id'),
        supportedLocales: const [Locale('id'), Locale('en')],
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        home: Consumer<Session>(
          builder: (context, s, _) {
            if (!s.ready) {
              return const Scaffold(
                body: Center(child: CircularProgressIndicator()),
              );
            }
            return s.loggedIn
                ? HomeShell(key: ValueKey('${s.token}|${s.iotId}'))
                : const LoginScreen();
          },
        ),
      ),
    );
  }
}
