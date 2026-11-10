import 'package:flutter/material.dart';

import '../citizen_requests.dart';
import 'api_client.dart';
import 'design.dart';
import 'forms_page.dart';

/// One server-backed activity feed for every signed-in account.
/// Notifications are in-app refreshes, NOT OS push notifications.
class ActivityPage extends StatefulWidget {
  final String token;
  final Map<String, dynamic> user;
  const ActivityPage({super.key, required this.token, required this.user});

  @override
  State<ActivityPage> createState() => _ActivityPageState();
}

class _ActivityPageState extends State<ActivityPage> {
  late Future<Map<String, dynamic>> future;

  @override
  void initState() {
    super.initState();
    refresh();
  }

  void refresh() => setState(
        () => future = PortalApi.call('workflow_feed', token: widget.token),
      );

  Future<void> pull() async {
    refresh();
    try {
      await future;
    } catch (_) {
      // FutureBuilder displays the user-facing retry message.
    }
  }

  Future<void> openRecord(Map<String, dynamic> record) async {
    final group = (record['group'] ?? '').toString();
    if (group == 'warga') {
      await Navigator.push<void>(
        context,
        MaterialPageRoute(
          builder: (_) => CitizenRequestsScreen(
              token: widget.token, user: widget.user),
        ),
      );
    } else {
      await Navigator.push<void>(
        context,
        MaterialPageRoute(
            builder: (_) => FormsPage(token: widget.token)),
      );
    }
    if (mounted) refresh();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const Text('Progres & Notifikasi'),
          actions: [
            IconButton(
              onPressed: refresh,
              icon: const Icon(Icons.refresh),
              tooltip: 'Muat ulang notifikasi',
            ),
          ],
        ),
        body: FutureBuilder<Map<String, dynamic>>(
          future: future,
          builder: (context, snapshot) {
            if (snapshot.hasError) {
              return Center(
                child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const CivicEmpty(
                          'Pusat aktivitas belum dapat dimuat. Periksa koneksi atau server.'),
                      TextButton.icon(
                        onPressed: refresh,
                        icon: const Icon(Icons.refresh),
                        label: const Text('Coba lagi'),
                      ),
                    ]),
              );
            }
            if (!snapshot.hasData) {
              return const Center(child: CircularProgressIndicator());
            }
            final data = Map<String, dynamic>.from(
                snapshot.data!['data'] as Map? ?? {});
            final records = PortalApi.rows(data['items']);
            final reviewQueue = PortalApi.rows(data['review_queue']);
            final notices = PortalApi.rows(data['notifications']);
            final summary = Map<String, dynamic>.from(
                data['summary'] as Map? ?? {});
            final unavailable = (data['unavailable_modules'] as List? ?? [])
                .map((item) => item.toString()).toList();

            return RefreshIndicator(
              onRefresh: pull,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                children: [
                  const CivicPageTitle(
                    title: 'Progres Laporan & Surat',
                    subtitle: 'Satu riwayat dari server SmartCikoko untuk website dan aplikasi.',
                  ),
                  const SizedBox(height: 14),
                  Card(
                    color: const Color(0xFF104D83),
                    child: Padding(
                      padding: const EdgeInsets.all(18),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceAround,
                        children: [
                          _Metric(
                              label: 'Aktivitas',
                              value: (summary['total'] ?? 0).toString()),
                          _Metric(
                              label: 'Perlu aksi',
                              value: (summary['need_action'] ?? 0).toString()),
                          _Metric(
                              label: 'Ditutup',
                              value: (summary['completed'] ?? 0).toString()),
                        ],
                      ),
                    ),
                  ),
                  if (reviewQueue.isNotEmpty) ...[
                    const SizedBox(height: 17),
                    const Text('Antrean Pemeriksaan Berwenang',
                        style: TextStyle(
                            fontSize: 17, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 7),
                    for (final item in reviewQueue)
                      Card(
                        child: ListTile(
                          leading: const Icon(Icons.fact_check_outlined,
                              color: CivicColors.blue),
                          title: Text((item['reference'] ?? '').toString()),
                          subtitle: Text(
                            '${item['status_label'] ?? ''} · ${item['progress_percent'] ?? 0}%',
                          ),
                          onTap: () => openRecord({'group': 'warga'}),
                        ),
                      ),
                  ],
                  const SizedBox(height: 19),
                  const Text('Pengajuan & Laporan Saya',
                      style: TextStyle(
                          fontSize: 17, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 10),
                  if (records.isEmpty)
                    const CivicEmpty('Belum ada laporan/pengajuan milik akun ini.'),
                  for (final record in records)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 11),
                      child: Card(
                        child: InkWell(
                          borderRadius: BorderRadius.circular(12),
                          onTap: () => openRecord(record),
                          child: Padding(
                            padding: const EdgeInsets.all(15),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text((record['reference'] ?? '').toString(),
                                    style: const TextStyle(
                                        fontWeight: FontWeight.w900)),
                                const SizedBox(height: 6),
                                Text((record['kind'] ?? '').toString(),
                                    style: const TextStyle(
                                        color: CivicColors.muted,
                                        fontSize: 11)),
                                const SizedBox(height: 10),
                                ProgressMilestone(progress: record),
                                const SizedBox(height: 8),
                                Text((record['next_step'] ?? '').toString(),
                                    style: const TextStyle(
                                        fontSize: 12,
                                        color: CivicColors.muted)),
                                const SizedBox(height: 7),
                                const Text('Buka modul →',
                                    style: TextStyle(
                                        fontSize: 11,
                                        color: CivicColors.blue,
                                        fontWeight: FontWeight.w800)),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                  const SizedBox(height: 18),
                  const Text('Notifikasi Proses',
                      style: TextStyle(
                          fontSize: 17, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 8),
                  if (notices.isEmpty)
                    const CivicEmpty('Belum ada pembaruan proses.'),
                  for (final notice in notices)
                    Card(
                      child: ListTile(
                        leading: const Icon(Icons.notifications_outlined,
                            color: CivicColors.blue),
                        title: Text((notice['title'] ?? '').toString(),
                            style: const TextStyle(
                                fontSize: 12, fontWeight: FontWeight.w800)),
                        subtitle: Text(
                          '${notice['message'] ?? ''}\n${notice['created_at'] ?? ''} WIB',
                          style: const TextStyle(fontSize: 11),
                        ),
                      ),
                    ),
                  if (unavailable.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 12),
                      child: Text(
                        'Modul belum tersedia: ${unavailable.join(', ')}',
                        style: const TextStyle(color: Colors.deepOrange),
                      ),
                    ),
                  const SizedBox(height: 13),
                  Text((data['progress_notice'] ?? '').toString(),
                      style: const TextStyle(
                          fontSize: 11, color: CivicColors.muted)),
                  const SizedBox(height: 5),
                  Text((data['notice'] ?? '').toString(),
                      style: const TextStyle(
                          fontSize: 11, color: CivicColors.muted)),
                  const SizedBox(height: 25),
                ],
              ),
            );
          },
        ),
      );
}

class _Metric extends StatelessWidget {
  final String label;
  final String value;
  const _Metric({required this.label, required this.value});

  @override
  Widget build(BuildContext context) => Column(
        children: [
          Text(value,
              style: const TextStyle(
                  color: Colors.white,
                  fontSize: 24,
                  fontWeight: FontWeight.w900)),
          Text(label,
              style: const TextStyle(
                  color: Color(0xFFDCECF7),
                  fontSize: 11,
                  fontWeight: FontWeight.w600)),
        ],
      );
}

/// The server is the sole authority for percentage and next-step semantics.
class ProgressMilestone extends StatelessWidget {
  final Map<String, dynamic> progress;
  const ProgressMilestone({super.key, required this.progress});

  @override
  Widget build(BuildContext context) {
    final raw = (progress['progress_percent'] as num?)?.toInt() ?? 0;
    final percent = raw.clamp(0, 100);
    return Semantics(
      label: 'Progres administratif $percent persen',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                  child: Text((progress['status_label'] ?? '').toString(),
                      style: const TextStyle(
                          fontSize: 12, fontWeight: FontWeight.w800))),
              Text('$percent%',
                  style: const TextStyle(
                      color: CivicColors.blue,
                      fontWeight: FontWeight.w900)),
            ],
          ),
          const SizedBox(height: 7),
          LinearProgressIndicator(
            value: percent / 100,
            minHeight: 9,
            borderRadius: BorderRadius.circular(12),
          ),
        ],
      ),
    );
  }
}
