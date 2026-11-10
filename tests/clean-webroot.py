#!/usr/bin/env python3
"""Static safety checks for full SmartCikoko replacement webroot."""
from __future__ import annotations
import json
import re
import sys
from pathlib import Path

BASE = Path(sys.argv[1]).resolve()
ROOT = BASE / 'public_html_baru'
assert ROOT.is_dir(), "Expected staged, NOT live, webroot"
files = [x for x in ROOT.rglob('*') if x.is_file()]
relative = {str(x.relative_to(ROOT)) for x in files}
needed = {
    '.htaccess', 'index.php','api/.htaccess','api/index.php',
    'mfa.php','super-admin-satu.php','super-admin.php',
    'pusat-laporan.php','formulir-pegawai16.php','laporan-wilayah17.php',
    'pusat-operasional23.php','pengajuan-warga.php','kelola-pengguna.php',
    'uji-operasional26.php','cek-operasional25.php',
    'kendali-verifikasi22.php','verifikasi-laporan19.php',
    'monitor-laporan20.php','tugas-pemeriksa21.php',
    'keamanan-superadmin.php','unduh-berkas.php',
    'assets/ui-modern.css','assets/ui-modern.js','assets/admin-modern.js',
    'assets/site-public-v3.css'
}
assert needed<=relative,sorted(needed-relative)
assert not any(
    p.startswith(('instal-','perbaiki-','pasang-'))
    or p=='aktivasi-super-admin.php'
    for p in relative
), "Installer/setup leftovers included"
assert not any(p.endswith(('.sql','.log','.bak','.zip')) for p in relative)
assert not any('portal_private/' in p for p in relative)
pages = []
for entry in files:
    if entry.suffix not in ('.php','.html'):
        continue
    contents = entry.read_text(encoding='utf8')
    if not re.search(r'<!doctype\s+html',contents,re.I):
        continue
    rel=str(entry.relative_to(ROOT))
    pages.append(rel)
    slug=entry.stem.lower().replace('_','-')
    assert f'sc-body sc-page-{slug}' in contents,rel
    assert '/assets/ui-modern.css?v=20261110' in contents,rel
    assert '/assets/ui-modern.js?v=20261110' in contents,rel
    assert len(re.findall(r'</head\s*>',contents,re.I))==1,rel
    assert len(re.findall(r'<body\b',contents,re.I))==1,rel
    assert re.search(r'<header\b',contents,re.I),rel
    assert re.search(r'<footer\b',contents,re.I),rel
    assert re.search(r'</body\s*>',contents,re.I),rel
assert len(pages)>=30, len(pages)

admin=(ROOT/'super-admin-satu.php').read_text()
assert 'require_super_admin()' in admin
assert 'verify_csrf()' in admin
assert 'lr_review($admin' in admin
assert 'super_admin_set_progress($admin' in admin
assert admin.count('id="sc-admin-workspace"')==1
assert '<iframe id="sc-admin-frame"' in admin
assert admin.count('data-sc-tab')==5
assert admin.count('data-sc-tool')>=18
assert admin.count('<section id="modul"')==1
assert admin.count('<section id="laporan"')==1
assert 'href="/super-admin-satu.php?kategori=' in admin
assert 'href="/pusat-laporan.php"' in admin
assert '/assets/admin-modern.js?v=20261110' in admin
assert 'href="/super-admin.php"' not in admin, "No duplicate dashboard links"
assert '<a href="#ringkasan">Ringkasan</a>' not in admin, "Old navigation should be replaced"
assert len(re.findall(r'<section\b',admin))>=5

oldadmin=(ROOT/'super-admin.php').read_text()
assert 'require_super_admin()' in oldadmin
assert "Location: /super-admin-satu.php" in oldadmin
assert 'super_admin_metrics()' not in oldadmin

index=(ROOT/'index.php').read_text()
assert 'function_exists(\'is_super_admin\')' in index
assert "Location: /super-admin-satu.php" in index
assert 'a(\'pppk\'' in index
assert 'a(\'pjlp\'' in index
assert 'a(\'waris\'' in index
assert 'login_matches' in index
assert 'waris_change_status' in index
assert '/assets/site-public-v3.css?v=20261110v3' in index
assert 'aria-labelledby="home-title"' in index
assert 'class="hero-panel-head"' in index
assert 'class="hero-quick-grid"' in index
assert index.count('class="hero-quick-link"')==4
assert 'class="outline">CIKOKO' not in index
assert '<span class="seal">KC</span>' in index

public_theme=(ROOT/'assets/site-public-v3.css').read_text()
assert '--site-navy' in public_theme
assert 'body.sc-page-index > header.header .headinner' in public_theme
assert 'flex-wrap:nowrap!important' in public_theme
assert 'body.sc-page-index .hero .hero-visual' in public_theme
assert 'background:linear-gradient(145deg,#103955' in public_theme
assert 'body.sc-page-index .hero-quick-grid' in public_theme
assert 'body.sc-page-index .hero .btn.primary' in public_theme
assert 'body.sc-page-index .service' in public_theme
assert 'body.sc-page-index > footer.footer' in public_theme
assert public_theme.count('{')==public_theme.count('}')

form=(ROOT/'formulir-pegawai16.php').read_text()
assert 'f16_create($user' in form
assert 'f16_review($user' in form
assert 'f16_authorized($user,$kind)' in form
assert 'name="submit_now" value="0"' in form
assert 'name="submit_now" value="1"' in form

ui=(ROOT/'assets/ui-modern.css').read_text()
assert 'min-height:100dvh' in ui
assert 'body.sc-body > header{position:sticky' in ui
assert 'body.sc-body > footer{flex-shrink:0;margin-top:auto' in ui
assert '.sc-embedded >' in ui or 'sc-embedded >' in ui
assert 'sc-page-index .hero-grid' in ui
assert 'sc-page-super-admin-satu' in ui
assert ui.count('{')==ui.count('}')
for file in ROOT.rglob('*.css'):
    style=file.read_text(encoding='utf8')
    assert style.count('{')==style.count('}'),str(file)

manifest=json.loads((BASE/'MANIFEST-DEPLOY.json').read_text(encoding='utf8'))
assert manifest['file_count']==len(files)
assert manifest['unified_html_pages']==len(pages)
assert set(manifest['files'])==relative
print(f"PASS: {len(files)} active files, {len(pages)} consistently themed HTML pages, complete routes, one admin, no installers, sticky header and footer.")
