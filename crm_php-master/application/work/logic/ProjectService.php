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

    // ========== 验收三档结果 ==========
    const ACC_GOOD      = '完成良好';
    const ACC_BASIC     = '基本完成';
    const ACC_IMPROVE   = '需要改进';

    // ========== 完整性 ==========
    const COMP_FULL     = '完整';
    const COMP_PARTIAL  = '待补充';
    const COMP_MISSING  = '缺失';

    private static $types       = [self::TYPE_OWN, self::TYPE_OUTSOURCE];
    private static $levels      = [self::LEVEL_1, self::LEVEL_2, self::LEVEL_3, self::LEVEL_4];
    private static $msTypes     = [self::MS_REQUIREMENT, self::MS_DEVELOP, self::MS_TEST, self::MS_RELEASE];
    private static $msStatuses  = [self::MS_STATUS_TODO, self::MS_STATUS_DOING, self::MS_STATUS_DONE, self::MS_STATUS_OVERDUE];
    private static $accResults  = [self::ACC_GOOD, self::ACC_BASIC, self::ACC_IMPROVE];
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
            'milestone_types'  => self::$msTypes,
            'milestone_status' => self::$msStatuses,
            'acceptance_result'=> self::$accResults,
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
     * 解析为整数时间戳；空或非法返回 0
     */
    public static function parseTime($v)
    {
        if ($v === '' || $v === null) return 0;
        if (is_numeric($v)) return (int)$v;
        $ts = strtotime($v);
        return $ts === false ? 0 : $ts;
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

        $projectType   = trim((string)($data['project_type'] ?? ''));
        $implLevel     = trim((string)($data['impl_level'] ?? ''));
        $acceptResult  = trim((string)($data['acceptance_result'] ?? ''));

        if ($projectType !== '' && !self::isValidType($projectType))      return [false, '项目类型不合法'];
        if ($implLevel !== '' && !self::isValidLevel($implLevel))        return [false, '实施等级不合法'];
        if ($acceptResult !== '' && !self::isValidAccResult($acceptResult)) return [false, '验收结果不合法'];

        $now = time();
        // 传入值（未传则为 null，用于区分“未提供”与“显式清空”）
        $in = [
            'project_type'      => array_key_exists('project_type', $data) ? $projectType : null,
            'impl_level'        => array_key_exists('impl_level', $data) ? $implLevel : null,
            'acceptance_result' => array_key_exists('acceptance_result', $data) ? $acceptResult : null,
            'risk_note'         => array_key_exists('risk_note', $data) ? trim((string)$data['risk_note']) : null,
            'stability_days'    => array_key_exists('stability_days', $data) && $data['stability_days'] !== '' ? max(0, (int)$data['stability_days']) : null,
        ];
        foreach (['plan_start_time','plan_end_time','actual_start_time','actual_end_time'] as $tf) {
            $in[$tf] = (array_key_exists($tf, $data) && $data[$tf] !== '') ? self::parseTime($data[$tf]) : null;
        }

        Db::startTrans();
        try {
            $existing = Db::name('work_profile')->where(['work_id' => $workId])->find();
            if ($existing) {
                $version = (int)($data['version'] ?? 0);
                if ($version > 0 && (int)$existing['version'] !== $version) {
                    Db::rollback();
                    return [false, '数据版本已变化，请刷新后重试'];
                }
                // 合并语义：仅覆盖显式传入的字段，未传字段保留旧值
                $row = ['update_user_id' => $userId, 'update_time' => $now];
                foreach ($in as $f => $val) {
                    if ($val !== null) $row[$f] = $val;
                }
                // 设置验收结果时自动补验收人与时间
                if ($in['acceptance_result'] !== null && $in['acceptance_result'] !== '') {
                    $row['acceptance_user_id'] = $userId;
                    $row['acceptance_time']    = $now;
                }
                $row['version'] = (int)$existing['version'] + 1;
                Db::name('work_profile')->where(['work_id' => $workId, 'version' => (int)$existing['version']])->update($row);
                $newVersion = $row['version'];
            } else {
                // 首次建档：使用传入值（未传则为空/0）
                $row = [
                    'work_id'        => $workId,
                    'project_type'   => $in['project_type'] ?? '',
                    'impl_level'     => $in['impl_level'] ?? '',
                    'stability_days' => $in['stability_days'] ?? 0,
                    'acceptance_result' => $in['acceptance_result'] ?? '',
                    'plan_start_time'=> $in['plan_start_time'] ?? 0,
                    'plan_end_time'  => $in['plan_end_time'] ?? 0,
                    'actual_start_time'=> $in['actual_start_time'] ?? 0,
                    'actual_end_time'=> $in['actual_end_time'] ?? 0,
                    'risk_note'      => $in['risk_note'] ?? '',
                    'version'        => 1,
                    'create_user_id' => $userId,
                    'update_user_id' => $userId,
                    'create_time'    => $now,
                    'update_time'    => $now,
                ];
                if ($in['acceptance_result'] !== null && $in['acceptance_result'] !== '') {
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
     * 写里程碑保存/删除的统一字段校验
     */
    public function validateMilestone(array $data)
    {
        $type = trim((string)($data['milestone_type'] ?? ''));
        if (!self::isValidMsType($type)) return '里程碑类型必须为：' . implode(' / ', self::$msTypes);
        $status = trim((string)($data['status'] ?? self::MS_STATUS_TODO));
        if ($status !== '' && !self::isValidMsStatus($status)) return '里程碑状态不合法';
        return '';
    }

    public function validateKnowledge(array $data)
    {
        $type = trim((string)($data['link_type'] ?? ''));
        if (!self::isValidKnowledgeType($type)) return '知识链接类型必须为：' . implode(' / ', self::$knowledgeTypes);
        $comp = trim((string)($data['completeness_status'] ?? self::COMP_PARTIAL));
        if ($comp !== '' && !self::isValidCompleteness($comp)) return '完整性状态不合法';
        if (trim((string)($data['title'] ?? '')) === '') return '知识链接标题不能为空';
        return '';
    }
}
