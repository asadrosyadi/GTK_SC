import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:taniverse/core/format.dart';
import 'package:taniverse/core/session.dart';
import 'package:taniverse/main.dart';

import 'fixtures.dart';

Future<Session> startApp(WidgetTester tester, FakeApi api, {bool loggedIn = true}) async {
  SharedPreferences.setMockInitialValues(
    loggedIn ? {'api_token': '1|token-uji', 'base_url': 'https://petaniasik.my.id'} : {},
  );
  final session = Session(httpClient: api.client);
  await tester.runAsync(session.load);
  await tester.pumpWidget(AgroGuardApp(session: session));
  await settle(tester);
  return session;
}

Future<void> settle(WidgetTester tester) async {
  for (var i = 0; i < 3; i++) {
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 50)));
    await tester.pump(const Duration(milliseconds: 300));
  }
}

Future<void> tearDownApp(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await tester.pump();
}

void main() {
  setUpAll(() => initializeDateFormatting('id'));

  setUp(() {
    TestWidgetsFlutterBinding.ensureInitialized().platformDispatcher.views.first
      ..physicalSize = const Size(1236, 2745)
      ..devicePixelRatio = 3.0;
  });

  group('format angka seperti web', () {
    test('fmt membuang nol di belakang koma', () {
      expect(fmt(26.0), '26');
      expect(fmt(0.635, digits: 2, unit: 'kPa'), '0.64 kPa');
      expect(fmt(null), '-');
    });

    test('tableNum seperti number_format', () {
      expect(tableNum(1009.6, 'pressure'), '1,010');
      expect(tableNum(26.271, 'temperature'), '26.27');
      expect(tableNum(null, 'ph'), '-');
    });

    test('kelas LSTM', () {
      expect(lstmClass('Healthy').$1, 'Sehat');
      expect(lstmClass('Moderate Stress').$1, 'Stres Sedang');
      expect(lstmClass('High Stress').$1, 'Stres Tinggi');
    });

    test('status dari server', () {
      final s = StatusBadge.fromJson({'code': 'normal', 'label': 'Normal', 'level': 'success'})!;
      expect(s.text, 'Normal');
      expect(StatusBadge.fromJson(null), isNull);
    });
  });

  testWidgets('login gagal menampilkan pesan, login berhasil membuka Beranda', (tester) async {
    final api = FakeApi();
    await startApp(tester, api, loggedIn: false);
    expect(find.text('AgroGuard'), findsOneWidget);
    expect(find.text('Lupa password?'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('email')), 'petani@example.test');
    await tester.enterText(find.byKey(const Key('password')), 'salah');
    await tester.tap(find.byKey(const Key('login')));
    await settle(tester);
    expect(find.text('Email atau password salah.'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('password')), 'secret123');
    await tester.tap(find.byKey(const Key('login')));
    await settle(tester);

    expect(find.text('Pemantauan Lingkungan Waktu Nyata'), findsOneWidget);
    expect(api.requests.any((r) => r.url.path.endsWith('/dashboard')), isTrue);
    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getString('api_token'), '1|token-uji');
    await tearDownApp(tester);
  });

  testWidgets('Beranda memuat panel seperti dashboard web', (tester) async {
    final api = FakeApi();
    await startApp(tester, api);
    expect(find.text('Pemantauan Lingkungan Waktu Nyata'), findsOneWidget);
    expect(find.text('Beranda'), findsWidgets);
    expect(find.text('Deteksi CNN'), findsOneWidget);
    expect(find.text('Asisten AI'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tearDownApp(tester);
  });

  testWidgets('tiap tab memuat data API tanpa galat tata letak', (tester) async {
    final api = FakeApi();
    await startApp(tester, api);
    final tabs = {
      'Prediksi': 'Gabungan Petak 1 & 2',
      'Riwayat': 'Riwayat Sensor IoT',
      'Deteksi CNN': 'Riwayat Deteksi Hama CNN',
      'Asisten AI': 'Kesimpulan Keadaan Sawah',
    };
    for (final MapEntry(key: tab, value: heading) in tabs.entries) {
      await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text(tab)));
      await settle(tester);
      expect(find.textContaining(heading), findsWidgets, reason: tab);
      expect(tester.takeException(), isNull, reason: tab);
    }
    expect(api.requests.any((r) => r.url.path.endsWith('/forecast') && r.url.queryParameters['titik'] == '0'), isTrue);
    expect(find.textContaining('Kondisi sawah'), findsOneWidget);
    await tearDownApp(tester);
  });

  testWidgets('token kedaluwarsa (401) mengembalikan ke halaman masuk', (tester) async {
    final api = FakeApi();
    final session = await startApp(tester, api);
    api.expireToken = true;
    await tester.runAsync(() => session.api.dashboard().catchError((_) => <String, dynamic>{}));
    await settle(tester);
    expect(find.byKey(const Key('login')), findsOneWidget);
    await tearDownApp(tester);
  });
}
