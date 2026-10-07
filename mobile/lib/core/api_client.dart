import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:http/http.dart' as http;

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  bool get unauthorized => statusCode == 401;

  @override
  String toString() => message;
}

class DownloadedFile {
  DownloadedFile(this.name, this.bytes);
  final String name;
  final Uint8List bytes;
}

class ApiClient {
  ApiClient({
    required this.baseUrl,
    this.token,
    this.iotId,
    http.Client? client,
    this.onUnauthorized,
  }) : _http = client ?? http.Client();

  final String baseUrl;
  String? token;

  String? iotId;
  final http.Client _http;
  final void Function()? onUnauthorized;

  static const _timeout = Duration(seconds: 45);

  static const _longTimeout = Duration(seconds: 180);

  String get _root =>
      baseUrl.endsWith('/') ? baseUrl.substring(0, baseUrl.length - 1) : baseUrl;

  Uri _uri(String path, [Map<String, dynamic>? query]) {
    final q = <String, String>{};
    query?.forEach((k, v) {
      if (v != null && v.toString().isNotEmpty) q[k] = v.toString();
    });
    if (iotId != null && iotId!.isNotEmpty) q['iot_id'] = iotId!;
    return Uri.parse('$_root/api/v1$path').replace(queryParameters: q.isEmpty ? null : q);
  }

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Accept-Language': 'id',
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Future<dynamic> get(String path, [Map<String, dynamic>? query, Duration? timeout]) =>
      _send(() => _http.get(_uri(path, query), headers: _headers), timeout);

  Future<dynamic> post(String path, [Map<String, dynamic>? body, Duration? timeout]) => _send(
        () => _http.post(_uri(path), headers: _headers, body: jsonEncode(body ?? {})),
        timeout,
      );

  Future<dynamic> put(String path, [Map<String, dynamic>? body]) =>
      _send(() => _http.put(_uri(path), headers: _headers, body: jsonEncode(body ?? {})));

  Future<dynamic> _send(Future<http.Response> Function() request, [Duration? timeout]) async {
    final http.Response res;
    try {
      res = await request().timeout(timeout ?? _timeout);
    } on TimeoutException {
      throw ApiException('Server tidak menjawab (timeout). Periksa koneksi.');
    } on SocketException {
      throw ApiException('Tidak dapat terhubung ke server. Periksa internet dan alamat server.');
    } on http.ClientException catch (e) {
      throw ApiException('Koneksi gagal: ${e.message}');
    }
    return _handle(res);
  }

  dynamic _handle(http.Response res) {
    if (res.statusCode == 204) return null;
    dynamic body;
    try {
      body = res.body.isEmpty ? null : jsonDecode(utf8.decode(res.bodyBytes));
    } on FormatException {
      throw ApiException('Respons server bukan JSON (cek alamat server).', statusCode: res.statusCode);
    }

    if (res.statusCode >= 200 && res.statusCode < 300) return body;

    if (res.statusCode == 401) onUnauthorized?.call();
    var message = 'Terjadi kesalahan (${res.statusCode})';
    if (body is Map) {
      final errors = body['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        message = first is List && first.isNotEmpty ? first.first.toString() : first.toString();
      } else if (body['message'] is String) {
        message = body['message'] as String;
      }
    }
    if (res.statusCode == 401) message = 'Sesi berakhir, silakan masuk lagi.';
    if (res.statusCode == 429) message = 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.';
    throw ApiException(message, statusCode: res.statusCode);
  }

  Map<String, dynamic> _data(dynamic body) => Map<String, dynamic>.from(body['data'] as Map);

  Future<Map<String, dynamic>> login(String email, String password, String deviceName) async =>
      Map<String, dynamic>.from(await post('/auth/login', {
        'email': email,
        'password': password,
        'device_name': deviceName,
      }));

  Future<void> logout() => post('/auth/logout');

  Future<Map<String, dynamic>> me() async => _data(await get('/auth/me'));

  Future<String> forgotPassword(String email) async =>
      ((await post('/auth/forgot-password', {'email': email})) as Map)['message'] as String;

  Future<Map<String, dynamic>> updateProfile(Map<String, dynamic> fields) async =>
      _data(await post('/auth/profile', fields));

  Future<Map<String, dynamic>> uploadPhoto(String filePath) async {
    final req = http.MultipartRequest('POST', _uri('/auth/profile'))
      ..headers.addAll({
        'Accept': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      })
      ..files.add(await http.MultipartFile.fromPath('image', filePath));
    return _data(await _send(() async => http.Response.fromStream(await _http.send(req))));
  }

  Future<String> changePassword(String current, String password, String confirmation) async =>
      ((await put('/auth/password', {
        'current_password': current,
        'password': password,
        'password_confirmation': confirmation,
      })) as Map)['message'] as String;

  Future<List<dynamic>> devices() async => (await get('/devices'))['data'] as List<dynamic>;

  Future<Map<String, dynamic>> dashboard() async => _data(await get('/dashboard', null, _longTimeout));

  Future<Map<String, dynamic>> recommendations() async =>
      _data(await get('/recommendations', null, _longTimeout));

  Future<Map<String, dynamic>> forecast(int titik, int days) async =>
      _data(await get('/forecast', {'titik': titik, 'days': days}, _longTimeout));

  Future<Map<String, dynamic>> history(int titik, int days) async =>
      _data(await get('/history', {'titik': titik, 'days': days}, _longTimeout));

  Future<Map<String, dynamic>> detectionSummary(int titik, int days) async =>
      _data(await get('/detections/summary', {'titik': titik, 'days': days}));

  Future<Map<String, dynamic>> aiSummary() async => _data(await get('/ai/summary', null, _longTimeout));

  Future<String> aiChat(String message, List<Map<String, String>> history) async =>
      _data(await post('/ai/chat', {'message': message, 'history': history}, _longTimeout))['answer']
          as String;

  Future<DownloadedFile> download(String path, Map<String, dynamic> query, String fallbackName) async {
    final http.Response res;
    try {
      res = await _http.get(_uri(path, query), headers: {
        'Accept': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      }).timeout(_longTimeout);
    } on TimeoutException {
      throw ApiException('Server tidak menjawab (timeout).');
    } on SocketException {
      throw ApiException('Tidak dapat terhubung ke server.');
    }
    if (res.statusCode != 200) {
      _handle(res);
    }
    final cd = res.headers['content-disposition'] ?? '';
    final name = RegExp(r'filename="?([^";]+)"?').firstMatch(cd)?.group(1) ?? fallbackName;
    return DownloadedFile(name, res.bodyBytes);
  }
}
