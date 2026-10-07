import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

class FakeApi {
  bool expireToken = false;
  final requests = <http.Request>[];

  static Map<String, dynamic> _file(String name) =>
      jsonDecode(File('test/fixtures/$name.json').readAsStringSync()) as Map<String, dynamic>;

  final dashboard = _file('dashboard');
  final recommendations = _file('recommendations');
  final forecast = _file('forecast');
  final history = _file('history');
  final detections = _file('detections_summary');

  MockClient get client => MockClient((req) async {
        requests.add(req);
        final path = req.url.path;
        http.Response json(Object body, [int status = 200]) => http.Response(
              jsonEncode(body),
              status,
              headers: {'content-type': 'application/json; charset=utf-8'},
            );

        if (path.endsWith('/auth/login')) {
          final body = jsonDecode(req.body) as Map;
          if (body['password'] != 'secret123') {
            return json({
              'message': 'Email atau password salah.',
              'errors': {
                'email': ['Email atau password salah.'],
              },
            }, 422);
          }
          return json({'token': '1|token-uji', 'token_type': 'Bearer', 'user': user}, 201);
        }
        if (path.endsWith('/auth/forgot-password')) {
          return json({'message': 'Kami telah mengirim tautan reset password ke email Anda.'});
        }
        if (req.headers['Authorization'] != 'Bearer 1|token-uji' || expireToken) {
          return json({'message': 'Unauthenticated.'}, 401);
        }
        if (path.endsWith('/auth/me')) return json({'data': user});
        if (path.endsWith('/auth/logout')) return http.Response('', 204);
        if (path.endsWith('/dashboard')) return json(dashboard);
        if (path.endsWith('/recommendations')) return json(recommendations);
        if (path.endsWith('/forecast')) return json(forecast);
        if (path.endsWith('/history')) return json(history);
        if (path.endsWith('/detections/summary')) return json(detections);
        if (path.endsWith('/ai/summary')) {
          return json({
            'data': {'text': '**Kondisi sawah** baik.\n- Pantau air petak 2', 'provider': 'gemini', 'generated_at': '2026-10-07 13:40'},
          });
        }
        return json({'message': 'Not found'}, 404);
      });
}

const user = {
  'id': 1,
  'name': 'Petani',
  'email': 'petani@example.test',
  'iot_id': 'jTZids5M',
  'locale': 'id',
  'roles': [],
};
