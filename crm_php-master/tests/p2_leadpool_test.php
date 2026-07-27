<?php
/**
 * P2 原始数据/有效线索纯逻辑测试（独立运行，不依赖 ThinkPHP）
 *   php crm_php-master/tests/p2_leadpool_test.php
 */
$RAW = ['待查重','已查重','重复','已归并','已转客户'];
$DEC = ['归并','独立','重复'];
function buildDedupeKey($name,$mobile){
    $n=preg_replace('/\s+/','', (string)$name);
    $m=preg_replace('/\D/','',(string)$mobile);
    $m = strlen($m)>11 ? substr($m,-11) : $m;
    return md5(mb_strtolower($n,'UTF-8').'|'.$m);
}
function isValidDecision($v){ global $DEC; return in_array($v,$DEC,true); }
$pass=0;
function check($c,$m){ global $pass; if(!$c){fwrite(STDERR,"FAIL: $m\n");exit(1);} $pass++; }

check(count($RAW)===5,'原始状态应为5种');
check(count($DEC)===3,'决策应为3种');
check(isValidDecision('归并') && isValidDecision('独立') && isValidDecision('重复'),'三种决策合法');
check(!isValidDecision('忽略'),'“忽略”非合法决策');

// 查重键归一化
$k1=buildDedupeKey('济南国政科技','15628802133');
$k2=buildDedupeKey(' 济南国政科技 ','156-2880-2133');
check($k1===$k2,'名称空白与手机分隔符应归一为同一查重键');
$k3=buildDedupeKey('济南国政科技','13900000000');
check($k1!==$k3,'手机号不同应得到不同查重键');
$k4=buildDedupeKey('济南国政科技','');
$k5=buildDedupeKey('','15628802133');
check($k4!==$k5,'名称或手机缺失不应碰撞');
// 超长号码取末11位
check(buildDedupeKey('x','8615600001111')===buildDedupeKey('x','15600001111'),'带国家码应取末11位');

fwrite(STDOUT,"P2 leadpool test passed ($pass assertions)\n");
