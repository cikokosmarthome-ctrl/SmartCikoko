import 'package:flutter/material.dart';
import 'src/portal_app.dart';
import 'src/mobile_push.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await MobilePush.initialize();
  runApp(const SmartCikokoApp());
}
