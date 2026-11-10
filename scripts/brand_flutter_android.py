#!/usr/bin/env python3
"""Brand a freshly generated Flutter Android project for ARM64 UAT.

No official government emblems are fabricated: the temporary icon is a
blue/gold public-service building motif matching the SmartCikoko UI.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

root = Path(sys.argv[1]) if len(sys.argv) > 1 else Path("android_flutter")
manifest = root / "android/app/src/main/AndroidManifest.xml"
build_gradle = root / "android/app/build.gradle.kts"
drawable = root / "android/app/src/main/res/drawable/smartcikoko_launcher.xml"

if not manifest.is_file() or not build_gradle.is_file():
    raise SystemExit("Android Flutter project not generated yet; run flutter create first.")

gradle = build_gradle.read_text(encoding="utf-8")
expected_app_id = 'id.smartcikoko.smartcikoko'
if f'applicationId = "{expected_app_id}"' not in gradle:
    raise SystemExit(f"Unexpected Android app id; expected {expected_app_id!r}")

text = manifest.read_text(encoding="utf-8")
text, labels = re.subn(r'android:label="smartcikoko"', 'android:label="SmartCikoko"', text)
text, icons = re.subn(
    r'android:icon="@mipmap/ic_launcher"',
    'android:icon="@drawable/smartcikoko_launcher"',
    text,
)
if labels != 1 or icons != 1:
    raise SystemExit("Default Flutter AndroidManifest changed; branding halted safely.")

if 'android:roundIcon=' not in text:
    text = text.replace(
        'android:icon="@drawable/smartcikoko_launcher"',
        'android:icon="@drawable/smartcikoko_launcher"\n'
        '        android:roundIcon="@drawable/smartcikoko_launcher"',
        1,
    )
manifest.write_text(text, encoding="utf-8")
drawable.parent.mkdir(parents=True, exist_ok=True)
drawable.write_text("""<?xml version="1.0" encoding="utf-8"?>
<vector xmlns:android="http://schemas.android.com/apk/res/android"
    android:width="108dp" android:height="108dp"
    android:viewportWidth="108" android:viewportHeight="108">
    <!-- SmartCikoko blue background / abstract civic building. Not an official crest. -->
    <path android:fillColor="#0952A3"
        android:pathData="M0,0 H108 V108 H0 Z"/>
    <path android:fillColor="#0B76D5"
        android:pathData="M0,0 H108 V31 Q49,54 0,31 Z"/>
    <path android:fillColor="#FABD3C"
        android:pathData="M53,15 L84,36 L84,41 L24,41 L24,36 Z"/>
    <path android:fillColor="#FFFFFF"
        android:pathData="M30,43 H78 V84 H30 Z"/>
    <path android:fillColor="#0A5BA8"
        android:pathData="M38,51 H48 V61 H38 Z M60,51 H70 V61 H60 Z M38,66 H48 V76 H38 Z M60,66 H70 V76 H60 Z"/>
    <path android:fillColor="#0A5BA8"
        android:pathData="M50,65 H58 V84 H50 Z"/>
    <path android:fillColor="#FABD3C"
        android:pathData="M21,85 H87 V90 H21 Z"/>
    <path android:fillColor="#FFFFFF"
        android:pathData="M20,96 H88 V98 H20 Z"/>
</vector>
""", encoding="utf-8")

print("BRAND OK: SmartCikoko / id.smartcikoko.smartcikoko / blue-gold temporary launcher")
