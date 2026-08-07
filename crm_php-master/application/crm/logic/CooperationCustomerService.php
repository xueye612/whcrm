<?php
/**
 * 合作企业客户核实与绩效事实同步。
 *
 * 复用 crm_customer 自定义字段和 performance_fact，不建立独立线索模块。
 * 服务不自行开启事务，由客户保存/编辑接口统一控制事务边界。
 */
namespace app\crm\logic;

use think\Db;

class CooperationCustomerService
{
    const TYPE_HOSPITAL = '医院客户';
    const STAGE_VERIFIED = '已核实';
    const STAGE_EFFECTIVE_CONTACT = '有效联系';
    const STAGE_NEGOTIATING = '洽谈中';
    const SOURCE_TYPE = 'cooperation_customer_verify';
    const CONTACT_SOURCE_TYPE = 'cooperation_customer_contact';
    const FORMAL_EXCHANGE_SOURCE_TYPE = 'cooperation_customer_formal_exchange';
    const CONTACT_ACTIVITY_PREFIX = '[有效联系]';
    const FORMAL_EXCHANGE_ACTIVITY_PREFIX = '[正式交流]';

    public static $customerTypes = [
        '医院客户', '代理商', '软件厂商', '渠道合作方', '系统集成商', '区域服务商', '其他合作企业',
    ];

    public static $stages = [
        '初筛', '已核实', '有效联系', '洽谈中', '已合作', '暂缓', '不适合', '无法联系',
    ];

    public static $mainStages = ['初筛', '已核实', '有效联系', '洽谈中', '已合作'];

    public static $verifyResults = ['推荐跟进', '储备观察', '不建议联系'];

    /** 同一企业首次合格核实使用稳定来源键，重复保存不会新增事实。 */
    public static function sourceId($customerId)
    {
        return 'customer:' . (int)$customerId;
    }

    public static function contactSourceId($customerId)
    {
        return 'customer:' . (int)$customerId . ':effective_contact';
    }

    public static function formalExchangeSourceId($customerId)
    {
        return 'customer:' . (int)$customerId . ':formal_exchange';
    }

    /** 将阶段达标证据写入客户活动；type=1 与系统阶段变更(type=3)严格区分。 */
    public static function recordMilestoneEvidenceActivity($customerId, $milestone, $note, $operatorUserId)
    {
        $customerId = (int)$customerId;
        $operatorUserId = (int)$operatorUserId;
        $note = trim((string)$note);
        $prefixes = [
            'effective_contact' => self::CONTACT_ACTIVITY_PREFIX,
            'formal_exchange' => self::FORMAL_EXCHANGE_ACTIVITY_PREFIX,
        ];
        if ($customerId <= 0 || $operatorUserId <= 0 || !isset($prefixes[$milestone])) return false;
        if (mb_strlen($note, 'UTF-8') < 10) {
            throw new \RuntimeException($milestone === 'effective_contact'
                ? '有效联系说明至少10个字，需写明具体联系人、真实回复和明确下一步'
                : '正式交流说明至少10个字，需写明交流对象、产品介绍或会议内容、结论和下一步');
        }
        $now = time();
        return Db::name('crm_activity')->insert([
            'type' => 1,
            'activity_type' => 2,
            'activity_type_id' => $customerId,
            'content' => $prefixes[$milestone] . $note,
            'create_user_id' => $operatorUserId,
            'status' => 1,
            'update_time' => $now,
            'create_time' => $now,
        ]) !== false;
    }

    /** 主流程不允许跨级前进；回退修正及转入旁路状态允许操作。 */
    public static function validateTransition($oldStage, $newStage)
    {
        $oldIndex = array_search((string)$oldStage, self::$mainStages, true);
        $newIndex = array_search((string)$newStage, self::$mainStages, true);
        if ($oldIndex !== false && $newIndex !== false && $newIndex > $oldIndex + 1) {
            return '合作阶段请按“初筛→已核实→有效联系→洽谈中→已合作”逐级推进';
        }
        return '';
    }

    /** 由核实时间确定所属季度。 */
    public static function periodOf($timestamp)
    {
        $timestamp = (int)$timestamp;
        if ($timestamp <= 0) return '';
        return date('Y', $timestamp) . 'Q' . (int)ceil((int)date('n', $timestamp) / 3);
    }

    /** 将合作阶段变化同步到客户活动，供详情页活动时间线查看。 */
    public static function recordStageActivity($customerId, $oldStage, $newStage, $operatorUserId)
    {
        $customerId = (int)$customerId;
        $operatorUserId = (int)$operatorUserId;
        $oldStage = trim((string)$oldStage);
        $newStage = trim((string)$newStage);
        if ($customerId <= 0 || $operatorUserId <= 0 || $oldStage === $newStage) {
            return false;
        }

        $displayNewStage = $newStage === '' ? '未设置' : $newStage;
        $content = $oldStage === ''
            ? '合作阶段设置为「' . $displayNewStage . '」'
            : '合作阶段由「' . $oldStage . '」变更为「' . $displayNewStage . '」';
        $now = time();
        return Db::name('crm_activity')->insert([
            'type' => 3,
            'activity_type' => 2,
            'activity_type_id' => $customerId,
            'content' => $content,
            'create_user_id' => $operatorUserId,
            'update_time' => $now,
            'create_time' => $now,
        ]) !== false;
    }

    public static function isCooperationCustomer(array $customer)
    {
        $type = trim((string)($customer['cooperation_type'] ?? ''));
        return $type !== '' && $type !== self::TYPE_HOSPITAL;
    }

    /**
     * 校验新增枚举；合作企业进入“已核实”时才条件必填核实资料。
     * 其他阶段及医院客户保持全部新增字段可空，兼容历史数据。
     */
    public static function validate(array $customer)
    {
        $type = trim((string)($customer['cooperation_type'] ?? ''));
        $stage = trim((string)($customer['cooperation_stage'] ?? ''));
        $result = trim((string)($customer['verify_result'] ?? ''));

        if ($type !== '' && !in_array($type, self::$customerTypes, true)) return '客户类型不在允许范围内';
        if ($stage !== '' && !in_array($stage, self::$stages, true)) return '合作阶段不在允许范围内';
        if ($result !== '' && !in_array($result, self::$verifyResults, true)) return '核实结果不在允许范围内';

        $requiresVerification = in_array($stage, [
            self::STAGE_VERIFIED, self::STAGE_EFFECTIVE_CONTACT, '洽谈中', '已合作',
        ], true);
        if (!self::isCooperationCustomer($customer) || !$requiresVerification) return '';

        $required = [
            'discover_user_id' => '发现人',
            'verify_user_id' => '核实人',
            'verify_time' => '核实时间',
            'verify_result' => '核实结果',
            'verify_note' => '核实说明',
        ];
        $missing = [];
        foreach ($required as $field => $label) {
            $value = trim((string)($customer[$field] ?? ''));
            $mustBePositiveIdOrTime = in_array($field, ['discover_user_id', 'verify_user_id', 'verify_time'], true);
            if ($value === '' || ($mustBePositiveIdOrTime && (int)$value <= 0)) {
                $missing[] = $label;
            }
        }
        return $missing ? '合作企业进入已核实及后续阶段前请完整填写：' . implode('、', $missing) : '';
    }

    /**
     * 首次从其他阶段进入“已核实”时生成待审核事实。
     * 返回 inserted/skipped/not_applicable，便于测试与调用方观察。
     */
    public function syncFirstVerified($customerId, $operatorUserId, $oldStage = '')
    {
        $customerId = (int)$customerId;
        if ($customerId <= 0) throw new \InvalidArgumentException('客户ID无效');

        $customer = Db::name('crm_customer')->where('customer_id', $customerId)->find();
        if (!$customer) throw new \RuntimeException('客户不存在');

        $error = self::validate($customer);
        if ($error !== '') throw new \RuntimeException($error);

        if (!self::isCooperationCustomer($customer)
            || (string)$customer['cooperation_stage'] !== self::STAGE_VERIFIED
            || (string)$oldStage === self::STAGE_VERIFIED) {
            return ['action' => 'not_applicable', 'fact_id' => 0];
        }

        $sourceId = self::sourceId($customerId);
        $exist = Db::name('performance_fact')
            ->where(['source_type' => self::SOURCE_TYPE, 'source_id' => $sourceId])
            ->lock(true)
            ->find();
        if ($exist) return ['action' => 'skipped', 'fact_id' => (int)$exist['fact_id']];

        $discoverUserId = (int)$customer['discover_user_id'];
        $verifyUserId = (int)$customer['verify_user_id'];
        $users = Db::name('admin_user')->whereIn('id', [$discoverUserId, $verifyUserId])->column('realname', 'id');
        if (empty($users[$discoverUserId]) || empty($users[$verifyUserId])) {
            throw new \RuntimeException('发现人或核实人不存在');
        }

        $verifyTime = (int)$customer['verify_time'];
        $period = self::periodOf($verifyTime);
        $perfId = $this->ensureSummary($verifyUserId, $period, $operatorUserId);
        $now = time();
        $evidence = json_encode([
            'customer_id' => $customerId,
            'customer_name' => $this->shorten((string)$customer['name'], 80),
            'discover_user_id' => $discoverUserId,
            'discover_user_name' => (string)$users[$discoverUserId],
            'verify_user_id' => $verifyUserId,
            'verify_user_name' => (string)$users[$verifyUserId],
            'verify_time' => $verifyTime,
            'verify_result' => (string)$customer['verify_result'],
            'verify_note' => $this->shorten(trim((string)$customer['verify_note']), 180),
        ], JSON_UNESCAPED_UNICODE);

        $row = [
            'perf_id' => $perfId,
            'user_id' => $verifyUserId,
            'period' => $period,
            'dimension' => 'task',
            'direction' => PerformanceService::DIR_POSITIVE,
            'fact_type' => self::SOURCE_TYPE,
            'title' => '合作企业线索核实：' . $this->shorten((string)$customer['name'], 80),
            'source_type' => self::SOURCE_TYPE,
            'source_id' => $sourceId,
            'occurred_time' => $verifyTime,
            'evidence' => $evidence,
            'status' => PerformanceService::FACT_PENDING,
            'submit_user_id' => (int)$operatorUserId,
            'create_time' => $now,
            'update_time' => $now,
        ];

        try {
            $factId = (int)Db::name('performance_fact')->insertGetId($row);
        } catch (\Exception $e) {
            if (!$this->isUniqueViolation($e)) throw $e;
            $again = Db::name('performance_fact')->where(['source_type' => self::SOURCE_TYPE, 'source_id' => $sourceId])->find();
            if (!$again) throw $e;
            return ['action' => 'skipped', 'fact_id' => (int)$again['fact_id']];
        }

        \updateActionLog((int)$operatorUserId, 'crm_customer', $customerId, '', '', '生成待审核绩效事实：合作企业线索核实');
        \RecordActionLog((int)$operatorUserId, 'performance_fact', 'save', (string)$customer['name'], '', '', '生成待审核绩效事实：合作企业线索核实');
        return ['action' => 'inserted', 'fact_id' => $factId];
    }

    /**
     * 首次进入“有效联系”生成独立待审核绩效事实。
     * 必须存在核实时间之后的客户跟进活动，避免仅切换阶段形成空事实。
     */
    public function syncFirstEffectiveContact($customerId, $operatorUserId, $oldStage = '')
    {
        $customerId = (int)$customerId;
        if ($customerId <= 0) throw new \InvalidArgumentException('客户ID无效');

        $customer = Db::name('crm_customer')->where('customer_id', $customerId)->find();
        if (!$customer) throw new \RuntimeException('客户不存在');
        $error = self::validate($customer);
        if ($error !== '') throw new \RuntimeException($error);
        if (!self::isCooperationCustomer($customer)
            || (string)$customer['cooperation_stage'] !== self::STAGE_EFFECTIVE_CONTACT
            || (string)$oldStage === self::STAGE_EFFECTIVE_CONTACT) {
            return ['action' => 'not_applicable', 'fact_id' => 0];
        }

        $activity = Db::name('crm_activity')
            ->where([
                'activity_type' => 2,
                'activity_type_id' => $customerId,
                'type' => 1,
                'status' => 1,
            ])
            ->where('create_time', '>=', (int)$customer['verify_time'])
            ->where('content', 'like', self::CONTACT_ACTIVITY_PREFIX . '%')
            ->order('create_time desc, activity_id desc')
            ->find();
        if (!$activity || trim((string)$activity['content']) === '') {
            throw new \RuntimeException('进入有效联系前，请先在客户活动中填写核实后的有效跟进记录');
        }

        $sourceId = self::contactSourceId($customerId);
        $exist = Db::name('performance_fact')
            ->where(['source_type' => self::CONTACT_SOURCE_TYPE, 'source_id' => $sourceId])
            ->lock(true)
            ->find();
        if ($exist) return ['action' => 'skipped', 'fact_id' => (int)$exist['fact_id']];

        $contactUserId = (int)$activity['create_user_id'];
        if ($contactUserId <= 0) $contactUserId = (int)$customer['owner_user_id'];
        $contactUserName = (string)Db::name('admin_user')->where('id', $contactUserId)->value('realname');
        if ($contactUserId <= 0 || $contactUserName === '') throw new \RuntimeException('有效联系人不存在');

        $occurredTime = (int)$activity['create_time'];
        $period = self::periodOf($occurredTime);
        $perfId = $this->ensureSummary($contactUserId, $period, $operatorUserId);
        $now = time();
        $evidence = json_encode([
            'customer_id' => $customerId,
            'customer_name' => $this->shorten((string)$customer['name'], 80),
            'activity_id' => (int)$activity['activity_id'],
            'contact_user_id' => $contactUserId,
            'contact_user_name' => $contactUserName,
            'contact_time' => $occurredTime,
            'contact_note' => $this->shorten(trim((string)$activity['content']), 180),
            'verify_user_id' => (int)$customer['verify_user_id'],
            'verify_result' => (string)$customer['verify_result'],
        ], JSON_UNESCAPED_UNICODE);

        $row = [
            'perf_id' => $perfId,
            'user_id' => $contactUserId,
            'period' => $period,
            'dimension' => 'task',
            'direction' => PerformanceService::DIR_POSITIVE,
            'fact_type' => self::CONTACT_SOURCE_TYPE,
            'title' => '合作企业有效联系：' . $this->shorten((string)$customer['name'], 80),
            'source_type' => self::CONTACT_SOURCE_TYPE,
            'source_id' => $sourceId,
            'occurred_time' => $occurredTime,
            'evidence' => $evidence,
            'status' => PerformanceService::FACT_PENDING,
            'submit_user_id' => (int)$operatorUserId,
            'create_time' => $now,
            'update_time' => $now,
        ];
        try {
            $factId = (int)Db::name('performance_fact')->insertGetId($row);
        } catch (\Exception $e) {
            if (!$this->isUniqueViolation($e)) throw $e;
            $again = Db::name('performance_fact')->where([
                'source_type' => self::CONTACT_SOURCE_TYPE,
                'source_id' => $sourceId,
            ])->find();
            if (!$again) throw $e;
            return ['action' => 'skipped', 'fact_id' => (int)$again['fact_id']];
        }

        \updateActionLog((int)$operatorUserId, 'crm_customer', $customerId, '', '', '生成待审核绩效事实：合作企业有效联系');
        \RecordActionLog((int)$operatorUserId, 'performance_fact', 'save', (string)$customer['name'], '', '', '生成待审核绩效事实：合作企业有效联系');
        return ['action' => 'inserted', 'fact_id' => $factId];
    }

    /** 首次进入“洽谈中”且有正式交流活动证据时生成独立待审核绩效事实。 */
    public function syncFirstFormalExchange($customerId, $operatorUserId, $oldStage = '')
    {
        $customerId = (int)$customerId;
        if ($customerId <= 0) throw new \InvalidArgumentException('客户ID无效');

        $customer = Db::name('crm_customer')->where('customer_id', $customerId)->find();
        if (!$customer) throw new \RuntimeException('客户不存在');
        $error = self::validate($customer);
        if ($error !== '') throw new \RuntimeException($error);
        if (!self::isCooperationCustomer($customer)
            || (string)$customer['cooperation_stage'] !== self::STAGE_NEGOTIATING
            || (string)$oldStage === self::STAGE_NEGOTIATING) {
            return ['action' => 'not_applicable', 'fact_id' => 0];
        }

        $contactFact = Db::name('performance_fact')->where([
            'source_type' => self::CONTACT_SOURCE_TYPE,
            'source_id' => self::contactSourceId($customerId),
        ])->find();
        if (!$contactFact) {
            throw new \RuntimeException('进入洽谈中前必须先完成有效联系绩效节点');
        }
        $activity = Db::name('crm_activity')
            ->where([
                'activity_type' => 2,
                'activity_type_id' => $customerId,
                'type' => 1,
                'status' => 1,
            ])
            ->where('create_time', '>=', (int)$contactFact['occurred_time'])
            ->where('content', 'like', self::FORMAL_EXCHANGE_ACTIVITY_PREFIX . '%')
            ->order('create_time desc, activity_id desc')
            ->find();
        if (!$activity || trim((string)$activity['content']) === '') {
            throw new \RuntimeException('进入洽谈中前，请填写正式产品介绍或合作交流会议记录');
        }

        $sourceId = self::formalExchangeSourceId($customerId);
        $exist = Db::name('performance_fact')->where([
            'source_type' => self::FORMAL_EXCHANGE_SOURCE_TYPE,
            'source_id' => $sourceId,
        ])->lock(true)->find();
        if ($exist) return ['action' => 'skipped', 'fact_id' => (int)$exist['fact_id']];

        $userId = (int)$activity['create_user_id'];
        if ($userId <= 0) $userId = (int)$customer['owner_user_id'];
        $userName = (string)Db::name('admin_user')->where('id', $userId)->value('realname');
        if ($userId <= 0 || $userName === '') throw new \RuntimeException('正式交流负责人不存在');

        $occurredTime = (int)$activity['create_time'];
        $period = self::periodOf($occurredTime);
        $perfId = $this->ensureSummary($userId, $period, $operatorUserId);
        $now = time();
        $evidence = json_encode([
            'customer_id' => $customerId,
            'customer_name' => $this->shorten((string)$customer['name'], 80),
            'activity_id' => (int)$activity['activity_id'],
            'exchange_user_id' => $userId,
            'exchange_user_name' => $userName,
            'exchange_time' => $occurredTime,
            'exchange_note' => $this->shorten(trim((string)$activity['content']), 180),
            'contact_fact_id' => (int)$contactFact['fact_id'],
        ], JSON_UNESCAPED_UNICODE);
        $row = [
            'perf_id' => $perfId,
            'user_id' => $userId,
            'period' => $period,
            'dimension' => 'task',
            'direction' => PerformanceService::DIR_POSITIVE,
            'fact_type' => self::FORMAL_EXCHANGE_SOURCE_TYPE,
            'title' => '合作企业正式交流：' . $this->shorten((string)$customer['name'], 80),
            'source_type' => self::FORMAL_EXCHANGE_SOURCE_TYPE,
            'source_id' => $sourceId,
            'occurred_time' => $occurredTime,
            'evidence' => $evidence,
            'status' => PerformanceService::FACT_PENDING,
            'submit_user_id' => (int)$operatorUserId,
            'create_time' => $now,
            'update_time' => $now,
        ];
        try {
            $factId = (int)Db::name('performance_fact')->insertGetId($row);
        } catch (\Exception $e) {
            if (!$this->isUniqueViolation($e)) throw $e;
            $again = Db::name('performance_fact')->where([
                'source_type' => self::FORMAL_EXCHANGE_SOURCE_TYPE,
                'source_id' => $sourceId,
            ])->find();
            if (!$again) throw $e;
            return ['action' => 'skipped', 'fact_id' => (int)$again['fact_id']];
        }
        \updateActionLog((int)$operatorUserId, 'crm_customer', $customerId, '', '', '生成待审核绩效事实：合作企业正式交流');
        \RecordActionLog((int)$operatorUserId, 'performance_fact', 'save', (string)$customer['name'], '', '', '生成待审核绩效事实：合作企业正式交流');
        return ['action' => 'inserted', 'fact_id' => $factId];
    }

    /**
     * 合作企业绩效事实审核通过后生成对应奖励候选：
     * 基础核实30元为独立即时奖励；有效联系200元、正式交流500元为业务获取奖金池阶段预发。
     */
    public function syncApprovedCooperationReward(array $fact, $reviewerUserId)
    {
        $factSource = (string)($fact['source_type'] ?? '');
        $rewardMap = [
            self::SOURCE_TYPE => ['经销商基础核实', 'verify', '基础核实即时奖励', false],
            self::CONTACT_SOURCE_TYPE => ['经销商有效联系', 'effective_contact', '业务获取奖金池阶段预发', true],
            self::FORMAL_EXCHANGE_SOURCE_TYPE => ['经销商正式交流', 'formal_exchange', '业务获取奖金池阶段预发', true],
        ];
        if (!isset($rewardMap[$factSource])
            || !preg_match('/customer:(\d+)/', (string)($fact['source_id'] ?? ''), $matches)) {
            return ['action' => 'not_applicable', 'cand_id' => 0];
        }
        $customerId = (int)$matches[1];
        $customer = Db::name('crm_customer')->where('customer_id', $customerId)->find();
        if (!$customer) throw new \RuntimeException('奖励关联客户不存在');
        $eligibleTypes = ['代理商', '渠道合作方', '系统集成商', '区域服务商'];
        if (!in_array((string)$customer['cooperation_type'], $eligibleTypes, true)) {
            return ['action' => 'not_applicable', 'cand_id' => 0];
        }

        list($sourceType, $refSuffix, $rewardNature, $isPoolAdvance) = $rewardMap[$factSource];
        $sourceRef = 'customer:' . $customerId . ':' . $refSuffix;
        $exist = Db::name('reward_candidate')->where([
            'source_type' => $sourceType,
            'source_ref' => $sourceRef,
        ])->lock(true)->find();
        if ($exist) return ['action' => 'skipped', 'cand_id' => (int)$exist['cand_id']];

        $amount = RewardService::fixedAmount($sourceType);
        if ($amount <= 0) throw new \RuntimeException($sourceType . '奖励金额未配置');
        $rewardService = new RewardService();
        list($needSpecial) = $rewardService->checkMonthlyCap((int)$fact['user_id'], $amount);
        $status = $needSpecial ? RewardService::ST_SPECIAL : RewardService::ST_PENDING;
        $now = time();
        $candidate = [
            'source_type' => $sourceType,
            'source_ref' => $sourceRef,
            'customer_id' => $customerId,
            'user_id' => (int)$fact['user_id'],
            'amount' => $amount,
            'reason' => '客户「' . (string)$customer['name'] . '」' . $sourceType . '绩效事实审核通过（' . $rewardNature . '）',
            'evidence_note' => 'performance_fact.fact_id=' . (int)$fact['fact_id']
                . ' cooperation_type=' . (string)$customer['cooperation_type']
                . ' reward_nature=' . $rewardNature
                . ' pool_advance=' . ($isPoolAdvance ? '1' : '0'),
            'rules_version' => 'policy-33-35-v1',
            'status' => $status,
            'occurred_time' => (int)$fact['occurred_time'],
            'create_user_id' => (int)$reviewerUserId,
            'create_time' => $now,
            'update_time' => $now,
        ];
        try {
            $candidateId = (int)Db::name('reward_candidate')->insertGetId($candidate);
        } catch (\Exception $e) {
            if (!$this->isUniqueViolation($e)) throw $e;
            $again = Db::name('reward_candidate')->where([
                'source_type' => $sourceType,
                'source_ref' => $sourceRef,
            ])->find();
            if (!$again) throw $e;
            return ['action' => 'skipped', 'cand_id' => (int)$again['cand_id']];
        }
        Db::name('reward_candidate_audit')->insert([
            'cand_id' => $candidateId,
            'operation_type' => 'performance_fact_approved',
            'old_data_json' => '',
            'new_data_json' => json_encode($candidate, JSON_UNESCAPED_UNICODE),
            'change_reason' => $sourceType . '绩效事实审核通过后生成' . $rewardNature . '候选',
            'operator_user_id' => (int)$reviewerUserId,
            'operator_name' => (string)Db::name('admin_user')->where('id', (int)$reviewerUserId)->value('realname'),
            'operation_time' => $now,
            'request_ip' => '',
            'create_time' => $now,
        ]);
        \RecordActionLog((int)$reviewerUserId, 'reward_candidate', 'save', (string)$customer['name'], '', '', '生成' . $amount . '元' . $sourceType . '奖励候选');
        return [
            'action' => 'inserted',
            'cand_id' => $candidateId,
            'amount' => $amount,
            'status' => $status,
            'pool_advance' => $isPoolAdvance,
        ];
    }

    /** 兼容原有基础核实调用。 */
    public function syncApprovedVerifyReward(array $fact, $reviewerUserId)
    {
        return $this->syncApprovedCooperationReward($fact, $reviewerUserId);
    }

    private function ensureSummary($userId, $period, $operatorUserId)
    {
        $exist = Db::name('performance')->where(['user_id' => (int)$userId, 'period' => (string)$period])->lock(true)->find();
        if ($exist) return (int)$exist['perf_id'];
        $now = time();
        $row = [
            'user_id' => (int)$userId,
            'period' => (string)$period,
            'duty_score' => 0,
            'task_score' => 0,
            'quality_score' => 0,
            'collab_score' => 0,
            'weighted_score' => 0,
            'status' => PerformanceService::SUMMARY_PENDING,
            'create_user_id' => (int)$operatorUserId,
            'create_time' => $now,
            'update_time' => $now,
        ];
        try {
            return (int)Db::name('performance')->insertGetId($row);
        } catch (\Exception $e) {
            if ($this->isUniqueViolation($e)) {
                $again = Db::name('performance')->where(['user_id' => (int)$userId, 'period' => (string)$period])->find();
                if ($again) return (int)$again['perf_id'];
            }
            throw $e;
        }
    }

    private function isUniqueViolation(\Exception $e)
    {
        $message = strtolower((string)$e->getMessage());
        return strpos($message, 'duplicate entry') !== false
            || strpos($message, 'error 1062') !== false
            || strpos($message, '[1062]') !== false;
    }

    private function shorten($value, $length)
    {
        if (function_exists('mb_substr')) return mb_substr($value, 0, $length, 'UTF-8');
        return substr($value, 0, $length);
    }
}
