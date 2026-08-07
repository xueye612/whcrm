<?php
define('APP_PATH', dirname(__DIR__) . '/application/');
define('CONF_PATH', dirname(__DIR__) . '/config/');
define('RUNTIME_PATH', dirname(__DIR__) . '/runtime/');
require dirname(__DIR__) . '/thinkphp/base.php';
require_once dirname(__DIR__) . '/thinkphp/helper.php';
require_once dirname(__DIR__) . '/application/common.php';
\think\Loader::addNamespace('app', APP_PATH);

\think\Config::set([
    'type' => 'mysql',
    'hostname' => getenv('CRM_TEST_DB_HOST') ?: '127.0.0.1',
    'database' => getenv('CRM_TEST_DB_NAME') ?: 'crm',
    'username' => getenv('CRM_TEST_DB_USER') ?: 'root',
    'password' => getenv('CRM_TEST_DB_PASS') ?: 'root',
    'hostport' => getenv('CRM_TEST_DB_PORT') ?: '3306',
    'charset' => 'utf8',
    'prefix' => '5kcrm_',
], 'database');

use app\crm\logic\CooperationCustomerService;
use think\Db;

$customerId = 0;
$factId = 0;
$contactFactId = 0;
$rewardCandidateId = 0;
$contactRewardCandidateId = 0;
$formalFactId = 0;
$formalRewardCandidateId = 0;
$perfId = 0;
$createdSummary = false;
$failed = false;
$token = 'CODEX_COOP_' . str_replace('.', '', uniqid('', true));

try {
    $stageSetting = (string)Db::name('admin_field')->where(['types' => 'crm_customer', 'field' => 'cooperation_stage'])->value('setting');
    if ($stageSetting === '' || strpos($stageSetting, '已联系') !== false || strpos($stageSetting, '有效联系') === false) {
        throw new RuntimeException('数据库合作阶段未正确配置有效联系节点');
    }

    $users = Db::name('admin_user')->where('status', 1)->order('id asc')->limit(1)->column('id');
    if (count($users) < 1) throw new RuntimeException('集成测试至少需要一名有效员工');
    $discoverUserId = (int)$users[0];
    $verifyUserId = $discoverUserId;
    $verifyTime = strtotime('2037-02-15 10:00:00');
    $period = CooperationCustomerService::periodOf($verifyTime);
    $createdSummary = !Db::name('performance')->where(['user_id' => $verifyUserId, 'period' => $period])->find();

    $now = time();
    $customerId = (int)Db::name('crm_customer')->insertGetId([
        'name' => $token,
        'deal_time' => $now,
        'create_user_id' => $discoverUserId,
        'owner_user_id' => $verifyUserId,
        'ro_user_id' => '',
        'rw_user_id' => '',
        'address' => '',
        'location' => '',
        'detail_address' => '',
        'obtain_time' => $now,
        'create_time' => $now,
        'update_time' => $now,
        'deal_status' => '未成交',
        'cooperation_type' => '区域服务商',
        'cooperation_stage' => '已核实',
        'discover_user_id' => $discoverUserId,
        'verify_user_id' => $verifyUserId,
        'verify_time' => $verifyTime,
        'verify_result' => '推荐跟进',
        'verify_note' => '经工商信息、公开项目与联系方式交叉核实，具备进一步联系价值。',
    ]);

    $service = new CooperationCustomerService();
    $first = $service->syncFirstVerified($customerId, $discoverUserId, '初筛');
    if ($first['action'] !== 'inserted' || (int)$first['fact_id'] <= 0) throw new RuntimeException('首次进入已核实未生成事实');
    $factId = (int)$first['fact_id'];
    $fact = Db::name('performance_fact')->where('fact_id', $factId)->find();
    $perfId = (int)$fact['perf_id'];
    if ((string)$fact['status'] !== '待审核') throw new RuntimeException('事实不是待审核状态');
    if ((string)$fact['period'] !== $period || (int)$fact['user_id'] !== $verifyUserId) throw new RuntimeException('季度或核实人归属错误');
    if ((string)$fact['fact_type'] !== CooperationCustomerService::SOURCE_TYPE
        || (string)$fact['source_type'] !== CooperationCustomerService::SOURCE_TYPE
        || (string)$fact['source_id'] !== CooperationCustomerService::sourceId($customerId)
        || (string)$fact['dimension'] !== 'task'
        || (string)$fact['direction'] !== '正向') {
        throw new RuntimeException('绩效事实类型、来源、维度或方向错误');
    }
    $evidence = json_decode((string)$fact['evidence'], true);
    if (!is_array($evidence)
        || (int)$evidence['customer_id'] !== $customerId
        || (string)$evidence['customer_name'] !== $token
        || (int)$evidence['discover_user_id'] !== $discoverUserId
        || (int)$evidence['verify_user_id'] !== $verifyUserId
        || (int)$evidence['verify_time'] !== $verifyTime
        || (string)$evidence['verify_result'] !== '推荐跟进'
        || strpos((string)$evidence['verify_note'], '交叉核实') === false) {
        throw new RuntimeException('事实证据缺少完整企业核实快照');
    }
    $summary = Db::name('performance')->where('perf_id', $perfId)->find();
    if (!$summary || (int)$summary['user_id'] !== $verifyUserId || (string)$summary['period'] !== $period) {
        throw new RuntimeException('绩效事实未关联核实人对应季度汇总');
    }
    $decorated = \app\crm\logic\PerformanceService::decorateFactFull($fact);
    if ((string)$decorated['fact_type_label'] !== '合作企业线索核实'
        || (string)$decorated['source_type_label'] !== '合作企业线索核实'
        || (string)$decorated['source_module'] !== '客户'
        || (string)$decorated['source_name'] !== $token) {
        throw new RuntimeException('绩效中心未正确展示事实类型或关联企业');
    }
    if ((int)Db::name('admin_action_record')->where(['types' => 'crm_customer', 'action_id' => $customerId])->count() < 1
        || (int)Db::name('admin_operation_log')->where('target_name', $token)->count() < 1) {
        throw new RuntimeException('生成绩效事实未记录客户或绩效操作日志');
    }

    if (!CooperationCustomerService::recordStageActivity($customerId, '初筛', '已核实', $discoverUserId)) {
        throw new RuntimeException('合作阶段活动写入失败');
    }
    $stageActivity = Db::name('crm_activity')->where([
        'activity_type' => 2,
        'activity_type_id' => $customerId,
        'type' => 3,
    ])->find();
    if (!$stageActivity || (int)$stageActivity['status'] !== 1
        || strpos((string)$stageActivity['content'], '初筛') === false
        || strpos((string)$stageActivity['content'], '已核实') === false) {
        throw new RuntimeException('合作阶段活动未完整记录变更前后值');
    }

    $sameStage = $service->syncFirstVerified($customerId, $discoverUserId, '已核实');
    if ($sameStage['action'] !== 'not_applicable') throw new RuntimeException('重复保存已核实状态仍尝试生成事实');

    $second = $service->syncFirstVerified($customerId, $discoverUserId, '初筛');
    if ($second['action'] !== 'skipped' || (int)$second['fact_id'] !== $factId) throw new RuntimeException('重复保存未保持幂等');
    $count = (int)Db::name('performance_fact')->where([
        'source_type' => CooperationCustomerService::SOURCE_TYPE,
        'source_id' => CooperationCustomerService::sourceId($customerId),
    ])->count();
    if ($count !== 1) throw new RuntimeException('同一企业生成了重复事实');

    $reward = $service->syncApprovedVerifyReward($fact, $discoverUserId);
    if ($reward['action'] !== 'inserted' || (float)$reward['amount'] !== 30.00) {
        throw new RuntimeException('基础核实事实通过后未生成30元奖励候选');
    }
    $rewardCandidateId = (int)$reward['cand_id'];
    $rewardAgain = $service->syncApprovedVerifyReward($fact, $discoverUserId);
    if ($rewardAgain['action'] !== 'skipped' || (int)$rewardAgain['cand_id'] !== $rewardCandidateId) {
        throw new RuntimeException('基础核实奖励候选未保持幂等');
    }

    $contactTime = $verifyTime + 3600;
    $contactActivityId = (int)Db::name('crm_activity')->insertGetId([
        'type' => 1,
        'activity_type' => 2,
        'activity_type_id' => $customerId,
        'content' => CooperationCustomerService::CONTACT_ACTIVITY_PREFIX . '已与企业项目负责人电话沟通，对方真实回复有集成合作意向，并约定下周产品交流。',
        'create_user_id' => $discoverUserId,
        'status' => 1,
        'update_time' => $contactTime,
        'create_time' => $contactTime,
    ]);
    Db::name('crm_customer')->where('customer_id', $customerId)->update([
        'cooperation_stage' => CooperationCustomerService::STAGE_EFFECTIVE_CONTACT,
        'update_time' => $contactTime,
    ]);
    $contact = $service->syncFirstEffectiveContact($customerId, $discoverUserId, '已核实');
    if ($contact['action'] !== 'inserted' || (int)$contact['fact_id'] <= 0) {
        throw new RuntimeException('首次进入有效联系未生成绩效事实');
    }
    $contactFactId = (int)$contact['fact_id'];
    $contactFact = Db::name('performance_fact')->where('fact_id', $contactFactId)->find();
    $contactEvidence = json_decode((string)$contactFact['evidence'], true);
    if ((string)$contactFact['source_type'] !== CooperationCustomerService::CONTACT_SOURCE_TYPE
        || (string)$contactFact['source_id'] !== CooperationCustomerService::contactSourceId($customerId)
        || (int)$contactEvidence['activity_id'] !== $contactActivityId
        || strpos((string)$contactEvidence['contact_note'], '合作意向') === false) {
        throw new RuntimeException('有效联系绩效事实未关联实质跟进活动');
    }
    $contactDecorated = \app\crm\logic\PerformanceService::decorateFactFull($contactFact);
    if ((string)$contactDecorated['fact_type_label'] !== '合作企业有效联系'
        || (string)$contactDecorated['source_name'] !== $token) {
        throw new RuntimeException('绩效中心未正确展示有效联系事实');
    }

    $contactReward = $service->syncApprovedCooperationReward($contactFact, $discoverUserId);
    if ($contactReward['action'] !== 'inserted' || (float)$contactReward['amount'] !== 200.00
        || empty($contactReward['pool_advance'])) {
        throw new RuntimeException('有效联系审核后未生成200元业务获取奖金池阶段预发候选');
    }
    $contactRewardCandidateId = (int)$contactReward['cand_id'];
    $contactRewardAgain = $service->syncApprovedCooperationReward($contactFact, $discoverUserId);
    if ($contactRewardAgain['action'] !== 'skipped' || (int)$contactRewardAgain['cand_id'] !== $contactRewardCandidateId) {
        throw new RuntimeException('有效联系200元阶段预发候选未保持幂等');
    }
    $contactRewardRow = Db::name('reward_candidate')->where('cand_id', $contactRewardCandidateId)->find();
    if ((string)$contactRewardRow['source_type'] !== '经销商有效联系'
        || strpos((string)$contactRewardRow['source_ref'], 'customer:' . $customerId) === false) {
        throw new RuntimeException('有效联系奖励候选未使用奖金池可抵扣来源');
    }

    $formalTime = $contactTime + 3600;
    $formalActivityId = (int)Db::name('crm_activity')->insertGetId([
        'type' => 1,
        'activity_type' => 2,
        'activity_type_id' => $customerId,
        'content' => CooperationCustomerService::FORMAL_EXCHANGE_ACTIVITY_PREFIX . '与企业负责人完成产品方案介绍会议，确认接口范围并约定下周提交技术资料。',
        'create_user_id' => $discoverUserId,
        'status' => 1,
        'update_time' => $formalTime,
        'create_time' => $formalTime,
    ]);
    Db::name('crm_customer')->where('customer_id', $customerId)->update([
        'cooperation_stage' => CooperationCustomerService::STAGE_NEGOTIATING,
        'update_time' => $formalTime,
    ]);
    $formal = $service->syncFirstFormalExchange($customerId, $discoverUserId, CooperationCustomerService::STAGE_EFFECTIVE_CONTACT);
    if ($formal['action'] !== 'inserted' || (int)$formal['fact_id'] <= 0) {
        throw new RuntimeException('进入洽谈中未生成正式交流绩效事实');
    }
    $formalFactId = (int)$formal['fact_id'];
    $formalAgain = $service->syncFirstFormalExchange($customerId, $discoverUserId, CooperationCustomerService::STAGE_EFFECTIVE_CONTACT);
    if ($formalAgain['action'] !== 'skipped' || (int)$formalAgain['fact_id'] !== $formalFactId) {
        throw new RuntimeException('正式交流绩效事实未保持幂等');
    }
    $formalFact = Db::name('performance_fact')->where('fact_id', $formalFactId)->find();
    $formalEvidence = json_decode((string)$formalFact['evidence'], true);
    if ((string)$formalFact['source_type'] !== CooperationCustomerService::FORMAL_EXCHANGE_SOURCE_TYPE
        || (string)$formalFact['dimension'] !== 'task'
        || (int)$formalEvidence['activity_id'] !== $formalActivityId) {
        throw new RuntimeException('正式交流绩效事实未关联正式交流活动证据');
    }
    $formalReward = $service->syncApprovedCooperationReward($formalFact, $discoverUserId);
    if ($formalReward['action'] !== 'inserted' || (float)$formalReward['amount'] !== 500.00
        || empty($formalReward['pool_advance'])) {
        throw new RuntimeException('正式交流审核后未生成500元业务获取奖金池阶段预发候选');
    }
    $formalRewardCandidateId = (int)$formalReward['cand_id'];
    $formalRewardAgain = $service->syncApprovedCooperationReward($formalFact, $discoverUserId);
    if ($formalRewardAgain['action'] !== 'skipped' || (int)$formalRewardAgain['cand_id'] !== $formalRewardCandidateId) {
        throw new RuntimeException('正式交流500元阶段预发候选未保持幂等');
    }

    echo "cooperation_customer_db_integration_test passed\n";
} catch (\Exception $e) {
    $failed = true;
    fwrite(STDERR, $e->getMessage() . "\n");
} finally {
    try {
        if ($factId > 0) Db::name('performance_fact')->where('fact_id', $factId)->delete();
        if ($contactFactId > 0) Db::name('performance_fact')->where('fact_id', $contactFactId)->delete();
        if ($formalFactId > 0) Db::name('performance_fact')->where('fact_id', $formalFactId)->delete();
        if ($rewardCandidateId > 0) {
            Db::name('reward_candidate_audit')->where('cand_id', $rewardCandidateId)->delete();
            Db::name('reward_candidate')->where('cand_id', $rewardCandidateId)->delete();
        }
        foreach ([$contactRewardCandidateId, $formalRewardCandidateId] as $stageCandidateId) {
            if ($stageCandidateId <= 0) continue;
            Db::name('reward_candidate_audit')->where('cand_id', $stageCandidateId)->delete();
            Db::name('reward_candidate')->where('cand_id', $stageCandidateId)->delete();
        }
        if ($customerId > 0) {
            Db::name('admin_action_record')->where(['types' => 'crm_customer', 'action_id' => $customerId])->delete();
            Db::name('crm_activity')->where(['activity_type' => 2, 'activity_type_id' => $customerId])->delete();
            Db::name('crm_customer')->where('customer_id', $customerId)->delete();
        }
        Db::name('admin_operation_log')->where('target_name', $token)->delete();
        if ($createdSummary && $perfId > 0 && (int)Db::name('performance_fact')->where('perf_id', $perfId)->count() === 0) {
            Db::name('performance')->where('perf_id', $perfId)->delete();
        }
    } catch (\Exception $cleanupError) {
        $failed = true;
        fwrite(STDERR, 'cleanup failed: ' . $cleanupError->getMessage() . "\n");
    }
}

exit($failed ? 1 : 0);
