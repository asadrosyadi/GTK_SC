import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import 'api_client.dart';

class Session extends ChangeNotifier {
  Session({http.Client? httpClient})
    : _httpClient = httpClient; // ignore: prefer_initializing_formals

  static const defaultBaseUrl = 'http://10.30.112.34/gemastik/backend/html/';
  static const _kBaseUrl = 'base_url';
  static const _kToken = 'api_token';
  static const _kIotId = 'iot_id';

  final http.Client? _httpClient;
  String baseUrl = defaultBaseUrl;
  String? token;
  Map<String, dynamic>? user;

  String? iotId;
  bool ready = false;

  bool get loggedIn => token != null;

  bool get isSuperAdmin =>
      (user?['roles'] as List?)?.contains('SuperAdmin') ?? false;

  late ApiClient api = _buildClient();

  ApiClient _buildClient() => ApiClient(
    baseUrl: baseUrl,
    token: token,
    iotId: iotId,
    client: _httpClient,
    onUnauthorized: _expire,
  );

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    baseUrl = prefs.getString(_kBaseUrl) ?? defaultBaseUrl;
    token = prefs.getString(_kToken);
    iotId = prefs.getString(_kIotId);
    api = _buildClient();
    if (token != null) {
      try {
        user = await api.me();
      } on ApiException catch (e) {
        if (e.unauthorized) await _clear();
      }
    }
    ready = true;
    notifyListeners();
  }

  Future<void> login({
    required String server,
    required String email,
    required String password,
  }) async {
    final url = server.trim().replaceAll(RegExp(r'/+$'), '');
    final client = ApiClient(baseUrl: url, client: _httpClient);
    final res = await client.login(email.trim(), password, 'agroguard-mobile');
    baseUrl = url;
    token = res['token'] as String;
    user = Map<String, dynamic>.from(res['user'] as Map);
    iotId = null;
    api = _buildClient();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kBaseUrl, baseUrl);
    await prefs.setString(_kToken, token!);
    await prefs.remove(_kIotId);
    notifyListeners();
  }

  Future<String> forgotPassword(String server, String email) => ApiClient(
    baseUrl: server.trim().replaceAll(RegExp(r'/+$'), ''),
    client: _httpClient,
  ).forgotPassword(email.trim());

  void setUser(Map<String, dynamic> data) {
    user = data;
    notifyListeners();
  }

  Future<void> selectDevice(String? id) async {
    iotId = (id == null || id == user?['iot_id']) ? null : id;
    api = _buildClient();
    final prefs = await SharedPreferences.getInstance();
    if (iotId == null) {
      await prefs.remove(_kIotId);
    } else {
      await prefs.setString(_kIotId, iotId!);
    }
    notifyListeners();
  }

  Future<void> logout() async {
    await api.logout().catchError(
      (Object _) {},
      test: (e) => e is ApiException,
    );
    await _clear();
    notifyListeners();
  }

  void _expire() {
    if (token == null) return;
    _clear().then((_) => notifyListeners());
  }

  Future<void> _clear() async {
    token = null;
    user = null;
    iotId = null;
    api = _buildClient();
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kToken);
    await prefs.remove(_kIotId);
  }
}
