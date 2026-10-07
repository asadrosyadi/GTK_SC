import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/sneat.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({super.key});

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  final _profileForm = GlobalKey<FormState>();
  final _passForm = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _email;
  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  String _locale = 'id';
  bool _savingProfile = false;
  bool _savingPass = false;
  bool _uploading = false;
  List<dynamic>? _devices;

  @override
  void initState() {
    super.initState();
    final u = context.read<Session>().user ?? {};
    _name = TextEditingController(text: u['name']?.toString() ?? '');
    _email = TextEditingController(text: u['email']?.toString() ?? '');
    _locale = u['locale']?.toString() ?? 'id';
    if (context.read<Session>().isSuperAdmin) _loadDevices();
  }

  @override
  void dispose() {
    for (final c in [_name, _email, _current, _password, _confirm]) {
      c.dispose();
    }
    super.dispose();
  }

  void _toast(String msg, {bool error = false}) => ScaffoldMessenger.of(context)
      .showSnackBar(SnackBar(content: Text(msg), backgroundColor: error ? Sneat.danger : Agro.green));

  Future<void> _loadDevices() async {
    try {
      final d = await context.read<Session>().api.devices();
      if (mounted) setState(() => _devices = d);
    } on ApiException {
      if (mounted) setState(() => _devices = const []);
    }
  }

  Future<void> _saveProfile() async {
    if (!_profileForm.currentState!.validate()) return;
    final session = context.read<Session>();
    setState(() => _savingProfile = true);
    try {
      final emailChanged = _email.text.trim() != session.user?['email'];
      final user = await session.api.updateProfile({'name': _name.text.trim(), 'email': _email.text.trim(), 'locale': _locale});
      session.setUser(user);
      _toast(emailChanged ? 'Profil disimpan. Verifikasi email baru Anda sebelum masuk lagi.' : 'Profil berhasil diperbarui.');
    } on ApiException catch (e) {
      _toast(e.message, error: true);
    }
    if (mounted) setState(() => _savingProfile = false);
  }

  Future<void> _pickPhoto(ImageSource source) async {
    final session = context.read<Session>();
    final picked = await ImagePicker().pickImage(source: source, maxWidth: 800, imageQuality: 85);
    if (picked == null) return;
    setState(() => _uploading = true);
    try {
      session.setUser(await session.api.uploadPhoto(picked.path));
      _toast('Foto profil diperbarui.');
    } on ApiException catch (e) {
      _toast(e.message, error: true);
    }
    if (mounted) setState(() => _uploading = false);
  }

  Future<void> _savePassword() async {
    if (!_passForm.currentState!.validate()) return;
    setState(() => _savingPass = true);
    try {
      final msg = await context.read<Session>().api.changePassword(_current.text, _password.text, _confirm.text);
      _current.clear();
      _password.clear();
      _confirm.clear();
      _toast(msg);
    } on ApiException catch (e) {
      _toast(e.message, error: true);
    }
    if (mounted) setState(() => _savingPass = false);
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final u = session.user ?? {};
    return ListView(padding: const EdgeInsets.fromLTRB(14, 14, 14, 24), children: [
      SneatCard(
        child: Row(children: [
          GestureDetector(
            onTap: _uploading
                ? null
                : () => showModalBottomSheet(
                      context: context,
                      builder: (ctx) => SafeArea(
                        child: Wrap(children: [
                          ListTile(
                            leading: const Icon(Icons.photo_library_outlined),
                            title: const Text('Pilih dari galeri'),
                            onTap: () {
                              Navigator.pop(ctx);
                              _pickPhoto(ImageSource.gallery);
                            },
                          ),
                          ListTile(
                            leading: const Icon(Icons.photo_camera_outlined),
                            title: const Text('Ambil foto'),
                            onTap: () {
                              Navigator.pop(ctx);
                              _pickPhoto(ImageSource.camera);
                            },
                          ),
                        ]),
                      ),
                    ),
            child: Stack(children: [
              CircleAvatar(
                radius: 34,
                backgroundColor: Sneat.primary.withValues(alpha: .15),
                backgroundImage: u['image_url'] != null ? NetworkImage(u['image_url']) : null,
                child: _uploading ? const CircularProgressIndicator() : null,
              ),
              Positioned(
                right: 0,
                bottom: 0,
                child: Container(
                  padding: const EdgeInsets.all(4),
                  decoration: const BoxDecoration(color: Sneat.primary, shape: BoxShape.circle),
                  child: const Icon(Icons.edit, size: 13, color: Colors.white),
                ),
              ),
            ]),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(u['name']?.toString() ?? '-', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 17, color: Sneat.heading)),
              Text(u['email']?.toString() ?? '-', style: const TextStyle(color: Sneat.muted)),
              const SizedBox(height: 6),
              Wrap(spacing: 6, runSpacing: 4, children: [
                for (final r in (u['roles'] as List? ?? [])) LabelBadge(r.toString(), color: Sneat.primary),
                if (u['iot_id'] != null) LabelBadge('Perangkat: ${u['iot_id']}', color: Agro.green),
              ]),
            ]),
          ),
        ]),
      ),
      if (session.isSuperAdmin) _deviceCard(session),
      SneatCard(
        title: 'Profil',
        icon: Icons.person_outline,
        child: Form(
          key: _profileForm,
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            TextFormField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'Nama'),
              validator: (v) => (v ?? '').trim().isEmpty ? 'Nama wajib diisi' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(labelText: 'Email'),
              validator: (v) => (v ?? '').contains('@') ? null : 'Email tidak valid',
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _locale,
              decoration: const InputDecoration(labelText: 'Bahasa'),
              items: const [
                DropdownMenuItem(value: 'id', child: Text('Bahasa Indonesia')),
                DropdownMenuItem(value: 'en', child: Text('English')),
              ],
              onChanged: (v) => setState(() => _locale = v ?? 'id'),
            ),
            const SizedBox(height: 14),
            FilledButton(
              onPressed: _savingProfile ? null : _saveProfile,
              child: _savingProfile
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Simpan profil'),
            ),
          ]),
        ),
      ),
      SneatCard(
        title: 'Ganti Password',
        icon: Icons.lock_outline,
        child: Form(
          key: _passForm,
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            TextFormField(
              controller: _current,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Password saat ini'),
              validator: (v) => (v ?? '').isEmpty ? 'Wajib diisi' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _password,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Password baru (min. 8 karakter)'),
              validator: (v) => (v ?? '').length < 8 ? 'Minimal 8 karakter' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _confirm,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Ulangi password baru'),
              validator: (v) => v != _password.text ? 'Konfirmasi password tidak cocok' : null,
            ),
            const SizedBox(height: 14),
            FilledButton(
              onPressed: _savingPass ? null : _savePassword,
              child: _savingPass
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Ganti password'),
            ),
          ]),
        ),
      ),
      SneatCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(children: [
            const Icon(Icons.dns_outlined, size: 18, color: Sneat.muted),
            const SizedBox(width: 8),
            Expanded(child: Text(session.baseUrl, style: const TextStyle(color: Sneat.muted, fontSize: 12.5))),
          ]),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            style: OutlinedButton.styleFrom(foregroundColor: Sneat.danger, side: const BorderSide(color: Sneat.danger)),
            onPressed: () async {
              final ok = await showDialog<bool>(
                context: context,
                builder: (ctx) => AlertDialog(
                  title: const Text('Keluar'),
                  content: const Text('Keluar dari akun ini di perangkat ini?'),
                  actions: [
                    TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Batal')),
                    FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Keluar')),
                  ],
                ),
              );
              if (ok == true && context.mounted) {
                Navigator.of(context).popUntil((r) => r.isFirst);
                await context.read<Session>().logout();
              }
            },
            icon: const Icon(Icons.logout),
            label: const Text('Keluar'),
          ),
        ]),
      ),
    ]);
  }

  Widget _deviceCard(Session session) => SneatCard(
        title: 'Perangkat (SuperAdmin)',
        icon: Icons.devices_other,
        child: _devices == null
            ? const Center(child: Padding(padding: EdgeInsets.all(8), child: CircularProgressIndicator()))
            : RadioGroup<String>(
                groupValue: session.iotId ?? session.user?['iot_id']?.toString(),
                onChanged: (v) async {
                  await session.selectDevice(v);
                  _toast('Menampilkan perangkat $v. Muat ulang tiap layar untuk melihat datanya.');
                },
                child: Column(children: [
                for (final d in _devices!.cast<Map>())
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(Icons.sensors, color: d['online'] == true ? Agro.green : Sneat.danger),
                    title: Text('${d['name']} · ${d['iot_id']}', style: const TextStyle(fontSize: 14)),
                    subtitle: Text(d['online'] == true ? 'Aktif' : 'Tidak aktif', style: const TextStyle(fontSize: 12)),
                    trailing: Radio<String>(value: d['iot_id'].toString()),
                  ),
              ]),
              ),
      );
}
