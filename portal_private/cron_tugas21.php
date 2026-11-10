<?php
declare(strict_types=1);
/**
 * Set in cPanel Cron Jobs only AFTER Super Admin explicitly enables the
 * schedule in the dashboard. Private CLI file; no web-accessible cron token.
 */
if(PHP_SAPI!=='cli'){
    http_response_code(403);
    exit('Hanya dapat dijalankan melalui PHP CLI.');
}
if(($argv[1]??'')!=='--dispatch'||count($argv)!==2){
    fwrite(STDERR,"Usage: php cron_tugas21.php --dispatch\n");exit(2);
}
try{
    require __DIR__.'/bootstrap.php';
    require_once __DIR__.'/tasks21.php';
    $r=t21_run(false);
    echo 'Stage21: '.($r['status']??'unknown').', internal alerts created='.(int)$r['created']
       .', checked='.(int)$r['checked'].', possibly_incomplete='.(int)($r['incomplete']??0)."\n";
    if(!empty($r['incomplete']))exit(1);
}catch(Throwable $e){
    error_log('Stage21 private scheduled check failed: '.get_class($e));
    fwrite(STDERR,"Stage21 scheduler unavailable. See private PHP error log.\n");
    exit(1);
}
