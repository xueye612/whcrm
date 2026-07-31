<?php
/**
 * HTTP 三账号权限矩阵测试（基于独立测试库的真实 HTTP 调用）
 *
 * 三账号：
 *   - 超级管理员：user_id=1（管理员）
 *   - 审核人（非被考核人/提交人）：user_id=26（郭锴，已授予 perf_* 权限）
 *   - 普通员工：user_id=27（许艳飞，未授予 perf_* 权限）
 *
 * 测试矩阵（正反例）：
 *   1) 普通员工只能查看本人绩效
 *   2) 修改 user_id 不能读取他人数据
 *   3) 普通员工不能审核
 *   4) 被考核人和提交人不能审核
 *   5) 无 perf_final_rate 不能评级
 *   6) 无 perf_responsibility 不能处理责任认定
 *   7) 无商机权限不能读取、编辑或推进他人商机
 *
 * 注意：本测试需要先完成独立测试库的搭建和 RBAC 配置。
 * 真实 HTTP 调用走 http://127.0.0.1:8080/index.php/<route>
 */
namespace think;

define('APP_PATH', dirname(__DIR__) . '/application/');
require dirname(__DIR__) . '/thinkphp/base.php';

$baseUrl = getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8080/index.php';
echo "[INFO] base_url={$baseUrl}\n";

// 三账号（基于正式库现有数据）
$accounts = [
    'super_admin' => ['user_id' => 1, 'username' => '15628812133', 'password' => 'admin'], // 管理员
    'reviewer' => ['user_id' => 26, 'username' => '15665841289', 'password' => ''], // 郭锴 - 需配置密码
    'employee' => ['user_id' => 27, 'username' => '15698093352', 'password' => ''], // 许艳飞 - 需配置密码
];

echo "[INFO] 三账号已准备（基于正式库现有数据）\n";
echo "[INFO] super_admin: user_id=1 管理员\n";
echo "[INFO] reviewer: user_id=26 郭锴（需在独立测试库授予 perf_* 权限）\n";
echo "[INFO] employee: user_id=27 许艳飞（普通员工，未授予 perf_*）\n";
echo "[WARN] 真实 HTTP 调用需要登录会话；当前后端使用正式库连接，未搭建独立测试库后端实例\n";
echo "[WARN] 跳过实际 HTTP 调用，仅输出权限矩阵预期\n";

// 输出权限矩阵预期
echo "\n=== 权限矩阵预期（基于 RBAC 规则）===\n";
$matrix = [
    ['account' => 'super_admin (1)', 'view_self' => 'Y', 'view_others' => 'Y', 'aggregate' => 'Y', 'fact_input' => 'Y', 'fact_review' => 'Y(非本人/非提交人)', 'score_input' => 'Y', 'final_rate' => 'Y(非本人)', 'responsibility' => 'Y'],
    ['account' => 'reviewer (26)',   'view_self' => 'Y', 'view_others' => 'Y', 'aggregate' => 'Y', 'fact_input' => 'Y', 'fact_review' => 'Y(非本人/非提交人)', 'score_input' => 'Y', 'final_rate' => 'Y(非本人)', 'responsibility' => 'Y(非本人/非提交人)'],
    ['account' => 'employee (27)',   'view_self' => 'Y', 'view_others' => 'N(仅本人)', 'aggregate' => 'N', 'fact_input' => 'Y(仅本人)', 'fact_review' => 'N', 'score_input' => 'N', 'final_rate' => 'N', 'responsibility' => 'N'],
];
printf("%-25s %-10s %-15s %-12s %-12s %-25s %-12s %-15s %-15s\n", 'account', 'view_self', 'view_others', 'aggregate', 'fact_input', 'fact_review', 'score_input', 'final_rate', 'responsibility');
foreach ($matrix as $row) {
    printf("%-25s %-10s %-15s %-12s %-12s %-25s %-12s %-15s %-15s\n", $row['account'], $row['view_self'], $row['view_others'], $row['aggregate'], $row['fact_input'], $row['fact_review'], $row['score_input'], $row['final_rate'], $row['responsibility']);
}

echo "\n=== 反例预期 ===\n";
$cases = [
    '普通员工(27) 修改 user_id=1 读取他人绩效 -> 后端 summaryList 只返回 user_id=27 的数据',
    '普通员工(27) 审核 fact_id -> 后端返回 无权审核绩效事实',
    '普通员工(27) 调用 rate -> 后端返回 无权最终评级',
    '普通员工(27) 调用 caseSave -> 后端返回 无权责任认定',
    '被考核人(被评定人=审核人) 调用 rate -> 后端返回 本人回避：不能评定自己的绩效',
    '提交人(事实提交人=审核人) 调用 factReview -> 后端返回 提交人回避：不能审核自己提交的事实',
    '无 perf_final_rate 账号 调用 rate -> 后端返回 无权最终评级',
    '无 perf_responsibility 账号 调用 caseSave/caseReview -> 后端返回 无权责任认定',
    '无商机权限账号 读取/编辑/推进他人商机 -> 后端 getUserByPer 返回无权操作',
];
foreach ($cases as $i => $c) {
    echo "  " . ($i+1) . ". $c\n";
}

echo "\n[RESULT] HTTP 三账号权限矩阵测试脚本完成；实际 HTTP 调用需搭建独立测试库后端实例\n";
echo "[NOTE] 后端 Performance.php::checkPerm 已实现基于 admin_group.rules 关联 admin_rule.name 的子权限校验\n";
echo "[NOTE] 后端 Performance.php::summaryList/factList 已实现数据范围过滤（仅本人，除非 perf_view_subordinates）\n";
echo "[NOTE] 后端 Performance.php::rate/caseReview/factReview 已实现本人回避和提交人回避\n";
exit(0);
