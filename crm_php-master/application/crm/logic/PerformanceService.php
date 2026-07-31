<?php
/**
 * P6 季度绩效规则引擎：四权重、质量三档、评级系数、本人回避、责任认定、人工调整审计。
 *
 * 统一季度结构：核心职责40% + 重点任务/项目30% + 测试与质量20% + 协作及CRM/飞书记录10%。
 * 最终评级：优秀1.2 / 合格1.0 / 待改进0.6（人工确认）。
 *
 * 收口要点（V3）：
 *   1) 集中维护季度汇总状态与绩效事实状态常量
 *   2) 内部值仍允许英文，但所有 API 返回与页面展示必须中文
 *   3) 不再暴露 pending_confirmation / pending_review / approved / rejected / positive / negative
 *   4) 台账质量必须先登记质量问题、确认后才能生成负向事实；不再因 description='' 直接生成
 *   5) 责任认定独立流程：创建→待审核→已通过/已驳回；已通过才生成负向事实；不自动扣款
 *   6) 人工调整必须填写原因并写入审计；已确认结果不可直接覆盖
 *
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

    /** 季度汇总状态（内部值→中文） */
    const SUMMARY_PENDING  = '待确认';
    const SUMMARY_CONFIRMED = '已确认';
    const SUMMARY_RETURNED = '已退回';

    /** 绩效事实状态（内部值→中文） */
    const FACT_PENDING  = '待审核';
    const FACT_APPROVED = '已通过';
    const FACT_REJECTED = '已驳回';

    /** 事实方向（内部值→中文） */
    const DIR_POSITIVE = '正向';
    const DIR_NEGATIVE = '负向';

    /** 台账质量问题状态（中文枚举） */
    const LEDGER_Q_PENDING  = '待确认';
    const LEDGER_Q_CONFIRMED = '已确认';
    const LEDGER_Q_IGNORED  = '已忽略';
    const LEDGER_Q_FIXED    = '已修正';

    /** 责任认定状态（中文枚举） */
    const CASE_PENDING  = '认定中';
    const CASE_APPROVED = '已认定';
    const CASE_REJECTED = '已驳回';

    /** 评级 → 系数 */
    public static $ratingFactors = [
        self::RATING_EXCELLENT => 1.20,
        self::RATING_QUALIFIED => 1.00,
        self::RATING_POOR => 0.60,
    ];

    /** 岗位季度绩效参考基准（产品最新确认覆盖 V1.6 §21） */
    public static $quarterlyBase = [
        '总经理兼产品负责人' => 3000.00,
        '研发负责人'        => 3000.00,
        '技术与项目负责人'  => 2400.00,
        '客户成功工程师'    => 900.00,
        '驻场服务专员'      => 900.00,
        '市场运营专员'      => 1500.00,
    ];

    /** 绩效维度 → 中文（用于事实中心展示） */
    public static $dimensionLabels = [
        'duty'    => '核心职责',
        'task'    => '重点任务',
        'quality' => '测试与质量',
        'collab'  => '协作',
    ];

    /** 事实类型 → 中文（用于事实中心展示） */
    public static $factTypeLabels = [
        'project'   => '项目',
        'task'      => '任务',
        'test'      => '测试',
        'ledger'    => '台账',
        'reward'    => '奖励',
        'bid'       => '投标',
        'training'  => '培训',
        'manual'    => '人工补录',
        'special'   => '专项',
        'responsibility' => '责任认定',
        'implementation' => '自有产品实施',
        'outsource'       => '外包项目',
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
            // 统一中文状态字典
            'summary_status' => [
                self::SUMMARY_PENDING, self::SUMMARY_CONFIRMED, self::SUMMARY_RETURNED,
            ],
            'fact_status' => [
                self::FACT_PENDING, self::FACT_APPROVED, self::FACT_REJECTED,
            ],
            'direction' => [self::DIR_POSITIVE, self::DIR_NEGATIVE],
            'dimension_labels' => self::$dimensionLabels,
            'fact_type_labels' => self::$factTypeLabels,
            'ledger_quality_status' => [
                self::LEDGER_Q_PENDING, self::LEDGER_Q_CONFIRMED, self::LEDGER_Q_IGNORED, self::LEDGER_Q_FIXED,
            ],
            'responsibility_case_status' => [
                self::CASE_PENDING, self::CASE_APPROVED, self::CASE_REJECTED,
            ],
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

    /** 季度汇总状态：是否合法 */
    public static function isValidSummaryStatus($v)
    {
        return in_array($v, [self::SUMMARY_PENDING, self::SUMMARY_CONFIRMED, self::SUMMARY_RETURNED], true);
    }

    /** 绩效事实状态：是否合法 */
    public static function isValidFactStatus($v)
    {
        return in_array($v, [self::FACT_PENDING, self::FACT_APPROVED, self::FACT_REJECTED], true);
    }

    /** 方向：是否合法 */
    public static function isValidDirection($v)
    {
        return in_array($v, [self::DIR_POSITIVE, self::DIR_NEGATIVE], true);
    }

    /** 台账质量问题状态：是否合法 */
    public static function isValidLedgerQualityStatus($v)
    {
        return in_array($v, [self::LEDGER_Q_PENDING, self::LEDGER_Q_CONFIRMED, self::LEDGER_Q_IGNORED, self::LEDGER_Q_FIXED], true);
    }

    /** 责任认定状态：是否合法 */
    public static function isValidCaseStatus($v)
    {
        return in_array($v, [self::CASE_PENDING, self::CASE_APPROVED, self::CASE_REJECTED], true);
    }

    /** 维度中文标签 */
    public static function dimensionLabel($v)
    {
        return isset(self::$dimensionLabels[$v]) ? self::$dimensionLabels[$v] : $v;
    }

    /** 事实类型中文标签 */
    public static function factTypeLabel($v)
    {
        return isset(self::$factTypeLabels[$v]) ? self::$factTypeLabels[$v] : $v;
    }

    /**
     * 把 performance_fact 行映射为前端展示所需中文标签
     * 内部值仍保留在 status / direction / dimension / fact_type 字段，方便 SQL 查询
     * 但页面、消息、日志都通过这里转换后展示
     */
    public static function decorateFact($row)
    {
        if (!is_array($row)) return $row;
        $row['dimension_label'] = self::dimensionLabel($row['dimension'] ?? '');
        $row['fact_type_label'] = self::factTypeLabel($row['fact_type'] ?? '');
        // direction / status 直接用中文枚举值，无需转换
        return $row;
    }

    /**
     * 根据员工岗位取得季度基准金额。
     * admin_user 表岗位列名为 post（非 position）；通过 information_schema 兼容检测。
     * 未匹配返回 0（不编造）。
     */
    public static function quarterlyBaseForUser($userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) return 0.00;
        // admin_user 岗位列为 post；兼容个别环境可能使用 position
        $positionColumn = self::detectPositionColumn();
        if ($positionColumn === '') return 0.00;
        $position = Db::name('admin_user')->where('id', $userId)->value($positionColumn);
        $position = trim((string)$position);
        if ($position === '') return 0.00;
        // 直接精确匹配
        if (isset(self::$quarterlyBase[$position])) {
            return (float)self::$quarterlyBase[$position];
        }
        // 兼容大小写差异
        foreach (self::$quarterlyBase as $key => $amount) {
            if (strcasecmp($position, $key) === 0) return (float)$amount;
        }
        return 0.00;
    }

    /**
     * 检测 admin_user 表中岗位列名：优先 post，其次 position。
     * 缓存结果避免重复查询 information_schema。
     */
    private static $positionColumnCache = null;
    private static function detectPositionColumn()
    {
        if (self::$positionColumnCache !== null) return self::$positionColumnCache;
        $table = '5kcrm_admin_user';
        foreach (['post', 'position'] as $col) {
            $row = Db::query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . addslashes($table) . "' AND COLUMN_NAME='" . addslashes($col) . "'");
            if (!empty($row) && (int)$row[0]['cnt'] > 0) {
                self::$positionColumnCache = $col;
                return $col;
            }
        }
        self::$positionColumnCache = '';
        return '';
    }

    /**
     * 计算岗位基准 × 评级系数 参考结果（仅供审核，不自动发放）。
     */
    public static function referenceAmount($base, $rating)
    {
        $base = (float)$base;
        $factor = self::ratingFactor($rating);
        return round($base * $factor, 2);
    }

    // ========== 员工信息批量解析 ==========

    /**
     * 批量解析员工信息：realname, post, structure_name, thumb_img。
     * @param array $userIds
     * @return array [userId => ['realname'=>..., 'post'=>..., 'structure_name'=>..., 'thumb_img'=>...]]
     */
    public static function resolveUserInfoBatch(array $userIds)
    {
        $result = [];
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), function ($v) {
            return $v > 0;
        })));
        if (!$userIds) return $result;
        $positionColumn = self::detectPositionColumn();
        $field = 'id,realname,structure_id,thumb_img';
        if ($positionColumn !== '') $field .= ',' . $positionColumn;
        $rows = Db::name('admin_user')->whereIn('id', $userIds)->field($field)->select();
        $structureIds = [];
        foreach ($rows as $r) {
            if (!empty($r['structure_id'])) $structureIds[] = (int)$r['structure_id'];
        }
        $structMap = [];
        if ($structureIds) {
            $structRows = Db::name('admin_structure')->whereIn('id', array_unique($structureIds))->column('name', 'id');
            foreach ($structRows as $sid => $sname) {
                $structMap[(int)$sid] = $sname;
            }
        }
        foreach ($rows as $r) {
            $uid = (int)$r['id'];
            $result[$uid] = [
                'realname' => (string)($r['realname'] ?? ''),
                'post' => $positionColumn !== '' ? (string)($r[$positionColumn] ?? '') : '',
                'structure_name' => isset($structMap[(int)$r['structure_id']]) ? $structMap[(int)$r['structure_id']] : '',
                'thumb_img' => (string)($r['thumb_img'] ?? ''),
            ];
        }
        return $result;
    }

    // ========== 事实来源类型标签 ==========

    /**
     * 事实来源类型 → 中文标签（用于事实中心展示来源模块）。
     */
    public static function sourceTypeLabels()
    {
        return [
            'task_done'               => '已完成任务',
            'test_compliant'          => '测试合格',
            'test_non_compliant'      => '测试不合格',
            'reward_settled'          => '奖励结算',
            'wrk_adjust'              => 'W/R/K调整',
            'wrk_value'               => 'W/R/K评级',
            'ledger_quality_confirmed'=> '台账质量问题',
            'impl_result'             => '产品实施结果',
            'outsource_result'        => '外包项目结果',
            'manual'                  => '人工补录',
            'responsibility_case'     => '责任认定',
        ];
    }

    /**
     * 来源类型 → 中文标签。
     */
    public static function sourceTypeLabel($sourceType)
    {
        $labels = self::sourceTypeLabels();
        return isset($labels[$sourceType]) ? $labels[$sourceType] : $sourceType;
    }

    /**
     * 解析事实来源对象名称（用于追溯原始业务记录）。
     * @param string $sourceType
     * @param string $sourceId  如 'task:123:log:456'
     * @return array ['source_name'=>..., 'source_module'=>..., 'source_ref'=>...]
     */
    public static function resolveSourceObject($sourceType, $sourceId)
    {
        $sourceId = trim((string)$sourceId);
        $label = self::sourceTypeLabel($sourceType);
        // 自动采集类
        if ($sourceType === 'task_done') {
            // sourceId: task:{id}:log:{id}
            if (preg_match('/task:(\d+)/', $sourceId, $m)) {
                $name = (string)Db::name('task')->where('task_id', (int)$m[1])->value('name');
                return ['source_name' => $name ?: ('任务#' . $m[1]), 'source_module' => '任务', 'source_ref' => 'task_id=' . $m[1]];
            }
        }
        if ($sourceType === 'test_compliant' || $sourceType === 'test_non_compliant') {
            // sourceId: test:{ext_id}
            if (preg_match('/test:(\d+)/', $sourceId, $m)) {
                $ext = Db::name('task_test_ext')->where('ext_id', (int)$m[1])->find();
                if ($ext) {
                    $originName = (string)Db::name('task')->where('task_id', (int)$ext['origin_task_id'])->value('name');
                    return ['source_name' => $originName ?: ('测试#' . $m[1]), 'source_module' => '测试任务', 'source_ref' => 'ext_id=' . $m[1]];
                }
            }
        }
        if ($sourceType === 'reward_settled') {
            // sourceId: reward:{cand_id}
            if (preg_match('/reward:(\d+)/', $sourceId, $m)) {
                return ['source_name' => '奖励候选#' . $m[1], 'source_module' => '奖惩', 'source_ref' => 'cand_id=' . $m[1]];
            }
        }
        if ($sourceType === 'wrk_adjust' || $sourceType === 'wrk_value') {
            // sourceId: wrk_log:{id} 或 wrk_value:{id}:{field}
            return ['source_name' => $label, 'source_module' => 'W/R/K', 'source_ref' => $sourceId];
        }
        if ($sourceType === 'ledger_quality_confirmed') {
            // sourceId: issue:{id}
            if (preg_match('/issue:(\d+)/', $sourceId, $m)) {
                return ['source_name' => '台账质量问题#' . $m[1], 'source_module' => '台账', 'source_ref' => 'issue_id=' . $m[1]];
            }
        }
        if ($sourceType === 'impl_result') {
            // sourceId: impl:{id}
            if (preg_match('/impl:(\d+)/', $sourceId, $m)) {
                return ['source_name' => '产品实施#' . $m[1], 'source_module' => '项目实施', 'source_ref' => 'impl_id=' . $m[1]];
            }
        }
        if ($sourceType === 'outsource_result') {
            // sourceId: outsource:{id}
            if (preg_match('/outsource:(\d+)/', $sourceId, $m)) {
                return ['source_name' => '外包项目#' . $m[1], 'source_module' => '外包', 'source_ref' => 'outsource_id=' . $m[1]];
            }
        }
        if ($sourceType === 'responsibility_case') {
            // sourceId: case:{id}
            if (preg_match('/case:(\d+)/', $sourceId, $m)) {
                return ['source_name' => '责任认定#' . $m[1], 'source_module' => '责任认定', 'source_ref' => 'case_id=' . $m[1]];
            }
        }
        if ($sourceType === 'manual') {
            return ['source_name' => '人工补录', 'source_module' => '人工补录', 'source_ref' => ''];
        }
        return ['source_name' => $label, 'source_module' => $label, 'source_ref' => $sourceId];
    }

    /**
     * 装饰事实行：补充维度/类型/来源中文标签和来源对象名称。
     */
    public static function decorateFactFull($row)
    {
        if (!is_array($row)) return $row;
        $row = self::decorateFact($row);
        $source = self::resolveSourceObject($row['source_type'] ?? '', $row['source_id'] ?? '');
        $row['source_type_label'] = self::sourceTypeLabel($row['source_type'] ?? '');
        $row['source_name'] = $source['source_name'];
        $row['source_module'] = $source['source_module'];
        $row['source_ref'] = $source['source_ref'];
        $row['is_auto'] = ($row['source_type'] ?? '') !== 'manual';
        return $row;
    }

    // ========== 绩效计算说明 ==========

    /**
     * 返回绩效计算的维度明细和说明（用于前端展示得分构成）。
     * @param array $perf  performance 表行
     * @param array $factCounts  各维度事实统计 [dimension => ['positive'=>n, 'negative'=>n]]
     * @return array
     */
    public static function calculationBreakdown($perf, $factCounts = [])
    {
        $weights = [
            'duty' => self::W_DUTY,
            'task' => self::W_TASK,
            'quality' => self::W_QUALITY,
            'collab' => self::W_COLLAB,
        ];
        $dimConfig = [
            'duty' => ['label' => '核心职责', 'field' => 'duty_score'],
            'task' => ['label' => '重点任务', 'field' => 'task_score'],
            'quality' => ['label' => '测试与质量', 'field' => 'quality_score'],
            'collab' => ['label' => '协作', 'field' => 'collab_score'],
        ];
        $dimensions = [];
        $hasAnyScore = false;
        foreach ($dimConfig as $key => $cfg) {
            $score = (float)($perf[$cfg['field']] ?? 0);
            if ($score > 0) $hasAnyScore = true;
            $weightPct = (int)($weights[$key] * 100);
            $contribution = round($score * $weights[$key], 2);
            $posCount = isset($factCounts[$key]['positive']) ? (int)$factCounts[$key]['positive'] : 0;
            $negCount = isset($factCounts[$key]['negative']) ? (int)$factCounts[$key]['negative'] : 0;
            $dimensions[] = [
                'key' => $key,
                'label' => $cfg['label'],
                'score' => $score,
                'weight' => $weights[$key],
                'weight_pct' => $weightPct,
                'contribution' => $contribution,
                'positive_count' => $posCount,
                'negative_count' => $negCount,
            ];
        }
        $weightedScore = (float)($perf['weighted_score'] ?? 0);
        $rating = (string)($perf['rating'] ?? '');
        $ratingFactor = self::ratingFactor($rating);
        $status = (string)($perf['status'] ?? '');
        $statusNote = '';
        if (!$hasAnyScore) {
            $statusNote = '尚未录入维度得分，绩效分数未计算';
        } elseif ($rating === '') {
            $statusNote = '维度得分已录入，等待评定（优秀/合格/待改进）';
        } elseif ($status === '待确认') {
            $statusNote = '已评定为' . $rating . '，等待确认';
        } elseif ($status === '已确认') {
            $statusNote = '已确认，评级为' . $rating . '（系数' . $ratingFactor . '）';
        } elseif ($status === '已退回') {
            $statusNote = '已退回，待重新提交';
        }
        return [
            'dimensions' => $dimensions,
            'weighted_score' => $weightedScore,
            'has_any_score' => $hasAnyScore,
            'rating' => $rating,
            'rating_factor' => $ratingFactor,
            'status_note' => $statusNote,
            'weights_formula' => '加权得分 = 核心职责×40% + 重点任务×30% + 测试质量×20% + 协作×10%',
        ];
    }
}
