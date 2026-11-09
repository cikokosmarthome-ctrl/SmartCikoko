<?php
declare(strict_types=1);
/* Pure TOTP tests using public RFC 6238 example seed, never production credentials. */
require dirname(__DIR__).'/portal_private/security_stage13.php';
function test13(bool $ok,string $label):void{
    if(!$ok){fwrite(STDERR,"FAILED: $label\n");exit(1);}
}
$seed='12345678901234567890';
$secret='GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
test13(s13_b32encode($seed)===$secret,'RFC seed Base32 encoding');
test13(s13_b32decode($secret)===$seed,'RFC seed Base32 decoding');
test13(s13_totp($secret,1)==='287082','RFC 6238 time 59 seconds six-digit TOTP');
test13(s13_match_step($secret,'287082',59)===1,'Valid TOTP accepted');
test13(s13_match_step($secret,'287082',59,1)===null,'TOTP replay rejected');
test13(s13_match_step($secret,'000000',59)===null,'Invalid TOTP rejected');
test13(s13_match_step($secret,'not-digits',59)===null,'Invalid OTP input rejected');
$random=s13_secret_new();
test13(preg_match('/^[A-Z2-7]{32}$/D',$random)===1,'Random secret format');
test13(strlen(s13_b32decode($random))===20,'Random secret binary length');
echo "Tahap 13: TOTP and replay guard unit tests passed.\n";
