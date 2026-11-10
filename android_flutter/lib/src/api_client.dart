import 'dart:convert';
import 'package:http/http.dart' as http;

/// Typed, HTTPS-only bridge to the EXISTING SmartCikoko PHP API.
/// No database passwords, implicit elevated permissions, or fake responses.
class PortalException implements Exception {
  final String message;
  final int? status;
  const PortalException(this.message, [this.status]);
  @override
  String toString() => message;
}

class PortalApi {
  static const String baseUrl = 'https://datawebs.my.id/api/';
  static const String formsUrl = 'https://datawebs.my.id/api/mobile-forms.php';

  static Future<Map<String, dynamic>> call(
    String resource, {
    String method = 'GET',
    String? token,
    Map<String, String>? query,
    Map<String, dynamic>? body,
  }) async {
    final uri = Uri.parse(baseUrl).replace(queryParameters: {
      'resource': resource,
      ...?query,
    });
    return _send(uri, method: method, token: token, body: body);
  }

  static Future<Map<String, dynamic>> forms(
    String action, {
    String method = 'GET',
    String? token,
    Map<String, String>? query,
    Map<String, dynamic>? body,
  }) async {
    final uri = Uri.parse(formsUrl).replace(queryParameters: {
      'action': action,
      ...?query,
    });
    return _send(uri, method: method, token: token, body: body);
  }

  static Future<Map<String, dynamic>> _send(
    Uri uri, {
    required String method,
    String? token,
    Map<String, dynamic>? body,
  }) async {
    if (uri.scheme != 'https') {
      throw const PortalException('Koneksi harus menggunakan HTTPS.');
    }
    final headers = <String, String>{
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
      if (method == 'POST') 'Content-Type': 'application/json',
    };
    final http.Response response;
    try {
      response = method == 'POST'
          ? await http
              .post(uri, headers: headers, body: jsonEncode(body ?? {}))
              .timeout(const Duration(seconds: 25))
          : await http
              .get(uri, headers: headers)
              .timeout(const Duration(seconds: 25));
    } catch (_) {
      throw const PortalException('Tidak dapat terhubung. Periksa internet Anda.');
    }
    Map<String, dynamic> decoded;
    try {
      final parsed = jsonDecode(response.body);
      if (parsed is! Map<String, dynamic>) {
        throw const FormatException();
      }
      decoded = parsed;
    } catch (_) {
      throw const PortalException('Server mengirim respons yang tidak valid.');
    }
    // Existing API deliberately uses HTTP 401 for a pending MFA challenge.
    if (resourceIsLogin(uri) &&
        (decoded['mfa_required'] == true ||
            decoded['selection_required'] == true)) {
      return decoded;
    }
    if (response.statusCode >= 400 || decoded['ok'] != true) {
      throw PortalException(
        (decoded['error'] ?? 'Permintaan tidak dapat diproses.').toString(),
        response.statusCode,
      );
    }
    return decoded;
  }

  static bool resourceIsLogin(Uri uri) => uri.queryParameters['resource'] == 'login';

  static List<Map<String, dynamic>> rows(dynamic data) {
    if (data is! List) return [];
    return data.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
  }
}
