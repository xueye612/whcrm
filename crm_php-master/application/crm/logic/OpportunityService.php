<?php
/**
 * P3 行业机会规则引擎：中文阶段、固定阶段奖励与重复防护。
 * PHP 7.0 / ThinkPHP 5.0.24 兼容。
 */
namespace app\crm\logic;

use think\Db;

class OpportunityService
{
    const TYPE_DEALER    = '经销商';
    const TYPE_HOSPITAL  = '医院';
    const TYPE_OUTSOURCE = '外包';

    const STAGE_DEALER    = ['初步接触', '首项目签约', '首期回款'];
    const STAGE_HOSPITAL  = ['接触评估', '立项', '签约', '上线交付'];
    const STAGE_OUTSOURCE = ['需求沟通', '方案报价', '签约', '交付'];

    /** 固定阶段奖励（元）。制度口径：外包正式需求沟通、方案或报价均为 200 元。 */
    private static $rewards = [
        self::TYPE_OUTSOURCE => ['需求沟通' => 200.00, '方案报价' => 200.00],
        // 经销商首期回款、医院等阶段奖励未在给定制度口径中明确，默认 0，待产品确认后配置
        self::TYPE_DEALER    => [],
        self::TYPE_HOSPITAL  => [],
    ];

    public static function dictionary()
    {
        return [
            'source_types' => [self::TYPE_DEALER, self::TYPE_HOSPITAL, self::TYPE_OUTSOURCE],
            'stages' => [
                self::TYPE_DEALER    => self::STAGE_DEALER,
                self::TYPE_HOSPITAL  => self::STAGE_HOSPITAL,
                self::TYPE_OUTSOURCE => self::STAGE_OUTSOURCE,
            ],
            'rewards' => self::$rewards,
        ];
    }

    public static function isValidType($v) { return in_array($v, [self::TYPE_DEALER, self::TYPE_HOSPITAL, self::TYPE_OUTSOURCE], true); }

    public static function stagesOfType($type)
    {
        $map = [self::TYPE_DEALER => self::STAGE_DEALER, self::TYPE_HOSPITAL => self::STAGE_HOSPITAL, self::TYPE_OUTSOURCE => self::STAGE_OUTSOURCE];
        return isset($map[$type]) ? $map[$type] : [];
    }

    public static function isValidStage($type, $stage)
    {
        return in_array($stage, self::stagesOfType($type), true);
    }

    /** 固定阶段奖励金额（无配置则为 0） */
    public static function stageReward($type, $stage)
    {
        return isset(self::$rewards[$type][$stage]) ? (float)self::$rewards[$type][$stage] : 0.00;
    }

    /**
     * 自主签单医院 5% 综合池分账（制度已确认）：
     *   内部业务获取奖励 = 收入 × 2%（进入 reward_candidate，人工审核）
     *   合规商务拓展费用上限 = 收入 × 3%（进入独立 business_expense，需预算/事项/审批/凭据）
     * 返回 ['reward_amount'=>, 'expense_max'=>, 'pool_total'=>]
     */
    public static function hospitalPool($revenue)
    {
        $rev = (float)$revenue;
        return [
            'pool_total' => round($rev * 0.05, 2),
            'reward_amount' => round($rev * 0.02, 2),
            'expense_max' => round($rev * 0.03, 2),
        ];
    }

    /**
     * 重复奖励防护：同一机会同一阶段只能推进一次。
     * 返回 [bool ok, string error]。
     */
    public function canAdvanceStage($oppId, $stage)
    {
        $exist = Db::name('opportunity_stage')->where(['opp_id' => (int)$oppId, 'stage' => $stage])->find();
        if ($exist) return [false, '该阶段已推进，不可重复计入奖励'];
        return [true, ''];
    }

    /** 负责人月度奖励合计（用于月度上限展示，默认不强制） */
    public function monthlyRewardTotal($userId, $month = null)
    {
        $month = $month ?: date('Ym');
        $start = strtotime(date('Y-m-01'));
        $end = strtotime('+1 month', $start);
        $sum = Db::name('opportunity_stage')
            ->where('operator', (int)$userId)
            ->where('create_time', '>=', $start)->where('create_time', '<', $end)
            ->sum('reward_amount');
        return (float)$sum;
    }
}
