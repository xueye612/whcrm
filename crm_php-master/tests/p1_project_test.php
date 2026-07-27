<?php
/**
 * P1 项目实施扩展纯逻辑测试（独立运行，不依赖 ThinkPHP 自动加载）
 *
 * 运行方式（PHP CLI 可用时）：
 *   php crm_php-master/tests/p1_project_test.php
 *
 * 说明：复制 ProjectService 中的纯逻辑常量与判定函数进行验证，
 * 确保项目类型、实施等级、里程碑类型/状态、验收三档、知识链接类型、
 * 完整性枚举与时间解析规则正确。
 */

// ===== 枚举（与 ProjectService 一致）=====
$TYPES       = ['自有产品', '外包项目'];
$LEVELS      = ['一级', '二级', '三级', '四级'];
$MS_TYPES    = ['需求确认', '开发完成', '测试通过', '上线交付'];
$MS_STATUS   = ['未开始', '进行中', '已完成', '已延期'];
$ACC_RESULTS = ['完成良好', '基本完成', '需要改进'];
$KNOWLEDGE   = ['目录', '接口', '业务规则', '开发变更', '上线模块', '使用指导'];
$COMPLETENESS= ['完整', '待补充', '缺失'];

function isValidType($v)       { global $TYPES; return in_array($v, $TYPES, true); }
function isValidLevel($v)      { global $LEVELS; return in_array($v, $LEVELS, true); }
function isValidMsType($v)     { global $MS_TYPES; return in_array($v, $MS_TYPES, true); }
function isValidMsStatus($v)   { global $MS_STATUS; return in_array($v, $MS_STATUS, true); }
function isValidAccResult($v)  { global $ACC_RESULTS; return in_array($v, $ACC_RESULTS, true); }
function isValidKnowledge($v)  { global $KNOWLEDGE; return in_array($v, $KNOWLEDGE, true); }
function isValidCompleteness($v){ global $COMPLETENESS; return in_array($v, $COMPLETENESS, true); }

function parseTime($v) {
    if ($v === '' || $v === null) return 0;
    if (is_numeric($v)) return (int)$v;
    $ts = strtotime($v);
    return $ts === false ? 0 : $ts;
}

// canAccept 纯逻辑：给定已完成里程碑数，判断是否可验收
function canAccept($doneCount) { return $doneCount > 0; }

$pass = 0;
function check($cond, $msg) {
    global $pass;
    if (!$cond) { fwrite(STDERR, "FAIL: " . $msg . "\n"); exit(1); }
    $pass++;
}

// ===== 项目类型边界 =====
check(isValidType('自有产品'), '自有产品应合法');
check(isValidType('外包项目'), '外包项目应合法');
check(!isValidType('其他'), '其他类型不应合法');
check(!isValidType(''), '空类型不合法');

// ===== 实施等级（四档）=====
check(isValidLevel('一级') && isValidLevel('四级'), '一级/四级应合法');
check(!isValidLevel('五级'), '五级不应合法');
check(!isValidLevel(''), '空等级不合法');

// ===== 里程碑类型（四类）=====
check(isValidMsType('需求确认'), '需求确认应合法');
check(isValidMsType('上线交付'), '上线交付应合法');
check(!isValidMsType('验收'), '“验收”不属于四类里程碑');
check(count($MS_TYPES) === 4, '里程碑必须四类');

// ===== 里程碑状态 =====
check(isValidMsStatus('未开始') && isValidMsStatus('已完成'), '未开始/已完成应合法');
check(!isValidMsStatus('待发布'), '任务工作流状态不应混入里程碑');

// ===== 验收三档 =====
check(count($ACC_RESULTS) === 3, '验收结果必须三档');
check(isValidAccResult('完成良好'), '完成良好应合法');
check(isValidAccResult('需要改进'), '需要改进应合法');
check(!isValidAccResult('优秀'), '“优秀”不属于三档');

// ===== 知识链接六类 =====
check(count($KNOWLEDGE) === 6, '知识链接必须六类');
check(isValidKnowledge('开发变更') && isValidKnowledge('使用指导'), '开发变更/使用指导应合法');
check(!isValidKnowledge('飞书'), '“飞书”不属于受控六类');

// ===== 完整性三态 =====
check(isValidCompleteness('完整') && isValidCompleteness('缺失'), '完整/缺失应合法');
check(!isValidCompleteness('已审核'), '“已审核”不属于完整性三态');

// ===== 时间解析 =====
check(parseTime('') === 0, '空时间应为0');
check(parseTime(null) === 0, 'null时间应为0');
check(parseTime(0) === 0, '0应保持0');
check(parseTime(1700000000) === 1700000000, '数字时间戳原样返回');
check(parseTime('2026-07-26') === strtotime('2026-07-26'), '日期字符串应解析为时间戳');
check(parseTime('not-a-date') === 0, '非法日期应返回0');

// ===== 验收前置门禁 =====
check(canAccept(0) === false, '无已完成里程碑不可验收');
check(canAccept(1) === true, '至少一条已完成里程碑方可验收');
check(canAccept(4) === true, '全部里程碑完成可验收');

fwrite(STDOUT, "P1 project implementation test passed (" . $pass . " assertions)\n");
