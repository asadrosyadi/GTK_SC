import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../core/session.dart';
import '../core/theme.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _form = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  late final TextEditingController _server;
  bool _hide = true;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _server = TextEditingController(text: context.read<Session>().baseUrl);
  }

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _server.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<Session>().login(
        server: _server.text,
        email: _email.text,
        password: _password.text,
      );
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } on FormatException {
      setState(() => _error = 'Alamat server tidak valid.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _forgot() async {
    final email = _email.text.trim();
    if (!email.contains('@')) {
      setState(() => _error = 'Isi email Anda terlebih dahulu, lalu ketuk "Lupa password?".');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final msg = await context.read<Session>().forgotPassword(_server.text, email);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } on FormatException {
      setState(() => _error = 'Alamat server tidak valid.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: Container(
              constraints: const BoxConstraints(maxWidth: 420),
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(Sneat.radius),
                boxShadow: Sneat.cardShadow,
              ),
              child: Material(
                type: MaterialType.transparency,
                child: Form(
                  key: _form,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: const [
                          Icon(Icons.spa_outlined, color: Agro.green, size: 34),
                          SizedBox(width: 8),
                          Text(
                            'AgroGuard',
                            style: TextStyle(
                              fontSize: 26,
                              fontWeight: FontWeight.w700,
                              color: Sneat.heading,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),
                      const Text(
                        'Selamat datang! 👋',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w600,
                          color: Sneat.heading,
                        ),
                      ),
                      const SizedBox(height: 4),
                      const Text(
                        'Masuk untuk memantau sawah Anda',
                        style: TextStyle(color: Sneat.body),
                      ),
                      const SizedBox(height: 20),
                      if (_error != null)
                        Container(
                          padding: const EdgeInsets.all(10),
                          margin: const EdgeInsets.only(bottom: 14),
                          decoration: BoxDecoration(
                            color: Sneat.danger.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            _error!,
                            style: const TextStyle(color: Sneat.danger),
                          ),
                        ),
                      const _FieldLabel('EMAIL'),
                      TextFormField(
                        key: const Key('email'),
                        controller: _email,
                        keyboardType: TextInputType.emailAddress,
                        autofillHints: const [AutofillHints.email],
                        decoration: const InputDecoration(
                          hintText: 'Masukkan email',
                        ),
                        validator: (v) => (v == null || !v.contains('@'))
                            ? 'Email tidak valid'
                            : null,
                      ),
                      const SizedBox(height: 14),
                      const _FieldLabel('PASSWORD'),
                      TextFormField(
                        key: const Key('password'),
                        controller: _password,
                        obscureText: _hide,
                        autofillHints: const [AutofillHints.password],
                        decoration: InputDecoration(
                          hintText: '············',
                          suffixIcon: IconButton(
                            icon: Icon(
                              _hide
                                  ? Icons.visibility_off_outlined
                                  : Icons.visibility_outlined,
                            ),
                            onPressed: () => setState(() => _hide = !_hide),
                          ),
                        ),
                        validator: (v) => (v == null || v.isEmpty)
                            ? 'Password wajib diisi'
                            : null,
                        onFieldSubmitted: (_) => _submit(),
                      ),
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton(
                          key: const Key('forgot'),
                          onPressed: _busy ? null : _forgot,
                          child: const Text('Lupa password?'),
                        ),
                      ),
                      Theme(
                        data: Theme.of(context)
                            .copyWith(dividerColor: Colors.transparent),
                        child: ExpansionTile(
                          tilePadding: EdgeInsets.zero,
                          title: const Text(
                            'Pengaturan server',
                            style: TextStyle(fontSize: 13, color: Sneat.body),
                          ),
                          children: [
                            TextFormField(
                              key: const Key('server'),
                              controller: _server,
                              keyboardType: TextInputType.url,
                              decoration: const InputDecoration(
                                hintText: 'https://petaniasik.my.id',
                                helperText: 'Alamat Laravel tanpa /api/v1',
                              ),
                              validator: (v) {
                                final u = Uri.tryParse((v ?? '').trim());
                                return (u == null ||
                                        !u.hasScheme ||
                                        u.host.isEmpty)
                                    ? 'Alamat server tidak valid'
                                    : null;
                              },
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      FilledButton(
                        key: const Key('login'),
                        onPressed: _busy ? null : _submit,
                        child: _busy
                            ? const SizedBox(
                                height: 18,
                                width: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : const Text('Masuk'),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _FieldLabel extends StatelessWidget {
  const _FieldLabel(this.text);
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Text(
      text,
      style: const TextStyle(
        fontSize: 12,
        color: Sneat.heading,
        fontWeight: FontWeight.w500,
      ),
    ),
  );
}
