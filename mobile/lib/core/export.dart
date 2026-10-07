import 'dart:io';

import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import 'api_client.dart';

Future<void> exportAndShare(
  BuildContext context,
  ApiClient api,
  String path,
  Map<String, dynamic> query,
  String fallbackName,
) async {
  final messenger = ScaffoldMessenger.of(context);
  messenger.showSnackBar(const SnackBar(content: Text('Menyiapkan file Excel…')));
  try {
    final file = await api.download(path, query, fallbackName);
    final dir = await getTemporaryDirectory();
    final out = File('${dir.path}/${file.name}');
    await out.writeAsBytes(file.bytes);
    messenger.hideCurrentSnackBar();
    await SharePlus.instance.share(ShareParams(
      files: [XFile(out.path, mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')],
      subject: file.name,
    ));
  } on ApiException catch (e) {
    messenger.hideCurrentSnackBar();
    messenger.showSnackBar(SnackBar(content: Text(e.message)));
  }
}
