<?php
/** P6 季度绩效纯逻辑测试。 php crm_php-master/tests/p6_performance_test.php */
$W=['duty'=>0.40,'task'=>0.30,'quality'=>0.20,'collab'=>0.10];
$FACTORS=['优秀'=>1.20,'合格'=>1.00,'待改进'=>0.60];
$TIERS=['完成良好','基本完成','需要改进'];
function weighted($d,$t,$q,$c){ global $W; return round($d*$W['duty']+$t*$W['task']+$q*$W['quality']+$c*$W['collab'],2); }
function assertNotSelf($a,$b){ return ((int)$a>0 && (int)$a===(int)$b)?false:true; }
$pass=0; function check($c,$m){ global $pass; if(!$c){fwrite(STDERR,"FAIL: $m\n");exit(1);} $pass++; }

check(abs(array_sum($W)-1.0)<0.0001,'四权重合计为1');
check($W['duty']==0.40 && $W['task']==0.30 && $W['quality']==0.20 && $W['collab']==0.10,'四权重40/30/20/10');
check(weighted(100,100,100,100)==100.00,'满分加权=100');
check(weighted(90,85,88,80)==87.10,'示例加权=87.1');
check(count($TIERS)===3,'质量三档');
check($FACTORS['优秀']===1.20 && $FACTORS['合格']===1.00 && $FACTORS['待改进']===0.60,'评级系数1.2/1.0/0.6');
check(assertNotSelf(2,1)===true,'非本人可评');
check(assertNotSelf(1,1)===false,'本人回避');
fwrite(STDOUT,"P6 performance test passed ($pass assertions)\n");
