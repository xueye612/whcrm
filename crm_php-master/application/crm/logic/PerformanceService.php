<?php
/**
 * P6 季度绩效规则引擎：四权重、质量三档、评级系数、本人回避。
 * 统一季度结构：核心职责40% + 重点任务/项目30% + 测试与质量20% + 协作及CRM/飞书记录10%。
 * 最终评级：优秀1.2 / 合格1.0 / 待改进0.6（人工确认）。
 * PHP 7.0 / ThinkPHP 5.0.24 兼容。
 */
namespace app\crm\logic;

use think\Db;

class PerformanceService
{
    const W_DUTY = 0.40;
    const W_TASK = 0.30;
    const W_QUALITY = 0.20;
    const W_COLLAB = 0.10;

    const TIER_GOOD = '完成良好';
    const TIER_BASIC = '基本完成';
    const TIER_IMPROVE = '需要改进';

    const RATING_EXCELLENT = '优秀';
    const RATING_QUALIFIED = '合格';
    const RATING_POOR = '待改进';

    /** 评级 → 系数 */
    public static $ratingFactors = [
        self::RATING_EXCELLENT => 1.20,
        self::RATING_QUALIFIED => 1.00,
        self::RATING_POOR => 0.60,
    ];

    /** 岗位季度绩效参考基准（V1.6 §21） */
    public static $quarterlyBase = [
        '总经理兼产品负责人' => 3000.00,
        '研发负责人'        => 3000.00,
        '技术与项目负责人'  => 2400.00,
        '客户成功工程师'    => 1500.00,
        '驻场服务专员'      => 1500.00,
        '市场运营专员'      => 1500.00,
    ];

    private static $tiers = [self::TIER_GOOD, self::TIER_BASIC, self::TIER_IMPROVE];

    public static function dictionary()
    {
        return [
            'weights' => ['duty' => self::W_DUTY, 'task' => self::W_TASK, 'quality' => self::W_QUALITY, 'collab' => self::W_COLLAB],
            'quality_tiers' => self::$tiers,
            'ratings' => array_keys(self::$ratingFactors),
            'rating_factors' => self::$ratingFactors,
            'quarterly_base' => self::$quarterlyBase,
        ];
    }

    /** 四权重加权得分（各分项 0-100） */
    public static function weightedScore($duty, $task, $quality, $collab)
    {
        return round(
            (float)$duty * self::W_DUTY + (float)$task * self::W_TASK +
            (float)$quality * self::W_QUALITY + (float)$collab * self::W_COLLAB, 2
        );
    }

    public static function isValidTier($v) { return in_array($v, self::$tiers, true); }
    public static function isValidRating($v) { return isset(self::$ratingFactors[$v]); }
    public static function ratingFactor($rating) { return self::isValidRating($rating) ? (float)self::$ratingFactors[$rating] : 1.00; }

    /** 本人回避：评定人不能等于被评定人 */
    public static function assertNotSelf($targetUserId, $reviewerUserId)
    {
        return ((int)$targetUserId > 0 && (int)$targetUserId === (int)$reviewerUserId) ? false : true;
    }
}
