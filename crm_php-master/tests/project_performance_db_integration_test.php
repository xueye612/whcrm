<?php
/**
 * 项目实施-绩效归集 MySQL 集成测试。
 *
 * 前置：先执行 20260803_project_performance_link_forward.sql。
 * 可选环境变量：CRM_TEST_DB_HOST/NAME/USER/PASS/PORT、CRM_TEST_WORK_ID、CRM_TEST_USER_ID。
 * 本测试只创建带唯一测试标识的里程碑、贡献、事实和审计，并在 finally 中清理。
 */
define('APP_PATH', dirname(__DIR__) . '/application/');
define('CONF_PATH', dirname(__DIR__) . '/config/');
define('RUNTIME_PATH', dirname(__DIR__) . '/runtime/');
require dirname(__DIR__) . '/thinkphp/base.php';
\think\Loader::addNamespace('app', APP_PATH);

\think\Config::set([
    'type' => 'mysql',
    'hostname' => getenv('CRM_TEST_DB_HOST') ?: '127.0.0.1',
    'database' => getenv('CRM_TEST_DB_NAME') ?: 'crm',
    'username' => getenv('CRM_TEST_DB_USER') ?: 'root',
    'password' => getenv('CRM_TEST_DB_PASS') ?: 'root',
    'hostport' => getenv('CRM_TEST_DB_PORT') ?: '3306',
    'charset' => 'utf8', 'prefix' => '5kcrm_',
], 'database');

use app\work\logic\ProjectPerformanceService;
use app\work\logic\ProjectService;
use think\Db;

$checks = 0;
function dbCheck($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}

// 子进程模式：两个独立 PHP/MySQL 连接等待同一屏障后同时归集，用于验证数据库唯一键与事务幂等。
if (isset($argv[1]) && $argv[1] === '--worker') {
    $sourceType = isset($argv[2]) ? (string)$argv[2] : '';
    $sourceId = isset($argv[3]) ? (int)$argv[3] : 0;
    $operatorId = isset($argv[4]) ? (int)$argv[4] : 0;
    $barrier = isset($argv[5]) ? (string)$argv[5] : '';
    $deadline = microtime(true) + 10;
    while (!is_file($barrier) && microtime(true) < $deadline) usleep(10000);
    if (!is_file($barrier)) {
        fwrite(STDERR, "concurrency barrier timeout\n");
        exit(2);
    }
    $workerSvc = new ProjectPerformanceService();
    $result = $sourceType === 'milestone'
        ? $workerSvc->syncMilestoneAtomic($sourceId, $operatorId)
        : $workerSvc->syncContributionAtomic($sourceId, $operatorId);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit(!empty($result['ok']) ? 0 : 1);
}

function concurrentSync($sourceType, $sourceId, $operatorId) {
    if (!function_exists('proc_open')) throw new RuntimeException('proc_open 不可用，无法执行真实双进程并发测试');
    $barrier = tempnam(sys_get_temp_dir(), 'crm_perf_barrier_');
    if ($barrier === false) throw new RuntimeException('无法创建并发测试屏障路径');
    unlink($barrier);
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
        . ' --worker ' . escapeshellarg($sourceType) . ' ' . (int)$sourceId . ' ' . (int)$operatorId
        . ' ' . escapeshellarg($barrier);
    $spec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $workers = [];
    try {
        for ($i = 0; $i < 2; $i++) {
            $pipes = [];
            $process = proc_open($command, $spec, $pipes);
            if (!is_resource($process)) throw new RuntimeException('无法启动并发测试子进程');
            $workers[] = ['process' => $process, 'pipes' => $pipes];
        }
        file_put_contents($barrier, 'go');
        $results = [];
        foreach ($workers as $worker) {
            $stdout = stream_get_contents($worker['pipes'][1]);
            $stderr = stream_get_contents($worker['pipes'][2]);
            fclose($worker['pipes'][1]);
            fclose($worker['pipes'][2]);
            $exitCode = proc_close($worker['process']);
            if ($exitCode !== 0) throw new RuntimeException('并发子进程失败：' . trim($stderr . ' ' . $stdout));
            $decoded = json_decode($stdout, true);
            if (!is_array($decoded)) throw new RuntimeException('并发子进程返回非 JSON：' . $stdout);
            $results[] = $decoded;
        }
        return $results;
    } finally {
        if (is_file($barrier)) unlink($barrier);
    }
}

$token = 'CODEX_PERF_' . str_replace('.', '', uniqid('', true));
$milestoneId = 0;
$contributionId = 0;
$createdPerfIds = [];

try {
    $workId = (int)(getenv('CRM_TEST_WORK_ID') ?: 0);
    $userId = (int)(getenv('CRM_TEST_USER_ID') ?: 0);
    if ($workId <= 0) {
        $work = Db::name('work')->where('ishidden', 0)->where('owner_user_id', '<>', '')->find();
        if (!$work) throw new RuntimeException('需要至少一个带 owner_user_id 的测试项目，或设置 CRM_TEST_WORK_ID/CRM_TEST_USER_ID');
        $workId = (int)$work['work_id'];
        $owners = array_values(array_filter(explode(',', trim((string)$work['owner_user_id'], ','))));
        $userId = (int)reset($owners);
    }
    dbCheck($workId > 0 && $userId > 0, '测试项目和成员有效');
    $secondUserId = (int)(getenv('CRM_TEST_USER_ID2') ?: 0);
    if ($secondUserId <= 0) {
        $ownerList = (string)Db::name('work')->where('work_id', $workId)->value('owner_user_id');
        foreach (array_values(array_filter(explode(',', trim($ownerList, ',')))) as $candidate) {
            if ((int)$candidate !== $userId) { $secondUserId = (int)$candidate; break; }
        }
    }
    if ($secondUserId <= 0) {
        $secondUserId = (int)Db::name('work_user')->where('work_id', $workId)->where('user_id', '<>', $userId)->value('user_id');
    }
    $projectSvc = new ProjectService();
    dbCheck($secondUserId > 0 && $projectSvc->isProjectMember($workId, $secondUserId), '需第二位项目成员验证负责人迁移，可设置 CRM_TEST_USER_ID2');

    $actualTime = strtotime('2037-11-15 12:00:00');
    $period = ProjectPerformanceService::periodOf($actualTime);
    $existingSummary = Db::name('performance')->where(['user_id' => $userId, 'period' => $period])->find();

    $milestoneId = (int)Db::name('work_milestone')->insertGetId([
        'work_id' => $workId, 'milestone_type' => ProjectService::MS_RELEASE, 'name' => $token,
        'responsible_user_id' => $userId, 'plan_time' => $actualTime - 86400,
        'actual_time' => $actualTime, 'status' => '已完成', 'sort' => 9999,
        'evidence_note' => $token, 'create_user_id' => $userId,
        'create_time' => time(), 'update_time' => time(),
    ]);
    $svc = new ProjectPerformanceService();
    $milestoneConcurrent = concurrentSync('milestone', $milestoneId, $userId);
    $first = $milestoneConcurrent[0];
    $second = $milestoneConcurrent[1];
    dbCheck($first['ok'] && $second['ok'], '里程碑双进程并发同步均成功');
    dbCheck((int)$first['fact_id'] > 0 && (int)$first['fact_id'] === (int)$second['fact_id'], '里程碑并发同步返回同一事实');
    dbCheck((int)Db::name('performance_fact')->where(['source_type' => 'project_milestone', 'source_id' => 'milestone:' . $milestoneId])->count() === 1, '里程碑来源只有一条事实');
    $fact = Db::name('performance_fact')->where('fact_id', $first['fact_id'])->find();
    $summary = Db::name('performance')->where('perf_id', (int)$fact['perf_id'])->find();
    dbCheck((int)$summary['user_id'] === $userId && (string)$summary['period'] === (string)$fact['period'], 'fact 的 perf_id/user_id/period 一致');
    if (!$existingSummary) $createdPerfIds[] = (int)$summary['perf_id'];

    // 负责人、季度及两者同时变化时，事实必须迁移到对应汇总，不能继续引用旧汇总。
    $beforeSecond = Db::name('performance')->where(['user_id' => $secondUserId, 'period' => $period])->find();
    Db::name('work_milestone')->where('milestone_id', $milestoneId)->update(['responsible_user_id' => $secondUserId]);
    $ownerMoved = $svc->syncMilestoneAtomic($milestoneId, $userId);
    $ownerFact = Db::name('performance_fact')->where('fact_id', $first['fact_id'])->find();
    dbCheck($ownerMoved['ok'] && (int)$ownerFact['user_id'] === $secondUserId && (int)$ownerFact['perf_id'] !== (int)$fact['perf_id'], '负责人变更同步 user_id/perf_id');
    if (!$beforeSecond) $createdPerfIds[] = (int)$ownerFact['perf_id'];

    $q3Time = strtotime('2037-08-15 12:00:00');
    $q3Period = ProjectPerformanceService::periodOf($q3Time);
    $beforeQ3 = Db::name('performance')->where(['user_id' => $secondUserId, 'period' => $q3Period])->find();
    Db::name('work_milestone')->where('milestone_id', $milestoneId)->update(['actual_time' => $q3Time]);
    $periodMoved = $svc->syncMilestoneAtomic($milestoneId, $userId);
    $q3Fact = Db::name('performance_fact')->where('fact_id', $first['fact_id'])->find();
    dbCheck($periodMoved['ok'] && (string)$q3Fact['period'] === $q3Period && (int)$q3Fact['perf_id'] !== (int)$ownerFact['perf_id'], '跨季度同步 period/perf_id');
    if (!$beforeQ3) $createdPerfIds[] = (int)$q3Fact['perf_id'];

    $q2Time = strtotime('2037-05-15 12:00:00');
    $q2Period = ProjectPerformanceService::periodOf($q2Time);
    $beforeCombined = Db::name('performance')->where(['user_id' => $userId, 'period' => $q2Period])->find();
    Db::name('work_milestone')->where('milestone_id', $milestoneId)->update(['responsible_user_id' => $userId, 'actual_time' => $q2Time]);
    $combinedMoved = $svc->syncMilestoneAtomic($milestoneId, $userId);
    $combinedFact = Db::name('performance_fact')->where('fact_id', $first['fact_id'])->find();
    $combinedSummary = Db::name('performance')->where('perf_id', (int)$combinedFact['perf_id'])->find();
    dbCheck($combinedMoved['ok'] && (int)$combinedFact['user_id'] === $userId && (string)$combinedFact['period'] === $q2Period, '负责人和季度同时变化同步事实');
    dbCheck((int)$combinedSummary['user_id'] === $userId && (string)$combinedSummary['period'] === $q2Period, '新 perf_id 指向一致的员工季度汇总');
    if (!$beforeCombined) $createdPerfIds[] = (int)$combinedFact['perf_id'];

    Db::name('performance_fact')->where('fact_id', $first['fact_id'])->update(['status' => '已通过']);
    Db::startTrans();
    $approvedDelete = $svc->prepareDeleteMilestone($milestoneId, $userId);
    Db::rollback();
    dbCheck(!$approvedDelete['can_delete'], '已通过里程碑服务端删除检查拒绝');
    $approvedSync = $svc->syncMilestoneAtomic($milestoneId, $userId);
    dbCheck(!$approvedSync['ok'] && $approvedSync['action'] === 'conflict', '已通过里程碑同步保持不可变');
    Db::name('performance_fact')->where('fact_id', $first['fact_id'])->update(['status' => '待审核']);

    Db::name('work_milestone')->where('milestone_id', $milestoneId)->update(['status' => '进行中']);
    $rejected = $svc->syncMilestoneAtomic($milestoneId, $userId);
    dbCheck($rejected['ok'] && $rejected['action'] === 'rejected', '源记录不再可归集时事实转为已驳回');
    Db::name('work_milestone')->where('milestone_id', $milestoneId)->update(['status' => '已完成']);
    $notAutoResubmitted = $svc->syncMilestoneAtomic($milestoneId, $userId);
    dbCheck($notAutoResubmitted['action'] === 'skipped', '已驳回事实不会自动重提');
    Db::startTrans();
    $resubmitted = $svc->resubmitMilestone($milestoneId, $userId);
    if ($resubmitted['ok']) Db::commit(); else Db::rollback();
    dbCheck($resubmitted['ok'] && $resubmitted['action'] === 'resubmitted', '显式重新提交成功');
    dbCheck((int)Db::name('project_performance_audit')->where('fact_id', $first['fact_id'])->count() >= 2, '驳回和重提均写入审计');

    $start = strtotime('2037-11-01 00:00:00');
    $end = strtotime('2037-11-05 00:00:00');
    $contributionId = (int)Db::name('work_member_contribution')->insertGetId([
        'work_id' => $workId, 'user_id' => $userId, 'contribution_role' => $token,
        'status' => '已确认', 'on_site_days' => 5, 'start_time' => $start, 'end_time' => $end,
        'confirm_user_id' => $userId, 'confirm_time' => $end, 'evidence_note' => $token,
        'create_user_id' => $userId, 'create_time' => time(), 'update_time' => time(),
    ]);
    $contributionExisting = Db::name('work_member_contribution')->where('contribution_id', $contributionId)->find();
    dbCheck($projectSvc->validateContribution(['contribution_role' => $token . '_EDIT'], $workId, $contributionExisting) === '', '已确认贡献只改角色沿用旧日期和人日');
    dbCheck($projectSvc->validateContribution(['on_site_days' => 6, 'evidence_note' => ''], $workId, $contributionExisting) !== '', '超周期人日无说明拒绝');
    dbCheck($projectSvc->validateContribution(['on_site_days' => 6, 'evidence_note' => '包含额外加班'], $workId, $contributionExisting) === '', '超周期人日有说明允许');
    $contributionConcurrent = concurrentSync('contribution', $contributionId, $userId);
    $ctFirst = $contributionConcurrent[0];
    $ctSecond = $contributionConcurrent[1];
    dbCheck($ctFirst['ok'] && $ctSecond['ok'] && $ctFirst['fact_id'] === $ctSecond['fact_id'], '贡献重复同步幂等');
    dbCheck((int)Db::name('performance_fact')->where(['source_type' => 'project_contribution', 'source_id' => 'contribution:' . $contributionId])->count() === 1, '贡献来源只有一条事实');
    Db::name('performance_fact')->where('fact_id', $ctFirst['fact_id'])->update(['status' => '已通过']);
    Db::startTrans();
    $approvedContributionDelete = $svc->prepareDeleteContribution($contributionId, $userId);
    Db::rollback();
    dbCheck(!$approvedContributionDelete['can_delete'], '已通过贡献服务端删除检查拒绝');
    $approvedContributionSync = $svc->syncContributionAtomic($contributionId, $userId);
    dbCheck(!$approvedContributionSync['ok'], '已通过贡献同步保持不可变');

    $uniqueMethod = new ReflectionMethod(ProjectPerformanceService::class, 'isUniqueViolation');
    $uniqueMethod->setAccessible(true);
    dbCheck($uniqueMethod->invoke($svc, new RuntimeException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry')) === true, '真实 1062 识别为唯一键冲突');
    dbCheck($uniqueMethod->invoke($svc, new RuntimeException('SQLSTATE[23000]: foreign key constraint fails')) === false, '普通完整性异常不得伪装成唯一键冲突');

    echo 'project_performance_db_integration_test passed (' . $checks . " assertions)\n";
} catch (\Exception $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    $failed = true;
} finally {
    try {
        if ($milestoneId > 0) {
            Db::name('project_performance_audit')->where(['source_type' => 'project_milestone', 'source_id' => 'milestone:' . $milestoneId])->delete();
            Db::name('performance_fact')->where(['source_type' => 'project_milestone', 'source_id' => 'milestone:' . $milestoneId])->delete();
            Db::name('work_milestone')->where('milestone_id', $milestoneId)->delete();
        }
        if ($contributionId > 0) {
            Db::name('project_performance_audit')->where(['source_type' => 'project_contribution', 'source_id' => 'contribution:' . $contributionId])->delete();
            Db::name('performance_fact')->where(['source_type' => 'project_contribution', 'source_id' => 'contribution:' . $contributionId])->delete();
            Db::name('work_member_contribution')->where('contribution_id', $contributionId)->delete();
        }
        foreach (array_unique($createdPerfIds) as $perfId) {
            if ((int)Db::name('performance_fact')->where('perf_id', $perfId)->count() === 0) Db::name('performance')->where('perf_id', $perfId)->delete();
        }
    } catch (\Exception $cleanupError) {
        fwrite(STDERR, 'cleanup failed: ' . $cleanupError->getMessage() . "\n");
    }
}

if (!empty($failed)) exit(1);
