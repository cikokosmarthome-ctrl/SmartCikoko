import 'dart:convert';
import 'dart:typed_data';

import 'package:http/http.dart' as http;

/// HTTP client for the cPanel PHP 8.4 /mobile-api/index.php contract.
/// This client does not use the older /api/?resource=... contract.
class MobileApiException implements Exception {
  const MobileApiException(this.message, [this.statusCode]);
  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

class MobileApi {
  static const String endpoint =
      'https://datawebs.my.id/mobile-api/index.php';
  static const Duration timeout = Duration(seconds: 25);

  static Uri uri(String action, [Map<String, String>? query]) {
    final base = Uri.parse(endpoint);
    if (base.scheme != 'https') {
      throw const MobileApiException('Koneksi API wajib menggunakan HTTPS.');
    }
    return base.replace(queryParameters: {
      'action': action,
      ...?query,
    });
  }

  static Map<String, dynamic> _decode(http.Response response) {
    Map<String, dynamic> parsed;
    try {
      final data = jsonDecode(response.body);
      if (data is! Map) throw const FormatException();
      parsed = Map<String, dynamic>.from(data);
    } catch (_) {
      throw MobileApiException(
        'Server tidak mengembalikan JSON. Periksa pemasangan Mobile API.',
        response.statusCode,
      );
    }
    if (response.statusCode >= 400 || parsed['ok'] != true) {
      throw MobileApiException(
        (parsed['error'] ?? 'Permintaan tidak dapat diproses.').toString(),
        response.statusCode,
      );
    }
    return parsed;
  }

  static Future<Map<String, dynamic>> request(
    String action, {
    String method = 'GET',
    String? token,
    Map<String, String>? query,
    Map<String, dynamic>? body,
  }) async {
    if (method != 'GET' && method != 'POST') {
      throw const MobileApiException('Metode HTTP tidak diizinkan.');
    }
    final headers = <String, String>{
      'Accept': 'application/json',
      if (token != null && token.isNotEmpty)
        'Authorization': 'Bearer ' + token,
      if (method == 'POST') 'Content-Type': 'application/json',
    };
    try {
      final result = method == 'GET'
          ? await http.get(uri(action, query), headers: headers).timeout(timeout)
          : await http.post(uri(action, query),
              headers: headers, body: jsonEncode(body ?? {})).timeout(timeout);
      return _decode(result);
    } on MobileApiException {
      rethrow;
    } catch (_) {
      throw const MobileApiException(
          'Tidak dapat terhubung ke server. Periksa koneksi internet.');
    }
  }

  static Future<Map<String, dynamic>> sendChatPhoto({
    required String token,
    required Uint8List bytes,
    required String filename,
    String caption = '',
    String? recipientId,
  }) async {
    if (bytes.isEmpty || bytes.lengthInBytes > 4 * 1024 * 1024) {
      throw const MobileApiException('Ukuran foto harus maksimal 4 MB.');
    }
    final ext = filename.toLowerCase().split('.').last;
    if (!['jpg', 'jpeg', 'png', 'webp'].contains(ext)) {
      throw const MobileApiException('Pilih foto JPG, PNG, atau WebP.');
    }
    final action =
        recipientId == null ? 'chat_photo_send' : 'chat_photo_reply';
    final req = http.MultipartRequest('POST', uri(action))
      ..headers['Authorization'] = 'Bearer ' + token
      ..headers['Accept'] = 'application/json'
      ..fields['message'] = caption;
    if (recipientId != null) req.fields['user_id'] = recipientId;
    req.files.add(http.MultipartFile.fromBytes('photo', bytes,
        filename: filename));
    try {
      final streamed = await req.send().timeout(timeout);
      final response = await http.Response.fromStream(streamed).timeout(timeout);
      return _decode(response);
    } on MobileApiException {
      rethrow;
    } catch (_) {
      throw const MobileApiException('Foto gagal dikirim. Coba kembali.');
    }
  }

  static Future<Uint8List> chatPhoto({
    required String token,
    required int messageId,
  }) async {
    if (messageId < 1) {
      throw const MobileApiException('ID foto tidak valid.');
    }
    try {
      final response = await http.get(
        uri('chat_photo_view', {'message_id': messageId.toString()}),
        headers: {'Authorization': 'Bearer ' + token},
      ).timeout(timeout);
      if (response.statusCode != 200) {
        _decode(response); // Raises the server's access/error message.
        throw const MobileApiException('Foto tidak tersedia.');
      }
      final type = response.headers['content-type'] ?? '';
      if (!type.startsWith('image/') ||
          response.bodyBytes.lengthInBytes > 4 * 1024 * 1024) {
        throw const MobileApiException('Berkas foto tidak valid.');
      }
      return response.bodyBytes;
    } on MobileApiException {
      rethrow;
    } catch (_) {
      throw const MobileApiException('Foto belum dapat dibuka.');
    }
  }

  static List<Map<String, dynamic>> rows(dynamic value) {
    if (value is! List) return [];
    return value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  /// Called only after FCM is configured on Android and permission is granted.
  static Future<void> registerDevice(String token, String fcmToken) async {
    await request('device', token: token, method: 'POST',
        body: {'fcm_token': fcmToken});
  }

  static Future<void> unregisterDevice(String token, String fcmToken) async {
    await request('device_unregister', token: token, method: 'POST',
        body: {'fcm_token': fcmToken});
  }
}
