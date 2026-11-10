import 'package:flutter/material.dart';

import 'api_client.dart';
import 'design.dart';

/// Real SmartCikoko account login. The existing PHP server decides identity,
/// role, multiple-account selection and Google Authenticator challenge.
class LoginPage extends StatefulWidget {
  final Future<void> Function(String token) onSignedIn;
  const LoginPage({super.key, required this.onSignedIn});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final username = TextEditingController();
  final password = TextEditingController();
  final otp = TextEditingController();
  bool busy = false;
  bool obscure = true;
  bool mfaRequired = false;
  String? error;
  List<Map<String, dynamic>> identities = [];
  Map<String, dynamic>? selected;

  @override
  void dispose() {
    username.dispose();
    password.dispose();
    otp.dispose();
    super.dispose();
  }

  Future<void> submit([Map<String, dynamic>? identity]) async {
    if (busy) return;
    FocusScope.of(context).unfocus();
    setState(() {
      busy = true;
      error = null;
      if (identity != null) selected = identity;
    });
    try {
      final data = await PortalApi.call(
        'login',
        method: 'POST',
        body: {
          'username': username.text.trim(),
          'password': password.text,
          if (selected != null) 'source': selected!['source'],
          if (selected != null) 'id': selected!['id'],
          if (mfaRequired) 'otp': otp.text.trim(),
        },
      );
      if (!mounted) return;
      if (data['selection_required'] == true) {
        setState(() => identities = PortalApi.rows(data['accounts']));
      } else if (data['mfa_required'] == true) {
        setState(() {
          identities = [];
          mfaRequired = true;
        });
      } else if (data['token'] is String) {
        final token = (data['token'] as String);
        password.clear();
        otp.clear();
        await widget.onSignedIn(token);
      } else {
        setState(() => error = 'Server belum memberikan sesi yang valid.');
      }
    } on PortalException catch (e) {
      if (mounted) setState(() => error = e.message);
    } catch (_) {
      if (mounted) setState(() => error = 'Login belum berhasil. Silakan coba lagi.');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  void reset() {
    setState(() {
      mfaRequired = false;
      selected = null;
      identities = [];
      otp.clear();
      error = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: CivicColors.background,
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 470),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(22, 22, 22, 35),
              children: [
                const SizedBox(height: 6),
                Container(
                  height: 265,
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF0A65AF), CivicColors.navy],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(22),
                  ),
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    children: [
                      const CivicLogo(size: 64),
                      const SizedBox(height: 18),
                      const Text(
                        'SmartCikoko',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 29,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 7),
                      const Text(
                        'Aplikasi Pelayanan Digital\nKelurahan Cikoko',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                            color: Color(0xFFDAEBFA),
                            fontSize: 13,
                            height: 1.5),
                      ),
                      const Spacer(),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.location_city,
                              color: Colors.white.withValues(alpha: .65),
                              size: 30),
                          const SizedBox(width: 8),
                          const Text(
                            'KECAMATAN PANCORAN · JAKARTA SELATAN',
                            style: TextStyle(
                              color: Color(0xFFBFDEF1),
                              fontSize: 9,
                              fontWeight: FontWeight.w800,
                              letterSpacing: .3,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 18),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const Text('Masuk ke Akun',
                            style: TextStyle(
                                color: CivicColors.ink,
                                fontSize: 22,
                                fontWeight: FontWeight.w900)),
                        const SizedBox(height: 6),
                        Text(
                          mfaRequired
                              ? 'Masukkan kode Google Authenticator atau kode pemulihan.'
                              : identities.isNotEmpty
                                  ? 'Pilih identitas akun yang akan digunakan.'
                                  : 'Gunakan akun SmartCikoko yang sudah terdaftar.',
                          style: const TextStyle(
                              color: CivicColors.muted, fontSize: 12),
                        ),
                        const SizedBox(height: 22),
                        if (mfaRequired) ...[
                          TextField(
                            controller: otp,
                            autofocus: true,
                            decoration: const InputDecoration(
                              labelText: 'Kode keamanan',
                              prefixIcon: Icon(Icons.verified_user_outlined),
                            ),
                            textInputAction: TextInputAction.done,
                            onSubmitted: (_) => submit(),
                          ),
                          const SizedBox(height: 15),
                          FilledButton(
                              onPressed: busy ? null : () => submit(),
                              child: const Text('Verifikasi dan Masuk')),
                          TextButton(
                              onPressed: busy ? null : reset,
                              child: const Text('Kembali')),
                        ] else if (identities.isNotEmpty) ...[
                          for (final item in identities)
                            Padding(
                              padding: const EdgeInsets.only(bottom: 9),
                              child: OutlinedButton.icon(
                                onPressed: busy ? null : () => submit(item),
                                icon: const Icon(Icons.account_circle_outlined),
                                label: Text((item['name'] ?? 'Akun').toString()),
                              ),
                            ),
                          TextButton(
                              onPressed: busy ? null : reset,
                              child: const Text('Gunakan akun lain')),
                        ] else ...[
                          TextField(
                            controller: username,
                            autofillHints: const [AutofillHints.username],
                            textInputAction: TextInputAction.next,
                            decoration: const InputDecoration(
                              labelText: 'Username atau email',
                              prefixIcon: Icon(Icons.person_outline),
                            ),
                          ),
                          const SizedBox(height: 13),
                          TextField(
                            controller: password,
                            obscureText: obscure,
                            autofillHints: const [AutofillHints.password],
                            textInputAction: TextInputAction.done,
                            onSubmitted: (_) => submit(),
                            decoration: InputDecoration(
                              labelText: 'Kata sandi',
                              prefixIcon: const Icon(Icons.lock_outline),
                              suffixIcon: IconButton(
                                icon: Icon(obscure
                                    ? Icons.visibility_outlined
                                    : Icons.visibility_off_outlined),
                                onPressed: () =>
                                    setState(() => obscure = !obscure),
                              ),
                            ),
                          ),
                          const SizedBox(height: 19),
                          FilledButton(
                            onPressed: busy ? null : () => submit(),
                            child: const Text('Masuk ke Akun'),
                          ),
                        ],
                        if (busy) ...[
                          const SizedBox(height: 14),
                          const LinearProgressIndicator(),
                        ],
                        if (error != null) ...[
                          const SizedBox(height: 14),
                          Text(error!,
                              style: const TextStyle(
                                  color: Color(0xFFB3453C), fontSize: 12)),
                        ],
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 22),
                const Text(
                  'Kelurahan Cikoko · Pelayanan digital terpadu',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: CivicColors.muted, fontSize: 11),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
