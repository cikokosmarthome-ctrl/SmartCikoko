#!/usr/bin/env python3
"""Prepare a generated Flutter Android project for *manual* Google Play AAB.

Requires Github Actions secrets. Does not push signing keys or service accounts
to GitHub and never prints secret values. The final .aab must still be tested.
"""
from __future__ import annotations

import base64
import json
import os
import re
import sys
from pathlib import Path

EXPECTED_PACKAGE = "id.smartcikoko.smartcikoko"
ANDROID_API = 36


def secret(name: str) -> str:
    value = os.environ.get(name, "")
    if not value:
        raise SystemExit(f"Missing GitHub Actions secret: {name}")
    return value


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if text.count(old) != 1:
        raise SystemExit(f"Android template changed: cannot safely patch {label}")
    return text.replace(old, new, 1)


def run(project: Path) -> None:
    android = project / "android"
    app = android / "app"
    gradle_path = app / "build.gradle.kts"
    settings_path = android / "settings.gradle.kts"
    manifest_path = app / "src/main/AndroidManifest.xml"

    for path in [gradle_path, settings_path, manifest_path]:
        if not path.is_file():
            raise SystemExit("Run flutter create --platforms android first: " + str(path))

    key_binary = base64.b64decode(secret("SC_PLAY_KEYSTORE_BASE64"), validate=True)
    if len(key_binary) < 100:
        raise SystemExit("Signing keystore is unexpectedly small")
    key_path = app / "upload-keystore.jks"
    key_path.write_bytes(key_binary)
    key_path.chmod(0o600)
    # The keystore is local to the ephemeral GitHub runner, never committed.
    properties = "\n".join([
        "storeFile=upload-keystore.jks",
        "storePassword=" + secret("SC_PLAY_STORE_PASSWORD"),
        "keyAlias=" + secret("SC_PLAY_KEY_ALIAS"),
        "keyPassword=" + secret("SC_PLAY_KEY_PASSWORD"),
        "",
    ])
    key_properties = android / "key.properties"
    key_properties.write_text(properties, encoding="utf-8")
    key_properties.chmod(0o600)

    config_bytes = base64.b64decode(
        secret("SC_FIREBASE_ANDROID_JSON_BASE64"), validate=True)
    config = json.loads(config_bytes.decode("utf-8"))
    clients = config.get("client", [])
    packages = [
        item.get("client_info", {})
        .get("android_client_info", {})
        .get("package_name")
        for item in clients
    ]
    if EXPECTED_PACKAGE not in packages:
        raise SystemExit(
            "Firebase Android package ID does not match " + EXPECTED_PACKAGE)
    firebase_file = app / "google-services.json"
    firebase_file.write_bytes(config_bytes)
    firebase_file.chmod(0o600)

    settings = settings_path.read_text(encoding="utf-8")
    if 'id("com.google.gms.google-services")' not in settings:
        settings = replace_once(
            settings,
            'id("com.android.application")',
            'id("com.google.gms.google-services") version "4.5.0" apply false\n'
            '    id("com.android.application")',
            "Firebase settings plugin",
        )
    settings_path.write_text(settings, encoding="utf-8")

    gradle = gradle_path.read_text(encoding="utf-8")
    if f'applicationId = "{EXPECTED_PACKAGE}"' not in gradle:
        raise SystemExit("Unexpected applicationId: stop release build")
    gradle = replace_once(
        gradle, 'id("dev.flutter.flutter-gradle-plugin")',
        'id("dev.flutter.flutter-gradle-plugin")\n'
        '    id("com.google.gms.google-services")',
        "Firebase app plugin",
    )
    gradle = replace_once(
        gradle, "compileSdk = flutter.compileSdkVersion",
        f"compileSdk = {ANDROID_API}", "compile SDK")
    gradle = replace_once(
        gradle, "targetSdk = flutter.targetSdkVersion",
        f"targetSdk = {ANDROID_API}", "target SDK")
    # Gradle Kotlin signingConfig defaults to debug for flutter create.
    gradle = replace_once(
        gradle, 'signingConfig = signingConfigs.getByName("debug")',
        'signingConfig = signingConfigs.getByName("release")',
        "release signing")
    gradle = (
        "import java.util.Properties\n"
        "import java.io.FileInputStream\n\n" + gradle
    )
    properties_setup = """
val playProperties = Properties()
val playPropertiesFile = rootProject.file("key.properties")
FileInputStream(playPropertiesFile).use { playProperties.load(it) }

android {
    signingConfigs {
        create("release") {
            storeFile = file(playProperties["storeFile"] as String)
            storePassword = playProperties["storePassword"] as String
            keyAlias = playProperties["keyAlias"] as String
            keyPassword = playProperties["keyPassword"] as String
        }
    }
"""
    gradle = replace_once(gradle, "android {", properties_setup,
                          "production signing configuration")
    gradle_path.write_text(gradle, encoding="utf-8")

    manifest = manifest_path.read_text(encoding="utf-8")
    for permission in ["android.permission.INTERNET",
                       "android.permission.POST_NOTIFICATIONS"]:
        if permission not in manifest:
            manifest = replace_once(
                manifest, "<application",
                '    <uses-permission android:name="' + permission +
                '" />\n    <application', "Android " + permission)
    manifest_path.write_text(manifest, encoding="utf-8")

    print("Play configuration validated: API 36, Firebase Android app, "
          "release upload signing, Android notification permission.")
    print("Production secrets remain only in the ephemeral runner.")


if __name__ == "__main__":
    run(Path(sys.argv[1]) if len(sys.argv) > 1 else Path("android_flutter"))
