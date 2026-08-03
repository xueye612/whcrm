<?php
/**
 * 项目实施—绩效归集 链路 后端测试（独立运行，直接 require 真实 ProjectService）。
 *
 * 运行方式（PHP CLI 可用时）：
 *   php crm_php-master/tests/project_performance_link_test.php
 *
 * 说明：
 *  - 直接调用生产实现的纯校验方法（periodDays / milestonePerformanceStatus /
 *    contributionPerformanceStatus），不复制实现。
 *  - 涉及数据库/并发/事实写入（syncMilestone/syncContribution/autoAggregate）的用例
 *    需 ThinkPHP + MySQL，放入 CI 集成测试（见文末）。
 */

require_once __DIR__ . '/../application/work/logic/ProjectService.php';
require_once __DIR__ . '/../application/crm/logic/PerformanceService.php';

use app\work\logic\ProjectService as Svc;
use app\crm\logic\PerformanceService as PerfSvc;

$pass = 0;
function check($cond, $msg) {
    global $pass;
    if (!$cond) { fwrite(STDERR, "FAIL: " . $msg . "\n"); exit(1); }
    $pass++;
}

// ===== 周期天数（包含首尾）=====
check(Svc::periodDays(strtotime('2026-01-01'), strtotime('2026-01-01')) === 1, '同一天周期 1 天');
check(Svc::periodDays(strtotime('2026-01-01'), strtotime('2026-01-05')) === 5, '1~5 号周期 5 天（含首尾）');
check(Svc::periodDays(0, strtotime('2026-01-05')) === 0, '缺开始 -> 0');
check(Svc::periodDays(strtotime('2026-01-05'), strtotime('2026-01-01')) === 0, '倒序 -> 0');

// ===== 里程碑绩效状态映射（纯逻辑）=====
// 未完成 -> 不计入
$r = Svc::milestonePerformanceStatus(['status' => '进行中', 'responsible_user_id' => 10, 'actual_time' => 1700000000], true);
check($r['status'] === Svc::PERF_EXCLUDED && $r['reason'] === '里程碑未完成', '未完成 -> 不计入（未完成）');
// 未指定负责人 -> 不计入
$r = Svc::milestonePerformanceStatus(['status' => '已完成', 'responsible_user_id' => 0, 'actual_time' => 1700000000], true);
check($r['status'] === Svc::PERF_EXCLUDED && $r['reason'] === '未指定负责人', '未指定负责人 -> 不计入');
// 负责人非项目成员 -> 不计入
$r = Svc::milestonePerformanceStatus(['status' => '已完成', 'responsible_user_id' => 10, 'actual_time' => 1700000000], false);
check($r['status'] === Svc::PERF_EXCLUDED && $r['reason'] === '负责人不是当前项目成员', '负责人非成员 -> 不计入');
// 实际时间不合法 -> 不计入
$r = Svc::milestonePerformanceStatus(['status' => '已完成', 'responsible_user_id' => 10, 'actual_time' => 0], true);
check($r['status'] === Svc::PERF_EXCLUDED && strpos($r['reason'], '实际时间') !== false, '实际时间不合法 -> 不计入');
// 条件满足但无事实 -> 待归集
$r = Svc::milestonePerformanceStatus(['status' => '已完成', 'responsible_user_id' => 10, 'actual_time' => 1700000000], true, null);
check($r['status'] === Svc::PERF_PENDING_COLLECT, '条件满足无事实 -> 待归集');
// 事实待审核 -> 待审核
$r = Svc::milestonePerformanceStatus(['status' => '已完成', 'responsible_user_id' => 10, 'actual_time' => 1700000000], true, '待审核', 77);
check($r['status'] === Svc::PERF_PENDING_REVIEW && $r['fact_id'] === 77, '事实待审核 -> 待审核（带 fact_id）');
// 事实已通过 -> 已通过
$r = Svc::milestonePerformanceStatus(['status' => '已完成', 'responsible_user_id' => 10, 'actual_time' => 1700000000], true, '已通过');
check($r['status'] === Svc::PERF_APPROVED, '事实已通过 -> 已通过');
// 事实已驳回 -> 已驳回
$r = Svc::milestonePerformanceStatus(['status' => '已完成', 'responsible_user_id' => 10, 'actual_time' => 1700000000], true, '已驳回');
check($r['status'] === Svc::PERF_REJECTED, '事实已驳回 -> 已驳回');

// ===== 贡献记录绩效状态映射（纯逻辑）=====
// 草稿 -> 不计入
$r = Svc::contributionPerformanceStatus(['status' => '草稿', 'user_id' => 10], true);
check($r['status'] === Svc::PERF_EXCLUDED, '草稿贡献 -> 不计入');
// 已作废 -> 不计入
$r = Svc::contributionPerformanceStatus(['status' => '已作废', 'user_id' => 10], true);
check($r['status'] === Svc::PERF_EXCLUDED, '已作废贡献 -> 不计入');
// 已确认且成员、无事实 -> 待归集
$validContribution = ['status' => '已确认', 'user_id' => 10, 'start_time' => strtotime('2026-01-01'), 'end_time' => strtotime('2026-01-05'), 'on_site_days' => 5, 'evidence_note' => ''];
$r = Svc::contributionPerformanceStatus($validContribution, true, null);
check($r['status'] === Svc::PERF_PENDING_COLLECT, '已确认无事实 -> 待归集');
// 已确认但贡献人非成员 -> 不计入
$r = Svc::contributionPerformanceStatus($validContribution, false);
check($r['status'] === Svc::PERF_EXCLUDED && strpos($r['reason'], '贡献人不是当前项目成员') !== false, '已确认但非成员 -> 不计入');
// 已确认、事实待审核 -> 待审核
$r = Svc::contributionPerformanceStatus($validContribution, true, '待审核', 88);
check($r['status'] === Svc::PERF_PENDING_REVIEW && $r['fact_id'] === 88, '已确认事实待审核 -> 待审核');
$invalidHistoric = $validContribution;
$invalidHistoric['on_site_days'] = 0;
$r = Svc::contributionPerformanceStatus($invalidHistoric, true);
check($r['status'] === Svc::PERF_EXCLUDED && strpos($r['reason'], '历史数据不完整') !== false, '历史确认贡献日期或人日非法 -> 不计入');
$overPeriod = $validContribution;
$overPeriod['on_site_days'] = 6;
$r = Svc::contributionPerformanceStatus($overPeriod, true);
check($r['status'] === Svc::PERF_EXCLUDED && strpos($r['reason'], '未填写说明') !== false, '超周期人日无说明 -> 不计入');
$overPeriod['evidence_note'] = '含周末加班投入';
$r = Svc::contributionPerformanceStatus($overPeriod, true);
check($r['status'] === Svc::PERF_PENDING_COLLECT, '超周期人日有说明 -> 可归集');

// ===== 手工事实幂等（直接调用生产实现）=====
$manualId1 = PerfSvc::manualSourceId(10, 'network-retry-key');
$manualId2 = PerfSvc::manualSourceId(10, 'network-retry-key');
check($manualId1 === $manualId2, '同一幂等键跨时间重试生成稳定 source_id');
$manualPayload = ['user_id'=>10, 'period'=>'2026Q1', 'dimension'=>'task', 'direction'=>'正向', 'fact_type'=>'manual', 'title'=>'同秒提交', 'occurred_time'=>1700000000, 'evidence'=>'e'];
check(PerfSvc::sameManualPayload($manualPayload, $manualPayload), '相同 key 相同载荷视为网络重试');
$changedPayload = $manualPayload;
$changedPayload['title'] = '同 key 不同内容';
check(!PerfSvc::sameManualPayload($manualPayload, $changedPayload), '相同 key 不同载荷识别为幂等冲突');

echo "project_performance_link_test passed ($pass assertions)\n";

/*
 * CI 集成测试（需 ThinkPHP + MySQL；本机无 PHP CLI，未执行）：
 *   - 已完成里程碑生成一条待审核事实（source=project_milestone, source_id=milestone:{id}, user=负责人）
 *   - 同一里程碑重复保存不重复生成事实（幂等）
 *   - 里程碑负责人变更时待审核事实同步
 *   - 已通过事实对应源记录关键字段禁止直接修改（syncMilestone 返回 conflict）
 *   - 贡献草稿不归集、已确认归集（source=project_contribution）
 *   - 精确重复贡献由 findDuplicateContribution 前置拦截 + 唯一键并发保护
 *   - 自动归集重复执行幂等（aggregateForWork 返回 inserted/updated/skipped）
 *   - occurred_time：贡献优先 confirm_time，无则 end_time
 */
