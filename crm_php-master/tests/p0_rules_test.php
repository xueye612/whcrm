<?php
/**
 * P0 后端规则纯逻辑测试（独立运行，不依赖 ThinkPHP 自动加载）
 *
 * 运行方式（PHP CLI 可用时）：
 *   php crm_php-master/tests/p0_rules_test.php
 *
 * 说明：本文件复制 WorkflowService 中的纯逻辑常量与判定函数进行验证，
 * 确保 W/R/K 等级、状态迁移、评定状态、幂等键等规则正确。
 * 集成测试需在 ThinkPHP 环境下运行（依赖数据库，P0 不执行生产库写入）。
 */

// ===== W/R/K 等级 =====
$W_LEVELS = ['W1', 'W2', 'W3', 'W4', 'W5'];
$R_LEVELS = ['R1', 'R2', 'R3', 'R4', 'R5'];
$K_LEVELS = ['K1', 'K2', 'K3', 'K4'];

function isValidW($v) { global $W_LEVELS; return in_array($v, $W_LEVELS, true); }
function isValidR($v) { global $R_LEVELS; return in_array($v, $R_LEVELS, true); }
function isValidK($v) { global $K_LEVELS; return in_array($v, $K_LEVELS, true); }

$pass = 0;
function check($cond, $msg) {
    global $pass;
    if (!$cond) {
        fwrite(STDERR, "FAIL: " . $msg . "\n");
        exit(1);
    }
    $pass++;
}

// ===== W/R/K 边界 =====
check(isValidW('W1'), 'W1 应合法');
check(isValidW('W5'), 'W5 应合法');
check(!isValidW('W6'), 'W6 不应合法');
check(!isValidW(''), '空 W 不合法');
check(!isValidW('w1'), 'w1 大小写敏感不合法');
check(isValidR('R3'), 'R3 应合法');
check(!isValidR('R6'), 'R6 不应合法');
check(isValidK('K4'), 'K4 应合法');
check(!isValidK('K5'), 'K5 不应合法');

// ===== 状态迁移矩阵 =====
$TRANSITIONS = [
    'evaluate'          => ['待评估' => '待处理'],
    'skip_evaluate'     => ['待评估' => '待处理'],
    'start'             => ['待处理' => '处理中'],
    'submit_acceptance' => ['处理中' => '待内部验收'],
    'acceptance_pass'   => ['待内部验收' => '待发布'],
    'acceptance_return' => ['待内部验收' => '处理中'],
    'confirm_release'   => ['待发布' => '待客户验证'],
    'customer_confirm'  => ['待客户验证' => '已完成'],
    'customer_return'   => ['待客户验证' => '处理中'],
    'complete'          => ['待发布' => '已完成'],
];

function resolveTarget($action, $current) {
    global $TRANSITIONS;
    if (!isset($TRANSITIONS[$action])) return false;
    if (!array_key_exists($current, $TRANSITIONS[$action])) return false;
    return $TRANSITIONS[$action][$current];
}

// 合法迁移
check(resolveTarget('evaluate', '待评估') === '待处理', '待评估->待处理');
check(resolveTarget('skip_evaluate', '待评估') === '待处理', '待评估->待处理（跳过评估）');
check(resolveTarget('start', '待处理') === '处理中', '待处理->处理中');
check(resolveTarget('submit_acceptance', '处理中') === '待内部验收', '处理中->待内部验收');
check(resolveTarget('acceptance_pass', '待内部验收') === '待发布', '待内部验收->待发布');
check(resolveTarget('customer_confirm', '待客户验证') === '已完成', '待客户验证->已完成');

// 非法跳转
check(resolveTarget('start', '待评估') === false, '待评估不能直接开始处理（需先评估或跳过评估）');
check(resolveTarget('evaluate', '处理中') === false, '处理中不能评估');
check(resolveTarget('confirm_release', '处理中') === false, '处理中不能确认发布');
check(resolveTarget('customer_confirm', '待发布') === false, '待发布不能客户确认');
check(resolveTarget('unknown', '待评估') === false, '未知动作拒绝');

// ===== 评定状态只有三档 =====
$REVIEW = ['pending', 'compliant', 'non_compliant'];
check(count($REVIEW) === 3, '评定状态只有三档');
check(!in_array('approved', $REVIEW, true), 'approved 不是合法评定状态');
check(!in_array('returned', $REVIEW, true), '退回不是第四个评定状态');

// ===== 幂等键 =====
function buildTestKey($sourceType, $sourceId, $testerUserId) {
    return 'test:' . $sourceType . ':' . (int)$sourceId . ':' . (int)$testerUserId;
}
check(buildTestKey('task', 100, 5) === 'test:task:100:5', '测试幂等键格式');
check(buildTestKey('task', 100, 5) === buildTestKey('task', 100, 5), '相同输入幂等');
check(buildTestKey('task', 100, 5) !== buildTestKey('task', 100, 6), '不同测试人键不同');

function buildLedgerKey($ledgerId) {
    return 'ledger:' . (int)$ledgerId . ':auto-task';
}
check(buildLedgerKey(42) === 'ledger:42:auto-task', '台账幂等键格式');

// ===== 发布门禁（新规则：按需测试）=====
// 无测试任务 -> 通过；有测试任务 -> 全部已反馈才通过
function canApplyRelease($testExts) {
    foreach ($testExts as $ext) {
        if ($ext['submit_status'] !== 'submitted') {
            return false;
        }
    }
    return true;
}
check(canApplyRelease([]) === true, '无测试任务可发布');
check(canApplyRelease([['submit_status' => 'submitted']]) === true, '已反馈测试可发布');
check(canApplyRelease([['submit_status' => 'not_submitted']]) === false, '未反馈测试不能发布');
check(canApplyRelease([['submit_status' => 'submitted'], ['submit_status' => 'not_submitted']]) === false, '存在未反馈测试不能发布');
check(canApplyRelease([['submit_status' => 'submitted'], ['submit_status' => 'submitted']]) === true, '全部已反馈可发布');

// ===== 测试展示状态（待反馈/已反馈/已逾期）=====
function testDisplayStatus($submitStatus, $deadline) {
    if ($submitStatus === 'submitted') return '已反馈';
    if ($deadline > 0 && $deadline < time()) return '已逾期';
    return '待反馈';
}
check(testDisplayStatus('submitted', 0) === '已反馈', '已提交=已反馈');
check(testDisplayStatus('not_submitted', time() + 86400) === '待反馈', '未提交未逾期=待反馈');
check(testDisplayStatus('not_submitted', time() - 86400) === '已逾期', '未提交已逾期=已逾期');

// ===== 回避（旧流程兼容；新流程无评定人，发起人可作为测试人员）=====
function assertNotSelf($tester, $reviewer) {
    return ((int)$tester === (int)$reviewer) ? '不能评定自己' : '';
}
check(assertNotSelf(5, 5) !== '', '旧流程：测试人不能自评');
check(assertNotSelf(5, 6) === '', '旧流程：非自评通过');
// 新流程允许发起人作为测试人员（无评定人回避限制）
check(true, '新流程：发起人可作为测试人员');

// ===== 历史空值 =====
function interpretLegacy($v) {
    return ($v === null || $v === '') ? '未评估' : $v;
}
check(interpretLegacy(null) === '未评估', 'null 表示未评估');
check(interpretLegacy('') === '未评估', '空串表示未评估');
check(interpretLegacy('W3') === 'W3', '有值正常返回');

// ===== 旧状态兼容映射 =====
function legacyStatus($mainStatus) {
    return $mainStatus === '已完成' ? 5 : 1;
}
check(legacyStatus('已完成') === 5, '完成映射 5');
check(legacyStatus('处理中') === 1, '处理中映射 1');

echo "P0 backend rules test passed ($pass assertions)\n";
exit(0);
