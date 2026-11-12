import 'package:flutter_test/flutter_test.dart';
import 'package:smartcikoko/src/mobile_api.dart';

void main() {
  test('connected Mobile API uses HTTPS and action parameter', () {
    final uri = MobileApi.uri('chat', {'after_id': '10'});
    expect(uri.scheme, 'https');
    expect(uri.host, 'datawebs.my.id');
    expect(uri.path, '/mobile-api/index.php');
    expect(uri.queryParameters['action'], 'chat');
    expect(uri.queryParameters['after_id'], '10');
    expect(uri.queryParameters.containsKey('resource'), isFalse);
  });

  test('untrusted array elements are removed', () {
    final rows = MobileApi.rows([
      {'id': 1, 'message': 'OK'},
      12,
      'invalid',
      {'id': 2},
      null,
    ]);
    expect(rows.length, 2);
    expect(rows[1]['id'], 2);
  });

  test('session token is never part of a request URL', () {
    final url = MobileApi.uri('chat_photo_view', {'message_id': '123'});
    expect(url.toString(), isNot(contains('Bearer')));
    expect(url.queryParameters.containsKey('token'), isFalse);
  });
}
