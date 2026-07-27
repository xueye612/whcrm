<?php
/** P4 外包项目纯逻辑测试。 php crm_php-master/tests/p4_outsource_test.php */
$LEVELS = ['一级','二级','三级','四级'];
$DEFAULT_DIST = [['role'=>'研发负责人','percentage'=>40],['role'=>'技术与项目负责人','percentage'=>28],['role'=>'客户成功工程师','percentage'=>25],['role'=>'驻场服务专员','percentage'=>5],['role'=>'市场运营专员','percentage'=>2]];
function computeMargin($rev,$cost){ return round((float)$rev-(float)$cost,2); }
function computePools($rev,$rp=2.0,$ep=3.0){ return ['reward_pool'=>round((float)$rev*$rp/100,2),'expense_pool'=>round((float)$rev*$ep/100,2)]; }
function validateRatios($rs){ $s=0; foreach($rs as $r){ $p=(float)$r['percentage']; if($p<0)return[false,'负']; $s+=$p;} if($s>100)return[false,'>100']; return [true,'']; }
function distribute($pool,$rs){ $out=[];$ap=0; foreach($rs as $r){$p=(float)$r['percentage'];$out[]=['role'=>$r['role'],'percentage'=>$p,'amount'=>round($pool*$p/100,2)];$ap+=$p;} return ['rows'=>$out,'unallocated'=>round($pool*(100-$ap)/100,2),'allocated_pct'=>$ap]; }
$pass=0; function check($c,$m){ global $pass; if(!$c){fwrite(STDERR,"FAIL: $m\n");exit(1);} $pass++; }

check(count($LEVELS)===4,'交付四级');
check(computeMargin(100000,40000)===60000.00,'毛利=收入-成本');
check(computePools(100000)['reward_pool']===2000.00,'奖励池默认2%');
check(computePools(100000)['expense_pool']===3000.00,'商务费用池默认3%');
// 默认比例 40/28/25/5/2 必须合法（含 28/25/2 非5倍数——制度默认值不强制5倍数）
list($ok,)=validateRatios($DEFAULT_DIST); check($ok===true,'默认比例40/28/25/5/2应通过校验');
list($ok,)=validateRatios([['role'=>'A','percentage'=>60],['role'=>'B','percentage'=>50]]); check($ok===false,'总和>100应拒绝');
list($ok,)=validateRatios([['role'=>'A','percentage'=>-5]]); check($ok===false,'负比例应拒绝');
// 分配：池2000，默认比例 → 各金额；不足100%不自动分配
$d=distribute(2000,$DEFAULT_DIST);
check($d['allocated_pct']==100,'默认分配满100%');
check($d['unallocated']==0,'满分配无未分配');
check($d['rows'][0]['amount']===800.00,'40%->800');
$d2=distribute(2000,[['role'=>'A','percentage'=>40],['role'=>'B','percentage'=>20]]);
check($d2['allocated_pct']==60,'部分分配60%');
check($d2['unallocated']===800.00,'不足部分不自动分配(800未分配)');
fwrite(STDOUT,"P4 outsource test passed ($pass assertions)\n");
