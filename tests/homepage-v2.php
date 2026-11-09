<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal_private/homepage_repair_v2.php';
function test_home(bool $ok,string $message):void{
 if(!$ok){fwrite(STDERR,"FAILED: ".$message."\n");exit(1);}
}
$root=sys_get_temp_dir().'/smartcikoko-home14-'.bin2hex(random_bytes(6));
$web=$root.'/public_html';$private=$root.'/portal_private';
mkdir($root,0700);mkdir($web,0700);mkdir($private,0700);
$target=$web.'/index.php';
$source="<?php\n// keep authentication and all PHP logic exactly unchanged\nrequire 'portal_private/bootstrap.php';\n?>\n"
    .'<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/assets/style.css"></head>'
    .'<body><header class="header">Menu</header><main class="hero-grid"><div class="hero-visual">Content</div></main></body></html>'
    .str_repeat(' ',3100);
file_put_contents($target,$source);
try{
 test_home(b14_home_target_ok($source),'Valid homepage template');
 $version='1a2b3c4d5e6f';
 $patched=b14_home_inject($source,$version);
 test_home(str_contains($patched,'/assets/beranda-fix.css?v='.$version),'Versioned new CSS link inserted');
 test_home(substr_count($patched,'SMARTCIKOKO_HOMEPAGE_V2')===1,'Unique marker');
 test_home(str_contains($patched,"require 'portal_private/bootstrap.php';"),'Login source is preserved');
 test_home(b14_home_inject($patched,$version)===$patched,'Second patch idempotent');
 test_home(!b14_home_target_ok('<html><head></head><body>wrong</body></html>'),'Unrecognized PHP template refused');
 $backup=b14_home_write_atomically($source,$patched,$target,$private);
 test_home($backup!=='' && is_file($private.'/beranda_backups/'.$backup),'Original index backed up outside webroot');
 test_home(file_get_contents($target)===$patched,'Atomic replacement writes new link');
 test_home(file_get_contents($private.'/beranda_backups/'.$backup)===$source,'Original PHP preserved in backup');
 test_home(b14_home_write_atomically($patched,$patched,$target,$private)==='','No action on identical patch');
 echo "Homepage V2: safe patch, backup and idempotence tests passed.\n";
}finally{
 foreach(glob($private.'/beranda_backups/*')?:[] as $file)@unlink($file);
 @rmdir($private.'/beranda_backups');
 @unlink($target);@rmdir($web);@rmdir($private);@rmdir($root);
}
