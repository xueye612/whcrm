<?php
/**
 * P1 项目实施扩展规则引擎
 *
 * 集中维护项目类型、实施等级、四类里程碑、成员贡献、知识链接、
 * 验收三档结果与合法性校验，避免规则散落在控制器和前端。
 *
 * PHP 7.0 / ThinkPHP 5.0.24 兼容。
 */

namespace app\work\logic;

use think\Db;
use think\Exception;

class ProjectService
{
    // ========== 项目类型 ==========
    const TYPE_OWN        = '自有产品';
    const TYPE_OUTSOURCE  = '外包项目';

    // ========== 实施等级（一至四级） ==========
    const LEVEL_1 = '一级';
    const LEVEL_2 = '二级';
    const LEVEL_3 = '三级';
    const LEVEL_4 = '四级';

    // ========== 四类里程碑 ==========
    const MS_REQUIREMENT = '需求确认';
    const MS_DEVELOP      = '开发完成';
    const MS_TEST         = '测试通过';
    const MS_RELEASE      = '上线交付';

    // ========== 里程碑状态 ==========
    const MS_STATUS_TODO       = '未开始';
    const MS_STATUS_DOING      = '进行中';
    const MS_STATUS_DONE       = '已完成';
    const MS_STATUS_OVERDUE    = '已延期';

    // ========== 验收三档结果（V1.6 §24） ==========
    const ACC_EXCELLENT = '优质';
    const ACC_QUALIFIED = '合格';
    const ACC_IMPROVE   = '待改进';

    /** 实施等级→合格交付基础比例（V1.6 §24） */
    public static $implLevelPct = [
        self::LEVEL_1 => 5.0,
        self::LEVEL_2 => 7.0,
        self::LEVEL_3 => 10.0,
        self::LEVEL_4 => 12.0, // 上限，立项审批
    ];

    /** 三档结果系数（V1.6 §24） */
    public static $resultCoeff = [
        self::ACC_EXCELLENT => 1.10,
        self::ACC_QUALIFIED => 1.00,
        self::ACC_IMPROVE   => 0.80,
    ];

    /** P1 自有产品标准岗位分配（V1.6 §25，与P4外包42/38/10/5/5不同） */
    const DEFAULT_DIST = [
        ['role' => '技术与项目负责人', 'percentage' => 40],
        ['role' => '客户成功工程师', 'percentage' => 28],
        ['role' => '研发负责人', 'percentage' => 25],
        ['role' => '总经理兼产品负责人', 'percentage' => 5],
        ['role' => '市场运营专员', 'percentage' => 2],
    ];

    // ========== 完整性 ==========
    const COMP_FULL     = '完整';
    const COMP_PARTIAL  = '待补充';
    const COMP_MISSING  = '缺失';

    /** 数据库日期列为 INT(11)，时间戳合法上下界（有符号 INT32） */
    const TS_MIN = 1;
    const TS_MAX = 2147483647;

    /** 贡献记录状态 */
    const CONTRIB_DRAFT     = '草稿';
    const CONTRIB_CONFIRMED = '已确认';
    const CONTRIB_VOID      = '已作废';

    /** 绩效状态（与业务状态分离） */
    const PERF_EXCLUDED        = '不计入';
    const PERF_PENDING_COLLECT = '待归集';
    const PERF_PENDING_REVIEW  = '待审核';
    const PERF_APPROVED        = '已通过';
    const PERF_REJECTED        = '已驳回';

    /** P1 自有产品交付奖金池 = 到账收入 × 实施等级比例 × 结果系数（V1.6 §24） */
    public static function computeDeliveryPool($revenue, $implLevel, $result)
    {
        $pct = isset(self::$implLevelPct[$implLevel]) ? self::$implLevelPct[$implLevel] : 5.0;
        $coeff = isset(self::$resultCoeff[$result]) ? self::$resultCoeff[$result] : 1.00;
        return round((float)$revenue * $pct / 100 * $coeff, 2);
    }

    /** 绩效状态 → 中文标签（与业务状态分开，来源：performance_fact.status） */
    public static $perfStatusText = [
        self::PERF_EXCLUDED        => '不计入',
        self::PERF_PENDING_COLLECT => '待归集',
        self::PERF_PENDING_REVIEW  => '待审核',
        self::PERF_APPROVED        => '已通过',
        self::PERF_REJECTED        => '已驳回',
    ];

    /** 绩效状态 → el-tag 颜色（前端展示，info/orange/blue/success/danger） */
    public static $perfStatusColor = [
        self::PERF_EXCLUDED        => 'info',
        self::PERF_PENDING_COLLECT => 'warning',
        self::PERF_PENDING_REVIEW  => '',       // 蓝色（Element 默认主色）
        self::PERF_APPROVED        => 'success',
        self::PERF_REJECTED        => 'danger',
    ];

    /**
     * 计算两个时间戳之间包含首尾的周期天数（用于贡献人日默认值与校验）。
     * 任一不合法或结束早于开始返回 0。
     */
    public static function periodDays($startTs, $endTs)
    {
        $s = (int)$startTs;
        $e = (int)$endTs;
        if ($s < self::TS_MIN || $e < self::TS_MIN || $e < $s) return 0;
        $ds = strtotime(date('Y-m-d', $s));
        $de = strtotime(date('Y-m-d', $e));
        return (int)round(($de - $ds) / 86400) + 1;
    }

    /**
     * 里程碑绩效状态（纯逻辑，DB 成员判定由调用方传入）。
     * @param array    $m                   里程碑记录（status/responsible_user_id/actual_time）
     * @param bool     $isResponsibleMember 负责人是否为当前项目成员（由控制器查 DB 后传入）
     * @param string|null $factStatus       关联绩效事实状态（待审核/已通过/已驳回）；无事实传 null
     * @return array {status, reason, fact_id}
     */
    public static function milestonePerformanceStatus(array $m, $isResponsibleMember, $factStatus = null, $factId = 0)
    {
        if (trim((string)($m['status'] ?? '')) !== self::MS_STATUS_DONE) {
            return ['status' => self::PERF_EXCLUDED, 'reason' => '里程碑未完成', 'fact_id' => 0];
        }
        $rid = (int)($m['responsible_user_id'] ?? 0);
        if ($rid <= 0) return ['status' => self::PERF_EXCLUDED, 'reason' => '未指定负责人', 'fact_id' => 0];
        if (!$isResponsibleMember) return ['status' => self::PERF_EXCLUDED, 'reason' => '负责人不是当前项目成员', 'fact_id' => 0];
        $actual = (int)($m['actual_time'] ?? 0);
        if ($actual < self::TS_MIN || $actual > self::TS_MAX) return ['status' => self::PERF_EXCLUDED, 'reason' => '实际时间不合法', 'fact_id' => 0];
        $r = self::factStatusToPerf($factStatus);
        $r['fact_id'] = (int)$factId;
        return $r;
    }

    /**
     * 贡献记录绩效状态（纯逻辑）。仅“已确认”才可能产生绩效事实。
     */
    public static function contributionPerformanceStatus(array $c, $isMember, $factStatus = null, $factId = 0)
    {
        $st = trim((string)($c['status'] ?? self::CONTRIB_DRAFT));
        if ($st !== self::CONTRIB_CONFIRMED) {
            return ['status' => self::PERF_EXCLUDED, 'reason' => '贡献记录未确认（' . $st . '）', 'fact_id' => 0];
        }
        $uid = (int)($c['user_id'] ?? 0);
        if ($uid <= 0) return ['status' => self::PERF_EXCLUDED, 'reason' => '未指定贡献人', 'fact_id' => 0];
        if (!$isMember) return ['status' => self::PERF_EXCLUDED, 'reason' => '贡献人不是当前项目成员', 'fact_id' => 0];
        $start = (int)($c['start_time'] ?? 0);
        $end = (int)($c['end_time'] ?? 0);
        $days = self::periodDays($start, $end);
        $onSiteDays = isset($c['on_site_days']) ? (float)$c['on_site_days'] : 0;
        if ($days <= 0 || $onSiteDays <= 0) {
            return ['status' => self::PERF_EXCLUDED, 'reason' => '历史数据不完整：贡献日期或人日不合法', 'fact_id' => 0];
        }
        if ($onSiteDays > $days && trim((string)($c['evidence_note'] ?? '')) === '') {
            return ['status' => self::PERF_EXCLUDED, 'reason' => '历史数据不完整：人日超过周期但未填写说明', 'fact_id' => 0];
        }
        $r = self::factStatusToPerf($factStatus);
        $r['fact_id'] = (int)$factId;
        return $r;
    }

    /**
     * 绩效事实状态（中文）→ 绩效状态。无事实（null/空）→ 待归集。
     */
    private static function factStatusToPerf($factStatus)
    {
        $fs = trim((string)$factStatus);
        if ($fs === '待审核') return ['status' => self::PERF_PENDING_REVIEW, 'reason' => ''];
        if ($fs === '已通过') return ['status' => self::PERF_APPROVED, 'reason' => ''];
        if ($fs === '已驳回') return ['status' => self::PERF_REJECTED, 'reason' => ''];
        return ['status' => self::PERF_PENDING_COLLECT, 'reason' => ''];
    }

    private static $types       = [self::TYPE_OWN, self::TYPE_OUTSOURCE];
    private static $levels      = [self::LEVEL_1, self::LEVEL_2, self::LEVEL_3, self::LEVEL_4];
    private static $msTypes     = [self::MS_REQUIREMENT, self::MS_DEVELOP, self::MS_TEST, self::MS_RELEASE];
    private static $msStatuses  = [self::MS_STATUS_TODO, self::MS_STATUS_DOING, self::MS_STATUS_DONE, self::MS_STATUS_OVERDUE];
    private static $accResults  = [self::ACC_EXCELLENT, self::ACC_QUALIFIED, self::ACC_IMPROVE];
    private static $knowledgeTypes = ['目录', '接口', '业务规则', '开发变更', '上线模块', '使用指导'];
    private static $completeness   = [self::COMP_FULL, self::COMP_PARTIAL, self::COMP_MISSING];

    /**
     * 前后端共享字典
     */
    public static function dictionary()
    {
        return [
            'project_types'    => self::$types,
            'impl_levels'      => self::$levels,
            'impl_level_pct'   => self::$implLevelPct,
            'milestone_types'  => self::$msTypes,
            'milestone_status' => self::$msStatuses,
            'acceptance_result'=> self::$accResults,
            'result_coeff'     => self::$resultCoeff,
            'default_dist'     => self::DEFAULT_DIST,
            'knowledge_types'  => self::$knowledgeTypes,
            'completeness'     => self::$completeness,
        ];
    }

    public static function isValidType($v)       { return in_array($v, self::$types, true); }
    public static function isValidLevel($v)      { return in_array($v, self::$levels, true); }
    public static function isValidMsType($v)     { return in_array($v, self::$msTypes, true); }
    public static function isValidMsStatus($v)   { return in_array($v, self::$msStatuses, true); }
    public static function isValidAccResult($v)  { return in_array($v, self::$accResults, true); }
    public static function isValidKnowledgeType($v){ return in_array($v, self::$knowledgeTypes, true); }
    public static function isValidCompleteness($v){ return in_array($v, self::$completeness, true); }

    /**
     * 解析为整数时间戳；空或非法返回 0。
     * 统一委托 resolveTimeState，保证“校验”与“落库”使用同一严格入口。
     */
    public static function parseTime($v)
    {
        $st = self::resolveTimeState($v);
        return $st['state'] === 2 ? $st['ts'] : 0;
    }

    /**
     * 仅当字段存在且非空时解析为时间戳，否则返回 null（向后兼容的便捷方法）。
     */
    public static function parseProvidedTime(array $data, $field)
    {
        if (!array_key_exists($field, $data)) return null;
        if ($data[$field] === '' || $data[$field] === null) return null;
        return self::parseTime($data[$field]);
    }

    /**
     * 严格日期状态机。返回 ['state'=>int, 'ts'=>int]：
     *   0  字段未提交（保留旧值）—— 仅 resolveFieldTimeState 会产生该状态
     *   1  字段存在且为 null/空串（显式清空 -> 0）
     *   2  合法日期（正整数时间戳 或 严格 Y-m-d / Y-m-d H:i:s，且在 INT32 范围内）
     *   -1 数组/对象/布尔/0/负数/小数/超 INT32/无法严格解析的字符串（非法）
     *
     * 说明：
     *  - null 在此表示“显式清空”（字段是否存在由 resolveFieldTimeState 用 array_key_exists 判定）。
     *  - 不使用 strtotime()，避免其接受 'next Thursday' 等自然语言或自动纠正不存在的日期。
     *  - 所有最终时间戳必须满足 1 <= ts <= 2147483647（数据库 INT(11)）。
     */
    public static function resolveTimeState($v)
    {
        // 数组/对象/布尔：非法（不得当成“未提交”绕过校验）
        if (is_array($v) || is_object($v) || is_bool($v)) {
            return ['state' => -1, 'ts' => 0];
        }
        // null / 空串：显式清空
        if ($v === null) return ['state' => 1, 'ts' => 0];
        $s = trim((string)$v);
        if ($s === '') return ['state' => 1, 'ts' => 0];
        // 数字时间戳（int 或纯数字串）：必须 >0 且不超 INT32 上限
        if (is_int($v)) {
            return ($v >= self::TS_MIN && $v <= self::TS_MAX) ? ['state' => 2, 'ts' => $v] : ['state' => -1, 'ts' => 0];
        }
        if (preg_match('/^[0-9]+$/', $s)) {
            // 超过 10 位的数字串必然超 INT32 上限，先按长度拒绝避免溢出
            if (strlen($s) > 10) return ['state' => -1, 'ts' => 0];
            $n = (int)$s;
            return ($n >= self::TS_MIN && $n <= self::TS_MAX) ? ['state' => 2, 'ts' => $n] : ['state' => -1, 'ts' => 0];
        }
        // 严格日期解析
        $ts = self::strictParseDate($s);
        if ($ts === false) return ['state' => -1, 'ts' => 0];
        return ['state' => 2, 'ts' => $ts];
    }

    /**
     * 按字段名解析日期状态：字段不存在 -> state 0；存在 -> 走 resolveTimeState。
     * 这是识别“字段是否提交”的入口，校验方法必须用它，不能用 ?? null。
     */
    public static function resolveFieldTimeState(array $data, $field)
    {
        if (!array_key_exists($field, $data)) return ['state' => 0, 'ts' => 0];
        return self::resolveTimeState($data[$field]);
    }

    /**
     * 构造日期字段的落库值，与校验使用同一 resolveFieldTimeState 入口。
     *   state 1（显式清空）-> 写 0；
     *   state 2（合法）   -> 写时间戳；
     *   state 0（未提交） -> $defaultZeroForMissing 为 true 则写 0（新增），为 false 则不写入（更新保留旧值）。
     * @return array 字段名 => 值
     */
    public static function buildDateRow(array $data, array $fields, $defaultZeroForMissing = false)
    {
        $row = [];
        foreach ($fields as $f) {
            $st = self::resolveFieldTimeState($data, $f);
            if ($st['state'] === 1) {
                $row[$f] = 0;
            } elseif ($st['state'] === 2) {
                $row[$f] = $st['ts'];
            } elseif ($defaultZeroForMissing) {
                $row[$f] = 0;
            }
            // state 0 且非新增：不写入，保留数据库旧值
        }
        return $row;
    }

    /**
     * 严格解析 Y-m-d 或 Y-m-d H:i:s；不依赖 strtotime。
     *  - 使用 ! 前缀（!Y-m-d / !Y-m-d H:i:s）：未指定的时分秒固定为 00:00:00（服务器时区当天零点），
     *    同一 Y-m-d 在任意时刻解析得到完全相同的时间戳；Y-m-d H:i:s 完整保留传入时间。
     *  - 同时校验 getLastErrors() 的 warning_count/error_count，并将结果格式化回原串完全比对
     *    （拒绝 2026-02-30 这类被自动纠正的日期）。
     *  - 最终时间戳必须在 1 <= ts <= 2147483647 范围内（拒绝 epoch 前负时间戳与超 INT32 的日期）。
     * 无法严格解析或越界返回 false。
     */
    private static function strictParseDate($s)
    {
        if (!is_string($s) || $s === '') return false;
        $formats = [
            ['!Y-m-d',          'Y-m-d'],
            ['!Y-m-d H:i:s',    'Y-m-d H:i:s'],
        ];
        foreach ($formats as $item) {
            list($parseFmt, $cmpFmt) = $item;
            $dt = \DateTime::createFromFormat($parseFmt, $s);
            if (!($dt instanceof \DateTime)) continue;
            $errs = \DateTime::getLastErrors();
            if (self::hasDateTimeErrors($errs)) continue;
            // 回格式化必须与原串完全一致
            if ($dt->format($cmpFmt) !== $s) continue;
            $ts = $dt->getTimestamp();
            // 数据库 INT(11) 范围校验
            if ($ts < self::TS_MIN || $ts > self::TS_MAX) return false;
            return $ts;
        }
        return false;
    }

    /**
     * 判定 DateTime::getLastErrors() 是否存在错误/警告。
     * 兼容不同 PHP 版本：无错误时可能返回 false，或 warning_count=0 的数组。
     */
    private static function hasDateTimeErrors($errs)
    {
        if ($errs === false) return false;
        if (is_array($errs)) {
            $wc = isset($errs['warning_count']) ? (int)$errs['warning_count'] : 0;
            $ec = isset($errs['error_count']) ? (int)$errs['error_count'] : 0;
            return $wc > 0 || $ec > 0;
        }
        return false;
    }

    /**
     * 非负整数校验（字符串/数字均可）
     */
    public static function isNonNegativeInt($v)
    {
        if ($v === '' || $v === null) return false;
        $s = (string)$v;
        if (!preg_match('/^-?\d+$/', $s)) return false;
        return (int)$s >= 0;
    }

    /**
     * 一位小数十进制校验（对应 DECIMAL(8,1) / DECIMAL(6,1)）。
     * 返回错误信息，合法返回空串。
     * @param mixed  $value
     * @param float  $min
     * @param float  $max
     * @return string
     */
    public static function checkDecimal1($value, $min, $max)
    {
        if ($value === '' || $value === null) return '数值不能为空';
        $s = (string)$value;
        if (!preg_match('/^-?\d+(\.\d+)?$/', $s)) return '数值格式不合法';
        $f = (float)$s;
        if ($f < $min) return '数值不得为负';
        if ($f > $max) return '数值超出允许范围';
        if (preg_match('/\.\d{2,}$/', $s)) return '最多保留一位小数';
        return '';
    }

    /**
     * 规范化为一位小数（仅在校验通过后用于落库）
     */
    public static function roundDecimal1($value)
    {
        return round((float)$value, 1);
    }

    /**
     * 校验知识链接 URL：非空时只允许绝对 http/https 且主机名非空，
     * 拒绝 javascript:/data:/vbscript:/协议相对地址/相对路径及控制字符。
     * 与前端 urlGuard.js 口径保持一致。返回错误信息，合法返回空串。
     */
    public static function checkKnowledgeUrl($value, $requireWhenFull = false, $completeness = '')
    {
        $raw = ($value === null) ? '' : (string)$value;
        if ($requireWhenFull && $completeness === self::COMP_FULL && trim($raw) === '') {
            return '完整性为“完整”时地址必填';
        }
        if (trim($raw) === '') return '';
        // 控制字符（含制表符/换行/NULL）直接拒绝，避免解析绕过
        if (preg_match('/[\x00-\x1F\x7F]/', $raw)) return '地址不得包含控制字符';
        $v = trim($raw);
        // 合法 http(s) URL 不得包含原始空白
        if (preg_match('/\s/', $v)) return '地址不得包含空白字符';
        // 协议与主机（parse_url 提取）
        $scheme = parse_url($v, PHP_URL_SCHEME);
        $host = parse_url($v, PHP_URL_HOST);
        $port = parse_url($v, PHP_URL_PORT);
        $schemeLower = strtolower((string)$scheme);
        if ($schemeLower !== 'http' && $schemeLower !== 'https') {
            return '地址必须为绝对 http:// 或 https://';
        }
        // 完整 URL 合法性：结构、主机名格式、端口（与前端 urlGuard.js 同口径）
        if (!self::isValidHttpHostPort($host, $port, $v)) {
            return '地址主机或端口不合法';
        }
        return '';
    }

    /**
     * 校验主机名与端口合法性（与前端 urlGuard.js 采用相同规则）。
     * 规则：
     *  - 仅 ASCII（拒绝原始 Unicode 主机名；国际域名须传 punycode）；
     *  - 不得含原始空白、不得携带 username/password；
     *  - 结构：协议://( [IPv6] | 域名/IPv4/localhost )[:数字端口][/路径][?查询][#锚]；
     *  - 主机：合法域名 / IPv4（各段 0-255）/ localhost / 合法 [IPv6] 字面量（filter_var 真实验证）；
     *  - 端口：可选，纯数字 0-65535。
     */
    private static function isValidHttpHostPort($host, $port, $v)
    {
        // 仅允许 ASCII（拒绝原始 Unicode 主机名；国际域名需传 punycode）
        if (!preg_match('/^[\x00-\x7F]+$/', $v)) return false;
        // 不得含原始空白
        if (preg_match('/\s/', $v)) return false;
        // 不得携带 username/password
        if (parse_url($v, PHP_URL_USER) !== null || parse_url($v, PHP_URL_PASS) !== null) return false;

        // 结构：协议://( [IPv6] | 域名/IPv4/localhost )[:端口][路径][查询][锚]
        // IPv6 字面量允许 hex、冒号及点（IPv4 映射 IPv6，如 [::ffff:192.0.2.1]），
        // 合法性最终交由下方 filter_var 权威判定。
        $ipv6 = '\[[0-9a-fA-F:.]+\]';
        $name = '[a-zA-Z0-9.\-]+';
        $portG = '(?::[0-9]{1,5})?';
        $tail = '(?:/[^\s]*)?(?:\?[^\s#]*)?(?:#[^\s]*)?';
        if (!preg_match('#^https?://(?:' . $ipv6 . '|' . $name . ')' . $portG . $tail . '$#i', $v)) {
            return false;
        }

        // 端口范围
        if ($port !== false && $port !== null && (string)$port !== '') {
            if (!preg_match('/^[0-9]{1,5}$/', (string)$port)) return false;
            if ((int)$port > 65535) return false;
        }

        // IPv6：基于原始串提取方括号内容并用 filter_var 真实验证（不得仅判断首字符为 [）
        if (preg_match('#^https?://(\[[0-9a-fA-F:.]+\])#i', $v, $m)) {
            $inner = trim($m[1], '[]');
            return filter_var($inner, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }

        // 域名 / IPv4 / localhost
        if (!is_string($host) || $host === '') return false;
        $host = rtrim($host, '.');
        if ($host === '') return false;
        if (strtolower($host) === 'localhost') return true;
        if (preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $host)) {
            foreach (explode('.', $host) as $seg) {
                if ((int)$seg > 255) return false;
            }
            return true;
        }
        $labels = explode('.', $host);
        if (count($labels) < 1) return false;
        foreach ($labels as $lab) {
            if ($lab === '' || strlen($lab) > 63) return false;
            if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?$|^[a-zA-Z0-9]$/', $lab)) return false;
        }
        return true;
    }

    /**
     * 校验现场人日（DECIMAL(6,1)）
     */
    public static function checkOnSiteDays($value)
    {
        return self::checkDecimal1($value, 0, 99999.9);
    }

    /**
     * 判定用户是否为指定项目成员（含创建人/owner_user_id/work_user）。
     */
    public function isProjectMember($workId, $userId)
    {
        $workId = (int)$workId;
        $userId = (int)$userId;
        if ($workId <= 0 || $userId <= 0) return false;
        $ownerUserId = Db::name('work')->where(['work_id' => $workId])->value('owner_user_id');
        if (!empty($ownerUserId)) {
            foreach (explode(',', trim($ownerUserId, ',')) as $uid) {
                if ((int)$uid === $userId) return true;
            }
        }
        $cnt = Db::name('work_user')->where(['work_id' => $workId, 'user_id' => $userId])->count();
        return $cnt > 0;
    }

    /**
     * 服务端精确重复校验（里程碑）：work_id + milestone_type + name + plan_time + responsible_user_id。
     * 编辑时排除自身 ID。返回已存在记录或 null。
     * 迁移未执行时 responsible_user_id 列不存在，自动降级为不含该列的重复校验。
     */
    public function findDuplicateMilestone($workId, $type, $name, $planTime, $responsibleUserId, $excludeId = 0)
    {
        $where = [
            'work_id' => (int)$workId,
            'milestone_type' => (string)$type,
            'name' => (string)$name,
            'plan_time' => (int)$planTime,
        ];
        // 仅当列存在时加入 responsible_user_id 条件
        if (self::columnExists('work_milestone', 'responsible_user_id')) {
            $where['responsible_user_id'] = (int)$responsibleUserId;
        }
        $q = Db::name('work_milestone')->where($where);
        if ((int)$excludeId > 0) $q->where('milestone_id', '<>', (int)$excludeId);
        return $q->find() ?: null;
    }

    /**
     * 缓存检测表的列是否存在（迁移未执行时降级使用）。
     */
    private static $colCache = [];
    public static function columnExists($table, $column)
    {
        $key = $table . '.' . $column;
        if (isset(self::$colCache[$key])) return self::$colCache[$key];
        try {
            $prefix = '';
            try { $prefix = (string)config('database.prefix'); } catch (\Exception $e) {}
            $fullTable = $prefix . $table;
            $row = Db::query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . addslashes($fullTable) . "' AND COLUMN_NAME='" . addslashes($column) . "'");
            $exists = !empty($row) && (int)$row[0]['cnt'] > 0;
        } catch (\Exception $e) {
            $exists = false;
        }
        self::$colCache[$key] = $exists;
        return $exists;
    }

    /**
     * 服务端精确重复校验（贡献）：work_id + user_id + contribution_role + start_time + end_time。
     * 编辑时排除自身 ID。返回已存在记录或 null。
     */
    public function findDuplicateContribution($workId, $userId, $role, $startTime, $endTime, $excludeId = 0)
    {
        $q = Db::name('work_member_contribution')->where([
            'work_id' => (int)$workId,
            'user_id' => (int)$userId,
            'contribution_role' => (string)$role,
            'start_time' => (int)$startTime,
            'end_time' => (int)$endTime,
        ]);
        if ((int)$excludeId > 0) $q->where('contribution_id', '<>', (int)$excludeId);
        return $q->find() ?: null;
    }

    /**
     * 读取项目实施档案（一对一），不存在返回 null（历史项目兼容）
     */
    public function getProfile($workId)
    {
        $workId = (int)$workId;
        if ($workId <= 0) return null;
        return Db::name('work_profile')->where(['work_id' => $workId])->find() ?: null;
    }

    /**
     * upsert 实施档案（乐观锁）。返回 [bool, string|array]
     */
    public function saveProfile($workId, array $data, $userId)
    {
        $workId = (int)$workId;
        $userId = (int)$userId;
        if ($workId <= 0) return [false, '参数错误'];

        $hasType      = array_key_exists('project_type', $data);
        $hasLevel     = array_key_exists('impl_level', $data);
        $projectType  = trim((string)($data['project_type'] ?? ''));
        $implLevel    = trim((string)($data['impl_level'] ?? ''));
        $acceptResult = trim((string)($data['acceptance_result'] ?? ''));

        # project_type / impl_level 业务必填：显式提交空值必须拒绝，不能通过 API 清空必填字典字段
        if ($hasType && $projectType === '') return [false, '项目类型不能为空'];
        if ($hasLevel && $implLevel === '') return [false, '实施等级不能为空'];
        if ($projectType !== '' && !self::isValidType($projectType))      return [false, '项目类型不合法'];
        if ($implLevel !== '' && !self::isValidLevel($implLevel))        return [false, '实施等级不合法'];
        if ($acceptResult !== '' && !self::isValidAccResult($acceptResult)) return [false, '验收结果不合法'];

        # 数值字段严格校验：不得为负，小数位数与数据库 DECIMAL 一致；非法直接报错而非静默纠正
        if (array_key_exists('stability_days', $data) && $data['stability_days'] !== '' && $data['stability_days'] !== null) {
            if (!self::isNonNegativeInt($data['stability_days'])) return [false, '稳定期必须为非负整数'];
        }
        if (array_key_exists('remote_support_hours', $data) && $data['remote_support_hours'] !== '' && $data['remote_support_hours'] !== null) {
            $rshErr = self::checkDecimal1($data['remote_support_hours'], 0, 9999999.9);
            if ($rshErr !== '') return [false, $rshErr];
        }

        # 日期字段：提交了非空但无法解析必须返回明确错误，禁止静默转 0 后继续保存
        $dateFields = ['plan_start_time','plan_end_time','actual_start_time','actual_end_time'];
        foreach ($dateFields as $tf) {
            $stt = self::resolveFieldTimeState($data, $tf);
            if ($stt['state'] === -1) return [false, '日期格式不合法：' . $tf];
        }

        $now = time();

        # 先明确是新增还是更新（SELECT，非事务），并完成所有不依赖事务的校验，
        # 确保开启写事务后不存在“直接 return 而未 rollback”的路径。
        $existing = Db::name('work_profile')->where(['work_id' => $workId])->find();

        # 构造待写入字段集合（update 时仅覆盖显式提交字段；新建时全部赋值）
        $dateFields = ['plan_start_time','plan_end_time','actual_start_time','actual_end_time'];

        if ($existing) {
            # 乐观锁：必须携带 version
            $version = (int)($data['version'] ?? 0);
            if ($version <= 0) {
                return [false, '缺少数据版本，请刷新后重试'];
            }
            if ((int)$existing['version'] !== $version) {
                return [false, '数据版本已变化，请刷新后重试'];
            }

            # 合并“本次提交值”与“已有值”后做日期顺序校验，防止部分更新绕过校验
            $effective = [];
            foreach ($dateFields as $tf) {
                $stt = self::resolveFieldTimeState($data, $tf);
                if ($stt['state'] === 2) $effective[$tf] = $stt['ts'];
                elseif ($stt['state'] === 1) $effective[$tf] = 0;
                else $effective[$tf] = isset($existing[$tf]) ? (int)$existing[$tf] : 0;
            }
            if (!empty($effective['plan_start_time']) && !empty($effective['plan_end_time']) && $effective['plan_end_time'] < $effective['plan_start_time']) {
                return [false, '计划结束不得早于计划开始'];
            }
            if (!empty($effective['actual_start_time']) && !empty($effective['actual_end_time']) && $effective['actual_end_time'] < $effective['actual_start_time']) {
                return [false, '实际结束不得早于实际开始'];
            }
        } else {
            # 新增档案：project_type / impl_level 业务必填且必须属于字典
            if ($projectType === '') return [false, '项目类型不能为空'];
            if ($implLevel === '') return [false, '实施等级不能为空'];

            $effNew = [];
            foreach ($dateFields as $tf) {
                $stt = self::resolveFieldTimeState($data, $tf);
                $effNew[$tf] = ($stt['state'] === 2) ? $stt['ts'] : 0;
            }
            if (!empty($effNew['plan_start_time']) && !empty($effNew['plan_end_time']) && $effNew['plan_end_time'] < $effNew['plan_start_time']) {
                return [false, '计划结束不得早于计划开始'];
            }
            if (!empty($effNew['actual_start_time']) && !empty($effNew['actual_end_time']) && $effNew['actual_end_time'] < $effNew['actual_start_time']) {
                return [false, '实际结束不得早于实际开始'];
            }
        }

        # === 写事务：此后所有失败路径必须 rollback，成功仅 commit 一次 ===
        Db::startTrans();
        try {
            if ($existing) {
                $row = ['update_user_id' => $userId, 'update_time' => $now];
                # 必填字典字段：未提交保留旧值（不写入）；显式提交才覆盖
                if ($hasType) $row['project_type'] = $projectType;
                if ($hasLevel) $row['impl_level'] = $implLevel;
                if (array_key_exists('acceptance_result', $data)) $row['acceptance_result'] = $acceptResult;
                if (array_key_exists('risk_note', $data)) $row['risk_note'] = trim((string)$data['risk_note']);
                if (array_key_exists('personnel_change', $data)) $row['personnel_change'] = trim((string)$data['personnel_change']);
                if (array_key_exists('stability_days', $data) && $data['stability_days'] !== '' && $data['stability_days'] !== null) $row['stability_days'] = (int)$data['stability_days'];
                if (array_key_exists('remote_support_hours', $data) && $data['remote_support_hours'] !== '' && $data['remote_support_hours'] !== null) $row['remote_support_hours'] = self::roundDecimal1((float)$data['remote_support_hours']);
                foreach ($dateFields as $tf) {
                    $stt = self::resolveFieldTimeState($data, $tf);
                    if ($stt['state'] === 1) $row[$tf] = 0;            // 显式清空
                    elseif ($stt['state'] === 2) $row[$tf] = $stt['ts']; // 显式提交
                    // state 0：不写入，保留旧值
                }
                if ($acceptResult !== '') {
                    $row['acceptance_user_id'] = $userId;
                    $row['acceptance_time']    = $now;
                }
                $row['version'] = $version + 1;

                # 乐观锁闭合：更新条件含 work_id 与客户端提供的旧 version，并校验受影响行数；
                # 并发版本冲突时受影响行数为 0，必须回滚并返回版本错误，不得伪成功。
                $affected = Db::name('work_profile')->where(['work_id' => $workId, 'version' => $version])->update($row);
                if ((int)$affected !== 1) {
                    Db::rollback();
                    return [false, '数据版本已变化，请刷新后重试'];
                }
                $newVersion = $row['version'];
            } else {
                $row = [
                    'work_id'        => $workId,
                    'project_type'   => $projectType,
                    'impl_level'     => $implLevel,
                    'stability_days' => (array_key_exists('stability_days', $data) && $data['stability_days'] !== '' && $data['stability_days'] !== null) ? (int)$data['stability_days'] : 0,
                    'acceptance_result' => $acceptResult,
                    'plan_start_time'=> $effNew['plan_start_time'],
                    'plan_end_time'  => $effNew['plan_end_time'],
                    'actual_start_time'=> $effNew['actual_start_time'],
                    'actual_end_time'=> $effNew['actual_end_time'],
                    'risk_note'      => array_key_exists('risk_note', $data) ? trim((string)$data['risk_note']) : '',
                    'remote_support_hours' => (array_key_exists('remote_support_hours', $data) && $data['remote_support_hours'] !== '' && $data['remote_support_hours'] !== null) ? self::roundDecimal1((float)$data['remote_support_hours']) : 0,
                    'personnel_change' => array_key_exists('personnel_change', $data) ? trim((string)$data['personnel_change']) : '',
                    'version'        => 1,
                    'create_user_id' => $userId,
                    'update_user_id' => $userId,
                    'create_time'    => $now,
                    'update_time'    => $now,
                ];
                if ($acceptResult !== '') {
                    $row['acceptance_user_id'] = $userId;
                    $row['acceptance_time']    = $now;
                }
                Db::name('work_profile')->insert($row);
                $newVersion = 1;
            }
            Db::commit();
            return [true, ['work_id' => $workId, 'version' => $newVersion]];
        } catch (\Exception $e) {
            Db::rollback();
            return [false, '保存实施档案失败：' . $e->getMessage()];
        }
    }

    /**
     * 项目是否已具备验收前置（至少有一条已完成的里程碑）。
     * 用于约束：未达成任何里程碑时不应直接验收通过。
     */
    public function canAccept($workId)
    {
        $workId = (int)$workId;
        $done = Db::name('work_milestone')
            ->where(['work_id' => $workId, 'status' => self::MS_STATUS_DONE])
            ->count();
        return $done > 0;
    }

    /**
     * 写里程碑保存/删除的统一字段校验。
     * @param array      $data    请求参数
     * @param array|null $existing 更新时的已有记录（用于“未提交沿用旧值”的状态联动判定）
     */
    public function validateMilestone(array $data, array $existing = null)
    {
        // 必填字段：提交用提交值；未提交（更新）沿用已有值；都没有则报错（新增必填）
        $type = trim((string)self::effectiveScalar($data, 'milestone_type', $existing, ''));
        if (!self::isValidMsType($type)) return '里程碑类型必须为：' . implode(' / ', self::$msTypes);
        $name = trim((string)self::effectiveScalar($data, 'name', $existing, ''));
        if ($name === '') return '里程碑名称不能为空';
        $status = trim((string)self::effectiveScalar($data, 'status', $existing, self::MS_STATUS_TODO));
        if (!self::isValidMsStatus($status)) return '里程碑状态不合法';
        // 负责人必填（绩效归属人）；项目成员校验在控制器中用 isProjectMember 完成
        $respId = (int)self::effectiveScalar($data, 'responsible_user_id', $existing, 0);
        if ($respId <= 0) return '请选择里程碑负责人';
        // 日期严格校验：用 resolveFieldTimeState 识别字段是否提交（不得用 ?? null 丢失存在状态）
        $plan = self::resolveFieldTimeState($data, 'plan_time');
        if ($plan['state'] === -1) return '计划时间格式不合法';
        $actual = self::resolveFieldTimeState($data, 'actual_time');
        if ($actual['state'] === -1) return '实际时间格式不合法';

        // 已完成：actual_time 必须有效。未提交时沿用已有合法值；显式清空或无有效值则拒绝。
        if ($status === self::MS_STATUS_DONE) {
            $hasValidActual = ($actual['state'] === 2);
            if (!$hasValidActual && $actual['state'] === 0 && $existing !== null) {
                $exActual = isset($existing['actual_time']) ? (int)$existing['actual_time'] : 0;
                if ($exActual >= self::TS_MIN) $hasValidActual = true;
            }
            if (!$hasValidActual) return '已完成里程碑必须填写实际时间';
        }

        // 已延期：evidence_note 必须非空。未提交时沿用已有值；显式清空或已有也空则拒绝。
        if ($status === self::MS_STATUS_OVERDUE) {
            if (array_key_exists('evidence_note', $data)) {
                if (trim((string)$data['evidence_note']) === '') return '已延期需填写证据/说明';
            } else {
                $exEv = ($existing !== null && isset($existing['evidence_note'])) ? trim((string)$existing['evidence_note']) : '';
                if ($exEv === '') return '已延期需填写证据/说明';
            }
        }
        return '';
    }

    /**
     * 成员贡献字段校验。
     * @param array|null $existing 更新时的已有记录
     */
    public function validateContribution(array $data, $workId, array $existing = null)
    {
        $userId = (int)self::effectiveScalar($data, 'user_id', $existing, 0);
        if ($userId <= 0) return '请选择贡献人';
        if (!$this->isProjectMember($workId, $userId)) return '贡献人必须是当前项目成员';
        $role = trim((string)self::effectiveScalar($data, 'contribution_role', $existing, ''));
        if ($role === '') return '贡献角色不能为空';
        // 贡献状态：草稿/已确认/已作废
        $contribStatus = trim((string)self::effectiveScalar($data, 'status', $existing, self::CONTRIB_DRAFT));
        if (!in_array($contribStatus, [self::CONTRIB_DRAFT, self::CONTRIB_CONFIRMED, self::CONTRIB_VOID], true)) return '贡献状态不合法';
        if (array_key_exists('on_site_days', $data) && $data['on_site_days'] !== '' && $data['on_site_days'] !== null) {
            $err = self::checkOnSiteDays($data['on_site_days']);
            if ($err !== '') return $err;
        }
        // 日期严格校验：用 resolveFieldTimeState 识别字段是否提交（不得用 ?? null 丢失存在状态）
        $start = self::resolveFieldTimeState($data, 'start_time');
        if ($start['state'] === -1) return '开始时间格式不合法';
        $end = self::resolveFieldTimeState($data, 'end_time');
        if ($end['state'] === -1) return '结束时间格式不合法';
        // 计算有效起止（提交优先，未提交沿用已有值）后做顺序校验，防止部分更新绕过
        $effStart = ($start['state'] === 2) ? $start['ts'] : (($start['state'] === 1) ? 0 : (($existing !== null && isset($existing['start_time'])) ? (int)$existing['start_time'] : 0));
        $effEnd = ($end['state'] === 2) ? $end['ts'] : (($end['state'] === 1) ? 0 : (($existing !== null && isset($existing['end_time'])) ? (int)$existing['end_time'] : 0));
        if ($effStart >= self::TS_MIN && $effEnd >= self::TS_MIN && $effEnd < $effStart) return '结束时间不得早于开始时间';
        // 已确认贡献的必填与人日关联，全部基于最终有效值，支持只修改角色等部分更新。
        if ($contribStatus === self::CONTRIB_CONFIRMED) {
            if ($effStart < self::TS_MIN) return '已确认贡献必须填写开始时间';
            if ($effEnd < self::TS_MIN) return '已确认贡献必须填写结束时间';
            $osd = self::effectiveScalar($data, 'on_site_days', $existing, 0);
            $osdErr = self::checkOnSiteDays($osd);
            if ($osdErr !== '') return $osdErr;
            if ((float)$osd <= 0) return '已确认贡献现场人日必须大于 0';
            $periodDays = self::periodDays($effStart, $effEnd);
            if ($periodDays <= 0) return '贡献周期不合法';
            $evidence = trim((string)self::effectiveScalar($data, 'evidence_note', $existing, ''));
            if ((float)$osd > $periodDays && $evidence === '') {
                return '现场人日超过周期天数时必须填写证据/说明';
            }
        }
        return '';
    }

    /**
     * 取“本次提交值优先，否则已有值，否则默认”的标量（用于更新时未提交字段沿用旧值）。
     */
    private static function effectiveScalar(array $data, $field, array $existing = null, $default = null)
    {
        if (array_key_exists($field, $data)) return $data[$field];
        if ($existing !== null && array_key_exists($field, $existing)) return $existing[$field];
        return $default;
    }

    public function validateKnowledge(array $data)
    {
        $type = trim((string)($data['link_type'] ?? ''));
        if (!self::isValidKnowledgeType($type)) return '知识链接类型必须为：' . implode(' / ', self::$knowledgeTypes);
        $comp = trim((string)($data['completeness_status'] ?? self::COMP_PARTIAL));
        if ($comp !== '' && !self::isValidCompleteness($comp)) return '完整性状态不合法';
        if (trim((string)($data['title'] ?? '')) === '') return '知识链接标题不能为空';
        $urlErr = self::checkKnowledgeUrl($data['url'] ?? '', true, $comp);
        if ($urlErr !== '') return $urlErr;
        return '';
    }

    /**
     * 知识链接维护人归属校验（非零时必须是项目成员）
     */
    public function validateKnowledgeOwner(array $data, $workId)
    {
        $owner = (int)($data['owner_user_id'] ?? 0);
        if ($owner > 0 && !$this->isProjectMember($workId, $owner)) return '维护人必须是当前项目成员';
        return '';
    }
}
