import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:smartcikoko/src/api_client.dart';
import 'package:smartcikoko/src/design.dart';
import 'package:smartcikoko/src/login_page.dart';

void main() {
  test('all API resources use HTTPS', () {
    expect(Uri.parse(PortalApi.baseUrl).scheme, 'https');
    expect(Uri.parse(PortalApi.formsUrl).scheme, 'https');
    expect(PortalApi.resourceIsLogin(
        Uri.parse('https://datawebs.my.id/api/?resource=login')), isTrue);
    expect(PortalApi.resourceIsLogin(
        Uri.parse('https://datawebs.my.id/api/?resource=posts')), isFalse);
  });

  test('non-map results are filtered safely', () {
    final records = PortalApi.rows([
      {'id': 1},
      'not-a-record',
      {'id': 2},
      null,
    ]);
    expect(records.length, 2);
    expect(records.first['id'], 1);
  });

  testWidgets('government identity and security login controls render',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: smartTheme(),
        home: LoginPage(onSignedIn: (_) async {}),
      ),
    );
    expect(find.text('SmartCikoko'), findsOneWidget);
    expect(find.text('Masuk ke Akun'), findsWidgets);
    expect(find.text('Username atau email'), findsOneWidget);
    expect(find.text('Kata sandi'), findsOneWidget);
    expect(find.byIcon(Icons.lock_outline), findsOneWidget);
  });

  testWidgets('status label maps draft to readable Indonesian',
      (tester) async {
    await tester.pumpWidget(MaterialApp(
      theme: smartTheme(),
      home: const Scaffold(
        body: CivicStatus(status: 'perbaikan'),
      ),
    ));
    expect(find.text('Perlu perbaikan'), findsOneWidget);
  });
}
