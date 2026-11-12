import 'dart:async';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'mobile_api.dart';
import 'mobile_push.dart';

/// Connected cPanel workspace for the live SmartCikoko Mobile API.
///
/// Kept separate from the older portal login until legacy form endpoints have
/// been migrated. Tokens for the two API backends must never be interchanged.
class ConnectedCikokoPage extends StatefulWidget {
  const ConnectedCikokoPage({
    super.key,
    this.startWithChat = false,
    this.threadUserId,
  });

  final bool startWithChat;
  final String? threadUserId;

  @override
  State<ConnectedCikokoPage> createState() => _ConnectedCikokoPageState();
}

class _ConnectedCikokoPageState extends State<ConnectedCikokoPage> {
  static const _storage = FlutterSecureStorage();
  static const _tokenKey = 'smartcikoko_mobile_api_token';

  final _username = TextEditingController();
  final _password = TextEditingController();
  final _message = TextEditingController();
  Timer? _poller;

  String? _token;
  Map<String, dynamic> _user = {};
  bool _loading = true;
  bool _sending = false;
  bool _fetching = false;
  bool _staffChat = false;
  String? _error;
  String? _threadId;
  int _tab = 0;
  int _unread = 0;
  List<Map<String, dynamic>> _messages = [];
  List<Map<String, dynamic>> _threads = [];
  List<Map<String, dynamic>> _events = [];
  List<Map<String, dynamic>> _history = [];
  List<Map<String, dynamic>> _services = [];
  final Map<int, Future<Uint8List>> _imageCache = {};

  @override
  void initState() {
    super.initState();
    _restore();
    _poller = Timer.periodic(const Duration(seconds: 4), (_) {
      if (mounted && _token != null && _tab == 1 && !_fetching) {
        _refresh(silent: true);
      }
    });
  }

  @override
  void dispose() {
    _poller?.cancel();
    _username.dispose();
    _password.dispose();
    _message.dispose();
    super.dispose();
  }

  void _showError(Object error) {
    if (mounted) {
      setState(() => _error = error is MobileApiException
          ? error.message : 'Permintaan tidak berhasil. Coba kembali.');
    }
  }

  Future<void> _restore() async {
    try {
      final saved = await _storage.read(key: _tokenKey);
      if (saved != null) await _setSession(saved);
    } catch (_) {
      // A missing or expired token must not block the login screen.
      try { await _storage.delete(key: _tokenKey); } catch (_) {}
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _setSession(String token) async {
    final account = await MobileApi.request('me', token: token);
    final permission = await MobileApi.request('permissions', token: token);
    final user = Map<String, dynamic>.from(account['user'] as Map? ?? {});
    final caps = Map<String, dynamic>.from(
        permission['capabilities'] as Map? ?? {});
    if (!mounted) return;
    setState(() {
      _token = token;
      _user = user;
      _staffChat = caps['chat:reply'] == true ||
          (caps['chat:reply'] is num && caps['chat:reply'] != 0);
      _error = null;
      _tab = widget.startWithChat ? 1 : 0;
      _threadId = _staffChat ? widget.threadUserId : null;
    });
    await _refresh(silent: true);
    unawaited(MobilePush.registerForAccount(token));
  }

  Future<void> _login() async {
    if (_sending || _username.text.trim().isEmpty ||
        _password.text.isEmpty) return;
    setState(() { _sending = true; _error = null; });
    try {
      final result = await MobileApi.request('login', method: 'POST', body: {
        'username': _username.text.trim(), 'password': _password.text,
      });
      final token = result['token'];
      if (token is! String || token.isEmpty) {
        throw const MobileApiException('Token login tidak diberikan server.');
      }
      await _setSession(token);
      await _storage.write(key: _tokenKey, value: token);
      _password.clear();
    } catch (e) {
      _showError(e);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _logout() async {
    final token = _token;
    if (token != null) {
      try {
        await MobileApi.request('logout', token: token, method: 'POST');
      } catch (_) {}
    }
    if (token != null) await MobilePush.releaseAccount(token);
    await _storage.delete(key: _tokenKey);
    if (!mounted) return;
    setState(() {
      _imageCache.clear();
      _token = null;
      _user = {};
      _messages = [];
      _threads = [];
      _events = [];
      _history = [];
      _services = [];
      _threadId = null;
      _tab = 0;
      _error = null;
    });
  }

  Future<void> _refresh({bool silent = false}) async {
    if (_fetching || _token == null) return;
    _fetching = true;
    if (!silent && mounted) setState(() => _loading = true);
    try {
      final token = _token!;
      switch (_tab) {
        case 0:
          final result = await MobileApi.request('integrated_services_public');
          if (!mounted || token != _token) return;
          setState(() => _services = MobileApi.rows(
              result['items'] ?? result['services']));
          break;
        case 1:
          if (_staffChat && _threadId == null) {
            final result = await MobileApi.request('chat_threads', token: token);
            if (!mounted || token != _token) return;
            setState(() => _threads = MobileApi.rows(result['threads']));
          } else {
            final action = _staffChat ? 'support_admin_thread' : 'chat';
            if (!_staffChat) {
              await MobileApi.request('chat_inbox', token: token);
            }
            final result = await MobileApi.request(action, token: token,
                query: _staffChat ? {'user_id': _threadId!} : null);
            if (!mounted || token != _token) return;
            setState(() => _messages = MobileApi.rows(result['messages']));
          }
          break;
        case 2:
          final result = await MobileApi.request('events', token: token);
          if (!mounted || token != _token) return;
          setState(() {
            _events = MobileApi.rows(result['events']);
            _unread = (result['unread_count'] as num?)?.toInt() ?? 0;
          });
          break;
        case 3:
          final result = await MobileApi.request('history', token: token);
          final rows = MobileApi.rows(result['items']);
          // RT/RW is a separate cPanel module; it is not in 'history'.
          // Other roles may not have access, so leave their history usable.
          try {
            final rtRw = await MobileApi.request('rt_rw_list', token: token);
            for (final letter in MobileApi.rows(rtRw['items'])) {
              final id = (letter['id'] ?? '').toString();
              rows.add({
                'id': letter['id'],
                'kind': 'rt_rw',
                'title': 'Pengantar RT/RW - ' +
                    (letter['subject'] ?? 'Permohonan').toString(),
                'reference': 'CK-RT-' + id.padLeft(6, '0'),
                'status': letter['status'],
                'created_at': letter['created_at'],
              });
            }
          } on MobileApiException catch (e) {
            if (e.statusCode != 403 && e.statusCode != 404) rethrow;
          }
          rows.sort((a, b) => (b['created_at'] ?? '').toString()
              .compareTo((a['created_at'] ?? '').toString()));
          if (!mounted || token != _token) return;
          setState(() => _history = rows);
          break;
      }
      if (mounted) setState(() => _error = null);
    } catch (e) {
      if (!silent) _showError(e);
    } finally {
      _fetching = false;
      if (mounted && !silent) setState(() => _loading = false);
    }
  }

  void _selectTab(int index) {
    setState(() {
      _tab = index;
      _error = null;
      _loading = false;
    });
    _refresh();
  }

  void _openThread(String? id) {
    if (_staffChat && (id == null || id.isEmpty)) return;
    setState(() {
      _threadId = _staffChat ? id : null;
      _tab = 1;
      _messages = [];
    });
    _refresh();
  }

  Future<void> _sendMessage() async {
    final token = _token;
    final content = _message.text.trim();
    if (token == null || _sending || content.isEmpty ||
        (_staffChat && _threadId == null)) return;
    setState(() { _sending = true; _error = null; });
    try {
      await MobileApi.request(_staffChat ? 'chat_reply' : 'chat_send',
          method: 'POST', token: token,
          body: {
            'message': content,
            if (_staffChat) 'user_id': _threadId,
          });
      _message.clear();
      await _refresh(silent: true);
    } catch (e) {
      _showError(e);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _sendPhoto() async {
    final token = _token;
    if (token == null || _sending || (_staffChat && _threadId == null)) return;
    final choice = await FilePicker.platform.pickFiles(
        type: FileType.image, allowMultiple: false, withData: true);
    if (choice == null || choice.files.isEmpty) return;
    final file = choice.files.single;
    final bytes = file.bytes;
    if (bytes == null) {
      _showError(const MobileApiException('Foto tidak dapat dibaca.'));
      return;
    }
    setState(() { _sending = true; _error = null; });
    try {
      await MobileApi.sendChatPhoto(
          token: token,
          bytes: bytes,
          filename: file.name,
          caption: _message.text.trim(),
          recipientId: _staffChat ? _threadId : null);
      _message.clear();
      await _refresh(silent: true);
    } catch (e) {
      _showError(e);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _openEvent(Map<String, dynamic> event) async {
    final token = _token;
    if (token == null) return;
    try {
      await MobileApi.request('event_read', token: token,
          method: 'POST', body: {'id': event['id']});
    } catch (e) { _showError(e); }
    if (!mounted) return;
    if (event['object_type'] == 'chat') {
      setState(() {
        _tab = 1;
        _threadId = null;
      });
      _refresh();
    } else {
      _selectTab(3);
    }
  }

  Widget _loginForm() => Scaffold(
        appBar: AppBar(title: const Text('Layanan Cikoko Terhubung')),
        body: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 440),
            child: ListView(padding: const EdgeInsets.all(20), children: [
              const Icon(Icons.verified_user_outlined, size: 54),
              const SizedBox(height: 16),
              const Text('Login akun cPanel Smart Cikoko',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              const Text('Menggunakan Mobile API website yang sama.',
                  textAlign: TextAlign.center),
              const SizedBox(height: 24),
              TextField(controller: _username,
                  decoration: const InputDecoration(labelText: 'Username')),
              const SizedBox(height: 12),
              TextField(controller: _password, obscureText: true,
                  onSubmitted: (_) => _login(),
                  decoration: const InputDecoration(labelText: 'Kata sandi')),
              const SizedBox(height: 16),
              FilledButton(onPressed: _sending ? null : _login,
                  child: const Text('Masuk')),
              if (_sending) const LinearProgressIndicator(),
              if (_error != null) Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(_error!, style: const TextStyle(color: Colors.red))),
            ]),
          ),
        ),
      );

  Widget _home() => RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(padding: const EdgeInsets.all(16), children: [
          Text('Halo, ' + (_user['name'] ?? 'Pengguna').toString(),
              style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
          const Text('Data layanan berasal dari website Cikoko yang sama.'),
          const SizedBox(height: 16),
          const Text('Layanan tersedia', style: TextStyle(fontSize: 17,
              fontWeight: FontWeight.bold)),
          if (_services.isEmpty) const ListTile(
              title: Text('Katalog belum tersedia atau belum disinkronkan.')),
          for (final item in _services)
            Card(child: ListTile(
              leading: const Icon(Icons.home_repair_service_outlined),
              title: Text((item['title'] ?? item['name'] ??
                  'Layanan').toString()),
              subtitle: Text((item['description'] ?? '').toString(),
                  maxLines: 2, overflow: TextOverflow.ellipsis),
            )),
          const SizedBox(height: 18),
          FilledButton.icon(
            icon: const Icon(Icons.message_outlined),
            onPressed: () => _selectTab(1),
            label: const Text('Hubungi Petugas'),
          ),
        ]),
      );

  Widget _photoPreview(Map<String, dynamic> item) {
    final id = (item['id'] as num?)?.toInt() ?? 0;
    if (id < 1 || _token == null) return const SizedBox.shrink();
    return FutureBuilder<Uint8List>(
      future: _imageCache.putIfAbsent(id,
          () => MobileApi.chatPhoto(token: _token!, messageId: id)),
      builder: (context, snap) {
        if (snap.hasData) {
          return ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: Image.memory(snap.data!, height: 180,
                fit: BoxFit.contain),
          );
        }
        if (snap.hasError) return const Text('Foto tidak dapat dibuka.');
        return const SizedBox(height: 40,
            child: Center(child: CircularProgressIndicator()));
      },
    );
  }

  Widget _chat() {
    if (_staffChat && _threadId == null) {
      return RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(children: [
          const ListTile(title: Text('Pesan Masuk Warga')),
          if (_threads.isEmpty) const ListTile(
              title: Text('Belum ada percakapan.')),
          for (final thread in _threads)
            ListTile(
              leading: const Icon(Icons.forum_outlined),
              title: Text((thread['name'] ?? thread['user_id']).toString()),
              subtitle: Text((thread['message'] ?? '').toString(),
                  maxLines: 1),
              onTap: () => _openThread(
                  (thread['user_id'] ?? '').toString()),
            ),
        ]),
      );
    }
    return Column(children: [
      if (_staffChat) ListTile(
        leading: IconButton(
            icon: const Icon(Icons.arrow_back),
            onPressed: () => setState(() {
              _threadId = null;
              _messages = [];
            })),
        title: const Text('Percakapan Warga'),
      ),
      Expanded(child: RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(padding: const EdgeInsets.all(12), children: [
          if (_messages.isEmpty) const ListTile(
              title: Text('Belum ada pesan.')),
          for (final item in _messages)
            Align(
              alignment: ((_staffChat && item['sender'] == 'admin') ||
                  (!_staffChat && item['sender'] == 'warga'))
                  ? Alignment.centerRight : Alignment.centerLeft,
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(10),
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 280),
                    child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text((item['message'] ?? '').toString()),
                          if (item['attachment'] is Map) _photoPreview(item),
                          const SizedBox(height: 4),
                          Row(mainAxisSize: MainAxisSize.min, children: [
                            Text((item['created_at'] ?? '').toString(),
                                style: const TextStyle(fontSize: 10)),
                            const SizedBox(width: 6),
                            if ((_staffChat && item['sender'] == 'admin') ||
                                (!_staffChat && item['sender'] == 'warga'))
                              Icon(
                                ['read', 'read_legacy', 'delivered'].contains(
                                    item['delivery_status'])
                                    ? Icons.done_all : Icons.done,
                                size: 15,
                                color: ['read', 'read_legacy'].contains(
                                    item['delivery_status'])
                                    ? Colors.green : Colors.grey,
                              ),
                          ]),
                        ]),
                  ),
                ),
              ),
            ),
        ]),
      )),
      Padding(
        padding: const EdgeInsets.all(8),
        child: Row(children: [
          IconButton(
              tooltip: 'Pilih foto galeri',
              onPressed: _sending ? null : _sendPhoto,
              icon: const Icon(Icons.add_photo_alternate_outlined)),
          Expanded(child: TextField(
              controller: _message,
              maxLines: 3, minLines: 1,
              decoration: const InputDecoration(hintText: 'Ketik pesan'))),
          IconButton(
              tooltip: 'Kirim pesan',
              onPressed: _sending ? null : _sendMessage,
              icon: const Icon(Icons.send)),
        ]),
      ),
    ]);
  }

  Widget _notifications() => RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(children: [
          ListTile(title: Text('Notifikasi (' + _unread.toString() + ' belum dibaca)')),
          if (_events.isEmpty) const ListTile(
              title: Text('Belum ada notifikasi.')),
          for (final event in _events)
            ListTile(
              leading: Icon(event['object_type'] == 'chat'
                  ? Icons.chat_bubble_outline : Icons.notifications_outlined),
              title: Text((event['title'] ?? 'Notifikasi').toString()),
              subtitle: Text((event['message'] ?? '').toString()),
              trailing: (event['is_read'] == 0 ||
                  event['is_read'] == '0')
                  ? const Icon(Icons.circle, size: 9) : null,
              onTap: () => _openEvent(event),
            ),
        ]),
      );

  Future<void> _openLetter(Map<String, dynamic> item) async {
    if (item['kind'] != 'rt_rw' || _token == null) return;
    try {
      final detail = await MobileApi.request('rt_rw_detail',
          token: _token!, query: {'id': (item['id'] ?? '').toString()});
      if (!mounted) return;
      final record = Map<String, dynamic>.from(detail['detail'] as Map? ?? {});
      final timeline = MobileApi.rows(detail['timeline']);
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        builder: (_) => SafeArea(
          child: ListView(
            shrinkWrap: true,
            padding: const EdgeInsets.all(18),
            children: [
              Text((record['reference_no'] ?? 'Surat RT/RW').toString(),
                  style: const TextStyle(fontSize: 18,
                      fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              Text('Status: ' + (record['status'] ?? '-').toString()),
              Text('Keperluan: ' + (record['subject'] ?? '-').toString()),
              const Divider(),
              const Text('Riwayat Progres'),
              for (final event in timeline)
                ListTile(
                  title: Text((event['new_status'] ?? '').toString()),
                  subtitle: Text((event['notes'] ?? '').toString()),
                  trailing: Text((event['created_at'] ?? '').toString(),
                      style: const TextStyle(fontSize: 10)),
                ),
            ],
          ),
        ),
      );
    } catch (e) { _showError(e); }
  }

  Widget _records() => RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(children: [
          const ListTile(title: Text('Riwayat Pengajuan & Laporan')),
          if (_history.isEmpty) const ListTile(
              title: Text('Belum ada pengajuan pada akun ini.')),
          for (final item in _history)
            ListTile(
              leading: const Icon(Icons.description_outlined),
              title: Text((item['reference'] ?? item['title'] ??
                  'Pengajuan').toString()),
              subtitle: Text((item['status'] ?? '-').toString()),
              onTap: item['kind'] == 'rt_rw'
                  ? () => _openLetter(item) : null,
            ),
        ]),
      );

  @override
  Widget build(BuildContext context) {
    if (_loading && _token == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (_token == null) return _loginForm();
    return Scaffold(
      appBar: AppBar(
        title: const Text('Smart Cikoko Terhubung'),
        actions: [
          IconButton(onPressed: () => _refresh(),
              icon: const Icon(Icons.refresh)),
          PopupMenuButton<String>(
            onSelected: (value) {
              if (value == 'logout') _logout();
            },
            itemBuilder: (_) => const [
              PopupMenuItem(value: 'logout', child: Text('Keluar akun')),
            ],
          ),
        ],
      ),
      body: Column(children: [
        if (_error != null)
          MaterialBanner(content: Text(_error!),
              actions: [TextButton(onPressed: () => setState(
                  () => _error = null), child: const Text('Tutup'))]),
        Expanded(child: _loading
            ? const Center(child: CircularProgressIndicator())
            : [_home(), _chat(), _notifications(), _records()][_tab]),
      ]),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: _selectTab,
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined),
              label: 'Beranda'),
          NavigationDestination(icon: Icon(Icons.chat_outlined),
              label: 'Chat'),
          NavigationDestination(icon: Icon(Icons.notifications_outlined),
              label: 'Notifikasi'),
          NavigationDestination(icon: Icon(Icons.history_outlined),
              label: 'Riwayat'),
        ],
      ),
    );
  }
}
