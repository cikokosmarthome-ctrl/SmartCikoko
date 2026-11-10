#!/usr/bin/env python3
"""Build a CLEAN, COMPLETE SmartCikoko webroot replacement.

Output always lives in <output>/public_html_baru, NEVER writes to an active
public_html. Keep every active PHP/API route and existing native backend
workflow. Exclude installer/recovery leftovers. Unified CSS/JS are additive.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "public_html"
EXCLUDED_PHP_PREFIX = ("instal-", "perbaiki-", "pasang-")
EXCLUDED_PHP_EXACT = {"aktivasi-super-admin.php"}
EXCLUDED_CSS_PREFIX = ("instal-",)
EXCLUDED_CSS_EXACT = {
    "aktivasi-super-admin.css", "pasang-tahap6.css",
    "beranda-fix.css",
}
THEME = '<link rel="stylesheet" href="/assets/ui-modern.css?v=20261110">'
PUBLIC_THEME = '<link rel="stylesheet" href="/assets/portal-mockup.css?v=20261110a">'
SHELL = '<script src="/assets/ui-modern.js?v=20261110" defer></script>'
ADMIN_SCRIPT = '<script src="/assets/admin-modern.js?v=20261110" defer></script>'
ADMIN_TABS = ("ringkasan", "laporan", "modul", "kemajuan", "pengaturan", "sc-admin-workspace")


def excluded(rel: Path) -> bool:
    if rel.parts[0] == "api":
        return False
    name = rel.name.lower()
    if len(rel.parts) == 1 and name.endswith(".php"):
        return name.startswith(EXCLUDED_PHP_PREFIX) or name in EXCLUDED_PHP_EXACT
    if rel.parts[0] == "assets" and name.endswith(".css"):
        return name.startswith(EXCLUDED_CSS_PREFIX) or name in EXCLUDED_CSS_EXACT
    return False


def replace_once(src: str, old: str, new: str, label: str) -> str:
    matches = src.count(old)
    if matches != 1:
        raise ValueError(f"{label}: expected one anchor, got {matches}")
    return src.replace(old, new, 1)


def generic_header() -> str:
    return (
        '<header class="sc-standard-header">'
        '<div class="sc-standard-head">'
        '<a class="sc-brand" href="/"><span class="sc-crest">KC</span>'
        '<span><b>KELURAHAN CIKOKO</b><small>Portal Digital Terpadu</small></span></a>'
        '<nav aria-label="Navigasi utama">'
        '<a href="/">Beranda</a><a href="/?page=dashboard">Ruang Kerja</a>'
        '</nav></div></header>'
    )


def generic_footer(is_php: bool) -> str:
    year = "<?=date('Y')?>" if is_php else "2026"
    return (
        '<footer class="sc-standard-footer"><div class="sc-standard-foot">'
        f'© {year} Kelurahan Cikoko · Kecamatan Pancoran, Jakarta Selatan'
        '</div></footer>'
    )


def make_admin_unified(src: str) -> str:
    original_nav = (
        '<nav><a href="#ringkasan">Ringkasan</a><a href="#laporan">Laporan</a>'
        '<a href="#modul">Modul</a><a href="#kemajuan">Progres</a>'
        '<a href="#pengaturan">Keamanan</a><a href="/pusat-laporan.php">Input Laporan</a></nav>'
    )
    tabs = (
        '<nav aria-label="Menu Super Admin">'
        '<a data-sc-tab href="#ringkasan">Ringkasan</a>'
        '<a data-sc-tab href="#laporan">Laporan</a>'
        '<a data-sc-tab href="#modul">Kelola</a>'
        '<a data-sc-tab href="#kemajuan">Progres</a>'
        '<a data-sc-tab href="#pengaturan">Pengaturan</a>'
        '</nav>'
    )
    src = replace_once(src, original_nav, tabs, "admin canonical nav")
    start = src.find('<section id="modul" class="section">')
    end = src.find('<section id="kemajuan" class="section">', start)
    if start < 0 or end < 0 or end <= start:
        raise ValueError("admin module section positions changed")

    tools = [
        ("Pengguna & Izin", "Akun pegawai, RT/RW, dan pengaturan hak akses", "/kelola-pengguna.php"),
        ("Kepegawaian", "Pantau PPPK, PJLP, dan data operasional", "/operasional.php"),
        ("Pelayanan Ahli Waris", "Permohonan serta tindak lanjut warga", "/pengajuan-warga.php"),
        ("Verifikasi Layanan", "Pemeriksaan dan alur pelayanan", "/verifikasi-layanan.php"),
        ("Tinjauan PPPK / Cuti", "Tinjau formulir pegawai yang telah diajukan", "/formulir-pegawai16.php?jenis=pppk"),
        ("Laporan Wilayah", "Kelola laporan RT/RW, LMK, dan Jumantik", "/laporan-wilayah17.php"),
        ("Monitoring Laporan", "Pantau status dan antrean tindak lanjut", "/monitor-laporan20.php"),
        ("Penugasan", "Tugas pemeriksa dan pembagian kerja", "/tugas-pemeriksa21.php"),
        ("Kendali Verifikasi", "Atur kebijakan verifikasi berjenjang", "/kendali-verifikasi22.php"),
        ("Uji Aplikasi", "Catat temuan dan uji ulang fungsi", "/uji-operasional26.php"),
        ("Pemeriksaan Sistem", "Periksa koneksi dan angka laporan", "/cek-operasional25.php"),
        ("Arsip & Dokumen", "Pemeriksaan arsip privat", "/arsip-keamanan.php"),
    ]
    cards = "\n".join(
        f'<a href="{url}" data-sc-tool data-sc-label="{title}">'
        f'<b>{title}</b><small>{subtitle}</small></a>'
        for title, subtitle, url in tools
    )
    module = (
        '<section id="modul" class="section">\n'
        '<div class="title"><h2>Menu Administrator</h2>'
        '<small>Semua fungsi admin dibuka di ruang kerja halaman ini</small></div>\n'
        '<div class="actions">\n' + cards + '\n</div>\n'
        '<p class="smallnote">Pilih menu untuk bekerja tanpa meninggalkan halaman Admin. '
        'Pengamanan akun, MFA, dan hak akses tetap diperiksa oleh setiap modul.</p>'
        '</section>\n'
        '<section id="sc-admin-workspace" class="section" hidden>\n'
        '<div class="sc-frame-toolbar"><h2 id="sc-admin-frame-title">'
        'Modul Administrator</h2><button type="button" id="sc-admin-close">'
        '← Kembali ke Menu Admin</button></div>\n'
        '<iframe id="sc-admin-frame" class="sc-admin-frame" src="about:blank" '
        'title="Ruang kerja modul Administrator" loading="lazy" '
        'referrerpolicy="strict-origin-when-cross-origin"></iframe>'
        '<p class="sc-workspace-hint">Hanya modul dalam website ini yang ditampilkan. '
        'Semua penyimpanan dan pemeriksaan tetap melalui alur serta izin asli.</p>'
        '</section>\n'
    )
    src = src[:start] + module + src[end:]
    src = replace_once(
        src,
        '<a class="link" href="/pusat-laporan.php">Buka Form Input →</a>',
        '<a class="link" data-sc-tool data-sc-label="Pusat Laporan" '
        'href="/pusat-laporan.php">Pusat Laporan →</a>',
        "in-page report link",
    )

    start = src.find('<section id="pengaturan" class="section">')
    end = src.find('<p class="notice">', start)
    if start < 0 or end < 0 or end <= start:
        raise ValueError("admin settings section changed")
    settings = [
        ("Keamanan Akun & MFA", "/keamanan-superadmin.php"),
        ("Uji Keamanan", "/uji-keamanan14.php"),
        ("Arsip Digital", "/arsip-keamanan.php"),
        ("Kesiapan Sistem", "/kesiapan-produksi.php"),
        ("Direktori Akun", "/direktori-akun.php"),
        ("Draf Administrasi", "/draf-administrasi.php"),
        ("Pengendalian Verifikasi", "/kendali-verifikasi22.php"),
        ("Pemeriksaan Operasional", "/cek-operasional25.php"),
        ("Catatan Uji Aplikasi", "/uji-operasional26.php"),
        ("Bukti Laporan", "/bukti-laporan18.php"),
    ]
    links = "\n".join(
        f'<a data-sc-tool data-sc-label="{name}" href="{url}">{name}</a>'
        for name, url in settings
    )
    replacement = (
        '<section id="pengaturan" class="section">'
        '<div class="title"><h2>Pengaturan Administrator</h2>'
        '<small>Peralatan internal · dibuka dalam halaman ini</small></div>'
        '<details class="advanced"><summary>Keamanan, audit, dokumen, dan konfigurasi lanjutan</summary>'
        '<div class="advanced-links">' + links + '</div>'
        '</details></section>\n'
    )
    src = src[:start] + replacement + src[end:]
    if src.count('id="sc-admin-workspace"') != 1:
        raise ValueError("admin workspace not unique")
    return src


def update_index(src: str) -> str:
    anchor = "$u=actor();\n"
    redirection = (
        "$u=actor();\n"
        "if($u && $page==='dashboard' && function_exists('is_super_admin')"
        " && is_super_admin($u)){\n"
        "    header('Location: /super-admin-satu.php',true,303);exit;\n"
        "}\n"
    )
    src = replace_once(src, anchor, redirection, "route Super Admin dashboard")
    old = (
        "if($u){a('dashboard','Ruang Kerja',$page);if(is_admin($u))a('pegawai','Pegawai',$page);\n"
        "if(permitted('pppk'))a('pppk','PPPK',$page);if(permitted('pjlp'))a('pjlp','PJLP',$page);\n"
        "if(permitted('waris'))a('waris','Ahli Waris',$page);a('profil','Akun',$page);}"
    )
    new = (
        "if($u && function_exists('is_super_admin') && is_super_admin($u)){\n"
        "    echo '<a href=\"/super-admin-satu.php\">Admin</a>';\n"
        "    a('profil','Akun',$page);\n"
        "}elseif($u){a('dashboard','Ruang Kerja',$page);if(is_admin($u))a('pegawai','Pegawai',$page);\n"
        "if(permitted('pppk'))a('pppk','PPPK',$page);if(permitted('pjlp'))a('pjlp','PJLP',$page);\n"
        "if(permitted('waris'))a('waris','Ahli Waris',$page);a('profil','Akun',$page);}"
    )
    return replace_once(src, old, new, "admin-only public navigation")


def redirect_old_admin() -> str:
    return """<?php
declare(strict_types=1);
header('Cache-Control: private,no-store,max-age=0');
header('X-Robots-Tag: noindex,nofollow,noarchive');
try{
    require dirname(__DIR__).'/portal_private/bootstrap.php';
    require_super_admin();
}catch(Throwable $e){
    http_response_code(503);exit('Super Administrator belum dapat dibuka.');
}
header('Location: /super-admin-satu.php',true,303);
exit;
"""


def modernize_html(src: str, relative: Path) -> str:
    if not re.search(r'<!doctype\s+html', src, flags=re.I):
        return src
    page = relative.stem.lower().replace("_", "-")
    src, n = re.subn(
        r'(?i)<body(?P<attrs>[^>]*)>',
        lambda m: make_body(m.group('attrs'), page),
        src, count=1,
    )
    if n != 1:
        raise ValueError(f"{relative}: expected exactly one body tag")
    link = THEME + "\n" + (PUBLIC_THEME + "\n" if page == "index" else "") + SHELL
    if page == "super-admin-satu":
        link += "\n" + ADMIN_SCRIPT
    src, n = re.subn(r'(?i)</head\s*>', lambda m: link + "\n" + m.group(), src, count=1)
    if n != 1:
        raise ValueError(f"{relative}: expected exactly one head closure")

    first_main = re.search(r'<main\b', src, re.I)
    first_head = re.search(r'<header\b', src, re.I)
    if first_head is None or (first_main and first_main.start() < first_head.start()):
        src, n = re.subn(
            r'(?i)<body\b[^>]*>',
            lambda m: m.group() + "\n" + generic_header() + "\n",
            src, count=1,
        )
        if n != 1:
            raise ValueError(f"{relative}: missing body for standard header")
    if not re.search(r'<footer\b', src, re.I):
        src, n = re.subn(
            r'(?i)</body\s*>',
            lambda m: generic_footer(relative.suffix.lower() == '.php') + "\n" + m.group(),
            src, count=1,
        )
        if n != 1:
            raise ValueError(f"{relative}: missing body closure for footer")
    return src


def make_body(attrs: str, page: str) -> str:
    expected = f"sc-body sc-page-{page}"
    if re.search(r'\bclass\s*=\s*["\']', attrs, flags=re.I):
        attrs = re.sub(
            r'(?i)\bclass\s*=\s*(["\'])(.*?)\1',
            lambda m: f'class={m.group(1)}{expected} {m.group(2)}{m.group(1)}',
            attrs, count=1
        )
    else:
        attrs += f' class="{expected}"'
    return "<body" + attrs + ">"


def generate(output: Path) -> dict:
    target = output / "public_html_baru"
    if target.exists():
        shutil.rmtree(target)
    target.mkdir(parents=True, exist_ok=True)
    included, excluded_files, transformed = [], [], []
    for original in sorted(SRC.rglob("*")):
        if not original.is_file():
            continue
        rel = original.relative_to(SRC)
        if excluded(rel):
            excluded_files.append(rel.as_posix())
            continue
        if original.is_symlink():
            raise ValueError(f"Cannot package symbolic link: {rel}")
        dest = target / rel
        dest.parent.mkdir(parents=True, exist_ok=True)
        if original.suffix.lower() in {".php", ".html"}:
            content = original.read_text(encoding="utf-8")
            if rel.as_posix() == "super-admin-satu.php":
                content = make_admin_unified(content)
            elif rel.as_posix() == "super-admin.php":
                content = redirect_old_admin()
            elif rel.as_posix() == "index.php":
                content = update_index(content)
            newer = modernize_html(content, rel)
            dest.write_text(newer, encoding="utf-8")
            if re.search(r'<!doctype\s+html', newer, re.I):
                transformed.append(rel.as_posix())
        else:
            shutil.copy2(original, dest)
        included.append(rel.as_posix())

    # No installer, private configuration, SQL dump, or debug file is deployable.
    assert (target / ".htaccess").exists()
    assert (target / "index.php").exists()
    assert (target / "api/index.php").exists()
    assert (target / "mfa.php").exists()
    assert (target / "super-admin-satu.php").exists()
    assert (target / "assets/ui-modern.css").exists()
    assert (target / "assets/ui-modern.js").exists()
    assert (target / "assets/site-public-v3.css").exists()
    assert (target / "assets/portal-mockup.css").exists()
    assert (target / "assets/admin-modern.js").exists()
    assert not any(x.startswith("instal-") for x in included)
    assert not any(x.startswith("perbaiki-") for x in included)
    assert not any(x.endswith((".sql", ".zip", ".log", ".bak")) for x in included)
    manifest = {
        "version": "2026-11-10-mockup-flutter-integration",
        "source": "GitHub SmartCikoko/main/public_html",
        "file_count": len(included),
        "unified_html_pages": len(transformed),
        "excluded_setup_files": excluded_files,
        "files": {
            p: hashlib.sha256((target / p).read_bytes()).hexdigest()
            for p in included
        },
        "warning": "NOT A COPY OF LIVE HOSTING: preserve user uploads, .well-known, custom files and private config; stage/test first."
    }
    (output / "MANIFEST-DEPLOY.json").write_text(
        json.dumps(manifest, indent=2, ensure_ascii=False), encoding="utf-8"
    )
    print(f"Built {len(included)} active files with {len(transformed)} unified pages; "
          f"excluded {len(excluded_files)} old installation/setup files.")
    return manifest


if __name__ == "__main__":
    p = argparse.ArgumentParser()
    p.add_argument("--output", type=Path, default=ROOT / "_webroot_replacement")
    args = p.parse_args()
    args.output.mkdir(parents=True, exist_ok=True)
    generate(args.output)
