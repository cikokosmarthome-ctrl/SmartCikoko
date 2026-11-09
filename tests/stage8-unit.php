<?php
declare(strict_types=1);
/** Tests of pure Stage-8 helpers without touching user data or databases. */
require dirname(__DIR__).'/portal_private/document_workflow.php';
function expect(bool $condition,string $msg):void {
    if(!$condition){fwrite(STDERR,"FAILED: $msg\n");exit(1);}
}
expect(count(t8_document_types())===5,'Five categorized document classes');
expect(isset(t8_check_statuses()['perbaikan']),'Document rework status');
expect(t8_month('2026-11')==='2026-11','Valid month');
expect(t8_month_range('2026-12')===['2026-12-01','2027-01-01'],'Year boundary');
expect(t8_month_range('2024-02')===['2024-02-01','2024-03-01'],'Leap month');
foreach(['2026-00','2026-13','2026-1','bad','2026-02-01'] as $invalid){
    $failed=false;
    try {t8_month($invalid);}catch(InvalidArgumentException $e){$failed=true;}
    expect($failed,"Reject invalid month: $invalid");
}
echo "Stage 8 pure helper tests passed.\n";
