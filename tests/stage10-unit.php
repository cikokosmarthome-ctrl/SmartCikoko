<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal_private/citizen_requests.php';
require dirname(__DIR__).'/portal_private/citizen_followup.php';
function t10_assert(bool $ok,string $why):void {
 if(!$ok){fwrite(STDERR,"FAILED: $why\n");exit(1);}
}
t10_assert(count(t10_types())===4,'four document categories');
t10_assert(isset(t10_types()['kematian']),'death document type');
t10_assert(isset(t9_stages()['diajukan']),'Stage 9 workflow intact');
t10_assert(function_exists('t10_notifications'),'notification function present');
t10_assert(function_exists('t10_download'),'private download gateway present');
t10_assert(function_exists('t10_notify'),'status change hook present');
echo "Tahap 10: unit helper checks passed.\n";
