import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';

import 'connected_portal.dart';
import 'mobile_api.dart';

/// FCM is optional until the Android app is connected to a Firebase project.
/// No credential is bundled here and nothing is sent before account sign-in.
class MobilePush {
  static final GlobalKey<NavigatorState> navigatorKey =
      GlobalKey<NavigatorState>();

  static bool _configured = false;
  static String? _sessionToken;
  static Map<String, dynamic>? _pendingNavigation;
  static StreamSubscription<String>? _tokenChanges;
  static StreamSubscription<RemoteMessage>? _openedMessages;

  static bool get configured => _configured;

  static Future<void> initialize() async {
    try {
      await Firebase.initializeApp();
      _configured = true;
      _openedMessages = FirebaseMessaging.onMessageOpenedApp.listen(
          (message) => _openOrQueue(message.data));
      final message = await FirebaseMessaging.instance.getInitialMessage();
      if (message != null) {
        _openOrQueue(message.data);
      }
    } catch (_) {
      // No Google Services configuration yet: the rest of the app still works.
      _configured = false;
    }
  }

  static Future<void> registerForAccount(String sessionToken) async {
    _sessionToken = sessionToken;
    if (!_configured) return;
    try {
      final settings = await FirebaseMessaging.instance.requestPermission();
      if (settings.authorizationStatus == AuthorizationStatus.denied) return;
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null && token.isNotEmpty) {
        await MobileApi.registerDevice(sessionToken, token);
      }
      await _tokenChanges?.cancel();
      _tokenChanges = FirebaseMessaging.instance.onTokenRefresh.listen(
        (newToken) {
          final active = _sessionToken;
          if (active != null) {
            MobileApi.registerDevice(active, newToken).catchError((_) {});
          }
        },
      );
    } catch (_) {
      // Push may be unavailable; messages and in-app events remain usable.
    }
  }

  static Future<void> releaseAccount(String sessionToken) async {
    _sessionToken = null;
    await _tokenChanges?.cancel();
    _tokenChanges = null;
    if (!_configured) return;
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) {
        await MobileApi.unregisterDevice(sessionToken, token);
      }
    } catch (_) {
      // Logout still continues when the notification service is offline.
    }
  }

  static void _openOrQueue(Map<String, dynamic> data) {
    // Reject unrelated payloads; server-side auth validates the thread.
    if (data['screen'] != 'chat') return;
    _pendingNavigation = Map<String, dynamic>.from(data);
    openPending();
  }

  static void openPending() {
    final data = _pendingNavigation;
    final state = navigatorKey.currentState;
    if (data == null || state == null) return;
    _pendingNavigation = null;
    final owner = (data['thread_user_id'] ?? '').toString();
    state.push(MaterialPageRoute<void>(
      builder: (_) => ConnectedCikokoPage(
        startWithChat: true,
        threadUserId: owner.isEmpty ? null : owner,
      ),
    ));
  }

  static Future<void> dispose() async {
    await _tokenChanges?.cancel();
    await _openedMessages?.cancel();
  }
}
