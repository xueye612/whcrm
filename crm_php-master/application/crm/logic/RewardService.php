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

    /** 制度已确认阈值/比例（非“待配置”） */
    const MONTHLY_CAP = 800.00;                  // 每人每月合计超过此值 → 专项审批
    const HOSPITAL_REWARD_PCT = 2.00;            // 自主签单医院 5%综合池：内部业务获取奖励 上限2%
    const HOSPITAL_EXPENSE_PCT_MAX = 3.00;       // 同池：合规商务拓展费用 上限3%
    const HOSPITAL_POOL_PCT = 5.00;              // 自主签单医院 综合池 5%

    /** 固定建议金额（元），按来源/岗位。 */
    const FIXED_AMOUNTS = [
        '客户成功工程师' => 900.00,
        '驻场服务专员'  => 900.00,
        '外包需求沟通'  => 200.00,
        '外包方案报价'  => 200.00,
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

    /** 单人当月奖励合计（待审核/待专项审批/已通过，按候选人 user_id） */
    public function monthlyRewardTotal($userId)
    {
        $start = strtotime(date('Y-m-01'));
        $end = strtotime('+1 month', $start);
        return (float)Db::name('reward_candidate')
            ->where('user_id', (int)$userId)
            ->whereIn('status', [self::ST_PENDING, self::ST_SPECIAL, self::ST_APPROVED])
            ->where('create_time', '>=', $start)->where('create_time', '<', $end)
            ->sum('amount');
    }
}
