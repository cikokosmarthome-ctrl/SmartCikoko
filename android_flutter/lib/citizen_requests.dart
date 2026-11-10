import 'dart:convert';
import 'dart:math';
import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:http/http.dart' as http;

/// Tahap 9 — pengajuan masyarakat dan peninjauan berjenjang lewat API yang sama.
class CitizenRequestsScreen extends StatefulWidget {
  final String token;
  final Map<String, dynamic> user;
  const CitizenRequestsScreen({super.key, required this.token, required this.user});
  @override
  State<CitizenRequestsScreen> createState() => _CitizenRequestsState();
}

class _CitizenRequestsState extends State<CitizenRequestsScreen> {
  static const statusLabels = <String, String>{
    'draf': 'Draf', 'diajukan': 'Diajukan',
    'ditinjau': 'Dalam Pemeriksaan', 'revisi': 'Perlu Perbaikan',
    'diverifikasi': 'Diverifikasi PTSP',
    'disetujui': 'Disetujui Lurah', 'ditolak': 'Ditolak'
  };
  static const relations = <String, String>{
    'anak': 'Anak', 'pasangan': 'Suami/Istri', 'orang_tua': 'Orang Tua',
    'saudara': 'Saudara', 'lainnya': 'Hubungan Lainnya'
  };
  List<Map<String, dynamic>> requests = [];
  List<Map<String, dynamic>> notifications = [];
  List<Map<String, dynamic>> requirements = [];
  List<Map<String, dynamic>> uploadedFiles = [];
  bool attachmentCatalogReady = false;
  bool uploading = false;
  Map<String, dynamic>? selected;
  List<Map<String, dynamic>> history = [];
  bool busy = true;
  String? error;
  int page = 1;
  int pages = 1;
  String get role => (widget.user['role'] ?? '').toString();
  bool get citizen => widget.user['source'] == 'smartapp' && role == 'warga';
  bool get ptsp => widget.user['source'] == 'smartapp' && role == 'ptsp';
  bool get lurah => widget.user['source'] == 'smartapp' && role == 'lurah';

  @override
  void initState() { super.initState(); refresh(); }

  Future<Map<String, dynamic>> api(String resource,
      {Map<String, String>? query, Map<String, dynamic>? body}) async {
    final uri = Uri.parse('https://datawebs.my.id/api/').replace(
      queryParameters: {'resource': resource, ...?query});
    final headers = <String, String>{
      'Authorization': 'Bearer ' + widget.token,
      'Accept': 'application/json',
      if (body != null) 'Content-Type': 'application/json',
    };
    final response = body == null
        ? await http.get(uri, headers: headers).timeout(const Duration(seconds: 20))
        : await http.post(uri, headers: headers, body: jsonEncode(body))
            .timeout(const Duration(seconds: 20));
    final decoded = jsonDecode(response.body);
    if (decoded is! Map<String, dynamic>) throw Exception('Respons server tidak valid.');
    if (response.statusCode >= 400 || decoded['ok'] != true) {
      throw Exception((decoded['error'] ?? 'Layanan tidak tersedia').toString());
    }
    return decoded;
  }

  Future<void> refresh() async {
    setState(() { busy = true; error = null; });
    try {
      final data = await api('citizen_list', query: {'page': page.toString()});
      final details = Map<String, dynamic>.from(data['data'] as Map);
      if (citizen) {
        try {
          final noticeResponse = await api('citizen_notifications');
          final noticeData = noticeResponse['data'] as List? ?? [];
          notifications = noticeData.map((v) => Map<String, dynamic>.from(v as Map)).toList();
        } catch (_) { notifications = []; /* Tahap 10 belum dipasang: alur dasar tetap berjalan. */ }
      }
      if (!mounted) return;
      setState(() {
        requests = (details['rows'] as List? ?? [])
            .map((e) => Map<String, dynamic>.from(e as Map)).toList();
        pages = (details['pages'] as num? ?? 1).toInt();
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> markNotificationRead(int id) async {
    try {
      await api('citizen_notice_read', body: {'id': id});
      if (!mounted) return;
      setState(() {
        for (final n in notifications) {
          if (n['id'] == id) n['read_at'] = 'dibaca';
        }
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    }
  }

    Future<void> detail(int id) async {
    setState(() { busy = true; error = null; });
    try {
      final response = await api('citizen_detail', query: {'id': id.toString()});
      final info = Map<String, dynamic>.from(response['data'] as Map);
      List<Map<String, dynamic>> nextRequirements = [];
      List<Map<String, dynamic>> nextUploads = [];
      var canAttach = false;
      try {
        final docResponse = await api('citizen_requirements',
            query: {'id': id.toString()});
        final documents =
            Map<String, dynamic>.from(docResponse['data'] as Map);
        nextRequirements = (documents['requirements'] as List? ?? [])
            .whereType<Map>()
            .map((row) => Map<String, dynamic>.from(row))
            .toList();
        nextUploads = (documents['files'] as List? ?? [])
            .whereType<Map>()
            .map((row) => Map<String, dynamic>.from(row))
            .toList();
        canAttach = true;
      } catch (_) {
        // The old Stage 9 workflow must remain usable if Stage 10
        // has not been deployed to the server.
      }
      if (!mounted) return;
      setState(() {
        selected = Map<String, dynamic>.from(info['case'] as Map);
        history = (info['history'] as List? ?? [])
            .map((e) => Map<String, dynamic>.from(e as Map)).toList();
        requirements = nextRequirements;
        uploadedFiles = nextUploads;
        attachmentCatalogReady = canAttach;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> send(String route, Map<String, dynamic> body, {int? id}) async {
    setState(() { busy = true; error = null; });
    try {
      final response = await api(route, body: body);
      final target = id ?? (response['id'] as num?)?.toInt();
      await refresh();
      if (target != null) await detail(target);
    } catch (e) {
      if (mounted) setState(() { error = e.toString(); busy = false; });
    }
  }

  Future<void> edit([Map<String, dynamic>? record]) async {
    final name = TextEditingController(text: (record?['deceased_name'] ?? '').toString());
    final purpose = TextEditingController(text: (record?['purpose'] ?? '').toString());
    String relationship = (record?['relationship'] ?? 'anak').toString();
    if (!relations.containsKey(relationship)) relationship = 'anak';
    try {
      final input = await showDialog<Map<String, String>>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: Text(record == null ? 'Buat Pengajuan Baru' : 'Perbarui Draf'),
          content: SingleChildScrollView(child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(controller: name, maxLength: 160,
                decoration: const InputDecoration(labelText: 'Nama Almarhum/Almarhumah')),
              DropdownButtonFormField<String>(
                initialValue: relationship,
                decoration: const InputDecoration(labelText: 'Hubungan keluarga'),
                items: relations.entries.map((e) =>
                  DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
                onChanged: (v) { if (v != null) relationship = v; },
              ),
              TextField(controller: purpose, maxLength: 500, maxLines: 3,
                decoration: const InputDecoration(labelText: 'Keterangan (opsional)')),
              const SizedBox(height: 8),
              const Text('Tidak perlu mengisi NIK atau mengunggah dokumen pribadi.'),
            ],
          )),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
            FilledButton(onPressed: () {
              if (name.text.trim().length < 3) return;
              Navigator.pop(ctx, {
                'deceased_name': name.text.trim(),
                'relationship': relationship,
                'purpose': purpose.text.trim(),
              });
            }, child: const Text('Simpan Draf')),
          ],
        ),
      );
      if (input == null) return;
      if (record == null) {
        final rng = Random.secure();
        input['request_key'] = List.generate(16,
          (_) => rng.nextInt(256).toRadixString(16).padLeft(2, '0')).join();
        await send('citizen_create', input);
      } else {
        await send('citizen_edit', {
          ...input, 'id': record['id'], 'version': record['version']
        }, id: (record['id'] as num).toInt());
      }
    } finally {
      name.dispose();
      purpose.dispose();
    }
  }

  Future<void> uploadDocument() async {
    final item = selected;
    if (item == null || busy || uploading || requirements.isEmpty) return;
    final requestId = (item['id'] as num?)?.toInt();
    if (requestId == null) return;
    final chosen = await showDialog<String>(
      context: context,
      builder: (dialogContext) => SimpleDialog(
        title: const Text('Pilih Jenis Lampiran'),
        children: [
          for (final requirement in requirements)
            SimpleDialogOption(
              onPressed: () => Navigator.pop(
                  dialogContext, (requirement['code'] ?? '').toString()),
              child: Text(
                (requirement['label'] ?? '').toString() +
                    (requirement['required'] == true ? ' · Wajib' : ' · Kondisional'),
              ),
            ),
        ],
      ),
    );
    if (chosen == null || !mounted) return;
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: const ['pdf', 'jpg', 'jpeg', 'png'],
        withData: true,
        allowMultiple: false,
      );
      if (result == null || result.files.isEmpty || !mounted) return;
      final file = result.files.single;
      if (file.size > 5 * 1024 * 1024 || file.bytes == null) {
        setState(() => error =
            'Lampiran harus berupa PDF/JPG/PNG dengan ukuran maksimal 5 MB.');
        return;
      }
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Konfirmasi Lampiran'),
          content: Text('Unggah ${file.name} ke penyimpanan privat? '
              'Dokumen hanya dapat diperiksa oleh akun dan petugas berwenang.'),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Batal'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Unggah Berkas'),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;
      setState(() { uploading = true; error = null; });
      final upload = http.MultipartRequest(
        'POST', Uri.parse('https://datawebs.my.id/api/citizen-upload.php'),
      );
      upload.headers['Authorization'] = 'Bearer ${widget.token}';
      upload.headers['Accept'] = 'application/json';
      upload.fields.addAll({
        'request_id': requestId.toString(),
        'doc_type': chosen,
        'confirmed': '1',
      });
      upload.files.add(http.MultipartFile.fromBytes(
        'document', file.bytes!, filename: file.name,
      ));
      final streamed = await upload.send().timeout(const Duration(seconds: 45));
      final response = await http.Response.fromStream(streamed);
      final decoded = jsonDecode(response.body);
      if (decoded is! Map ||
          response.statusCode >= 400 || decoded['ok'] != true) {
        throw Exception(decoded is Map
            ? (decoded['error'] ?? 'Gagal menyimpan lampiran.').toString()
            : 'Respons server tidak valid.');
      }
      if (!mounted) return;
      setState(() => uploading = false);
      await detail(requestId);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Lampiran berhasil diunggah secara privat.')),
        );
      }
    } catch (e) {
      if (mounted) setState(() => error = 'Unggah gagal: $e');
    } finally {
      if (mounted) setState(() => uploading = false);
    }
  }

  Map<String, String> actions(Map<String, dynamic> record) {
    final status = record['status'];
    if (citizen && (status == 'draf' || status == 'revisi')) {
      return {'diajukan': 'Ajukan untuk Pemeriksaan'};
    }
    if (ptsp && status == 'diajukan') return {'ditinjau': 'Mulai Pemeriksaan'};
    if (ptsp && status == 'ditinjau') {
      return {'revisi': 'Minta Perbaikan', 'diverifikasi': 'Verifikasi Administratif'};
    }
    if (lurah && status == 'diverifikasi') {
      return {'disetujui': 'Setujui', 'ditolak': 'Tolak', 'revisi': 'Kembalikan untuk Revisi'};
    }
    return {};
  }

  Future<void> action(Map<String, dynamic> record) async {
    final choices = actions(record);
    if (choices.isEmpty) return;
    final note = TextEditingController();
    try {
      final choice = await showDialog<String>(
        context: context,
        builder: (ctx) => SimpleDialog(
          title: const Text('Pilih Tindak Lanjut'),
          children: choices.entries.map((e) => SimpleDialogOption(
            onPressed: () => Navigator.pop(ctx, e.key),
            child: Text(e.value),
          )).toList(),
        ),
      );
      if (choice == null || !mounted) return;
      final explanation = await showDialog<String>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Konfirmasi Tindakan'),
          content: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(choices[choice] ?? ''),
            if (!citizen) TextField(controller: note,
              maxLength: 500, maxLines: 3,
              decoration: const InputDecoration(labelText: 'Alasan atau catatan')),
          ]),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
            FilledButton(onPressed: () {
              if ((choice == 'revisi' || choice == 'ditolak') &&
                  note.text.trim().isEmpty) return;
              Navigator.pop(ctx, citizen
                ? 'Permohonan diajukan melalui aplikasi SmartCikoko.'
                : note.text.trim());
            }, child: const Text('Setujui Tindakan')),
          ],
        ),
      );
      if (explanation == null) return;
      await send('citizen_transition', {
        'id': record['id'], 'version': record['version'],
        'to_status': choice, 'note': explanation,
      }, id: (record['id'] as num).toInt());
    } finally {
      note.dispose();
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Pengajuan Pelayanan')),
    floatingActionButton: citizen
      ? FloatingActionButton.extended(
          onPressed: busy ? null : () => edit(),
          icon: const Icon(Icons.add), label: const Text('Buat Draf'))
      : null,
    body: RefreshIndicator(onRefresh: refresh, child: ListView(
      padding: const EdgeInsets.all(16), children: [
        Card(color: const Color(0xFF17476E), child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Icon(Icons.account_balance, color: Colors.white),
            const SizedBox(height: 10),
            Text(citizen ? 'Permohonan Masyarakat' : 'Antrean Pemeriksaan',
              style: const TextStyle(color: Colors.white, fontSize: 21,
                fontWeight: FontWeight.w800)),
            const Text('Pengajuan • PTSP • Lurah',
              style: TextStyle(color: Color(0xFFD6E7F3))),
          ]))),
        if (busy) const Center(child: CircularProgressIndicator()),
        if (error != null) Card(child: Padding(padding: const EdgeInsets.all(16),
          child: Text(error!, style: const TextStyle(color: Colors.red)))),
        if (citizen && notifications.isNotEmpty) ...[
          const SizedBox(height: 14),
          const Text('Pemberitahuan Pelayanan',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
          for (final notice in notifications.take(8))
            Card(child: ListTile(
              leading: Icon(notice['read_at'] == null
                  ? Icons.notifications_active_outlined : Icons.notifications_none),
              title: Text((notice['message'] ?? '').toString()),
              subtitle: Text((notice['created_at'] ?? '').toString()),
              trailing: notice['read_at'] == null
                  ? TextButton(onPressed: () =>
                      markNotificationRead((notice['id'] as num).toInt()),
                      child: const Text('Dibaca'))
                  : null,
              onTap: () => detail((notice['request_id'] as num).toInt()),
            )),
        ],
        const SizedBox(height: 14),
        Text(citizen ? 'Pengajuan Saya' : 'Daftar Permohonan',
          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
        if (requests.isEmpty && !busy)
          const Padding(padding: EdgeInsets.all(16),
            child: Text('Belum terdapat pengajuan pada halaman ini.')),
        for (final item in requests)
          Card(child: ListTile(
            title: Text((item['reference_no'] ?? '').toString()),
            subtitle: Text((item['applicant_name'] ?? '').toString() +
              ' • ' + (statusLabels[item['status']] ?? '').toString()),
            trailing: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  '${((item['progress'] as Map?)?['progress_percent'] ?? 0)}%',
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                const Icon(Icons.chevron_right, size: 18),
              ],
            ),
            onTap: busy ? null : () => detail((item['id'] as num).toInt()),
          )),
        Row(children: [
          TextButton(onPressed: page > 1 ? () { page--; refresh(); } : null,
            child: const Text('Sebelumnya')),
          Text('Halaman ' + page.toString() + ' / ' + pages.toString()),
          TextButton(onPressed: page < pages ? () { page++; refresh(); } : null,
            child: const Text('Berikutnya')),
        ]),
        if (selected != null) ...[
          const SizedBox(height: 16),
          const Text('Rincian Permohonan',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
          Card(child: Padding(padding: const EdgeInsets.all(17),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text((selected!['reference_no'] ?? '').toString(),
                style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
              Chip(label: Text(statusLabels[selected!['status']] ?? 'Tidak diketahui')),
              if (selected!['progress'] is Map) ...[
                const SizedBox(height: 7),
                Row(children: [
                  const Expanded(child: Text('Progres administratif')),
                  Text('${(selected!['progress'] as Map)['progress_percent'] ?? 0}%',
                    style: const TextStyle(fontWeight: FontWeight.w900)),
                ]),
                LinearProgressIndicator(
                  value: (((selected!['progress'] as Map)['progress_percent']
                      as num?)?.toDouble() ?? 0).clamp(0, 100) / 100,
                  minHeight: 9,
                ),
                Text((selected!['progress'] as Map)['next_step']?.toString() ?? '',
                  style: const TextStyle(fontSize: 11)),
              ],
              Text('Pemohon: ' + (selected!['applicant_name'] ?? '').toString()),
              Text('Almarhum/Almarhumah: ' + (selected!['deceased_name'] ?? '').toString()),
              Text('Hubungan: ' + (relations[selected!['relationship']] ?? '').toString()),
              Text('Keterangan: ' + (selected!['purpose'] ?? '').toString()),
              const SizedBox(height: 12),
              const Text('Checklist Persyaratan Ahli Waris (Arsip Lama)',
                style: TextStyle(fontWeight: FontWeight.w800)),
              if (!attachmentCatalogReady)
                const Text('Lampiran belum tersedia di server. Hubungi petugas.',
                  style: TextStyle(fontSize: 11)),
              for (final requirement in requirements)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 3),
                  child: Row(
                    children: [
                      Icon(requirement['uploaded'] == true
                          ? Icons.check_circle : Icons.radio_button_unchecked,
                        color: requirement['uploaded'] == true
                          ? Colors.green : CivicColors.muted, size: 17),
                      const SizedBox(width: 8),
                      Expanded(child: Text(
                        (requirement['label'] ?? '').toString() +
                          (requirement['required'] == true ? ' (wajib)' : ' (kondisional)'),
                        style: const TextStyle(fontSize: 11))),
                    ],
                  ),
                ),
              if (uploadedFiles.isNotEmpty)
                Text('${uploadedFiles.length} lampiran tersimpan privat.',
                  style: const TextStyle(fontSize: 11)),
              if (citizen && attachmentCatalogReady &&
                  (selected!['status'] == 'draf' || selected!['status'] == 'revisi'))
                OutlinedButton.icon(
                  onPressed: busy || uploading ? null : uploadDocument,
                  icon: const Icon(Icons.attach_file),
                  label: const Text('Unggah PDF / Foto (maks. 5 MB)'),
                ),
              if (uploading) const LinearProgressIndicator(),
              const Text('Periksa lampiran sebelum mengirim. Checklist tidak menggantikan pemeriksaan petugas.',
                style: TextStyle(fontSize: 10, color: CivicColors.muted)),
              if (citizen && (selected!['status'] == 'draf' || selected!['status'] == 'revisi'))
                OutlinedButton.icon(onPressed: busy ? null : () => edit(selected),
                  icon: const Icon(Icons.edit), label: const Text('Perbarui Draf')),
              if (actions(selected!).isNotEmpty)
                FilledButton.icon(onPressed: busy ? null : () => action(selected!),
                  icon: const Icon(Icons.check), label: const Text('Tindak Lanjut')),
            ]))),
          const SizedBox(height: 12),
          const Text('Riwayat Proses', style: TextStyle(fontSize: 17,
            fontWeight: FontWeight.w800)),
          for (final h in history) Card(child: ListTile(
            title: Text(statusLabels[h['to_status']] ?? 'Draf Dibuat'),
            subtitle: Text((h['created_at'] ?? '').toString() +
              ' • ' + (h['note'] ?? '').toString()),
          )),
        ],
        const SizedBox(height: 90),
      ],
    )),
  );
}
