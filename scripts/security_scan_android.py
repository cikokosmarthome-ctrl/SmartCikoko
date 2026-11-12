#!/usr/bin/env python3
"""Fail-closed structural checks for Android APK/AAB built in GitHub Actions.

Does not infer that a passing bundle is malware-free. Antivirus, signer and
device-side checks remain necessary in addition to these archive checks.
"""
from __future__ import annotations

import hashlib
import subprocess
import sys
import zipfile
from pathlib import Path, PurePosixPath

MAX_ENTRIES = 100_000
MAX_UNCOMPRESSED_BYTES = 2 * 1024 * 1024 * 1024
MAX_MEMBER_BYTES = 512 * 1024 * 1024


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as source:
        for block in iter(lambda: source.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def check_archive(path: Path, must_contain: tuple[str, ...]) -> str:
    if not path.is_file() or not zipfile.is_zipfile(path):
        raise ValueError(f"Not a valid APK/AAB ZIP: {path}")
    with zipfile.ZipFile(path) as archive:
        entries = archive.infolist()
        if not entries or len(entries) > MAX_ENTRIES:
            raise ValueError(f"Unexpected archive entry count: {path}")
        names: set[str] = set()
        total = 0
        for entry in entries:
            name = entry.filename
            parts = PurePosixPath(name).parts
            if (
                not name
                or name.startswith("/")
                or chr(92) in name
                or ".." in parts
                or name in names
            ):
                raise ValueError(f"Unsafe archive member in {path}: {name}")
            names.add(name)
            if entry.is_dir():
                continue
            if entry.file_size > MAX_MEMBER_BYTES:
                raise ValueError(f"Oversize archive member: {name}")
            total += entry.file_size
            if total > MAX_UNCOMPRESSED_BYTES:
                raise ValueError(f"Archive expands beyond safety bound: {path}")
            if entry.file_size > 4 * 1024 * 1024 and (
                entry.compress_size == 0
                or entry.file_size / entry.compress_size > 10_000
            ):
                raise ValueError(f"Suspicious compression ratio: {name}")
        for required in must_contain:
            if required not in names:
                raise ValueError(f"Missing required Android file: {required}")
        if archive.testzip() is not None:
            raise ValueError(f"Archive CRC check failed: {path}")
    return (
        f"{path.name}: {len(entries)} members, "
        f"{total:,} uncompressed bytes, sha256:{sha256(path)}"
    )


def tool_check(args: list[str], expected: str, name: str) -> str:
    output = subprocess.run(
        args, capture_output=True, text=True, check=False
    )
    combined = output.stdout + "\n" + output.stderr
    if output.returncode != 0 or expected not in combined:
        raise ValueError(
            f"{name} failed (exit {output.returncode}):\n"
            + combined[-2500:]
        )
    return combined


def main() -> None:
    if len(sys.argv) != 5:
        raise ValueError(
            "Usage: security_scan_android.py APK AAB APKSIGNER AAPT"
        )
    apk, aab = Path(sys.argv[1]), Path(sys.argv[2])
    apksigner, aapt = sys.argv[3], sys.argv[4]
    summaries = [
        check_archive(
            apk,
            ("AndroidManifest.xml", "classes.dex",
             "lib/arm64-v8a/libflutter.so"),
        ),
        check_archive(
            aab,
            ("BundleConfig.pb", "base/manifest/AndroidManifest.xml"),
        ),
    ]
    output = tool_check(
        [apksigner, "verify", "--verbose", "--print-certs", str(apk)],
        "Verified using v2 scheme",
        "APK signature validation",
    )
    # The debug signing certificate is expected in UAT and is NOT a
    # Google Play production upload signing key.
    signing = (
        "UAT/debug certificate; NOT a production Play signature"
        if "Android Debug" in output else "Certificate must be reviewed "
        "against the expected Play upload signer"
    )
    badging = tool_check(
        [aapt, "dump", "badging", str(apk)],
        "name='id.smartcikoko.smartcikoko'",
        "Android application ID",
    )
    permissions = [
        line for line in badging.splitlines()
        if line.startswith("uses-permission:")
    ]
    bundle = tool_check(
        ["jarsigner", "-verify", "-verbose", str(aab)],
        "jar verified.",
        "AAB JAR signature",
    )
    if "jar verified." not in bundle:
        raise ValueError("Bundle is unsigned")
    lines = [
        "SMARTCIKOKO ANDROID BUILD INSPECTION",
        *summaries,
        "APK signature: verified by Android SDK apksigner",
        "AAB JAR signature: verified by jarsigner",
        "APK signing: " + signing,
        "Package ID: id.smartcikoko.smartcikoko",
        "Permissions from Android manifest:",
        *(permissions or ["(none reported)"]),
        "",
        "No malware-free certification is implied by this report.",
    ]
    print("\n".join(lines))
    Path("security-report.txt").write_text(
        "\n".join(lines) + "\n", encoding="utf-8"
    )


if __name__ == "__main__":
    try:
        main()
    except (ValueError, OSError, zipfile.BadZipFile) as exc:
        print(f"SECURITY GATE FAILED: {exc}", file=sys.stderr)
        raise SystemExit(1)
