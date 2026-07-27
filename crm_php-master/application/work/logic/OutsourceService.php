<?php
/**
 * P4 外包项目规则引擎：交付等级、毛利、两类奖金池、实施三级比例分配。
 * 制度口径：实施三级默认比例 40%/28%/25%/5%/2%；以 5 个百分点为基本单位；
 *           总比例不得超过 100%；不足部分不自动分配。
 * PHP 7.0 / ThinkPHP 5.0.24 兼容。
 */
namespace app\work\logic;

use think\Db;

class OutsourceService
{
    const LEVEL_1 = '一级'; const LEVEL_2 = '二级'; const LEVEL_3 = '三级'; const LEVEL_4 = '四级';
    private static $levels = [self::LEVEL_1, self::LEVEL_2, self::LEVEL_3, self::LEVEL_4];

    /** 实施三级默认分配比例（角色=>%）。已确认口径：
     *  技术与项目负责人 40% / 客户成功工程师 28% / 研发负责人 25% / 总经理兼产品负责人 5% / 市场运营专员 2%。 */
    const DEFAULT_DIST = [
        ['role' => '技术与项目负责人', 'percentage' => 40],
        ['role' => '客户成功工程师', 'percentage' => 28],
        ['role' => '研发负责人', 'percentage' => 25],
        ['role' => '总经理兼产品负责人', 'percentage' => 5],
        ['role' => '市场运营专员', 'percentage' => 2],
    ];

    const DEFAULT_REWARD_PCT = 2.00;  // 自主签单/外包 奖励池默认 2%
    const DEFAULT_EXPENSE_PCT = 3.00; // 合规商务费用池默认 3%

    /** P4 外包 70/30 发放节奏（制度已确认）：交付阶段 70%、验收/稳定期 30% */
    const PAYOUT_PHASE1_PCT = 70.00;  // 交付阶段发放
    const PAYOUT_PHASE2_PCT = 30.00;  // 验收/稳定期发放

    /** 按 70/30 发放节奏拆分奖池金额 */
    public static function payoutSplit($rewardPool)
    {
        $pool = (float)$rewardPool;
        return [
            'phase1_deliver' => round($pool * self::PAYOUT_PHASE1_PCT / 100, 2),
            'phase2_accept' => round($pool * self::PAYOUT_PHASE2_PCT / 100, 2),
            'phase1_pct' => self::PAYOUT_PHASE1_PCT,
            'phase2_pct' => self::PAYOUT_PHASE2_PCT,
        ];
    }

    public static function dictionary()
    {
        return [
            'delivery_levels' => self::$levels,
            'default_distribution' => self::DEFAULT_DIST,
            'default_reward_pct' => self::DEFAULT_REWARD_PCT,
            'default_expense_pct' => self::DEFAULT_EXPENSE_PCT,
            'payout_rhythm' => ['phase1_deliver_pct' => self::PAYOUT_PHASE1_PCT, 'phase2_accept_pct' => self::PAYOUT_PHASE2_PCT],
        ];
    }

    public static function isValidLevel($v) { return in_array($v, self::$levels, true); }

    /** 毛利 = 实际到账收入 - 直接成本 */
    public static function computeMargin($revenue, $directCost)
    {
        return round((float)$revenue - (float)$directCost, 2);
    }

    /** 两类奖金池：奖励池与商务费用池，均按收入百分比计算 */
    public static function computePools($revenue, $rewardPct = null, $expensePct = null)
    {
        $rp = $rewardPct === null ? self::DEFAULT_REWARD_PCT : (float)$rewardPct;
        $ep = $expensePct === null ? self::DEFAULT_EXPENSE_PCT : (float)$expensePct;
        return [
            'reward_pool'  => round((float)$revenue * $rp / 100, 2),
            'expense_pool' => round((float)$revenue * $ep / 100, 2),
        ];
    }

    /**
     * 校验分配比例：每项非负、总和不超过 100。
     * （制度口径“以5个百分点为基本单位”指从默认值调整的粒度，非要求每个绝对值为5的倍数——
     *   默认比例 40/28/25/5/2 本身含 28/25/2，故不对其做5倍数硬校验。）
     * 返回 [bool, error]。
     */
    public static function validateRatios(array $ratios)
    {
        $sum = 0.0;
        foreach ($ratios as $r) {
            $p = (float)($r['percentage'] ?? 0);
            if ($p < 0) return [false, '分配比例不能为负'];
            $sum += $p;
        }
        if ($sum > 100) return [false, '总分配比例不得超过 100%'];
        return [true, ''];
    }

    /**
     * 按比例分配奖池金额；不足 100% 的部分不自动分配（保留为未分配）。
     * 返回 [['role'=>, 'percentage'=>, 'amount'=>], 未分配金额 unallocated]
     */
    public static function distribute($pool, array $ratios)
    {
        $pool = (float)$pool;
        $result = [];
        $allocatedPct = 0.0;
        foreach ($ratios as $r) {
            $pct = (float)($r['percentage'] ?? 0);
            $result[] = [
                'role' => (string)($r['role'] ?? ''),
                'percentage' => $pct,
                'amount' => round($pool * $pct / 100, 2),
            ];
            $allocatedPct += $pct;
        }
        return ['rows' => $result, 'unallocated' => round($pool * (100 - $allocatedPct) / 100, 2), 'allocated_pct' => $allocatedPct];
    }
}
