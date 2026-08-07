<?php
require_once __DIR__ . '/../application/crm/logic/CooperationCustomerService.php';

use app\crm\logic\CooperationCustomerService;

$failed = 0;
function cooperationCheck($condition, $message)
{
    global $failed;
    if ($condition) {
        echo "[PASS] {$message}\n";
    } else {
        $failed++;
        echo "[FAIL] {$message}\n";
    }
}

cooperationCheck(CooperationCustomerService::validate([
    'cooperation_type' => '医院客户',
]) === '', '医院客户不强制填写合作阶段及核实资料');

cooperationCheck(CooperationCustomerService::validate([
    'cooperation_type' => '代理商',
    'cooperation_stage' => '初筛',
]) === '', '合作企业在初筛阶段允许核实字段为空');

$missing = CooperationCustomerService::validate([
    'cooperation_type' => '软件厂商',
    'cooperation_stage' => '已核实',
]);
cooperationCheck(strpos($missing, '发现人') !== false && strpos($missing, '核实说明') !== false, '进入已核实时条件必填完整核实资料');

cooperationCheck(CooperationCustomerService::validate([
    'cooperation_type' => '系统集成商',
    'cooperation_stage' => '已核实',
    'discover_user_id' => 10,
    'verify_user_id' => 11,
    'verify_time' => strtotime('2026-08-06 10:00:00'),
    'verify_result' => '不建议联系',
    'verify_note' => '经工商信息及公开项目资料交叉核实，当前不具备联系价值。',
]) === '', '不建议联系但核实资料完整时允许进入绩效审核');

cooperationCheck(CooperationCustomerService::validate([
    'cooperation_type' => '代理商',
    'cooperation_stage' => '已核实',
    'discover_user_id' => 10,
    'verify_user_id' => 10,
    'verify_time' => strtotime('2026-08-06 10:00:00'),
    'verify_result' => '推荐跟进',
    'verify_note' => '由同一员工完成线索发现和资料核实，核实依据完整。',
]) === '', '发现人和核实人允许为同一员工');

cooperationCheck(CooperationCustomerService::validate([
    'cooperation_type' => '渠道商',
]) !== '', '拒绝客户类型枚举之外的值');

cooperationCheck(CooperationCustomerService::periodOf(strtotime('2026-08-06 10:00:00')) === '2026Q3', '核实时间正确归属季度');
cooperationCheck(CooperationCustomerService::sourceId(123) === 'customer:123', '同一企业使用稳定幂等来源键');
cooperationCheck(!in_array('已联系', CooperationCustomerService::$stages, true), '合作阶段移除与活动记录重复的已联系');
cooperationCheck(in_array('有效联系', CooperationCustomerService::$stages, true), '合作阶段包含独立的有效联系节点');
cooperationCheck(CooperationCustomerService::validateTransition('初筛', '洽谈中') !== '', '主流程禁止从初筛跨级跳到洽谈中');
cooperationCheck(CooperationCustomerService::validateTransition('已核实', '有效联系') === '', '主流程允许从已核实推进到有效联系');
cooperationCheck(CooperationCustomerService::contactSourceId(123) === 'customer:123:effective_contact', '有效联系使用独立稳定幂等来源键');
cooperationCheck(CooperationCustomerService::formalExchangeSourceId(123) === 'customer:123:formal_exchange', '正式交流使用独立稳定幂等来源键');

$customerController = file_get_contents(__DIR__ . '/../application/crm/controller/Customer.php');
cooperationCheck(substr_count($customerController, 'syncFirstVerified(') >= 2, '客户新增和编辑接口均接入首次核实绩效同步');
cooperationCheck(strpos($customerController, 'Db::startTrans()') !== false && strpos($customerController, 'Db::rollback()') !== false, '客户保存与绩效事实生成处于事务保护中');
cooperationCheck(substr_count($customerController, 'normalizeCooperationUserFields(') >= 3, '客户新增和编辑统一归一化发现人及核实人');
cooperationCheck(strpos($customerController, 'function cooperationStage()') !== false, '客户详情提供合作阶段快捷调整接口');
cooperationCheck(substr_count($customerController, 'recordStageActivity(') >= 2, '完整编辑和快捷调整均写入合作阶段活动');
cooperationCheck(substr_count($customerController, 'syncFirstEffectiveContact(') >= 3, '新增、编辑和快捷推进均接入有效联系绩效同步');
cooperationCheck(substr_count($customerController, 'syncFirstFormalExchange(') >= 3, '新增、编辑和快捷推进均接入正式交流绩效同步');
cooperationCheck(strpos($customerController, 'recordMilestoneEvidenceActivity') !== false, '阶段推进将有效联系和正式交流证据写入客户活动');
cooperationCheck(strpos($customerController, "checkPerByAction('crm', 'customer', 'update')") !== false, '合作阶段快捷调整复用客户更新权限');
$crmRoutes = file_get_contents(__DIR__ . '/../config/route_crm.php');
cooperationCheck(strpos($crmRoutes, "'crm/customer/cooperationStage'") !== false, '合作阶段快捷接口已注册CRM路由');

$cooperationService = file_get_contents(__DIR__ . '/../application/crm/logic/CooperationCustomerService.php');
cooperationCheck(strpos($cooperationService, "'activity_type' => 2") !== false, '合作阶段变化写入客户活动时间线');
cooperationCheck(strpos($cooperationService, '合作阶段由「') !== false, '阶段活动清楚记录变更前后值');

$customerModel = file_get_contents(__DIR__ . '/../application/crm/model/Customer.php');
cooperationCheck(strpos($customerModel, "updateActionLog(\$user_id, 'crm_customer', \$customer_id, \$dataInfo->data, \$param)") !== false, '合作阶段及核实人员变化复用客户字段变更日志');
cooperationCheck(strpos($customerModel, "whereOr('customer.cooperation_stage'") !== false, '客户列表关键词支持检索合作阶段');
cooperationCheck(strpos($customerModel, "['cooperation_type', 'cooperation_stage']") !== false, '客户列表固定返回合作类型和阶段供标识展示');

$performanceController = file_get_contents(__DIR__ . '/../application/crm/controller/Performance.php');
cooperationCheck(strpos($performanceController, "'合作企业' . \$nodeName . '绩效事实审核状态变更为：'") !== false, '核实及有效联系事实审核变化同步记录到客户操作日志');
cooperationCheck(strpos($performanceController, 'syncApprovedCooperationReward') !== false, '合作企业三个绩效节点审核后统一生成对应奖励候选');

$rewardService = file_get_contents(__DIR__ . '/../application/crm/logic/RewardService.php');
cooperationCheck(strpos($rewardService, "'高质量原始数据基础批次' => 100.00") !== false, '基础数据批次奖励金额为100元');
cooperationCheck(strpos($rewardService, "'高质量原始数据优质批次' => 200.00") !== false, '优质数据批次奖励金额为200元');
cooperationCheck(strpos($rewardService, "'经销商有效联系' => 200.00") !== false, '有效联系奖金池阶段预发金额为200元');
cooperationCheck(strpos($rewardService, "'经销商正式交流' => 500.00") !== false, '正式交流奖金池阶段预发金额为500元');
cooperationCheck(strpos($cooperationService, "self::CONTACT_SOURCE_TYPE => ['经销商有效联系'") !== false
    && strpos($cooperationService, "self::FORMAL_EXCHANGE_SOURCE_TYPE => ['经销商正式交流'") !== false, '有效联系和正式交流事实分别映射奖金池阶段奖励');
cooperationCheck(strpos($cooperationService, "'dimension' => 'task'") !== false, '合作企业核实、联系和正式交流归入重点任务维度');

$rewardController = file_get_contents(__DIR__ . '/../application/crm/controller/Reward.php');
cooperationCheck(strpos($rewardController, "'raw_data_batch_basic' => 100.00") !== false
    && strpos($rewardController, "'raw_data_batch_premium' => 200.00") !== false, '批次奖励仅识别基础和优质两个档次');
cooperationCheck(strpos($rewardController, "'raw_batch:user:'") !== false, '批次奖励按人员和月份使用稳定幂等键');
cooperationCheck(strpos($rewardController, '同档或更高档数据批次奖励，不重复叠加') !== false, '同一人员同月仅计算最高档且不重复叠加');
cooperationCheck(strpos($rewardController, "'operation_type' => 'batch_tier_upgrade'") !== false, '达到优质档时原基础批次候选可原位升级');

exit($failed > 0 ? 1 : 0);
