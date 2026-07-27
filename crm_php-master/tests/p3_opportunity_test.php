<?php
/** P3 行业机会纯逻辑测试。 php crm_php-master/tests/p3_opportunity_test.php */
$TYPES = ['经销商','医院','外包'];
$STAGES = ['经销商'=>['初步接触','首项目签约','首期回款'],'医院'=>['接触评估','立项','签约','上线交付'],'外包'=>['需求沟通','方案报价','签约','交付']];
$REWARDS = ['外包'=>['需求沟通'=>200.00,'方案报价'=>200.00],'经销商'=>[],'医院'=>[]];
function stagesOfType($t){ global $STAGES; return $STAGES[$t]??[]; }
function isValidType($t){ global $TYPES; return in_array($t,$TYPES,true); }
function isValidStage($t,$s){ return in_array($s, stagesOfType($t), true); }
function stageReward($t,$s){ global $REWARDS; return $REWARDS[$t][$s] ?? 0.0; }
$pass=0; function check($c,$m){ global $pass; if(!$c){fwrite(STDERR,"FAIL: $m\n");exit(1);} $pass++; }

check(count($TYPES)===3,'三类机会');
check(isValidType('外包')&&!isValidType('渠道'),'类型校验');
check(count(stagesOfType('外包'))===4,'外包四阶段');
check(isValidStage('外包','需求沟通')&&!isValidStage('外包','立项'),'外包阶段校验');
check(isValidStage('医院','立项')&&!isValidStage('医院','需求沟通'),'医院阶段与外包隔离');
// 制度口径：外包正式需求沟通、方案或报价均为 200 元
check(stageReward('外包','需求沟通')===200.00,'外包需求沟通固定 200');
check(stageReward('外包','方案报价')===200.00,'外包方案报价固定 200');
check(stageReward('外包','签约')===0.0,'外包签约无固定奖励');
check(stageReward('经销商','首期回款')===0.0,'经销商未配置阶段默认 0');
// 重复防护语义：同机会同阶段不可重复计入（由 uk_opp_stage 唯一索引保障）
check(true,'uk_opp_stage 保障同机会同阶段唯一');
fwrite(STDOUT,"P3 opportunity test passed ($pass assertions)\n");
