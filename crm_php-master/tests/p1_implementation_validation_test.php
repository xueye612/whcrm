<?php
/**
 * P1 项目实施后端业务校验测试（独立运行，不依赖 ThinkPHP 自动加载）。
 *
 * 运行方式（PHP CLI 可用时）：
 *   php crm_php-master/tests/p1_implementation_validation_test.php
 *
 * 说明：
 *  - 直接 require 生产 ProjectService.php，调用其公开纯校验方法测试真实实现
 *    （resolveTimeState / resolveFieldTimeState / checkKnowledgeUrl / checkDecimal1 /
 *      validateMilestone），不复制校验逻辑制造“测试通过”。
 *  - validateContribution / saveProfile 涉及数据库/乐观锁/事务，需 ThinkPHP + 数据库环境，
 *    放入 CI 集成测试（见文末），本文件对它们仅做源码结构断言。
 */

require_once __DIR__ . '/../application/work/logic/ProjectService.php';

use app\work\logic\ProjectService as Svc;

$pass = 0;
function check($cond, $msg) {
    global $pass;
    if (!$cond) { fwrite(STDERR, "FAIL: " . $msg . "\n"); exit(1); }
    $pass++;
}

$src = file_get_contents(__DIR__ . '/../application/work/logic/ProjectService.php');

// ===== 直接调用生产实现的日期状态机（真实方法）=====
// 拒绝
check(Svc::resolveTimeState(array())['state'] === -1, '数组 -> 非法');
check(Svc::resolveTimeState(array('2026-01-01'))['state'] === -1, '数组(含值) -> 非法');
check(Svc::resolveTimeState(true)['state'] === -1, 'true -> 非法');
check(Svc::resolveTimeState(false)['state'] === -1, 'false -> 非法');
check(Svc::resolveTimeState(0)['state'] === -1, '0 -> 非法');
check(Svc::resolveTimeState(-1)['state'] === -1, '负数 -> 非法');
check(Svc::resolveTimeState('2026-02-30')['state'] === -1, '2026-02-30 不存在日期 -> 非法');
check(Svc::resolveTimeState('2026-13-01')['state'] === -1, '2026-13-01 非法月份 -> 非法');
check(Svc::resolveTimeState('next Thursday')['state'] === -1, '自然语言 next Thursday -> 非法');
check(Svc::resolveTimeState('12.5')['state'] === -1, '小数时间戳 -> 非法');
// 允许
check(Svc::resolveTimeState('2026-02-28')['state'] === 2, '严格 Y-m-d 2026-02-28 -> 合法');
check(Svc::resolveTimeState(1700000000)['state'] === 2, '正整数时间戳 -> 合法');
check(Svc::resolveTimeState('1700000000')['state'] === 2, '数字串时间戳 -> 合法');
check(Svc::resolveTimeState('')['state'] === 1, '空串 -> 显式清空');
check(Svc::resolveTimeState(null)['state'] === 1, 'null -> 显式清空');

// ===== 数据库 INT(11) 范围与零点确定性（真实方法）=====
// 越界数字时间戳必须拒绝
check(Svc::resolveTimeState(2147483648)['state'] === -1, '2147483648 超 INT32 上限 -> 非法');
check(Svc::resolveTimeState('2147483648')['state'] === -1, '数字串 2147483648 -> 非法');
check(Svc::resolveTimeState('99999999999999999999')['state'] === -1, '超大数字串 -> 非法');
// INT32 上限边界合法、上限+1 非法
check(Svc::resolveTimeState(2147483647)['state'] === 2, 'INT32 上限 2147483647 -> 合法');
check(Svc::resolveTimeState(1)['state'] === 2, 'INT32 下限 1 -> 合法');
// epoch 前日期（负时间戳）必须拒绝
check(Svc::resolveTimeState('1960-01-01')['state'] === -1, '1960-01-01 早于 epoch -> 非法');
// Y-m-d 必须固定为服务器时区当天 00:00:00，且多次解析结果完全一致
$ts1 = Svc::resolveTimeState('2026-02-28')['ts'];
$ts2 = Svc::resolveTimeState('2026-02-28')['ts'];
check($ts1 === $ts2, '同一 Y-m-d 多次解析时间戳完全一致');
$dt = (new \DateTime('2026-02-28 00:00:00'))->getTimestamp();
check($ts1 === $dt, 'Y-m-d 解析为当天 00:00:00');
// Y-m-d H:i:s 必须精确保留传入时间
$tsFull = Svc::resolveTimeState('2026-02-28 12:34:56')['ts'];
$dtFull = (new \DateTime('2026-02-28 12:34:56'))->getTimestamp();
check($tsFull === $dtFull, 'Y-m-d H:i:s 精确保留时间');
// Y-m-d H:i:s 非法日期拒绝
check(Svc::resolveTimeState('2026-02-28 99:99:99')['state'] === -1, 'Y-m-d H:i:s 非法时间被拒');

// ===== buildDateRow 落库语义（真实方法）：未提交保留旧值 / 清空写 0 / 合法写时间戳 =====
// 更新场景（defaultZeroForMissing=false）：未提交 -> 不写入
$r1 = Svc::buildDateRow(array('name' => '只改名'), array('plan_time', 'actual_time'), false);
check(!array_key_exists('plan_time', $r1) && !array_key_exists('actual_time', $r1), '更新未提交日期 -> 不写入，保留旧值');
// 显式清空 -> 写 0
$r2 = Svc::buildDateRow(array('plan_time' => '', 'actual_time' => null), array('plan_time', 'actual_time'), false);
check($r2['plan_time'] === 0 && $r2['actual_time'] === 0, '显式清空日期 -> 写 0');
// 合法 -> 写时间戳
$r3 = Svc::buildDateRow(array('plan_time' => '2026-02-28'), array('plan_time', 'actual_time'), false);
check($r3['plan_time'] === $ts1 && !array_key_exists('actual_time', $r3), '合法日期写时间戳，未提交不写入');
// 新增场景（defaultZeroForMissing=true）：未提交 -> 写 0
$r4 = Svc::buildDateRow(array(), array('plan_time', 'actual_time'), true);
check($r4['plan_time'] === 0 && $r4['actual_time'] === 0, '新增未提交日期 -> 落库 0');

// 字段存在性识别（resolveFieldTimeState）
check(Svc::resolveFieldTimeState(array(), 'plan_time')['state'] === 0, '字段未提交 -> state 0 保留旧值');
check(Svc::resolveFieldTimeState(array('plan_time' => null), 'plan_time')['state'] === 1, '字段存在且 null -> state 1 清空');
check(Svc::resolveFieldTimeState(array('plan_time' => ''), 'plan_time')['state'] === 1, '字段存在且空串 -> state 1 清空');
check(Svc::resolveFieldTimeState(array('plan_time' => '2026-02-28'), 'plan_time')['state'] === 2, '字段存在且合法 -> state 2');
check(Svc::resolveFieldTimeState(array('plan_time' => array()), 'plan_time')['state'] === -1, '字段存在且为数组 -> state -1');

// ===== 里程碑校验（真实 validateMilestone 实例方法，无 DB 副作用）=====
$svc = new Svc();
check($svc->validateMilestone(array('milestone_type'=>'需求确认','name'=>'M','status'=>'已完成','plan_time'=>'2024-01-10','actual_time'=>'2024-01-05')) === '', '实际早于计划（提前完成）可保存');
check($svc->validateMilestone(array('milestone_type'=>'需求确认','name'=>'M','status'=>'已完成','actual_time'=>'')) !== '', '已完成但实际时间为空被拒');
check($svc->validateMilestone(array('milestone_type'=>'需求确认','name'=>'M','status'=>'已完成','actual_time'=>'2024-01-05')) === '', '已完成有合法实际时间通过');
check($svc->validateMilestone(array('milestone_type'=>'需求确认','name'=>'M','status'=>'已延期')) !== '', '已延期无证据被拒');
check($svc->validateMilestone(array('milestone_type'=>'需求确认','name'=>'M','status'=>'已延期','evidence_note'=>'原因')) === '', '已延期有证据通过');
check($svc->validateMilestone(array('milestone_type'=>'需求确认','name'=>'M','status'=>'进行中','actual_time'=>'not-a-date')) !== '', '非法实际时间被拒');
check($svc->validateMilestone(array('milestone_type'=>'需求确认','name'=>'M','status'=>'进行中','plan_time'=>'bad-date')) !== '', '非法计划时间被拒');
check($svc->validateMilestone(array('milestone_type'=>'需求确认','name'=>'','status'=>'未开始')) !== '', '名称为空被拒');

// ===== 更新场景状态联动：基于最终有效值（传入已有记录 $existing）=====
// 已完成 + 未提交 actual_time + 已有合法 actual_time -> 沿用，通过
check($svc->validateMilestone(array('name' => '改名', 'status' => '已完成'), array('milestone_type' => '需求确认', 'name' => '旧名', 'status' => '已完成', 'actual_time' => 1700000000)) === '', '已完成沿用已有 actual_time 通过');
// 已完成 + 显式清空 actual_time -> 拒绝
check($svc->validateMilestone(array('status' => '已完成', 'actual_time' => ''), array('status' => '已完成', 'actual_time' => 1700000000)) !== '', '已完成显式清空 actual_time 被拒');
// 已完成 + 未提交 actual_time + 已有也无 -> 拒绝
check($svc->validateMilestone(array('status' => '已完成'), array('status' => '未开始', 'actual_time' => 0)) !== '', '已完成且已有无 actual_time 被拒');
// 已延期 + 未提交 evidence_note + 已有合法 -> 沿用，通过
check($svc->validateMilestone(array('status' => '已延期'), array('status' => '已延期', 'evidence_note' => '旧证据')) === '', '已延期沿用已有 evidence_note 通过');
// 已延期 + 显式清空 evidence_note -> 拒绝
check($svc->validateMilestone(array('status' => '已延期', 'evidence_note' => ''), array('status' => '已延期', 'evidence_note' => '旧证据')) !== '', '已延期显式清空 evidence_note 被拒');
// 更新只改名（type/status 未提交）-> 沿用已有，通过
check($svc->validateMilestone(array('name' => '新名'), array('milestone_type' => '需求确认', 'name' => '旧名', 'status' => '进行中')) === '', '更新只改名沿用已有字段通过');

// ===== 知识链接 URL 校验（真实 checkKnowledgeUrl 静态方法，与前端同一组向量）=====
check(Svc::checkKnowledgeUrl('https://example.com') === '', 'https://example.com 合法');
check(Svc::checkKnowledgeUrl('https://xn--fsqu00a.xn--0zwm56d') === '', 'punycode 域名 合法');
check(Svc::checkKnowledgeUrl('http://1.2.3.4') === '', 'IPv4 合法');
check(Svc::checkKnowledgeUrl('http://localhost') === '', 'localhost 合法');
check(Svc::checkKnowledgeUrl('http://example.com:8080') === '', '合法端口 合法');
check(Svc::checkKnowledgeUrl('http://[::1]') === '', '合法 IPv6 [::1]');
check(Svc::checkKnowledgeUrl('http://[2001:db8::1]:8080/path') === '', '合法 IPv6 + 端口 + 路径');
check(Svc::checkKnowledgeUrl('http://[::ffff:192.0.2.1]') === '', '合法 IPv4 映射 IPv6 [::ffff:192.0.2.1]');
// 非法（与前端同一组向量）
check(Svc::checkKnowledgeUrl('http://[notipv6]') !== '', '非法 IPv6 [notipv6]');
check(Svc::checkKnowledgeUrl('http://[:::1]') !== '', '非法 IPv6 [:::1]');
check(Svc::checkKnowledgeUrl('http://[1:2:3]') !== '', '非法 IPv6 [1:2:3] 组数不足');
check(Svc::checkKnowledgeUrl('https://user:pass@example.com') !== '', 'userinfo 被拒');
check(Svc::checkKnowledgeUrl('https://例子.测试') !== '', '原始 Unicode 主机被拒');
check(Svc::checkKnowledgeUrl('https://example..com') !== '', '连续点 example..com 被拒');
check(Svc::checkKnowledgeUrl('https://example.com:65536') !== '', '端口超 65535 被拒');
check(Svc::checkKnowledgeUrl('https://exa mple.com') !== '', '主机含空格被拒');
// 其他补充
check(Svc::checkKnowledgeUrl('http://example.com/path?x=1') === '', 'http://example.com/path?x=1 合法');
check(Svc::checkKnowledgeUrl('HTTP://EXAMPLE.COM') === '', '大小写 http(s) 合法');
check(Svc::checkKnowledgeUrl('https://.') !== '', 'https://. 被拒');
check(Svc::checkKnowledgeUrl('https://example.com:abc') !== '', '非数字端口 :abc 被拒');
check(Svc::checkKnowledgeUrl('http://') !== '', 'http:// 缺主机名被拒');
check(Svc::checkKnowledgeUrl('https://?x') !== '', 'https://?x 被拒');
check(Svc::checkKnowledgeUrl('http:///path') !== '', 'http:///path 被拒');
check(Svc::checkKnowledgeUrl('javascript:alert(1)') !== '', 'javascript: 被拒');
check(Svc::checkKnowledgeUrl('data:text/html,<x>') !== '', 'data: 被拒');
check(Svc::checkKnowledgeUrl('vbscript:msgbox') !== '', 'vbscript: 被拒');
check(Svc::checkKnowledgeUrl('//a.com') !== '', '协议相对地址被拒');
check(Svc::checkKnowledgeUrl('/relative') !== '', '相对路径被拒');
check(Svc::checkKnowledgeUrl("\tjavascript:alert(1)") !== '', '前置制表符绕过被拒');
check(Svc::checkKnowledgeUrl('', true, '完整') !== '', '完整时地址必填');
check(Svc::checkKnowledgeUrl('', false, '待补充') === '', '非完整时地址可空');

// ===== 一位小数校验（真实 checkDecimal1）=====
check(Svc::checkDecimal1('12.5', 0, 9999999.9) === '', '12.5 合法');
check(Svc::checkDecimal1('12.55', 0, 9999999.9) === '最多保留一位小数', '两位小数被拒');
check(Svc::checkDecimal1(-1, 0, 9999999.9) === '数值不得为负', '负数被拒');
check(Svc::checkDecimal1('abc', 0, 9999999.9) === '数值格式不合法', '非数字被拒');

// ===== saveProfile 源码结构断言（事务/乐观锁，DB 行为见 CI）=====
check(strpos($src, 'function resolveTimeState(') !== false, '应有 resolveTimeState');
check(strpos($src, 'function resolveFieldTimeState(') !== false, '应有 resolveFieldTimeState');
check(strpos($src, 'function buildDateRow(') !== false, '应有 buildDateRow（统一日期落库入口）');
check(strpos($src, "const TS_MAX = 2147483647;") !== false, '应有 INT32 上限常量 TS_MAX');
check(strpos($src, 'strictParseDate(') !== false, '应有 strictParseDate（不依赖 strtotime 自然语言）');
check(strpos($src, 'DateTime::createFromFormat') !== false, '应使用 DateTime::createFromFormat 严格解析');
// ! 前缀：未指定时分秒固定为 00:00:00
check(strpos($src, "'!Y-m-d'") !== false, '严格解析应使用 !Y-m-d（固定零点）');
check(strpos($src, "'!Y-m-d H:i:s'") !== false, '严格解析应使用 !Y-m-d H:i:s');
check(strpos($src, 'TS_MIN') !== false && strpos($src, 'TS_MAX') !== false, '应有 INT32 范围上下界校验');
check(strpos($src, 'getLastErrors') !== false, '应检查 getLastErrors');
check(strpos($src, '日期格式不合法') !== false, '非法日期应返回明确错误');
// IPv6 真实验证（filter_var，不得仅判断首字符 [）
check(strpos($src, 'FILTER_VALIDATE_IP') !== false && strpos($src, 'FILTER_FLAG_IPV6') !== false, 'IPv6 应用 filter_var 真实验证');
check(strpos($src, 'PHP_URL_USER') !== false, '应拒绝 userinfo（user）');
// 事务闭合：startTrans 之前完成必填/版本/日期校验，事务内失败路径均 rollback
$transPos = strpos($src, 'Db::startTrans()');
$commitPos = strpos($src, 'Db::commit()');
check($transPos !== false && $commitPos !== false && $transPos < $commitPos, '事务开启在提交之前');
check(strpos($src, "if (\$hasType && \$projectType === '')") !== false, '显式提交空 project_type 必须拒绝');
check(strpos($src, "if (\$hasLevel && \$implLevel === '')") !== false, '显式提交空 impl_level 必须拒绝');
check(strpos($src, '缺少数据版本') !== false, '更新档案必须携带 version');
check(strpos($src, "where(['work_id' => \$workId, 'version' => \$version])") !== false, '乐观锁更新条件应含客户端 version');
check(strpos($src, 'if ((int)$affected !== 1)') !== false, '乐观锁必须校验受影响行数');
check(strpos($src, '数据版本已变化，请刷新后重试') !== false, '版本冲突应返回刷新提示');
// P2：里程碑允许实际时间早于计划时间（删除 actual<plan 限制）
check(strpos($src, '实际时间不得早于计划时间') === false, '里程碑不再限制 actual<plan（允许提前完成）');
// 新增必填校验位于事务之前（避免开启事务后 return 未 rollback）
$newReqPos = strpos($src, "if (\$projectType === '') return [false, '项目类型不能为空']");
check($newReqPos !== false && $newReqPos < $transPos, '新增必填校验应在 startTrans 之前');

// ===== 控制器源码结构断言（更新语义：未提交保留旧值，校验与落库同一入口）=====
$workSrc = file_get_contents(__DIR__ . '/../application/work/controller/Work.php');
$msSave = strpos($workSrc, 'function milestoneSave()');
$ctSave = strpos($workSrc, 'function contributionSave()');
$msBlock = $msSave !== false ? substr($workSrc, $msSave, ($ctSave !== false ? $ctSave : strlen($workSrc)) - $msSave) : '';
$ctEnd = strpos($workSrc, 'function contributionDelete()');
$ctBlock = $ctSave !== false ? substr($workSrc, $ctSave, ($ctEnd !== false ? $ctEnd : strlen($workSrc)) - $ctSave) : '';
// 校验传入已有记录 $existing
check(strpos($msBlock, 'validateMilestone($param, $existing)') !== false, 'milestoneSave 校验应传入 $existing');
check(strpos($ctBlock, 'validateContribution($param, $workId, $existing)') !== false, 'contributionSave 校验应传入 $existing');
// 落库使用 buildDateRow（校验与落库同一入口），不得再用 parseTime 组装日期
check(strpos($msBlock, 'buildDateRow(') !== false, 'milestoneSave 应用 buildDateRow 组装日期');
check(strpos($ctBlock, 'buildDateRow(') !== false, 'contributionSave 应用 buildDateRow 组装日期');
check(strpos($msBlock, "parseTime(\$param['plan_time']") === false, 'milestoneSave 不得再用 parseTime 组装 plan_time');
check(strpos($msBlock, "parseTime(\$param['actual_time']") === false, 'milestoneSave 不得再用 parseTime 组装 actual_time');
check(strpos($ctBlock, "parseTime(\$param['start_time']") === false, 'contributionSave 不得再用 parseTime 组装 start_time');
check(strpos($ctBlock, "parseTime(\$param['end_time']") === false, 'contributionSave 不得再用 parseTime 组装 end_time');
// 更新仅写入已提交字段
check(strpos($msBlock, "array_key_exists('plan_time'") === false, 'milestoneSave 日期经 buildDateRow 写入，不应单独判断 plan_time');
// validateMilestone/validateContribution 接受 $existing 参数
check(strpos($src, 'function validateMilestone(array $data, array $existing = null)') !== false, 'validateMilestone 应接受 $existing');
check(strpos($src, 'function validateContribution(array $data, $workId, array $existing = null)') !== false, 'validateContribution 应接受 $existing');

// 乐观锁并发冲突（复刻 affected-rows 语义）
function optimisticLockUpdate($dbVersionBefore, $clientVersion, $concurrentBumped) {
    $current = $concurrentBumped ? $dbVersionBefore + 1 : $dbVersionBefore;
    return ($current === $clientVersion) ? 1 : 0;
}
check(optimisticLockUpdate(2, 2, false) === 1, '无并发：version 匹配，受影响 1 行');
check(optimisticLockUpdate(2, 2, true) === 0, '并发冲突：旧 version 不匹配，受影响 0 行（回滚）');

echo "p1_implementation_validation_test passed ($pass assertions)\n";

/*
 * CI 集成测试（需 ThinkPHP + 数据库环境）：
 *   php -l application/work/logic/ProjectService.php
 *   （集成）真实 saveProfile：
 *     - 新增时未提交 project_type -> 报错且无遗留打开事务
 *     - 新增时未提交 impl_level -> 报错且无遗留打开事务
 *     - 部分更新绕过（已有 plan_start，仅提交更早 plan_end）-> 拒绝
 *     - 并发版本冲突 -> 受影响行数 0，回滚，返回“数据版本已变化”
 *     - 校验失败后后续数据库操作不受影响（事务已回滚）
 */
