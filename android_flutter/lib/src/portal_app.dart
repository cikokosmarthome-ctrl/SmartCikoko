import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../citizen_requests.dart';
import 'api_client.dart';
import 'design.dart';
import 'forms_page.dart';
import 'login_page.dart';

const _storage = FlutterSecureStorage();
const _storageKey = 'smartcikoko_api_token';

class SmartCikokoApp extends StatelessWidget {
  const SmartCikokoApp({super.key});
  @override
  Widget build(BuildContext context) => MaterialApp(
        title: 'SmartCikoko',
        theme: smartTheme(),
        debugShowCheckedModeBanner: false,
        home: const PortalShell(),
      );
}

class PortalShell extends StatefulWidget {
  const PortalShell({super.key});
  @override
  State<PortalShell> createState() => _PortalShellState();
}

class _PortalShellState extends State<PortalShell> {
  bool loading = true;
  String? token;
  Map<String, dynamic> user = {};
  Map<String, dynamic> permissions = {};
  int tab = 0;

  @override
  void initState() {
    super.initState();
    restore();
  }

  Future<void> restore() async {
    try {
      final saved = await _storage.read(key: _storageKey);
      if (saved != null) {
        final account = await PortalApi.call('me', token: saved);
        if (!mounted) return;
        setState(() {
          token = saved;
          user = Map<String, dynamic>.from(account['user'] as Map);
          permissions = Map<String, dynamic>.from(account['permissions'] as Map? ?? {});
        });
      }
    } catch (_) {
      // An expired session or unavailable secure storage must never trap
      // the user indefinitely on the splash spinner.
      try {
        await _storage.delete(key: _storageKey);
      } catch (_) {
        // Storage is unavailable; continue without authentication.
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> signIn(String newToken) async {
    final account = await PortalApi.call('me', token: newToken);
    await _storage.write(key: _storageKey, value: newToken);
    if (!mounted) return;
    setState(() {
      token = newToken;
      user = Map<String, dynamic>.from(account['user'] as Map);
      permissions = Map<String, dynamic>.from(account['permissions'] as Map? ?? {});
      tab = 0;
    });
  }

  Future<void> logout() async {
    final current = token;
    if (current != null) {
      try {
        await PortalApi.call('logout', method: 'POST', token: current);
      } catch (_) {
        // Even if network is unavailable, clear local access credentials.
      }
    }
    await _storage.delete(key: _storageKey);
    if (mounted) {
      setState(() {
        token = null;
        user = {};
        permissions = {};
        tab = 0;
      });
    }
  }

  Future<void> login() async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute(
        builder: (loginContext) => LoginPage(
          onSignedIn: (value) async {
            await signIn(value);
            if (loginContext.mounted) Navigator.pop(loginContext);
          },
        ),
      ),
    );
  }

  Future<void> openService() async {
    if (token == null) {
      await login();
      return;
    }
    final isCitizen = user['source'] == 'smartapp' &&
        ['warga', 'ptsp', 'lurah'].contains(user['role']);
    if (isCitizen) {
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => CitizenRequestsScreen(token: token!, user: user),
        ),
      );
    } else {
      await Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => FormsPage(token: token!)),
      );
    }
  }

  Future<void> openForms() async {
    if (token == null) {
      await login();
      return;
    }
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => FormsPage(token: token!)),
    );
  }

  Future<void> openCitizen() async {
    if (token == null) {
      await login();
      return;
    }
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => CitizenRequestsScreen(token: token!, user: user),
      ),
    );
  }

  Future<void> openAdmin() async {
    if (token == null || permissions['super_admin'] != true) return;
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => AdminDashboard(token: token!),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Scaffold(
          body: Center(child: CircularProgressIndicator()));
    }
    return Scaffold(
      appBar: AppBar(
        toolbarHeight: 64,
        titleSpacing: 15,
        title: const Row(
          children: [
            CivicLogo(size: 37),
            SizedBox(width: 10),
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Kelurahan Cikoko',
                  style: TextStyle(
                      fontSize: 16, fontWeight: FontWeight.w900)),
              Text('Kec. Pancoran · Jakarta Selatan',
                  style: TextStyle(
                      fontSize: 10, color: CivicColors.muted)),
            ]),
          ],
        ),
        actions: [
          if (token == null)
            TextButton(
              onPressed: login,
              child: const Text('Masuk',
                  style: TextStyle(fontWeight: FontWeight.w800)),
            )
          else
            IconButton(
                tooltip: 'Akun',
                onPressed: () => setState(() => tab = 3),
                icon: const Icon(Icons.account_circle_outlined)),
        ],
      ),
      body: IndexedStack(index: tab, children: [
        HomeTab(
          signedIn: token != null,
          user: user,
          onService: openService,
          onForms: openForms,
          onInfo: () => setState(() => tab = 1),
          onHistory: () => setState(() => tab = 2),
        ),
        ServicesTab(
          signedIn: token != null,
          user: user,
          permissions: permissions,
          token: token,
          onLogin: login,
          onForms: openForms,
          onCitizen: openCitizen,
          onAdmin: openAdmin,
        ),
        HistoryTab(
          token: token,
          onLogin: login,
          onForms: openForms,
          onCitizen: openCitizen,
        ),
        AccountTab(
          token: token,
          user: user,
          permissions: permissions,
          onLogin: login,
          onLogout: logout,
          onAdmin: openAdmin,
        ),
      ]),
      bottomNavigationBar: NavigationBar(
        selectedIndex: tab,
        onDestinationSelected: (next) => setState(() => tab = next),
        destinations: const [
          NavigationDestination(
              icon: Icon(Icons.home_outlined),
              selectedIcon: Icon(Icons.home),
              label: 'Beranda'),
          NavigationDestination(
              icon: Icon(Icons.widgets_outlined),
              selectedIcon: Icon(Icons.widgets),
              label: 'Layanan'),
          NavigationDestination(
              icon: Icon(Icons.history_outlined),
              selectedIcon: Icon(Icons.history),
              label: 'Riwayat'),
          NavigationDestination(
              icon: Icon(Icons.person_outline),
              selectedIcon: Icon(Icons.person),
              label: 'Akun'),
        ],
      ),
    );
  }
}

class HomeTab extends StatelessWidget {
  final bool signedIn;
  final Map<String, dynamic> user;
  final VoidCallback onService, onForms, onInfo, onHistory;
  const HomeTab({
    super.key,
    required this.signedIn,
    required this.user,
    required this.onService,
    required this.onForms,
    required this.onInfo,
    required this.onHistory,
  });

  @override
  Widget build(BuildContext context) {
    final cards = <_Shortcut>[
      _Shortcut('Ajukan\nLayanan', Icons.send_outlined, onService),
      _Shortcut('Status\nLayanan', Icons.assignment_turned_in_outlined, onHistory),
      _Shortcut('Laporan', Icons.assessment_outlined, onForms),
      _Shortcut('Informasi', Icons.info_outline, onInfo),
      _Shortcut('Agenda', Icons.calendar_month_outlined, () {
        Navigator.push(context,
            MaterialPageRoute(builder: (_) => const NewsPage(events: true)));
      }),
      _Shortcut('Kontak', Icons.call_outlined, () {
        showModalBottomSheet<void>(
            context: context,
            builder: (_) => const SafeArea(
                  child: Padding(
                    padding: EdgeInsets.all(22),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        CivicPageTitle(title: 'Kontak Kelurahan'),
                        SizedBox(height: 12),
                        Text('Informasi kontak dan layanan resmi dapat '
                            'diperoleh melalui portal SmartCikoko:'),
                        SizedBox(height: 10),
                        SelectableText('https://datawebs.my.id',
                            style: TextStyle(
                                color: CivicColors.blue,
                                fontWeight: FontWeight.w800)),
                      ],
                    ),
                  ),
                ));
      }),
    ];
    final width = MediaQuery.sizeOf(context).width;
    final count = width >= 950 ? 6 : width >= 600 ? 4 : 3;

    return ListView(
      padding: const EdgeInsets.fromLTRB(17, 16, 17, 26),
      children: [
        Container(
          clipBehavior: Clip.antiAlias,
          decoration: BoxDecoration(
            gradient: const LinearGradient(
                colors: [Color(0xFF0A5DAB), CivicColors.navy],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight),
            borderRadius: BorderRadius.circular(18),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SvgPicture.asset(
                'assets/cikoko-office-illustration.svg',
                height: width >= 650 ? 245 : 170,
                fit: BoxFit.cover,
                semanticsLabel: 'Ilustrasi pelayanan Kelurahan Cikoko',
              ),
              Padding(
                padding: const EdgeInsets.all(19),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      signedIn
                          ? 'SELAMAT DATANG DI SMARTCIKOKO'
                          : 'PORTAL LAYANAN DIGITAL',
                      style: const TextStyle(
                          color: CivicColors.gold,
                          fontWeight: FontWeight.w900,
                          letterSpacing: .8,
                          fontSize: 10),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      signedIn
                          ? 'Halo, ' + (user['name'] ?? 'Pengguna').toString()
                          : 'Layanan Digital Kelurahan Cikoko',
                      style: const TextStyle(
                          color: Colors.white,
                          fontSize: 24,
                          fontWeight: FontWeight.w900,
                          height: 1.2),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Pelayanan, informasi, dan pelaporan dalam satu aplikasi.',
                      style: TextStyle(
                          color: Color(0xFFD7E7F4),
                          fontSize: 12,
                          height: 1.5),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 23),
        const CivicPageTitle(
            title: 'Akses Cepat',
            subtitle: 'Pilih layanan sesuai kebutuhan Anda.'),
        const SizedBox(height: 13),
        GridView.builder(
          itemCount: cards.length,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: count,
              mainAxisExtent: width >= 600 ? 104 : 99,
              mainAxisSpacing: 9,
              crossAxisSpacing: 9),
          itemBuilder: (_, i) => _ShortcutTile(item: cards[i]),
        ),
        const SizedBox(height: 25),
        Row(children: [
          const Expanded(
            child: Text(
              'Informasi Terbaru',
              style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                  color: CivicColors.ink),
            ),
          ),
          TextButton(
            onPressed: () => Navigator.push(context,
                MaterialPageRoute(builder: (_) => const NewsPage())),
            child: const Text('Lihat Semua'),
          ),
        ]),
        const PublicNewsPreview(),
      ],
    );
  }
}

class _Shortcut {
  final String label;
  final IconData icon;
  final VoidCallback action;
  const _Shortcut(this.label, this.icon, this.action);
}

class _ShortcutTile extends StatelessWidget {
  final _Shortcut item;
  const _ShortcutTile({required this.item});
  @override
  Widget build(BuildContext context) => Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(13),
        child: InkWell(
          borderRadius: BorderRadius.circular(13),
          onTap: item.action,
          child: Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              border: Border.all(color: CivicColors.border),
              borderRadius: BorderRadius.circular(13),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(item.icon, color: CivicColors.blue, size: 28),
                const SizedBox(height: 8),
                Text(item.label,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                        height: 1.18,
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        color: CivicColors.ink)),
              ],
            ),
          ),
        ),
      );
}

class PublicNewsPreview extends StatefulWidget {
  const PublicNewsPreview({super.key});
  @override State<PublicNewsPreview> createState() => _PublicNewsPreviewState();
}

class _PublicNewsPreviewState extends State<PublicNewsPreview> {
  late Future<Map<String, dynamic>> posts;

  void retry() => setState(() => posts = PortalApi.call('posts'));
  @override
  void initState() {
    super.initState();
    posts = PortalApi.call('posts');
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<Map<String, dynamic>>(
        future: posts,
        builder: (context, snapshot) {
          if (!snapshot.hasData) {
            if (snapshot.hasError) {
              return Column(
                children: [
                  const CivicEmpty('Informasi terbaru belum dapat dimuat.'),
                  TextButton.icon(
                    onPressed: retry,
                    icon: const Icon(Icons.refresh),
                    label: const Text('Coba lagi'),
                  ),
                ],
              );
            }
            return const LinearProgressIndicator();
          }
          final rows = PortalApi.rows(snapshot.data!['data']);
          if (rows.isEmpty) return const CivicEmpty('Belum ada publikasi terbaru.');
          return Column(
            children: [
              for (final post in rows.take(3))
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Card(
                    child: ListTile(
                      contentPadding: const EdgeInsets.all(12),
                      leading: Container(
                        width: 62,
                        height: 63,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(colors: [
                            Color(0xFF1766A1),
                            Color(0xFF34B3CC)
                          ]),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Icon(Icons.newspaper,
                            color: Colors.white, size: 27),
                      ),
                      title: Text((post['title'] ?? '').toString(),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                              fontSize: 12, fontWeight: FontWeight.w800)),
                      subtitle: Text(
                        (post['summary'] ?? '').toString(),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            fontSize: 11, color: CivicColors.muted),
                      ),
                    ),
                  ),
                ),
            ],
          );
        },
      );
}

class NewsPage extends StatefulWidget {
  final bool events;
  const NewsPage({super.key, this.events = false});
  @override State<NewsPage> createState() => _NewsPageState();
}
class _NewsPageState extends State<NewsPage> {
  late Future<Map<String, dynamic>> future;

  void retry() => setState(
      () => future = PortalApi.call(widget.events ? 'events' : 'posts'));
  @override
  void initState() {
    super.initState();
    future = PortalApi.call(widget.events ? 'events' : 'posts');
  }
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(widget.events ? 'Agenda' : 'Informasi Publik')),
        body: FutureBuilder<Map<String, dynamic>>(
          future: future,
          builder: (context, snap) {
            if (snap.hasError) {
              return Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    CivicEmpty('Data belum tersedia: ' + snap.error.toString()),
                    TextButton.icon(
                      onPressed: retry,
                      icon: const Icon(Icons.refresh),
                      label: const Text('Muat ulang'),
                    ),
                  ],
                ),
              );
            }
            if (!snap.hasData) {
              return const Center(child: CircularProgressIndicator());
            }
            final rows = PortalApi.rows(snap.data!['data']);
            return ListView(
              padding: const EdgeInsets.all(18),
              children: [
                CivicPageTitle(
                    title: widget.events ? 'Agenda Kelurahan' : 'Informasi Publik',
                    subtitle: 'Informasi berdasarkan publikasi portal resmi.'),
                const SizedBox(height: 16),
                if (rows.isEmpty) const CivicEmpty('Belum ada informasi terbaru.'),
                for (final entry in rows)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 11),
                    child: Card(
                      child: Padding(
                        padding: const EdgeInsets.all(15),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text((entry['title'] ?? '').toString(),
                                style: const TextStyle(
                                    fontSize: 15, fontWeight: FontWeight.w900)),
                            const SizedBox(height: 7),
                            Text(
                              (entry[widget.events ? 'location' : 'summary'] ?? '')
                                  .toString(),
                              style: const TextStyle(
                                  fontSize: 12, color: CivicColors.muted),
                            ),
                            const SizedBox(height: 9),
                            Text(
                              (entry[widget.events ? 'starts_at' : 'published_at'] ?? '')
                                  .toString(),
                              style: const TextStyle(
                                  fontSize: 10, color: CivicColors.blue),
                            ),
                          ],
                        ),
                      ),
                    ),
                  )
              ],
            );
          },
        ),
      );
}

class ServicesTab extends StatelessWidget {
  final bool signedIn;
  final String? token;
  final Map<String, dynamic> user;
  final Map<String, dynamic> permissions;
  final VoidCallback onLogin, onForms, onCitizen, onAdmin;
  const ServicesTab({
    super.key,
    required this.signedIn,
    required this.token,
    required this.user,
    required this.permissions,
    required this.onLogin,
    required this.onForms,
    required this.onCitizen,
    required this.onAdmin,
  });
  @override
  Widget build(BuildContext context) => ListView(
        padding: const EdgeInsets.all(18),
        children: [
          const CivicPageTitle(
            title: 'Layanan Digital',
            subtitle: 'Semua layanan ditampilkan sesuai kewenangan.',
          ),
          const SizedBox(height: 18),
          _ServiceItem(
              icon: Icons.fact_check_outlined,
              title: 'Pelayanan Masyarakat',
              caption: 'Pengajuan warga dan pemantauan status.',
              onTap: signedIn ? onCitizen : onLogin),
          _ServiceItem(
              icon: Icons.assignment_outlined,
              title: 'Laporan dan Formulir',
              caption: 'PPPK, PJLP, cuti, RT/RW, LMK, Jumantik, dan kegiatan.',
              onTap: signedIn ? onForms : onLogin),
          if (permissions['super_admin'] == true)
            _ServiceItem(
                icon: Icons.admin_panel_settings_outlined,
                title: 'Pusat Administrator',
                caption: 'Ringkasan operasional dan monitoring aplikasi.',
                onTap: onAdmin),
          _ServiceItem(
              icon: Icons.newspaper_outlined,
              title: 'Berita dan Informasi',
              caption: 'Publikasi Kelurahan Cikoko.',
              onTap: () => Navigator.push(context,
                  MaterialPageRoute(builder: (_) => const NewsPage()))),
          _ServiceItem(
              icon: Icons.event_outlined,
              title: 'Agenda',
              caption: 'Jadwal kegiatan kelurahan.',
              onTap: () => Navigator.push(context,
                  MaterialPageRoute(builder: (_) => const NewsPage(events: true)))),
          if (!signedIn)
            Padding(
              padding: const EdgeInsets.only(top: 10),
              child: FilledButton(
                onPressed: onLogin,
                child: const Text('Masuk untuk Membuka Layanan'),
              ),
            ),
        ],
      );
}

class _ServiceItem extends StatelessWidget {
  final IconData icon;
  final String title, caption;
  final VoidCallback onTap;
  const _ServiceItem({
    required this.icon,
    required this.title,
    required this.caption,
    required this.onTap,
  });
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Card(
          child: ListTile(
            contentPadding: const EdgeInsets.all(14),
            leading: CircleAvatar(
              radius: 24,
              backgroundColor: const Color(0xFFE8F4FB),
              child: Icon(icon, color: CivicColors.blue),
            ),
            title: Text(title,
                style: const TextStyle(
                    fontWeight: FontWeight.w900, fontSize: 14)),
            subtitle: Text(caption,
                style: const TextStyle(
                    fontSize: 11, color: CivicColors.muted)),
            trailing: const Icon(Icons.chevron_right),
            onTap: onTap,
          ),
        ),
      );
}

class HistoryTab extends StatefulWidget {
  final String? token;
  final VoidCallback onLogin, onForms, onCitizen;
  const HistoryTab({
    super.key,
    required this.token,
    required this.onLogin,
    required this.onForms,
    required this.onCitizen,
  });
  @override State<HistoryTab> createState() => _HistoryTabState();
}

class _HistoryTabState extends State<HistoryTab> {
  Future<List<Map<String, dynamic>>>? entries;
  @override
  void initState() {
    super.initState();
    if (widget.token != null) entries = fetch();
  }
  @override
  void didUpdateWidget(HistoryTab oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.token != widget.token) {
      entries = widget.token == null ? null : fetch();
    }
  }
  Future<List<Map<String, dynamic>>> fetch() async {
    final catalog = await PortalApi.forms('catalog', token: widget.token);
    final forms = PortalApi.rows(catalog['data']);
    final lists = await Future.wait(forms.map((form) async {
      final result = await PortalApi.forms(
        'list',
        token: widget.token,
        query: {
          'group': (form['group'] ?? '').toString(),
          'kind': (form['kind'] ?? '').toString(),
        },
      );
      return PortalApi.rows(
        (result['data'] as Map?)?['rows'],
      ).map((row) => {
            ...row,
            'form_title': form['title'],
            'form_group': form['group'],
          }).toList();
    }));
    final result = lists.expand((x) => x).toList();
    result.sort((a, b) =>
        (b['updated_at'] ?? '').toString().compareTo(
              (a['updated_at'] ?? '').toString(),
            ));
    return result;
  }

  @override
  Widget build(BuildContext context) {
    if (widget.token == null) {
      return ListView(padding: const EdgeInsets.all(18), children: [
        const CivicPageTitle(
          title: 'Riwayat dan Status',
          subtitle: 'Masuk untuk melihat permohonan dan laporan milik Anda.',
        ),
        const SizedBox(height: 24),
        const CivicEmpty('Riwayat pribadi hanya bisa dilihat setelah login.'),
        FilledButton(onPressed: widget.onLogin, child: const Text('Masuk ke Akun')),
      ]);
    }
    return RefreshIndicator(
      onRefresh: () async {
        setState(() => entries = fetch());
        await entries;
      },
      child: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          const CivicPageTitle(
            title: 'Riwayat Layanan',
            subtitle: 'Status dari formulir dan permohonan milik akun ini.',
          ),
          const SizedBox(height: 17),
          _ServiceItem(
            icon: Icons.fact_check_outlined,
            title: 'Riwayat Pelayanan Warga',
            caption: 'Lihat permohonan serta riwayat pemeriksaannya.',
            onTap: widget.onCitizen,
          ),
          _ServiceItem(
            icon: Icons.assignment_outlined,
            title: 'Semua Laporan Saya',
            caption: 'Lihat, buat, atau perbaiki laporan administratif.',
            onTap: widget.onForms,
          ),
          const SizedBox(height: 15),
          const Text('Laporan Terbaru',
              style: TextStyle(
                  fontWeight: FontWeight.w900, fontSize: 17)),
          const SizedBox(height: 9),
          FutureBuilder<List<Map<String, dynamic>>>(
            future: entries,
            builder: (context, snapshot) {
              if (!snapshot.hasData) {
                if (snapshot.hasError) {
                  return const CivicEmpty('Riwayat laporan belum dapat dimuat.');
                }
                return const LinearProgressIndicator();
              }
              if (snapshot.data!.isEmpty) {
                return const CivicEmpty('Belum ada laporan administratif.');
              }
              return Column(children: [
                for (final row in snapshot.data!.take(10))
                  Padding(
                    padding: const EdgeInsets.only(bottom: 9),
                    child: Card(
                      child: ListTile(
                        title: Text(
                          (row['form_title'] ?? 'Laporan').toString() +
                              ' #' + (row['id'] ?? '').toString(),
                          style: const TextStyle(
                              fontWeight: FontWeight.w800, fontSize: 12),
                        ),
                        subtitle: Text(
                          (row['updated_at'] ?? '').toString(),
                          style: const TextStyle(
                              color: CivicColors.muted, fontSize: 10),
                        ),
                        trailing: CivicStatus(
                            status: (row['status'] ?? '').toString()),
                      ),
                    ),
                  ),
              ]);
            },
          ),
        ],
      ),
    );
  }
}

class AccountTab extends StatelessWidget {
  final String? token;
  final Map<String, dynamic> user, permissions;
  final VoidCallback onLogin, onLogout, onAdmin;
  const AccountTab({
    super.key,
    required this.token,
    required this.user,
    required this.permissions,
    required this.onLogin,
    required this.onLogout,
    required this.onAdmin,
  });
  @override
  Widget build(BuildContext context) => ListView(
        padding: const EdgeInsets.all(18),
        children: [
          const CivicPageTitle(
              title: 'Akun Saya',
              subtitle: 'Identitas pengguna dan pengaturan akses.'),
          const SizedBox(height: 18),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Row(children: [
                const CivicLogo(size: 57),
                const SizedBox(width: 15),
                Expanded(
                  child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          token == null
                              ? 'Belum Masuk'
                              : (user['name'] ?? 'Pengguna').toString(),
                          style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          token == null
                              ? 'Silakan masuk ke akun SmartCikoko.'
                              : (user['role'] ?? '').toString().toUpperCase(),
                          style: const TextStyle(
                              color: CivicColors.muted, fontSize: 12),
                        ),
                      ]),
                ),
              ]),
            ),
          ),
          const SizedBox(height: 17),
          if (permissions['super_admin'] == true)
            _ServiceItem(
                icon: Icons.admin_panel_settings,
                title: 'Dashboard Administrator',
                caption: 'Ringkasan keamanan, sistem, dan modul.',
                onTap: onAdmin),
          if (token == null)
            FilledButton(
              onPressed: onLogin,
              child: const Text('Masuk ke Akun'),
            )
          else
            OutlinedButton.icon(
              onPressed: onLogout,
              icon: const Icon(Icons.logout),
              label: const Text('Keluar dari Akun'),
            ),
          const SizedBox(height: 18),
          const Card(
            child: Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'Keamanan akun dilindungi server SmartCikoko. '
                'Autentikasi dua faktor untuk Super Admin tetap menggunakan '
                'Google Authenticator yang telah terdaftar.',
                style: TextStyle(color: CivicColors.muted, fontSize: 12),
              ),
            ),
          ),
        ],
      );
}

class AdminDashboard extends StatefulWidget {
  final String token;
  const AdminDashboard({super.key, required this.token});
  @override State<AdminDashboard> createState() => _AdminDashboardState();
}

class _AdminDashboardState extends State<AdminDashboard> {
  late Future<Map<String, dynamic>> future;
  @override void initState() {
    super.initState();
    future = PortalApi.call('superadmin_dashboard', token: widget.token);
  }
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Pusat Administrator')),
        body: FutureBuilder<Map<String, dynamic>>(
            future: future,
            builder: (context, snap) {
              if (snap.hasError) {
                return CivicEmpty('Pusat admin belum tersedia: ' +
                    snap.error.toString());
              }
              if (!snap.hasData) {
                return const Center(child: CircularProgressIndicator());
              }
              final data = Map<String, dynamic>.from(
                  snap.data!['data'] as Map? ?? {});
              final health = Map<String, dynamic>.from(
                  data['health'] as Map? ?? {});
              final connections = Map<String, dynamic>.from(
                  health['connections'] as Map? ?? {});
              final metrics = PortalApi.rows(health['metrics']);
              final modules = PortalApi.rows(data['modules']);
              return ListView(
                padding: const EdgeInsets.all(18),
                children: [
                  const CivicPageTitle(
                    title: 'Ringkasan Administrator',
                    subtitle:
                        'Status operasional. Pengelolaan lanjutan melalui sistem resmi.',
                  ),
                  const SizedBox(height: 18),
                  for (final item in connections.entries)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 9),
                      child: Card(
                        child: ListTile(
                          leading: Icon(item.value == true
                              ? Icons.check_circle
                              : Icons.error_outline,
                              color: item.value == true
                                  ? CivicColors.success
                                  : Colors.red),
                          title: Text(item.key == 'pegawai'
                              ? 'Database Pegawai'
                              : 'Database SmartApp'),
                          trailing: Text(item.value == true
                              ? 'Terhubung'
                              : 'Perlu pemeriksaan'),
                        ),
                      ),
                    ),
                  const SizedBox(height: 14),
                  const Text('Indikator',
                      style: TextStyle(
                          fontSize: 17, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 9),
                  for (final m in metrics)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 9),
                      child: Card(
                        child: ListTile(
                          title: Text((m['title'] ?? m['label'] ?? '').toString()),
                          trailing: Text(
                            (m['value'] ?? '—').toString(),
                            style: const TextStyle(
                                fontSize: 19,
                                fontWeight: FontWeight.w900,
                                color: CivicColors.blue),
                          ),
                        ),
                      ),
                    ),
                  const SizedBox(height: 15),
                  const Text('Status Modul',
                      style: TextStyle(
                          fontSize: 17, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 9),
                  for (final module in modules)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 9),
                      child: Card(
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text((module['label'] ?? '').toString(),
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w800)),
                              const SizedBox(height: 8),
                              LinearProgressIndicator(
                                value: ((module['progress_percent'] as num?) ??
                                            0)
                                        .toDouble()
                                        .clamp(0, 100) /
                                    100,
                              ),
                              const SizedBox(height: 5),
                              Text(
                                (module['status'] ?? 'belum').toString(),
                                style: const TextStyle(
                                    color: CivicColors.muted, fontSize: 11),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                ],
              );
            }),
      );
}
