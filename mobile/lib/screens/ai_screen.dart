import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/agro.dart';
import '../widgets/sneat.dart';

class AiScreen extends StatefulWidget {
  const AiScreen({super.key});

  @override
  State<AiScreen> createState() => _AiScreenState();
}

class _AiScreenState extends State<AiScreen> {
  Map<String, dynamic>? _summary;
  String? _summaryError;
  bool _summaryLoading = false;

  final _messages = <Map<String, String>>[];
  final _input = TextEditingController();
  final _scroll = ScrollController();
  bool _sending = false;

  @override
  void initState() {
    super.initState();
    _loadSummary();
  }

  @override
  void dispose() {
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _loadSummary() async {
    setState(() {
      _summaryLoading = true;
      _summaryError = null;
    });
    try {
      _summary = await context.read<Session>().api.aiSummary();
    } on ApiException catch (e) {
      _summaryError = e.message;
    }
    if (mounted) setState(() => _summaryLoading = false);
  }

  Future<void> _send() async {
    final q = _input.text.trim();
    if (q.isEmpty || _sending) return;
    final history = _messages.where((m) => m['error'] == null).toList();
    setState(() {
      _messages.add({'role': 'user', 'content': q});
      _sending = true;
      _input.clear();
    });
    _toBottom();
    try {
      final answer = await context.read<Session>().api.aiChat(
            q,
            history.length > 20 ? history.sublist(history.length - 20) : history,
          );
      _messages.add({'role': 'assistant', 'content': answer});
    } on ApiException catch (e) {
      _messages.add({'role': 'assistant', 'content': e.message, 'error': '1'});
    }
    if (mounted) setState(() => _sending = false);
    _toBottom();
  }

  void _toBottom() => WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_scroll.hasClients) {
          _scroll.animateTo(_scroll.position.maxScrollExtent, duration: const Duration(milliseconds: 250), curve: Curves.easeOut);
        }
      });

  @override
  Widget build(BuildContext context) {
    final provider = _summary?['provider']?.toString() ?? 'AI';
    return Column(children: [
      Expanded(
        child: ListView(controller: _scroll, padding: const EdgeInsets.fromLTRB(14, 14, 14, 12), children: [
          Container(
            margin: const EdgeInsets.only(bottom: 14),
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(Sneat.radius), boxShadow: Sneat.cardShadow),
            clipBehavior: Clip.antiAlias,
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              PanelHeader(
                icon: Icons.smart_toy_outlined,
                title: 'Asisten AI',
                gradient: Agro.aiGradient,
                trailing: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(4)),
                  child: Text(provider.toUpperCase(),
                      style: const TextStyle(color: Sneat.primary, fontSize: 10, fontWeight: FontWeight.w700)),
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(14),
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Row(children: [
                    const Icon(Icons.assignment_outlined, size: 18, color: Sneat.primary),
                    const SizedBox(width: 6),
                    const Expanded(
                      child: Text('Kesimpulan Keadaan Sawah', style: TextStyle(fontWeight: FontWeight.w700, color: Sneat.heading)),
                    ),
                    OutlinedButton(
                      onPressed: _summaryLoading ? null : _loadSummary,
                      style: OutlinedButton.styleFrom(visualDensity: VisualDensity.compact, padding: EdgeInsets.zero, minimumSize: const Size(38, 32)),
                      child: const Icon(Icons.refresh, size: 18),
                    ),
                  ]),
                  const SizedBox(height: 8),
                  Container(
                    constraints: const BoxConstraints(minHeight: 120),
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFE3E6FF)),
                      gradient: const LinearGradient(colors: [Color(0xFFF5F5FF), Color(0xFFEEFBFF)]),
                    ),
                    child: _summaryLoading
                        ? const Row(children: [
                            SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
                            SizedBox(width: 8),
                            Expanded(child: EmptyText('AI sedang merangkum data sawah...')),
                          ])
                        : _summaryError != null
                            ? Row(children: [
                                const Icon(Icons.error_outline, size: 16, color: Color(0xFFC62828)),
                                const SizedBox(width: 6),
                                Expanded(child: Text(_summaryError!, style: const TextStyle(color: Color(0xFFC62828), fontSize: 13))),
                              ])
                            : AiText(_summary?['text']?.toString() ?? '', size: 13.5),
                  ),
                  if (_summary?['generated_at'] != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Text('Dibuat ${_summary!['generated_at']}', style: const TextStyle(color: Sneat.muted, fontSize: 11.5)),
                    ),
                ]),
              ),
            ]),
          ),
          const Row(children: [
            Icon(Icons.chat_bubble_outline, size: 18, color: Sneat.primary),
            SizedBox(width: 6),
            Text('Tanya Asisten', style: TextStyle(fontWeight: FontWeight.w700, color: Sneat.heading)),
          ]),
          const SizedBox(height: 8),
          _bubble(const {
            'role': 'assistant',
            'content': 'Halo! Saya bisa menjawab pertanyaan tentang sawah Anda berdasarkan data sensor, kamera, dan rekomendasi saat ini. Silakan bertanya.',
          }),
          for (final m in _messages) _bubble(m),
          if (_sending)
            _bubble(const {'role': 'assistant', 'content': ''}, typing: true),
        ]),
      ),
      SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
          decoration: const BoxDecoration(color: Colors.white, border: Border(top: BorderSide(color: Agro.line))),
          child: Row(children: [
            Expanded(
              child: TextField(
                controller: _input,
                enabled: !_sending,
                maxLength: 1000,
                minLines: 1,
                maxLines: 4,
                textInputAction: TextInputAction.send,
                onSubmitted: (_) => _send(),
                decoration: const InputDecoration(
                  hintText: 'Tulis pertanyaan, mis. bolehkah saya menyemprot obat besok?',
                  counterText: '',
                ),
              ),
            ),
            const SizedBox(width: 8),
            FilledButton(
              onPressed: _sending ? null : _send,
              style: FilledButton.styleFrom(padding: const EdgeInsets.all(14), minimumSize: const Size(48, 48)),
              child: const Icon(Icons.send, size: 20),
            ),
          ]),
        ),
      ),
    ]);
  }

  Widget _bubble(Map<String, String> m, {bool typing = false}) {
    final user = m['role'] == 'user';
    final error = m['error'] != null;
    return Align(
      alignment: user ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * .82),
        padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 9),
        decoration: BoxDecoration(
          color: user ? Sneat.primary : Colors.white,
          border: user ? null : Border.all(color: const Color(0xFFE3E6FF)),
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(14),
            topRight: const Radius.circular(14),
            bottomLeft: Radius.circular(user ? 14 : 4),
            bottomRight: Radius.circular(user ? 4 : 14),
          ),
        ),
        child: typing
            ? const Row(mainAxisSize: MainAxisSize.min, children: [
                SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2)),
                SizedBox(width: 8),
                EmptyText('Mengetik...'),
              ])
            : user
                ? Text(m['content']!, style: const TextStyle(color: Colors.white, fontSize: 13.5, height: 1.45))
                : error
                    ? Text(m['content']!, style: const TextStyle(color: Color(0xFFC62828), fontSize: 13))
                    : AiText(m['content']!, size: 13.5),
      ),
    );
  }
}
