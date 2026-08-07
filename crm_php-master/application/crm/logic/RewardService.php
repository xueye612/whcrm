<?php
/**
 * P5 奖励候选规则引擎：固定金额、审核状态、本人回避、结算批次与冲销；商务费用独立。
 * 制度口径：客户成功工程师、驻场服务专员均为 900 元；
 *           外包正式需求沟通、外包方案或报价均为 200 元。
 * 系统只生成建议，不提供自动发薪/转账。
 * PHP 7.0 / ThinkPHP 5.0.24 兼容。
 */
namespace app\crm\logic;

use think\Db;

class RewardService
{
    const ST_PENDING = '待审核';
    const ST_SPECIAL = '待专项审批';   // 制度口径：每人每月合计超过 800 元进入专项审批
    const ST_APPROVED = '已通过';
    const ST_REJECTED = '已驳回';
    const ST_SETTLED = '已结算';
    const ST_OFFSET  = '已冲销';
    const ST_VOIDED  = '已作废';       // 阶段回退时标记作废，可被重新推进时激活

    /** 制度已确认阈值/比例（非“待配置”） */
    const MONTHLY_CAP = 800.00;                  // 每人每月合计超过此值 → 专项审批
    const HOSPITAL_REWARD_PCT = 2.00;            // 自主签单医院 5%综合池：内部业务获取奖励 上限2%
    const HOSPITAL_EXPENSE_PCT_MAX = 3.00;       // 同池：合规商务拓展费用 上限3%
    const HOSPITAL_POOL_PCT = 5.00;              // 自主签单医院 综合池 5%

    /** 固定建议金额（元），按来源/岗位/阶段。V1.6 §33-37 全量。 */
    const FIXED_AMOUNTS = [
        // 岗位/角色固定
        '客户成功工程师' => 900.00,
        '驻场服务专员'  => 900.00,
        // 基础核实即时奖励（不抵扣，计入800元月度专项审批）
        '经销商基础核实' => 30.00,
        '医院基础核实'   => 50.00,
        '外包基础核实'   => 50.00,
        '高质量原始数据基础批次' => 100.00,
        '高质量原始数据优质批次' => 200.00,
        // 经销商阶段（§35，预发抵扣）
        '经销商有效联系' => 200.00,
        '经销商正式交流' => 500.00,
        '经销商明确项目' => 1000.00,
        // 医院阶段（§36，预发抵扣）
        '医院有效联系'   => 300.00,
        '医院正式演示或拜访' => 800.00,
        '医院明确项目'   => 1500.00,
        // 外包阶段（§37，预发抵扣）
        '外包正式需求沟通' => 200.00,
        '外包方案或报价' => 200.00, // 产品确认=200（非V1.6的500）
        // V1.6 §50 专项奖励
        '标书主标负责人' => 500.00,
        '标书副标负责人' => 300.00,
        '标书参与投标' => 100.00,
        '培训组织10人以下' => 100.00,
        '培训组织11至50人' => 200.00,
        '培训组织50人以上' => 500.00,
        '周度会议调度' => 100.00,
        '运维文档' => 100.00,
        '正式运维汇报' => 200.00,
        // V1.6 §51 即时/重要/重大贡献（下限）
        '即时奖励' => 100.00,
        '重要贡献' => 500.00,
        '重大贡献' => 3000.00,
    ];

    private static $statuses = [self::ST_PENDING, self::ST_SPECIAL, self::ST_APPROVED, self::ST_REJECTED, self::ST_SETTLED, self::ST_OFFSET];
    private static $reviewDecisions = ['approve', 'reject'];

    public static function dictionary()
    {
        return [
            'fixed_amounts' => self::FIXED_AMOUNTS,
            'statuses' => self::$statuses,
            'monthly_cap' => self::MONTHLY_CAP,
            'hospital_pool' => ['pool_pct' => self::HOSPITAL_POOL_PCT, 'reward_pct' => self::HOSPITAL_REWARD_PCT, 'expense_pct_max' => self::HOSPITAL_EXPENSE_PCT_MAX],
        ];
    }

    /** 读取可配置项；未配置返回 null（调用方须按“待配置”处理，不得编造） */
    public static function getConfig($key)
    {
        $row = Db::name('reward_config')->where(['config_key' => $key])->find();
        if (!$row || $row['config_value'] === null || $row['config_value'] === '') return null;
        return $row['config_value'];
    }

    public static function setConfig($key, $value, $userId)
    {
        $now = time();
        $exist = Db::name('reward_config')->where(['config_key' => $key])->find();
        if ($exist) {
            Db::name('reward_config')->where(['config_key' => $key])->update(['config_value' => $value, 'update_user_id' => (int)$userId, 'update_time' => $now]);
        } else {
            Db::name('reward_config')->insert(['config_key' => $key, 'config_value' => $value, 'update_user_id' => (int)$userId, 'update_time' => $now]);
        }
        return true;
    }

    /** 全部配置项状态（值或“待配置”）；仅保留制度确无数值的项，已确认阈值/比例不入此表 */
    public static function configStatus()
    {
        $keys = ['dealer_first_payment_reward', 'outsource_business_pool_pct', 'outsource_revenue_cap'];
        $out = [];
        foreach ($keys as $k) {
            $v = self::getConfig($k);
            $out[$k] = ($v === null) ? '待配置' : $v;
        }
        return $out;
    }

    /**
     * 月度专项审批判定：每人每月合计（已通过+待审+本笔）超过 800 元 → 需专项审批。
     * 返回 [needSpecial bool, usedAmount float]。
     */
    public function checkMonthlyCap($userId, $addAmount)
    {
        $used = $this->monthlyRewardTotal($userId);
        $needSpecial = ($used + (float)$addAmount) > self::MONTHLY_CAP;
        return [$needSpecial, $used];
    }

    public static function fixedAmount($sourceType)
    {
        return isset(self::FIXED_AMOUNTS[$sourceType]) ? (float)self::FIXED_AMOUNTS[$sourceType] : 0.00;
    }

    /** 本人回避：审核人不能等于候选人 */
    public static function assertNotSelf($candidateUserId, $reviewerUserId)
    {
        return ((int)$candidateUserId > 0 && (int)$candidateUserId === (int)$reviewerUserId) ? false : true;
    }

    /** 候选汇总（按状态） */
    public function summary()
    {
        $out = [];
        foreach (self::$statuses as $s) {
            $out[$s] = (int)Db::name('reward_candidate')->where(['status' => $s])->count();
        }
        $out['approved_amount'] = (float)Db::name('reward_candidate')->where(['status' => self::ST_APPROVED])->sum('amount');
        return $out;
    }

    /** 仅统计基础核实类（经销商30/家、医院50/家、外包50/项）的当月合计，用于800元专项审批判定。
     *  不得统计 900/900/200/200、阶段奖励、项目奖金或其他候选。 */
    private static $basicVerifySources = ['经销商基础核实', '医院基础核实', '外包基础核实'];

    public function monthlyRewardTotal($userId)
    {
        $start = strtotime(date('Y-m-01'));
        $end = strtotime('+1 month', $start);
        // 仅统计正金额（奖励），处罚（负金额）不降低月度奖励累计值
        return (float)Db::name('reward_candidate')
            ->where('user_id', (int)$userId)
            ->whereIn('source_type', self::$basicVerifySources)
            ->whereIn('status', [self::ST_PENDING, self::ST_SPECIAL, self::ST_APPROVED])
            ->where('amount', '>', 0)
            ->where('create_time', '>=', $start)->where('create_time', '<', $end)
            ->sum('amount');
    }

    /**
     * 统一读取「商机阶段奖励规则」并关联商机类型与阶段的真实名称。
     * 不再依赖前端硬编码名称，所有关联基于稳定 ID（type_id / status_id）。
     *
     * 返回每行含：
     *   rule_id, type_id, type_name, type_is_active(crm_business_type.is_display),
     *   type_status(crm_business_type.status), business_category,
     *   status_id, stage_name(crm_business_status.name), stage_order(order_id),
     *   is_terminal(order_id>=99), rule_name, amount, calc_method, is_enabled,
     *   auto_generate, need_review, rules_version, description, update_time, update_user_id
     */
    public static function stageRewardRuleList($onlyActiveType = false)
    {
        // 用 r.* 避免硬编码可选列（calc_method/rule_name 等由 reward_audit 迁移补齐），
        // update_user_id 由本迁移新增，单独判断
        $fields = 'r.*, t.name as type_name, t.is_display as type_is_display, t.status as type_status, t.business_category, s.name as stage_name, s.order_id as stage_order';
        $q = Db::name('business_stage_reward_rule')->alias('r')
            ->join('__CRM_BUSINESS_TYPE__ t', 'r.type_id = t.type_id', 'LEFT')
            ->join('__CRM_BUSINESS_STATUS__ s', 'r.status_id = s.status_id', 'LEFT');
        if ($onlyActiveType) {
            $q->where('t.is_display', 1);
        }
        $rows = $q->field($fields)->order('t.is_display desc, r.type_id asc, s.order_id asc, r.rule_id asc')->select();
        if (!$rows) return [];
        foreach ($rows as &$row) {
            $row['type_name'] = isset($row['type_name']) && $row['type_name'] !== null ? $row['type_name'] : ('类型#' . ($row['type_id'] ?? 0));
            $row['stage_name'] = isset($row['stage_name']) && $row['stage_name'] !== null ? $row['stage_name'] : ($row['rule_name'] ?: '阶段#' . ($row['status_id'] ?? 0));
            $row['is_terminal'] = ((int)($row['stage_order'] ?? 0) >= 99) ? 1 : 0;
            $row['stage_order'] = (int)($row['stage_order'] ?? 0);
            $row['type_is_active'] = ((int)($row['type_is_display'] ?? 0) === 1) ? 1 : 0;
            $row['update_user_id'] = (int)($row['update_user_id'] ?? 0);
            $row['is_enabled'] = isset($row['is_enabled']) ? (int)$row['is_enabled'] : 1;
            $row['auto_generate'] = isset($row['auto_generate']) ? (int)$row['auto_generate'] : 1;
        }
        unset($row);
        return $rows;
    }

    /**
     * 读取「启用的商机类型 + 其阶段 + 是否已配置奖励」用于配置页选择器与类型/阶段总览。
     * 名称来自数据库，不硬编码。
     */
    public static function businessTypeStageTree()
    {
        $types = Db::name('crm_business_type')
            ->where('is_display', 1)
            ->order('type_id asc')
            ->field('type_id,name,business_category,status,is_display')
            ->select();
        if (!$types) return [];
        $ruleKeys = [];
        $rules = self::stageRewardRuleList(false);
        foreach ($rules as $r) {
            $ruleKeys[$r['type_id'] . ':' . $r['status_id']] = $r;
        }
        foreach ($types as &$t) {
            $stages = Db::name('crm_business_status')
                ->where('type_id', $t['type_id'])
                ->order('order_id asc')
                ->field('status_id,name,order_id,rate')
                ->select();
            if (!$stages) $stages = [];
            foreach ($stages as &$s) {
                $key = $t['type_id'] . ':' . $s['status_id'];
                $s['has_reward_rule'] = isset($ruleKeys[$key]) ? 1 : 0;
                $s['is_terminal'] = ((int)$s['order_id'] >= 99) ? 1 : 0;
            }
            unset($s);
            $t['stages'] = $stages;
        }
        unset($t);
        return $types;
    }

    /**
     * 写入阶段奖励规则变更审计（变更前后内容）。
     * 审计表 reward_rule_audit 由迁移创建；不存在时仅记日志，不中断主流程。
     */
    public static function logRuleAudit($ruleId, $operationType, $oldData, $newData, $reason, $userInfo, $ip)
    {
        $now = time();
        try {
            Db::name('reward_rule_audit')->insert([
                'rule_id'         => (int)$ruleId,
                'operation_type'  => (string)$operationType,
                'old_data_json'   => json_encode($oldData ?: [], JSON_UNESCAPED_UNICODE),
                'new_data_json'   => json_encode($newData ?: [], JSON_UNESCAPED_UNICODE),
                'change_reason'   => (string)$reason,
                'operator_user_id'=> (int)($userInfo['id'] ?? 0),
                'operator_name'   => (string)($userInfo['realname'] ?? ''),
                'operation_time'  => $now,
                'request_ip'      => (string)$ip,
                'create_time'     => $now,
            ]);
        } catch (\Exception $e) {
            \think\Log::record('reward_rule_audit 写入失败: ' . $e->getMessage(), 'error');
        }
    }

    /** 判断 reward_rule 表是否已有某列（兼容迁移未执行环境） */
    public static function ruleHasColumn($column)
    {
        $prefix = config('database.prefix') ?: '';
        $tableName = $prefix . 'business_stage_reward_rule';
        $row = Db::query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . addslashes($tableName) . "' AND COLUMN_NAME='" . addslashes($column) . "'");
        return !empty($row) && (int)$row[0]['cnt'] > 0;
    }
}
