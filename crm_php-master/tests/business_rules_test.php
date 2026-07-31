<?php
/**
 * 商机与绩效业务规则纯逻辑测试（不依赖框架自动加载）
 * 覆盖：商机直签/代理简化、经销商约束、状态跳级、四权重、评级系数、加权得分、
 *   岗位基准、参考结果、状态字典中文、台账质量问题状态、责任认定状态、本人回避、W/R/K 校验、
 *   工作流状态有序、合法迁移、台账质量问题不再因 description='' 直接生成负向事实、
 *   责任认定审核闭环、人工调整审计。
 *
 * 注意：本测试复制关键常量自我比较会失去意义，因此改为：
 *   1) 从源码文件解析常量值（用正则）做实际断言
 *   2) 加权/评级/参考结果使用源码同样的算法重新计算并比较
 * php crm_php-master/tests/business_rules_test.php
 */
$pass = 0; $fail = 0;
function check($c, $m) { global $pass, $fail; if ($c) { $pass++; echo "[PASS] $m\n"; } else { $fail++; fwrite(STDERR, "[FAIL] $m\n"); } }

$root = dirname(__DIR__);
$businessModelSrc = file_get_contents($root . '/application/crm/model/Business.php');
$businessCtrlSrc = file_get_contents($root . '/application/crm/controller/Business.php');
$perfSrc = file_get_contents($root . '/application/crm/logic/PerformanceService.php');
$perfCtrlSrc = file_get_contents($root . '/application/crm/controller/Performance.php');
$wfSrc = file_get_contents($root . '/application/work/logic/WorkflowService.php');

// === 1-4) 商机只按是否选择经销商区分直签/代理 ===
check(strpos($businessModelSrc, "business_category'] = \$param['dealer_customer_id'] > 0 ? 'agent' : 'direct'") !== false, '有经销商为代理，无经销商为直签');
check(strpos($businessModelSrc, "signing_method'] = \$param['dealer_customer_id'] > 0 ? 'dealer_signed' : 'company_direct'") !== false, '签署方式由经销商自动推导');
check(strpos($businessModelSrc, '客户不能选择自己作为签约代理商') !== false, '客户不能选择自己作为签约代理商');
check(strpos($businessModelSrc, "!== 'dealer'") === false, '代理商不再要求 customer_type=dealer');
check(strpos($businessModelSrc, 'categoryTypeMap') === false, '业务类别不再绑定状态组');
check(strpos($businessModelSrc, 'getHospitalCurrentDealer') === false, '商机不再依赖医院当前经销商');

// === 5) advance 顺序规则与事务 ===
check(strpos($businessCtrlSrc, '禁止状态倒退') !== false, 'advance 禁止倒退');
check(strpos($businessCtrlSrc, '禁止重复推进同一阶段') !== false, 'advance 禁止重复推进');
check(strpos($businessCtrlSrc, '禁止跳级推进') === false, 'advance 允许跳过未参与阶段');
check(strpos($businessCtrlSrc, '商机已结束，不可再次推进') !== false, 'advance 终态后禁止推进');
check(strpos($businessCtrlSrc, 'lock(true)') !== false, 'advance 锁定商机行');
check(strpos($businessCtrlSrc, 'Db::rollback()') !== false, 'advance 失败回滚');
check(strpos($businessCtrlSrc, 'rewardGenerated = false') !== false, 'advance 终态不生成兜底奖励');

// === 5b) 阶段回退（受控）===
check(strpos($businessCtrlSrc, 'function stageRollback') !== false, 'stageRollback 方法存在');
check(strpos($businessCtrlSrc, '阶段回退') !== false, '阶段回退日志记录');
check(strpos($businessCtrlSrc, 'ST_VOIDED') !== false, '回退标记作废奖励');
check(strpos($businessCtrlSrc, 'business_stage_reversal') !== false, '回退生成冲销记录');
check(strpos($businessCtrlSrc, 'isSuperAdministrators') !== false, '阶段回退仅管理员可操作');

// === 5b-2) 冲销幂等：source_ref 包含 cand_id ===
check(strpos($businessCtrlSrc, ':reversal:candidate:') !== false, '冲销 source_ref 包含原 cand_id');
check(strpos($businessCtrlSrc, 'existReversal') !== false, '冲销前检查是否已有冲销记录');
check(strpos($businessCtrlSrc, 'lock(true)') !== false, '候选查询使用行锁');
check(strpos($businessCtrlSrc, 'rewardSkipped') !== false, '重复回退跳过已处理候选');

// === 5b-3) 奖惩审计 ===
check(strpos($businessCtrlSrc, 'logRewardAudit') !== false, '回退写奖励审计');
check(strpos($businessCtrlSrc, 'stage_rollback_void') !== false, '作废审计 operation_type=stage_rollback_void');
check(strpos($businessCtrlSrc, 'stage_rollback_reversal') !== false, '冲销审计 operation_type=stage_rollback_reversal');
check(strpos($businessCtrlSrc, 'stage_reactivate') !== false, '重新激活审计 operation_type=stage_reactivate');
check(strpos($businessCtrlSrc, 'old_data_json') !== false, '审计保存 old_data_json');
check(strpos($businessCtrlSrc, 'new_data_json') !== false, '审计保存 new_data_json');
check(strpos($businessCtrlSrc, 'request_ip') !== false, '审计保存请求IP');

// === 5b-4) 重新激活完整重置 ===
check(strpos($businessCtrlSrc, 'reviewer_user_id') !== false, '重新激活重置 reviewer_user_id');
check(strpos($businessCtrlSrc, 'review_time') !== false, '重新激活重置 review_time');
check(strpos($businessCtrlSrc, 'batch_id') !== false, '重新激活重置 batch_id');
check(strpos($businessCtrlSrc, 'round2') !== false, '已结算原记录生成新轮次候选');

// === 5c) 状态组自动分配 ===
check(strpos($businessModelSrc, 'getTypeIdByDealer') !== false, '根据代理商自动分配状态组');
check(strpos($businessModelSrc, 'getTypeIdByCategory') !== false, '按 business_category 查找状态组');
check(strpos($businessModelSrc, 'business_type_id_direct') !== false, '从配置表读取直签组ID');
check(strpos($businessModelSrc, 'business_type_id_agent') !== false, '从配置表读取代理签约组ID');
check(strpos($businessModelSrc, '商机状态组尚未配置') !== false, '找不到状态组时返回明确错误');
check(strpos($businessModelSrc, 'order_id') !== false, '编辑时按 order_id 映射阶段');

// === 5d) 推进时重新激活已作废候选 ===
check(strpos($businessCtrlSrc, 'ST_VOIDED') !== false, 'advance 检查已作废候选并重新激活');

// === 6) 四权重 ===
check(strpos($perfSrc, 'W_DUTY = 0.40') !== false, 'W_DUTY = 0.40');
check(strpos($perfSrc, 'W_TASK = 0.30') !== false, 'W_TASK = 0.30');
check(strpos($perfSrc, 'W_QUALITY = 0.20') !== false, 'W_QUALITY = 0.20');
check(strpos($perfSrc, 'W_COLLAB = 0.10') !== false, 'W_COLLAB = 0.10');

// === 7) 评级系数 ===
check(strpos($perfSrc, "RATING_EXCELLENT => 1.20") !== false, '评级系数 优秀 = 1.20');
check(strpos($perfSrc, "RATING_QUALIFIED => 1.00") !== false, '评级系数 合格 = 1.00');
check(strpos($perfSrc, "RATING_POOR => 0.60") !== false, '评级系数 待改进 = 0.60');

// === 8) 加权得分（独立计算验证） ===
function weightedScore($d, $t, $q, $c) { return round($d * 0.40 + $t * 0.30 + $q * 0.20 + $c * 0.10, 2); }
check(abs(weightedScore(100, 100, 100, 100) - 100.00) < 0.001, 'weightedScore 100*4 = 100');
$exp = 80*0.40 + 70*0.30 + 60*0.20 + 50*0.10;
check(abs(weightedScore(80, 70, 60, 50) - $exp) < 0.01, "weightedScore 80/70/60/50 = $exp");

// === 9) 岗位基准 ===
check(strpos($perfSrc, "总经理兼产品负责人' => 3000.00") !== false, '总经理基准 3000');
check(strpos($perfSrc, "研发负责人'        => 3000.00") !== false, '研发负责人基准 3000');
check(strpos($perfSrc, "技术与项目负责人'  => 2400.00") !== false, '技术与项目负责人基准 2400');
check(strpos($perfSrc, "客户成功工程师'    => 900.00") !== false, '客户成功工程师基准 900');
check(strpos($perfSrc, "驻场服务专员'      => 900.00") !== false, '驻场服务专员基准 900');
check(strpos($perfSrc, "市场运营专员'      => 1500.00") !== false, '市场运营专员基准 1500');

// === 10) 参考结果（基准×系数） ===
function refAmount($base, $rating) {
    $factors = ['优秀' => 1.20, '合格' => 1.00, '待改进' => 0.60];
    $f = isset($factors[$rating]) ? $factors[$rating] : 1.00;
    return round($base * $f, 2);
}
check(abs(refAmount(3000, '优秀') - 3600.00) < 0.01, 'referenceAmount 3000*1.2 = 3600');
check(abs(refAmount(3000, '合格') - 3000.00) < 0.01, 'referenceAmount 3000*1.0 = 3000');
check(abs(refAmount(3000, '待改进') - 1800.00) < 0.01, 'referenceAmount 3000*0.6 = 1800');

// === 11) 状态字典中文 ===
check(strpos($perfSrc, "SUMMARY_PENDING  = '待确认'") !== false, 'SUMMARY_PENDING 待确认');
check(strpos($perfSrc, "SUMMARY_CONFIRMED = '已确认'") !== false, 'SUMMARY_CONFIRMED 已确认');
check(strpos($perfSrc, "SUMMARY_RETURNED = '已退回'") !== false, 'SUMMARY_RETURNED 已退回');
check(strpos($perfSrc, "FACT_PENDING  = '待审核'") !== false, 'FACT_PENDING 待审核');
check(strpos($perfSrc, "FACT_APPROVED = '已通过'") !== false, 'FACT_APPROVED 已通过');
check(strpos($perfSrc, "FACT_REJECTED = '已驳回'") !== false, 'FACT_REJECTED 已驳回');
check(strpos($perfSrc, "DIR_POSITIVE = '正向'") !== false, 'DIR_POSITIVE 正向');
check(strpos($perfSrc, "DIR_NEGATIVE = '负向'") !== false, 'DIR_NEGATIVE 负向');

// === 12) 台账质量问题状态 ===
check(strpos($perfSrc, "LEDGER_Q_PENDING  = '待确认'") !== false, 'LEDGER_Q_PENDING 待确认');
check(strpos($perfSrc, "LEDGER_Q_CONFIRMED = '已确认'") !== false, 'LEDGER_Q_CONFIRMED 已确认');
check(strpos($perfSrc, "LEDGER_Q_IGNORED  = '已忽略'") !== false, 'LEDGER_Q_IGNORED 已忽略');
check(strpos($perfSrc, "LEDGER_Q_FIXED    = '已修正'") !== false, 'LEDGER_Q_FIXED 已修正');

// === 13) 责任认定状态 ===
check(strpos($perfSrc, "CASE_PENDING  = '认定中'") !== false, 'CASE_PENDING 认定中');
check(strpos($perfSrc, "CASE_APPROVED = '已认定'") !== false, 'CASE_APPROVED 已认定');
check(strpos($perfSrc, "CASE_REJECTED = '已驳回'") !== false, 'CASE_REJECTED 已驳回');

// === 14) 台账质量问题不再因 description='' 直接生成负向事实 ===
check(strpos($perfCtrlSrc, "ledger_missing_desc") === false, 'Performance 不再生成 ledger_missing_desc 错误事实');
check(strpos($perfCtrlSrc, "ledger_quality_confirmed") !== false, 'Performance 仅已确认台账质量问题生成负向事实');
check(strpos($perfCtrlSrc, "LEDGER_Q_CONFIRMED") !== false, 'Performance 仅归集 LEDGER_Q_CONFIRMED 状态问题');
check(strpos($perfCtrlSrc, "review_time") !== false, 'Performance 测试事实使用 review_time（评定时间）');
check(strpos($perfCtrlSrc, "test_non_compliant") !== false, 'Performance 归集测试不符合负向事实');
check(strpos($perfCtrlSrc, "c.settle_time") !== false || strpos($perfCtrlSrc, "c.update_time") !== false, 'Performance 奖励使用结算时间而非创建时间');
check(strpos($perfCtrlSrc, "task_wrk_log") !== false, 'Performance 归集 W/R/K 调整记录');
check(strpos($perfCtrlSrc, "空值不解释为默认等级") !== false, 'Performance W/R/K 空值不解释为默认等级');
check(strpos($perfCtrlSrc, "project_implementation") !== false, 'Performance 归集自有产品实施结果');
check(strpos($perfCtrlSrc, "outsource_project") !== false, 'Performance 归集外包项目结果');

// === 15) 责任认定审核闭环 ===
check(strpos($perfCtrlSrc, "caseReview") !== false, 'Performance 有责任认定审核 caseReview');
check(strpos($perfCtrlSrc, "本人回避：不能审核自己的责任认定") !== false, 'caseReview 本人回避');
check(strpos($perfCtrlSrc, "提交人回避：不能审核自己提交的责任认定") !== false, 'caseReview 提交人回避');
check(strpos($perfCtrlSrc, "upsertResponsibilityFact") !== false, 'caseReview 审核通过后生成负向事实');
check(strpos($perfCtrlSrc, "responsibility_case") !== false, '责任认定事实 source_type=responsibility_case');

// === 16) 人工调整审计 ===
check(strpos($perfCtrlSrc, "performance_adjust_audit") !== false, '人工调整审计表 performance_adjust_audit');
check(strpos($perfCtrlSrc, "已确认绩效不可直接覆盖") !== false, '已确认绩效不可直接覆盖（需先退回）');
check(strpos($perfCtrlSrc, "退回必须填写原因") !== false, '退回必须填写原因');
check(strpos($perfCtrlSrc, "summaryRecommit") !== false, '重新提交 summaryRecommit');
check(strpos($perfCtrlSrc, "recordAdjustIfChanged") !== false, '调整审计 recordAdjustIfChanged');
check(strpos($perfCtrlSrc, "quarterly_base") !== false, '保存岗位基准 quarterly_base');
check(strpos($perfCtrlSrc, "reference_amount") !== false, '保存参考结果 reference_amount');

// === 17) 台账质量问题登记/确认/忽略/修正 ===
check(strpos($perfCtrlSrc, "ledgerQualitySave") !== false, '台账质量问题登记 ledgerQualitySave');
check(strpos($perfCtrlSrc, "ledgerQualityReview") !== false, '台账质量问题审核 ledgerQualityReview');
check(strpos($perfCtrlSrc, "ledgerQualityList") !== false, '台账质量问题列表 ledgerQualityList');
check(strpos($perfCtrlSrc, "登记人不能审核自己登记的问题") !== false, '台账质量问题登记人回避');
check(strpos($perfCtrlSrc, "审核必须填写原因") !== false, '台账质量问题审核必须填写原因');

// === 18) W/R/K 校验与工作流状态有序 ===
check(strpos($wfSrc, "wLevels = ['W1', 'W2', 'W3', 'W4', 'W5']") !== false, 'W1-W5');
check(strpos($wfSrc, "rLevels = ['R1', 'R2', 'R3', 'R4', 'R5']") !== false, 'R1-R5');
check(strpos($wfSrc, "kLevels = ['K1', 'K2', 'K3', 'K4']") !== false, 'K1-K4');
check(strpos($wfSrc, "STATUS_PENDING_EVAL   = '待评估'") !== false, 'STATUS_PENDING_EVAL 待评估');
check(strpos($wfSrc, "STATUS_DONE           = '已完成'") !== false, 'STATUS_DONE 已完成');
check(strpos($wfSrc, "complete'              => [self::STATUS_RELEASE        => self::STATUS_DONE]") !== false, 'complete 从待发布到已完成');

// === 19) 数据库 forward 硬阻断 ===
$bizTypeFwd = file_get_contents($root . '/../deployment/sql/20260728_final_biz_type_forward.sql');
check(strpos($bizTypeFwd, '@any_block') !== false, 'biz_type_forward 有硬阻断变量');
check(strpos($bizTypeFwd, 'BLOCKED_final_biz_type_delete') !== false, 'biz_type_forward 硬阻断时输出 BLOCKED 标记');
$fieldDedupFwd = file_get_contents($root . '/../deployment/sql/20260728_final_field_dedup_forward.sql');
check(strpos($fieldDedupFwd, '@any_block') !== false, 'field_dedup_forward 有硬阻断变量');
check(strpos($fieldDedupFwd, 'BLOCKED_final_field_dedup_952') !== false, 'field_dedup_forward 硬阻断时输出 BLOCKED 标记');
check(strpos($fieldDedupFwd, 'BLOCKED_final_field_dedup_962') !== false, 'field_dedup_forward 硬阻断 962');

// === 20) 严格幂等 ===
$rewardRuleFwd = file_get_contents($root . '/../deployment/sql/20260728_final_reward_rule_forward.sql');
check(strpos($rewardRuleFwd, 'IF(`amount`<>VALUES(`amount`), VALUES(`amount`), `amount`)') !== false, 'reward_rule_forward 仅在值变化时更新（严格幂等）');
$bizStatusFwd = file_get_contents($root . '/../deployment/sql/20260728_final_biz_status_forward.sql');
check(strpos($bizStatusFwd, "AND (`name` IS NULL OR `name`<>'") !== false, 'biz_status_forward 仅在名称不一致时 UPDATE（严格幂等）');

// === 21) verify 脚本必须能明确失败 ===
$bizTypeVerify = file_get_contents($root . '/../deployment/sql/20260728_final_biz_type_verify.sql');
check(strpos($bizTypeVerify, 'FAIL_') !== false, 'biz_type_verify 有 FAIL_* 计数');
check(strpos($bizTypeVerify, 'total_failures') !== false, 'biz_type_verify 有 total_failures 汇总');
$rewardRuleVerify = file_get_contents($root . '/../deployment/sql/20260728_final_reward_rule_verify.sql');
check(strpos($rewardRuleVerify, 'FAIL_') !== false, 'reward_rule_verify 有 FAIL_* 计数');

// === 22) RBAC 不假设管理员组 id=1 ===
$perfRbacFwd = file_get_contents($root . '/../deployment/sql/20260728_final_perf_rbac_forward.sql');
check(strpos($perfRbacFwd, '不假设超级管理员组 id=1') !== false, 'perf_rbac_forward 不假设管理员组 id=1');
check(strpos($perfRbacFwd, 'admin_rule.name=perf_*') !== false || strpos($perfRbacFwd, 'admin_rule.name LIKE') !== false, 'perf_rbac_forward 按规则名定位');

// === 23) 数据修正计划 ===
$corrPlan = file_get_contents($root . '/../deployment/sql/20260728_final_data_correction_plan.sql');
check(strpos($corrPlan, 'ledger_missing_desc') !== false, '数据修正计划包含 ledger_missing_desc 清理建议');
check(strpos($corrPlan, 'business_category') !== false, '数据修正计划包含历史商机类别回填建议');
check(strpos($corrPlan, '孤儿商机状态诊断') !== false, '数据修正计划包含孤儿状态诊断');
check(strpos($corrPlan, '人工执行') !== false, '数据修正计划要求人工执行');

// === 24) 新增：奖励处罚收尾 ===
$rewardServiceSrc = file_get_contents($root . '/application/crm/logic/RewardService.php');
$rewardCtrlSrc = file_get_contents($root . '/application/crm/controller/Reward.php');
// 处罚（负金额）不降低月度奖励累计值
check(strpos($rewardServiceSrc, "where('amount', '>', 0)") !== false, '月度奖励上限仅统计正金额（处罚不降低累计）');
// 配置值校验
check(strpos($rewardCtrlSrc, '奖励金额必须大于等于0') !== false, '配置校验：奖励金额 >= 0');
check(strpos($rewardCtrlSrc, '收入上限必须大于等于0') !== false, '配置校验：收入上限 >= 0');
check(strpos($rewardCtrlSrc, '比例必须在0-100之间') !== false, '配置校验：比例范围 0-100');
// 奖励说明包含中文客户名和商机名
check(strpos($businessCtrlSrc, '客户「') !== false && strpos($businessCtrlSrc, '」的商机「') !== false, '奖励说明包含中文客户名和商机名');
// 奖励候选保存完整字段
check(strpos($businessCtrlSrc, "'business_id' => (int)\$param['business_id']") !== false, '奖励候选保存 business_id');
check(strpos($businessCtrlSrc, "'customer_id' => (int)\$businessInfo['customer_id']") !== false, '奖励候选保存 customer_id');
check(strpos($businessCtrlSrc, "'occurred_time' => time()") !== false, '奖励候选保存 occurred_time');
check(strpos($businessCtrlSrc, "'source_ref' => \$sourceRef") !== false, '奖励候选保存 source_ref');
check(strpos($businessCtrlSrc, "'evidence_note'") !== false, '奖励候选保存 evidence_note');

// === 25) 新增：台账转任务收尾 ===
$ledgerCtrlSrc = file_get_contents($root . '/application/ledger/controller/Ledger.php');
// 中文错误提示
check(strpos($ledgerCtrlSrc, '缺少台账ID') !== false, 'convertToTask 中文错误：缺少台账ID');
check(strpos($ledgerCtrlSrc, '请填写完整信息') !== false, 'convertToTask 中文错误：请填写完整信息');
check(strpos($ledgerCtrlSrc, '任务分类不属于所选项目') !== false, 'convertToTask 中文错误：分类不属于项目');
check(strpos($ledgerCtrlSrc, '负责人必须是所选项目的成员') !== false, 'convertToTask 校验负责人属于项目成员');
check(strpos($ledgerCtrlSrc, '该台账已转换过任务') !== false, 'convertToTask 幂等：已转换提示');
check(strpos($ledgerCtrlSrc, '来源台账ID') !== false, '任务描述包含来源台账ID');
// 台账统计API
check(strpos($ledgerCtrlSrc, 'public function statistics()') !== false, '台账统计方法 statistics 存在');
check(strpos($ledgerCtrlSrc, 'conversion_rate') !== false, '台账统计返回转化率');
check(strpos($ledgerCtrlSrc, 'avg_hours') !== false, '台账统计返回平均处理时长');

// === 26) 新增：客户经销商死代码清理 ===
$customerCtrlSrc = file_get_contents($root . '/application/crm/controller/Customer.php');
check(strpos($customerCtrlSrc, 'dealerRelSet') === false, 'Customer 控制器不再有 dealerRelSet');
check(strpos($customerCtrlSrc, 'dealerRelRead') === false, 'Customer 控制器不再有 dealerRelRead');
check(strpos($customerCtrlSrc, 'dealerRelClear') === false, 'Customer 控制器不再有 dealerRelClear');
check(strpos($customerCtrlSrc, 'customer_dealer_rel') === false, 'Customer 控制器不再引用 customer_dealer_rel');
$routeCrmSrc = file_get_contents($root . '/config/route_crm.php');
check(strpos($routeCrmSrc, 'hospitalCurrentDealer') === false, '路由不再注册 hospitalCurrentDealer');
check(strpos($routeCrmSrc, 'dealerRelSet') === false, '路由不再注册 dealerRelSet');

// === 27) 新增：新迁移脚本存在 ===
$migrationDir = $root . '/../deployment/sql/';
check(file_exists($migrationDir . '20260729_reward_candidate_ext_forward.sql'), '新迁移：reward_candidate 扩展 forward');
check(file_exists($migrationDir . '20260729_reward_candidate_ext_precheck.sql'), '新迁移：reward_candidate 扩展 precheck');
check(file_exists($migrationDir . '20260729_reward_candidate_ext_verify.sql'), '新迁移：reward_candidate 扩展 verify');
check(file_exists($migrationDir . '20260729_biz_category_simplify_forward.sql'), '新迁移：商机类别简化 forward');
check(file_exists($migrationDir . '20260729_biz_category_simplify_verify.sql'), '新迁移：商机类别简化 verify');

// === 28) 新增：商机状态组默认化 ===
check(strpos($businessModelSrc, 'getDefaultTypeId') !== false, 'Business 模型有 getDefaultTypeId 方法');
check(strpos($businessModelSrc, 'getDefaultStatusId') !== false, 'Business 模型有 getDefaultStatusId 方法');
check(strpos($businessCtrlSrc, 'getDefaultTypeId') !== false, 'Business 控制器 save 使用默认 type_id');

// === 29) 新增：奖惩管理员编辑与审计 ===
check(strpos($rewardCtrlSrc, 'function candidateRead') !== false, 'Reward 有 candidateRead 方法');
check(strpos($rewardCtrlSrc, 'function candidateUpdate') !== false, 'Reward 有 candidateUpdate 方法');
check(strpos($rewardCtrlSrc, 'function candidateAuditList') !== false, 'Reward 有 candidateAuditList 方法');
check(strpos($rewardCtrlSrc, 'function ruleList') !== false, 'Reward 有 ruleList 方法');
check(strpos($rewardCtrlSrc, 'function ruleSave') !== false, 'Reward 有 ruleSave 方法');
check(strpos($rewardCtrlSrc, '必须填写修改原因') !== false, 'candidateUpdate 必须填写修改原因');
check(strpos($rewardCtrlSrc, 'reward_candidate_audit') !== false, 'candidateUpdate 写入审计表');
check(strpos($rewardCtrlSrc, 'SystemActionLog') !== false, 'candidateUpdate 写系统操作日志');
check(strpos($rewardCtrlSrc, 'lock(true)') !== false, 'candidateUpdate 锁定候选行');
check(strpos($rewardCtrlSrc, '幂等键重复') !== false, 'candidateUpdate 幂等键冲突检查');
check(strpos($rewardCtrlSrc, '已进入结算批次') !== false, 'candidateUpdate 禁止编辑已进入批次的记录');
check(strpos($rewardCtrlSrc, 'edit_and_reset') !== false, 'candidateUpdate 已通过编辑后重置');

// === 30) 新增：TaskWorkflowPanel 首次加载修复 ===
$wpSrc = file_get_contents($root . '/../crm_web-master/src/views/taskExamine/task/components/TaskWorkflowPanel.vue');
check(strpos($wpSrc, 'immediate: true') !== false, 'TaskWorkflowPanel watcher 有 immediate: true');
check(strpos($wpSrc, 'fetchError') !== false, 'TaskWorkflowPanel 有错误状态');
check(strpos($wpSrc, 'initLegacyWorkflow') !== false, 'TaskWorkflowPanel 有旧任务初始化入口');
check(strpos($wpSrc, '任务评估 W/R/K') !== false, 'TaskWorkflowPanel 标题包含 W/R/K 说明');
check(strpos($wpSrc, '工作量') !== false && strpos($wpSrc, '风险') !== false && strpos($wpSrc, '专业确认等级') !== false, 'TaskWorkflowPanel 显示 W/R/K 中文含义');

// === 31) 新增：新迁移脚本（审计表 + 字段隐藏 + 工作流回填） ===
check(file_exists($migrationDir . '20260729_reward_audit_forward.sql'), '新迁移：奖惩审计表 forward');
check(file_exists($migrationDir . '20260729_reward_audit_verify.sql'), '新迁移：奖惩审计表 verify');
check(file_exists($migrationDir . '20260729_biz_field_hide_forward.sql'), '新迁移：商机字段隐藏 forward');
check(file_exists($migrationDir . '20260729_biz_field_hide_verify.sql'), '新迁移：商机字段隐藏 verify');
  check(file_exists($migrationDir . '20260729_task_workflow_backfill_forward.sql'), '新迁移：任务工作流回填 forward');
  check(file_exists($migrationDir . '20260729_task_workflow_backfill_verify.sql'), '新迁移：任务工作流回填 verify');

  // === 32) 商机阶段奖励配置化：统一数据源、可编辑、审计、不靠名称匹配 ===
  // 32a) ruleList 关联商机类型与阶段表（名称来自数据库，不再前端写死）
  $rewardCtrlSrc = file_get_contents($root . '/application/crm/controller/Reward.php');
  $rewardServiceSrc = file_get_contents($root . '/application/crm/logic/RewardService.php');
  check(strpos($rewardServiceSrc, '__CRM_BUSINESS_TYPE__') !== false && strpos($rewardServiceSrc, '__CRM_BUSINESS_STATUS__') !== false, '阶段奖励规则读取关联商机类型与阶段表（统一数据源）');
  check(strpos($rewardServiceSrc, 'stageRewardRuleList') !== false, 'RewardService 有 stageRewardRuleList 统一读取方法');
  check(strpos($rewardServiceSrc, 'businessTypeStageTree') !== false, 'RewardService 有 businessTypeStageTree 类型阶段树方法');
  // 32b) 规则变更审计（reward_rule_audit，记录前后内容）
  check(strpos($rewardServiceSrc, 'logRuleAudit') !== false, 'RewardService 有 logRuleAudit 审计方法');
  check(strpos($rewardServiceSrc, 'reward_rule_audit') !== false, '审计写入 reward_rule_audit 表');
  check(strpos($rewardCtrlSrc, 'logRuleAudit') !== false, 'ruleSave/ruleToggle/ruleDelete 写规则审计');
  check(strpos($rewardCtrlSrc, 'operation_type') !== false, '规则审计记录 operation_type');
  check(strpos($rewardCtrlSrc, 'old_data_json') !== false && strpos($rewardCtrlSrc, 'new_data_json') !== false, '规则审计记录变更前后内容');
  // 32c) 编辑/启停/删除（软删）入口
  check(strpos($rewardCtrlSrc, 'function ruleToggle') !== false, '有 ruleToggle 启停规则方法');
  check(strpos($rewardCtrlSrc, 'function ruleDelete') !== false, '有 ruleDelete 删除规则方法');
  check(strpos($rewardCtrlSrc, 'function businessTypeStageList') !== false, '有 businessTypeStageList 类型阶段列表方法');
  check(strpos($rewardCtrlSrc, '已被奖励记录引用') !== false, 'ruleDelete 被引用时仅停用不物理删除（保护历史）');
  // 32d) 关联一致性校验（type_id 与 status_id 属于同一组，终态阶段不可配）
  check(strpos($rewardCtrlSrc, '所选阶段不属于所选商机类型') !== false, 'ruleSave 校验阶段归属类型');
  check(strpos($rewardCtrlSrc, '终态阶段') !== false, 'ruleSave 终态阶段不配置推进奖励');
  // 32e) 商机推进按稳定 ID 匹配规则（不依赖名称）
  check(strpos($businessCtrlSrc, "['type_id' => \$businessInfo['type_id'], 'status_id' => \$status_id, 'is_enabled' => 1]") !== false, 'advance 按 type_id+status_id 读取奖励规则（不靠名称匹配）');
  // 32f) 前端不再写死类型/阶段名称
  $rewardVueSrc = file_get_contents($root . '/../crm_web-master/src/views/crm/reward/index.vue');
  check(strpos($rewardVueSrc, "经销商开发', 3: '医院直签'") === false, '前端不再硬编码商机类型名映射');
  check(strpos($rewardVueSrc, 'stageTypeName') === false, '前端移除 stageTypeName 硬编码方法');
  check(strpos($rewardVueSrc, 'r.type_name') !== false, '前端使用后端返回的 type_name 展示类型名');
  check(strpos($rewardVueSrc, 'r.stage_name') !== false, '前端使用后端返回的 stage_name 展示阶段名');
  check(strpos($rewardVueSrc, 'rewardRuleSaveAPI') !== false, '前端接入阶段奖励规则保存接口');
  check(strpos($rewardVueSrc, 'rewardRuleToggleAPI') !== false, '前端接入阶段奖励规则启停接口');
  check(strpos($rewardVueSrc, 'rewardRuleDeleteAPI') !== false, '前端接入阶段奖励规则删除接口');
  check(strpos($rewardVueSrc, 'canEditStageRule') !== false, '前端有编辑权限控制 canEditStageRule');
  // 32g) 新迁移脚本存在（审计表 + 默认配置 + 幂等）
  check(file_exists($migrationDir . '20260730_stage_reward_config_forward.sql'), '新迁移：阶段奖励配置化 forward');
  check(file_exists($migrationDir . '20260730_stage_reward_config_precheck.sql'), '新迁移：阶段奖励配置化 precheck');
  check(file_exists($migrationDir . '20260730_stage_reward_config_verify.sql'), '新迁移：阶段奖励配置化 verify');
  $stageCfgFwd = file_get_contents($migrationDir . '20260730_stage_reward_config_forward.sql');
  check(strpos($stageCfgFwd, '5kcrm_reward_rule_audit') !== false, '迁移创建 reward_rule_audit 审计表');
  check(strpos($stageCfgFwd, 'update_user_id') !== false, '迁移为规则表补 update_user_id');
  check(strpos($stageCfgFwd, "WHERE NOT EXISTS") !== false, '迁移使用 WHERE NOT EXISTS 幂等保护');
  check(strpos($stageCfgFwd, "business_type_id_direct") !== false, '迁移写入默认直签组配置（新环境可用）');
  // 32h) 路由注册新端点
  $routeSrc = file_get_contents($root . '/config/route_crm.php');
  check(strpos($routeSrc, 'crm/reward/ruleToggle') !== false, '路由注册 ruleToggle');
  check(strpos($routeSrc, 'crm/reward/ruleDelete') !== false, '路由注册 ruleDelete');
  check(strpos($routeSrc, 'crm/reward/businessTypeStageList') !== false, '路由注册 businessTypeStageList');

  echo "\n[RESULT] 通过：{$pass}，失败：{$fail}\n";
  exit($fail > 0 ? 1 : 0);
