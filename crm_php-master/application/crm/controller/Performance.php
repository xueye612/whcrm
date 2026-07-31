<?php
// +----------------------------------------------------------------------
// | P6 季度绩效：四权重汇总、质量三档、评级限制、本人回避；责任认定独立(不自动扣款)
// | 收口 V2：统一中文状态字典、RBAC 子权限、autoAggregate 持久化事实
// +----------------------------------------------------------------------
namespace app\crm\controller;

use app\crm\logic\PerformanceService;
use think\Db;
use think\Request;
use think\Hook;
use app\admin\controller\ApiCommon;

class Performance extends ApiCommon
{
    /**
     * 权限初始化
     * - 普通员工只能查看本人绩效、提交本人事实
     * - 绩效管理人员在授权人员范围内操作
     * - 审核人必须同时：不是被考核人、不是事实提交人
     * - 不把全部绩效接口放在 allow 范围
     */
    public function _initialize()
    {
        $action = [
            // 普通员工允许查看字典与本人事实/汇总查询
            'permission' => [''],
            'allow' => ['dictionary', 'summaryread', 'summarylist', 'factlist', 'factdetail']
        ];
        Hook::listen('check_auth', $action);
        if (!in_array(strtolower(Request::instance()->action()), $action['permission'])) {
            parent::_initialize();
        }
    }

    public function dictionary()
    {
        $userInfo = $this->userInfo;
        $perms = $this->listPerms((int)$userInfo['id']);
        return resultArray(['data' => array_merge(PerformanceService::dictionary(), [
            'perms' => $perms,
            'current_user_id' => (int)$userInfo['id'],
            'is_super_admin' => $this->isSuperAdmin((int)$userInfo['id']),
        ])]);
    }

    /** 保存绩效汇总：录入四项分值，自动计算加权得分；并保存参考结果。 */
    public function summarySave()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $userId = (int)($param['user_id'] ?? 0);
        $period = trim((string)($param['period'] ?? ''));
        if ($userId <= 0 || $period === '') return resultArray(['error' => 'user_id 与 period 必填']);
        // 校验员工真实存在且有效
        $empUser = Db::name('admin_user')->where(['id' => $userId, 'status' => 1])->field('id,realname')->find();
        if (!$empUser) return resultArray(['error' => '员工不存在或已禁用，不能录入绩效']);
        // 仅允许录入/调整维度得分
        if (!$this->checkPerm('perf_score_input')) {
            return resultArray(['error' => '无权录入维度得分']);
        }
        foreach (['duty_score','task_score','quality_score','collab_score'] as $f) {
            $v = (float)($param[$f] ?? 0);
            if ($v < 0 || $v > 100) return resultArray(['error' => '各项分值应在 0-100']);
        }
        $weighted = PerformanceService::weightedScore($param['duty_score'], $param['task_score'], $param['quality_score'], $param['collab_score']);
        $now = time();
        $exist = Db::name('performance')->where(['user_id' => $userId, 'period' => $period])->find();
        // 不允许直接覆盖已确认结果；必须先退回到待确认
        if ($exist && in_array($exist['status'], [PerformanceService::SUMMARY_CONFIRMED], true)) {
            return resultArray(['error' => '已确认绩效不可直接覆盖，需先退回到待确认（带审计）']);
        }
        $base = PerformanceService::quarterlyBaseForUser($userId);
        $currentRating = $exist ? (string)$exist['rating'] : '';
        $referenceAmount = PerformanceService::referenceAmount($base, $currentRating !== '' ? $currentRating : PerformanceService::RATING_QUALIFIED);
        $row = [
            'duty_score' => (float)$param['duty_score'], 'task_score' => (float)$param['task_score'],
            'quality_score' => (float)$param['quality_score'], 'collab_score' => (float)$param['collab_score'],
            'weighted_score' => $weighted,
            'update_time' => $now,
        ];
        if ($exist) {
            // 写入人工调整审计（在维度分值变化时）
            $this->recordAdjustIfChanged($exist, $row, $userInfo['id'], $now, $period, $userId, '录入维度得分');
            $this->filterOptionalPerfColumns($row);
            Db::name('performance')->where(['perf_id' => $exist['perf_id']])->update($row);
            $id = $exist['perf_id'];
        } else {
            $row['user_id'] = $userId; $row['period'] = $period; $row['status'] = PerformanceService::SUMMARY_PENDING;
            $row['rules_version'] = 'v2';
            $row['create_method'] = 'manual';
            $row['quarterly_base'] = $base;
            $row['reference_amount'] = $referenceAmount;
            $row['create_user_id'] = (int)$userInfo['id']; $row['create_time'] = $now;
            $this->filterOptionalPerfColumns($row);
            $id = Db::name('performance')->insertGetId($row);
        }
        return resultArray(['data' => [
            'perf_id' => $id, 'weighted_score' => $weighted,
            'quarterly_base' => $base,
            'reference_amount' => $referenceAmount,
            'note' => '参考结果仅用于审核，不自动发放',
        ]]);
    }

    /**
     * 批量生成季度绩效记录：为指定周期下所有有效员工创建空白绩效汇总。
     * 已存在的记录跳过（幂等）。需要 perf_score_input 权限。
     */
    public function generateQuarterly()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $period = trim((string)($param['period'] ?? ''));
        if ($period === '') return resultArray(['error' => 'period 必填（如 2026Q3）']);
        if (!$this->checkPerm('perf_score_input')) {
            return resultArray(['error' => '无权生成绩效记录']);
        }
        // 查询所有有效员工（status=1）
        $empField = 'id,realname,structure_id,post';
        $employees = Db::name('admin_user')->where(['status' => 1])->field($empField)->select();
        if (!$employees) return resultArray(['error' => '当前没有有效员工']);
        $now = time();
        $created = [];
        $skipped = [];
        foreach ($employees as $emp) {
            $eid = (int)$emp['id'];
            // 幂等：已有记录则跳过
            $exist = Db::name('performance')->where(['user_id' => $eid, 'period' => $period])->find();
            if ($exist) {
                $skipped[] = ['user_id' => $eid, 'realname' => $emp['realname'], 'reason' => '已存在'];
                continue;
            }
            $base = PerformanceService::quarterlyBaseForUser($eid);
            $newPerfRow = [
                'user_id' => $eid, 'period' => $period,
                'duty_score' => 0, 'task_score' => 0, 'quality_score' => 0, 'collab_score' => 0,
                'weighted_score' => 0, 'status' => PerformanceService::SUMMARY_PENDING,
                'rules_version' => 'v2',
                'create_method' => 'auto',
                'quarterly_base' => $base,
                'reference_amount' => PerformanceService::referenceAmount($base, PerformanceService::RATING_QUALIFIED),
                'create_user_id' => (int)$userInfo['id'],
                'create_time' => $now, 'update_time' => $now,
            ];
            $this->filterOptionalPerfColumns($newPerfRow);
            $perfId = Db::name('performance')->insertGetId($newPerfRow);
            $created[] = ['perf_id' => $perfId, 'user_id' => $eid, 'realname' => $emp['realname']];
        }
        return resultArray(['data' => [
            'period' => $period,
            'created_count' => count($created),
            'skipped_count' => count($skipped),
            'created' => $created,
            'skipped' => $skipped,
            'note' => '已为有效员工生成本季度绩效记录（已存在则跳过）',
        ]]);
    }

    /**
     * 写入人工调整审计记录：调整前后分值、原因、操作人、操作时间。
     * 仅在分值发生变化时写一条审计；调用方必须显式提供原因。
     */
    private function recordAdjustIfChanged($existRow, $newRow, $userId, $now, $period, $targetUserId, $reason)
    {
        $changes = [];
        foreach (['duty_score','task_score','quality_score','collab_score','weighted_score'] as $f) {
            $old = isset($existRow[$f]) ? (float)$existRow[$f] : 0.00;
            $new = isset($newRow[$f]) ? (float)$newRow[$f] : 0.00;
            if (abs($old - $new) >= 0.001) {
                $changes[$f] = ['from' => $old, 'to' => $new];
            }
        }
        if (!$changes) return;
        $this->safeInsertAudit([
            'perf_id' => (int)$existRow['perf_id'],
            'user_id' => (int)$targetUserId, 'period' => (string)$period,
            'changes_json' => json_encode($changes, JSON_UNESCAPED_UNICODE),
            'reason' => trim((string)$reason) !== '' ? (string)$reason : '未填写原因',
            'operator_user_id' => (int)$userId, 'create_time' => $now,
        ]);
    }

    public function summaryRead()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $perfId = (int)($param['perf_id'] ?? 0);
        if ($perfId <= 0) {
            $userId = (int)($param['user_id'] ?? (int)$userInfo['id']);
            $period = trim((string)($param['period'] ?? ''));
            $row = Db::name('performance')->where(['user_id' => $userId, 'period' => $period])->find();
        } else {
            $row = Db::name('performance')->where(['perf_id' => $perfId])->find();
        }
        // 数据范围：普通员工只能查看本人，除非拥有 perf_view_subordinates
        if ($row && (int)$row['user_id'] !== (int)$userInfo['id'] && !$this->checkPerm('perf_view_subordinates') && !$this->isSuperAdmin((int)$userInfo['id'])) {
            return resultArray(['error' => '无权查看他人绩效']);
        }
        if ($row) {
            $base = (float)($row['quarterly_base'] ?? 0);
            if ($base <= 0) {
                $base = PerformanceService::quarterlyBaseForUser((int)$row['user_id']);
            }
            $rating = (string)$row['rating'];
            $row['quarterly_base'] = $base;
            $row['reference_amount'] = PerformanceService::referenceAmount($base, $rating !== '' ? $rating : PerformanceService::RATING_QUALIFIED);
            // 员工信息
            $userMap = PerformanceService::resolveUserInfoBatch([(int)$row['user_id'], (int)$row['create_user_id'], (int)$row['reviewer_user_id']]);
            $uid = (int)$row['user_id'];
            $row['user_name'] = isset($userMap[$uid]) ? $userMap[$uid]['realname'] : '';
            $row['user_post'] = isset($userMap[$uid]) ? $userMap[$uid]['post'] : '';
            $row['user_structure'] = isset($userMap[$uid]) ? $userMap[$uid]['structure_name'] : '';
            $row['user_thumb'] = isset($userMap[$uid]) ? $userMap[$uid]['thumb_img'] : '';
            $cid = (int)$row['create_user_id'];
            $row['create_user_name'] = isset($userMap[$cid]) ? $userMap[$cid]['realname'] : '';
            $rid = (int)$row['reviewer_user_id'];
            $row['reviewer_name'] = isset($userMap[$rid]) ? $userMap[$rid]['realname'] : '';
            $row['create_method_label'] = self::createMethodLabel((string)($row['create_method'] ?? ''));
        }
        $audits = [];
        if (!empty($row['perf_id']) && $this->tableExists('5kcrm_performance_adjust_audit')) {
            $audits = Db::name('performance_adjust_audit')->where('perf_id', (int)$row['perf_id'])->order('id desc')->limit(20)->select();
            // 审计操作人姓名
            $auditUserIds = [];
            foreach ($audits as $a) { $auditUserIds[] = (int)$a['operator_user_id']; }
            $auditUserMap = PerformanceService::resolveUserInfoBatch($auditUserIds);
            foreach ($audits as &$a) {
                $a['operator_name'] = isset($auditUserMap[(int)$a['operator_user_id']]) ? $auditUserMap[(int)$a['operator_user_id']]['realname'] : '';
            }
        }
        // 计算说明 + 各维度事实统计
        $factCounts = [];
        if (!empty($row['user_id']) && !empty($row['period']) && $this->tableExists('5kcrm_performance_fact')) {
            $facts = Db::name('performance_fact')
                ->where(['user_id' => (int)$row['user_id'], 'period' => (string)$row['period']])
                ->where('status', '<>', PerformanceService::FACT_REJECTED)
                ->field('dimension,direction,count(*) as cnt')
                ->group('dimension,direction')
                ->select();
            foreach ($facts as $f) {
                $dim = (string)$f['dimension'];
                if (!isset($factCounts[$dim])) $factCounts[$dim] = ['positive' => 0, 'negative' => 0];
                if ($f['direction'] === PerformanceService::DIR_POSITIVE) $factCounts[$dim]['positive'] = (int)$f['cnt'];
                if ($f['direction'] === PerformanceService::DIR_NEGATIVE) $factCounts[$dim]['negative'] = (int)$f['cnt'];
            }
        }
        $calculation = $row ? PerformanceService::calculationBreakdown($row, $factCounts) : null;
        return resultArray(['data' => ['summary' => $row, 'adjust_audits' => $audits, 'dictionary' => PerformanceService::dictionary(), 'calculation' => $calculation]]);
    }

    public function summaryList()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $q = Db::name('performance');
        // 普通员工：只能查看本人绩效（除非拥有"查看下属/授权范围绩效"权限）
        if (!$this->checkPerm('perf_view_subordinates') && !$this->isSuperAdmin((int)$userInfo['id'])) {
            $q->where(['user_id' => (int)$userInfo['id']]);
        }
        if (!empty($param['period'])) $q->where(['period' => $param['period']]);
        if (!empty($param['user_id'])) $q->where(['user_id' => (int)$param['user_id']]);
        $list = $q->order('perf_id desc')->limit(200)->select();
        // 批量解析员工信息（姓名、岗位、部门）
        $userIds = [];
        $creatorIds = [];
        foreach ($list as $row) {
            $userIds[] = (int)$row['user_id'];
            $creatorIds[] = (int)$row['create_user_id'];
            $creatorIds[] = (int)$row['reviewer_user_id'];
        }
        $userMap = PerformanceService::resolveUserInfoBatch(array_merge($userIds, $creatorIds));
        // 补充 reference_amount + 员工信息 + 生成方式
        foreach ($list as &$row) {
            $base = (float)($row['quarterly_base'] ?? 0);
            if ($base <= 0) $base = PerformanceService::quarterlyBaseForUser((int)$row['user_id']);
            $row['quarterly_base'] = $base;
            $rating = (string)$row['rating'];
            $row['reference_amount'] = PerformanceService::referenceAmount($base, $rating !== '' ? $rating : PerformanceService::RATING_QUALIFIED);
            // 员工信息
            $uid = (int)$row['user_id'];
            $row['user_name'] = isset($userMap[$uid]) ? $userMap[$uid]['realname'] : '';
            $row['user_post'] = isset($userMap[$uid]) ? $userMap[$uid]['post'] : '';
            $row['user_structure'] = isset($userMap[$uid]) ? $userMap[$uid]['structure_name'] : '';
            $row['user_thumb'] = isset($userMap[$uid]) ? $userMap[$uid]['thumb_img'] : '';
            // 创建人信息
            $cid = (int)$row['create_user_id'];
            $row['create_user_name'] = isset($userMap[$cid]) ? $userMap[$cid]['realname'] : '';
            $rid = (int)$row['reviewer_user_id'];
            $row['reviewer_name'] = isset($userMap[$rid]) ? $userMap[$rid]['realname'] : '';
            // 生成方式
            $row['create_method_label'] = self::createMethodLabel((string)($row['create_method'] ?? ''));
        }
        return resultArray(['data' => ['list' => $list]]);
    }

    /** 生成方式标签 */
    private static function createMethodLabel($method)
    {
        if ($method === 'auto') return '系统自动归集';
        if ($method === 'manual') return '人工录入';
        return '';
    }

    /** 评级：质量三档 + 最终评级(1.2/1.0/0.6)；本人回避；仅待确认可评 */
    public function rate()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $perfId = (int)($param['perf_id'] ?? 0);
        $tier = trim((string)($param['quality_tier'] ?? ''));
        $rating = trim((string)($param['rating'] ?? ''));
        $reviewNote = trim((string)($param['review_note'] ?? ''));
        if ($perfId <= 0) return resultArray(['error' => '参数错误']);
        if (!PerformanceService::isValidTier($tier)) return resultArray(['error' => '质量三档必须为：完成良好/基本完成/需要改进']);
        if (!PerformanceService::isValidRating($rating)) return resultArray(['error' => '评级必须为：优秀/合格/待改进']);
        // 后端必须再次校验权限，不能靠隐藏按钮
        if (!$this->checkPerm('perf_final_rate')) {
            return resultArray(['error' => '无权最终评级']);
        }
        $p = Db::name('performance')->where(['perf_id' => $perfId])->find();
        if (!$p) return resultArray(['error' => '绩效汇总不存在']);
        if ($p['status'] !== PerformanceService::SUMMARY_PENDING) return resultArray(['error' => '仅待确认绩效可评定']);
        // 审核人必须同时不是被考核人
        if (!PerformanceService::assertNotSelf($p['user_id'], $userInfo['id'])) return resultArray(['error' => '本人回避：不能评定自己的绩效']);
        $factor = PerformanceService::ratingFactor($rating);
        $base = (float)($p['quarterly_base'] ?? 0);
        if ($base <= 0) $base = PerformanceService::quarterlyBaseForUser((int)$p['user_id']);
        $referenceAmount = PerformanceService::referenceAmount($base, $rating);
        $rateUpdate = [
            'quality_tier' => $tier, 'rating' => $rating,
            'rating_factor' => $factor,
            'quarterly_base' => $base,
            'reference_amount' => $referenceAmount,
            'reviewer_user_id' => (int)$userInfo['id'], 'review_time' => time(),
            'review_note' => $reviewNote,
            'status' => PerformanceService::SUMMARY_CONFIRMED, 'update_time' => time(),
        ];
        $this->filterOptionalPerfColumns($rateUpdate);
        Db::name('performance')->where(['perf_id' => $perfId])->update($rateUpdate);
        // 评级变化也写入调整审计
        $changes = [];
        if ((string)($p['quality_tier'] ?? '') !== $tier) $changes['quality_tier'] = ['from' => (string)($p['quality_tier'] ?? ''), 'to' => $tier];
        if ((string)($p['rating'] ?? '') !== $rating) $changes['rating'] = ['from' => (string)($p['rating'] ?? ''), 'to' => $rating];
        if ($changes) {
            $this->safeInsertAudit([
                'perf_id' => $perfId, 'user_id' => (int)$p['user_id'], 'period' => (string)$p['period'],
                'changes_json' => json_encode($changes, JSON_UNESCAPED_UNICODE),
                'reason' => $reviewNote !== '' ? $reviewNote : '评级/质量档次调整',
                'operator_user_id' => (int)$userInfo['id'], 'create_time' => time(),
            ]);
        }
        return resultArray(['data' => [
            'perf_id' => $perfId, 'rating' => $rating, 'rating_factor' => $factor,
            'quarterly_base' => $base, 'reference_amount' => $referenceAmount,
        ]]);
    }

    /** 退回：把已确认绩效退回到待确认（必须填写原因并写审计） */
    public function summaryReturn()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $perfId = (int)($param['perf_id'] ?? 0);
        $reason = trim((string)($param['reason'] ?? ''));
        if ($perfId <= 0) return resultArray(['error' => '参数错误']);
        if ($reason === '') return resultArray(['error' => '退回必须填写原因（写审计）']);
        if (!$this->checkPerm('perf_final_rate')) {
            return resultArray(['error' => '无权退回绩效']);
        }
        $p = Db::name('performance')->where(['perf_id' => $perfId])->find();
        if (!$p) return resultArray(['error' => '绩效汇总不存在']);
        if ($p['status'] !== PerformanceService::SUMMARY_CONFIRMED) return resultArray(['error' => '仅已确认绩效可退回']);
        if (!PerformanceService::assertNotSelf($p['user_id'], $userInfo['id'])) return resultArray(['error' => '本人回避：不能退回自己的绩效']);
        Db::name('performance')->where(['perf_id' => $perfId])->update([
            'status' => PerformanceService::SUMMARY_RETURNED,
            'update_time' => time(),
        ]);
        // 退回也写审计
        $this->safeInsertAudit([
            'perf_id' => $perfId, 'user_id' => (int)$p['user_id'], 'period' => (string)$p['period'],
            'changes_json' => json_encode(['status' => ['from' => PerformanceService::SUMMARY_CONFIRMED, 'to' => PerformanceService::SUMMARY_RETURNED]], JSON_UNESCAPED_UNICODE),
            'reason' => $reason,
            'operator_user_id' => (int)$userInfo['id'], 'create_time' => time(),
        ]);
        return resultArray(['data' => ['perf_id' => $perfId, 'status' => PerformanceService::SUMMARY_RETURNED]]);
    }

    /**
     * 重新提交：把已退回绩效回到待确认（必须填原因并写审计）。
     */
    public function summaryRecommit()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $perfId = (int)($param['perf_id'] ?? 0);
        $reason = trim((string)($param['reason'] ?? ''));
        if ($perfId <= 0) return resultArray(['error' => '参数错误']);
        if ($reason === '') return resultArray(['error' => '重新提交必须填写原因（写审计）']);
        if (!$this->checkPerm('perf_score_input')) return resultArray(['error' => '无权重新提交']);
        $p = Db::name('performance')->where(['perf_id' => $perfId])->find();
        if (!$p) return resultArray(['error' => '绩效汇总不存在']);
        if ($p['status'] !== PerformanceService::SUMMARY_RETURNED) return resultArray(['error' => '仅已退回绩效可重新提交']);
        Db::name('performance')->where(['perf_id' => $perfId])->update([
            'status' => PerformanceService::SUMMARY_PENDING, 'update_time' => time(),
        ]);
        $this->safeInsertAudit([
            'perf_id' => $perfId, 'user_id' => (int)$p['user_id'], 'period' => (string)$p['period'],
            'changes_json' => json_encode(['status' => ['from' => PerformanceService::SUMMARY_RETURNED, 'to' => PerformanceService::SUMMARY_PENDING]], JSON_UNESCAPED_UNICODE),
            'reason' => $reason,
            'operator_user_id' => (int)$userInfo['id'], 'create_time' => time(),
        ]);
        return resultArray(['data' => ['perf_id' => $perfId, 'status' => PerformanceService::SUMMARY_PENDING]]);
    }

    /**
     * 删除/作废绩效记录。
     * - 未评级、未确认且无关联事实 → 可硬删除（连同审计一起清理）
     * - 已评级/已确认/有关联事实 → 拒绝硬删除，返回提示（前端引导作废或先清理关联）
     * 需要 perf_score_input 权限。
     */
    public function summaryDelete()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $perfId = (int)($param['perf_id'] ?? 0);
        if ($perfId <= 0) return resultArray(['error' => 'perf_id 必填']);
        if (!$this->checkPerm('perf_score_input')) {
            return resultArray(['error' => '无权删除绩效记录']);
        }
        $p = Db::name('performance')->where(['perf_id' => $perfId])->find();
        if (!$p) return resultArray(['error' => '绩效记录不存在']);
        $hasRating = (string)$p['rating'] !== '';
        $isConfirmed = $p['status'] === PerformanceService::SUMMARY_CONFIRMED;
        $hasFacts = false;
        if ($this->factTableExists()) {
            $factCount = Db::name('performance_fact')->where(['perf_id' => $perfId])->count();
            $hasFacts = $factCount > 0;
        }
        // 安全规则：已评级/已确认/有关联事实的记录不可直接删除
        if ($hasRating || $isConfirmed || $hasFacts) {
            $reasons = [];
            if ($hasRating) $reasons[] = '已评级';
            if ($isConfirmed) $reasons[] = '已确认';
            if ($hasFacts) $reasons[] = '存在关联绩效事实';
            return resultArray(['error' => '该绩效记录' . implode('、', $reasons) . '，不能直接删除。请先退回并清理关联事实后再删除，或联系管理员作废。']);
        }
        // 可安全删除：连同审计一起清理
        if (self::$auditTableExists === null) {
            self::$auditTableExists = $this->tableExists('5kcrm_performance_adjust_audit');
        }
        if (self::$auditTableExists) {
            Db::name('performance_adjust_audit')->where('perf_id', $perfId)->delete();
        }
        Db::name('performance')->where(['perf_id' => $perfId])->delete();
        return resultArray(['data' => ['perf_id' => $perfId, 'deleted' => true]]);
    }

    /** 责任认定（独立，不自动扣款）：必须包含事实和证据 */
    public function caseSave()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $userId = (int)($param['user_id'] ?? 0);
        $title = trim((string)($param['title'] ?? ''));
        $description = trim((string)($param['description'] ?? ''));
        $evidence = trim((string)($param['evidence'] ?? ''));
        if ($userId <= 0 || $title === '') return resultArray(['error' => 'user_id 与 title 必填']);
        if ($description === '') return resultArray(['error' => '责任认定必须有事实描述']);
        if ($evidence === '') return resultArray(['error' => '责任认定必须有证据']);
        if (!$this->checkPerm('perf_responsibility')) {
            return resultArray(['error' => '无权责任认定']);
        }
        $now = time();
        $id = Db::name('responsibility_case')->insertGetId([
            'user_id' => $userId, 'period' => trim((string)($param['period'] ?? '')),
            'title' => $title, 'severity' => trim((string)($param['severity'] ?? '')),
            'description' => $description, 'evidence' => $evidence,
            'status' => PerformanceService::CASE_PENDING,
            'create_user_id' => (int)$userInfo['id'], 'create_time' => $now, 'update_time' => $now,
        ]);
        return resultArray(['data' => ['case_id' => $id, 'note' => '责任认定独立流程，不通过系统自动扣款']]);
    }

    public function caseList()
    {
        $userInfo = $this->userInfo;
        $param = $this->param;
        $q = Db::name('responsibility_case');
        // 普通员工：只能查看本人责任认定（除非拥有"责任认定"权限）
        if (!$this->checkPerm('perf_responsibility') && !$this->isSuperAdmin((int)$userInfo['id'])) {
            $q->where(['user_id' => (int)$userInfo['id']]);
        } else if (!empty($param['user_id'])) {
            $q->where(['user_id' => (int)$param['user_id']]);
        }
        $list = $q->order('case_id desc')->limit(200)->select();
        return resultArray(['data' => ['list' => $list]]);
    }

    /**
     * 责任认定审核：approve/reject
     * - 审核人不能是被认定人
     * - 审核人不能是责任认定提交人
     * - 审核通过后才生成负向绩效事实
     * - 不自动扣款
     */
    public function caseReview()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $caseId = (int)($param['case_id'] ?? 0);
        $decision = trim((string)($param['decision'] ?? ''));
        $reviewNote = trim((string)($param['review_note'] ?? ''));
        if ($caseId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
            return resultArray(['error' => 'case_id 和 decision(approve/reject) 必填']);
        }
        if (!$this->checkPerm('perf_responsibility')) {
            return resultArray(['error' => '无权责任认定审核']);
        }
        $case = Db::name('responsibility_case')->where(['case_id' => $caseId])->find();
        if (!$case) return resultArray(['error' => '责任认定不存在']);
        if ($case['status'] !== PerformanceService::CASE_PENDING) return resultArray(['error' => '仅认定中可审核']);
        if ((int)$case['user_id'] === (int)$userInfo['id']) {
            return resultArray(['error' => '本人回避：不能审核自己的责任认定']);
        }
        if ((int)$case['create_user_id'] === (int)$userInfo['id']) {
            return resultArray(['error' => '提交人回避：不能审核自己提交的责任认定']);
        }
        $newStatus = $decision === 'approve' ? PerformanceService::CASE_APPROVED : PerformanceService::CASE_REJECTED;
        $now = time();
        Db::startTrans();
        try {
            Db::name('responsibility_case')->where(['case_id' => $caseId])->update([
                'status' => $newStatus, 'reviewer_user_id' => (int)$userInfo['id'],
                'review_time' => $now, 'review_note' => $reviewNote, 'update_time' => $now,
            ]);
            if ($newStatus === PerformanceService::CASE_APPROVED) {
                $case['reviewer_user_id'] = (int)$userInfo['id'];
                $case['review_time'] = $now;
                $case['review_note'] = $reviewNote;
                $this->upsertResponsibilityFact($case, $userInfo['id']);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => '审核失败：' . $e->getMessage()]);
        }
        return resultArray(['data' => ['case_id' => $caseId, 'status' => $newStatus]]);
    }

    private function upsertResponsibilityFact($case, $submitterUserId)
    {
        $userId = (int)$case['user_id'];
        $period = (string)$case['period'];
        $caseId = (int)$case['case_id'];
        $sourceType = 'responsibility_case';
        $sourceId = 'case:' . $caseId;
        $now = time();
        $exist = Db::name('performance_fact')->where(['source_type' => $sourceType, 'source_id' => $sourceId, 'period' => $period])->find();
        $perfId = $this->ensurePerformanceSummary($userId, $period, $submitterUserId);
        $row = [
            'perf_id' => $perfId, 'user_id' => $userId, 'period' => $period,
            'dimension' => 'quality', 'direction' => PerformanceService::DIR_NEGATIVE,
            'fact_type' => 'responsibility',
            'title' => '责任认定：' . (string)$case['title'],
            'source_type' => $sourceType, 'source_id' => $sourceId,
            'occurred_time' => (int)($case['create_time'] ?: $now),
            'evidence' => (string)$case['evidence'] . ' | 事实：' . (string)$case['description'],
            'status' => PerformanceService::FACT_APPROVED,
            'submit_user_id' => (int)$submitterUserId,
            'reviewer_user_id' => (int)$case['reviewer_user_id'],
            'review_time' => (int)($case['review_time'] ?: $now),
            'review_note' => (string)$case['review_note'],
            'create_time' => $now, 'update_time' => $now,
        ];
        if ($exist) {
            unset($row['create_time']);
            Db::name('performance_fact')->where(['fact_id' => $exist['fact_id']])->update($row);
        } else {
            Db::name('performance_fact')->insert($row);
        }
    }

    /**
     * 台账质量问题登记：登记后状态为"待确认"。
     * 不再因 description='' 直接生成负向事实。
     */
    public function ledgerQualitySave()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $ledgerId = (int)($param['ledger_id'] ?? 0);
        $issueType = trim((string)($param['issue_type'] ?? ''));
        $issueDesc = trim((string)($param['issue_desc'] ?? ''));
        $evidence = trim((string)($param['evidence'] ?? ''));
        if ($ledgerId <= 0 || $issueType === '' || $issueDesc === '') {
            return resultArray(['error' => 'ledger_id, issue_type, issue_desc 必填']);
        }
        $allowTypes = ['invalid_relation','duplicate','time_anomaly','non_standard','missing_description','status_task_mismatch','other'];
        if (!in_array($issueType, $allowTypes, true)) {
            return resultArray(['error' => 'issue_type 不合法']);
        }
        $now = time();
        $exist = Db::name('ledger_quality_issue')->where([
            'ledger_id' => $ledgerId, 'issue_type' => $issueType,
            'status' => ['in', [PerformanceService::LEDGER_Q_PENDING, PerformanceService::LEDGER_Q_CONFIRMED]],
        ])->find();
        if ($exist) {
            return resultArray(['error' => '该台账已存在同类型未关闭质量问题']);
        }
        $id = Db::name('ledger_quality_issue')->insertGetId([
            'ledger_id' => $ledgerId, 'issue_type' => $issueType,
            'issue_desc' => $issueDesc, 'evidence' => $evidence,
            'status' => PerformanceService::LEDGER_Q_PENDING,
            'register_user_id' => (int)$userInfo['id'], 'register_time' => $now,
            'create_time' => $now, 'update_time' => $now,
        ]);
        return resultArray(['data' => ['issue_id' => $id, 'status' => PerformanceService::LEDGER_Q_PENDING]]);
    }

    /**
     * 台账质量问题审核：confirm/ignore/fix
     * - confirm：已确认 → 进入绩效归集（在 autoAggregate 中查询）
     * - ignore：已忽略 → 不得进入绩效
     * - fix：已修正 → 不得进入绩效
     * 审核人必须填写原因，且不是登记人
     */
    public function ledgerQualityReview()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $issueId = (int)($param['issue_id'] ?? 0);
        $decision = trim((string)($param['decision'] ?? ''));
        $reviewNote = trim((string)($param['review_note'] ?? ''));
        if ($issueId <= 0 || !in_array($decision, ['confirm', 'ignore', 'fix'], true)) {
            return resultArray(['error' => 'issue_id 和 decision(confirm/ignore/fix) 必填']);
        }
        if ($reviewNote === '') return resultArray(['error' => '审核必须填写原因']);
        if (!$this->checkPerm('perf_fact_review')) {
            return resultArray(['error' => '无权审核台账质量问题']);
        }
        $issue = Db::name('ledger_quality_issue')->where(['issue_id' => $issueId])->find();
        if (!$issue) return resultArray(['error' => '质量问题不存在']);
        if ($issue['status'] !== PerformanceService::LEDGER_Q_PENDING) {
            return resultArray(['error' => '仅待确认问题可审核']);
        }
        if ((int)$issue['register_user_id'] === (int)$userInfo['id']) {
            return resultArray(['error' => '登记人不能审核自己登记的问题']);
        }
        $statusMap = [
            'confirm' => PerformanceService::LEDGER_Q_CONFIRMED,
            'ignore' => PerformanceService::LEDGER_Q_IGNORED,
            'fix' => PerformanceService::LEDGER_Q_FIXED,
        ];
        $newStatus = $statusMap[$decision];
        $now = time();
        Db::name('ledger_quality_issue')->where(['issue_id' => $issueId])->update([
            'status' => $newStatus,
            'confirmer_user_id' => (int)$userInfo['id'],
            'confirm_time' => $now,
            'review_note' => $reviewNote,
            'update_time' => $now,
        ]);
        return resultArray(['data' => ['issue_id' => $issueId, 'status' => $newStatus]]);
    }

    public function ledgerQualityList()
    {
        $param = $this->param;
        $q = Db::name('ledger_quality_issue');
        if (!empty($param['status'])) $q->where('status', $param['status']);
        if (!empty($param['ledger_id'])) $q->where('ledger_id', (int)$param['ledger_id']);
        $list = $q->order('issue_id desc')->limit(200)->select();
        return resultArray(['data' => ['list' => $list]]);
    }

    /**
     * Auto-aggregate quarterly performance facts for a user.
     * 收口 V3：
     *   1) 项目任务：workflow_version=2；使用进入"已完成"的迁移日志时间（不能使用 stop_time）
     *   2) 测试任务：符合/不符合分别生成正/负向事实；使用评定时间；测试人和评定人回避
     *   3) 已结算奖励候选：使用真实结算时间（reward_candidate.settle_time 或回退 update_time）
     *   4) W/R/K：初始值、最终值和调整记录形成可追溯事实（空值不得解释为默认等级）
     *   5) 台账质量问题：仅"已确认"状态生成负向事实；"已忽略"不得进入绩效
     *   6) 项目实施/外包结果/项目任务结果
     *   7) 责任认定：已通过时由 caseReview 触发，不在自动归集中重复
     */
    public function autoAggregate()
    {
        if (!$this->factTableExists()) return resultArray(['error' => '绩效事实功能尚未启用（数据库表未创建）']);
        $param = $this->param;
        $userInfo = $this->userInfo;
        $userId = (int)($param['user_id'] ?? 0);
        $period = trim((string)($param['period'] ?? ''));
        if ($userId <= 0 || $period === '') return resultArray(['error' => 'user_id and period required']);
        $isSelf = ((int)$userInfo['id'] === $userId);
        if (!$isSelf && !$this->checkPerm('perf_auto_aggregate')) {
            return resultArray(['error' => '无权自动归集他人绩效']);
        }

        $now = time();
        $year = (int)substr($period, 0, 4);
        $q = (int)substr($period, -1);
        $qStart = mktime(0, 0, 0, ($q - 1) * 3 + 1, 1, $year);
        $qEnd = mktime(23, 59, 59, $q * 3, cal_days_in_month(CAL_GREGORIAN, $q * 3, $year), $year);

        $factsSummary = [];
        $perfId = $this->ensurePerformanceSummary($userId, $period, $userInfo['id']);

        // 1) 项目任务：workflow_version=2；以 task_transition_log 迁移到"已完成"的日志时间为准
        $taskRows = Db::name('task_transition_log')->alias('l')
            ->join('__TASK__ t', 'l.task_id = t.task_id')
            ->join('__TASK_WORKFLOW__ w', 't.task_id = w.task_id')
            ->where('t.main_user_id', $userId)
            ->where('w.workflow_version', 2)
            ->where('l.to_status', \app\work\logic\WorkflowService::STATUS_DONE)
            ->where('l.create_time', '>=', $qStart)->where('l.create_time', '<=', $qEnd)
            ->field('t.task_id, t.name, l.create_time as done_time, l.log_id, l.user_id as op_user_id')
            ->select();
        $taskInserted = 0;
        foreach ($taskRows as $t) {
            $sourceType = 'task_done';
            $sourceId = 'task:' . (int)$t['task_id'] . ':log:' . (int)$t['log_id'];
            $existFact = Db::name('performance_fact')->where(['source_type' => $sourceType, 'source_id' => $sourceId, 'period' => $period])->find();
            if ($existFact) continue;
            Db::name('performance_fact')->insertGetId([
                'perf_id' => $perfId, 'user_id' => $userId, 'period' => $period,
                'dimension' => 'task', 'direction' => PerformanceService::DIR_POSITIVE,
                'fact_type' => 'task',
                'title' => '已完成任务：' . ($t['name'] ?? ('#' . $t['task_id'])),
                'source_type' => $sourceType, 'source_id' => $sourceId,
                'occurred_time' => (int)$t['done_time'],
                'evidence' => 'task_id=' . (int)$t['task_id'] . ' done_log=' . (int)$t['log_id'] . ' op_user_id=' . (int)$t['op_user_id'] . ' workflow_version=2',
                'status' => PerformanceService::FACT_PENDING,
                'submit_user_id' => (int)$userInfo['id'],
                'create_time' => $now, 'update_time' => $now,
            ]);
            $taskInserted++;
        }
        $factsSummary['task_done_inserted'] = $taskInserted;
        $factsSummary['task_done_total'] = count($taskRows);

        // 2) 测试任务：符合/不符合分别生成正/负向事实；使用评定时间（review_time）
        $testRowsCompliant = $this->queryTestFacts($userId, $qStart, $qEnd, \app\work\logic\WorkflowService::REVIEW_COMPLIANT);
        $testRowsNonCompliant = $this->queryTestFacts($userId, $qStart, $qEnd, \app\work\logic\WorkflowService::REVIEW_NON_COMPLY);
        $testPosInserted = 0; $testNegInserted = 0;
        foreach ($testRowsCompliant as $tr) {
            if ($this->upsertTestFact($perfId, $userId, $period, $tr, true, $userInfo['id'], $now)) $testPosInserted++;
        }
        foreach ($testRowsNonCompliant as $tr) {
            if ($this->upsertTestFact($perfId, $userId, $period, $tr, false, $userInfo['id'], $now)) $testNegInserted++;
        }
        $factsSummary['test_compliant_inserted'] = $testPosInserted;
        $factsSummary['test_compliant_total'] = count($testRowsCompliant);
        $factsSummary['test_non_compliant_inserted'] = $testNegInserted;
        $factsSummary['test_non_compliant_total'] = count($testRowsNonCompliant);

        // 3) 已结算奖励候选：使用真实结算时间
        $settleTimeExpr = $this->settleTimeExpr();
        $rewardRows = Db::name('reward_candidate')->alias('c')
            ->join('__REWARD_BATCH__ b', 'c.batch_id = b.batch_id', 'LEFT')
            ->where('c.user_id', $userId)
            ->where('c.status', \app\crm\logic\RewardService::ST_SETTLED)
            ->where($settleTimeExpr, '>=', $qStart)->where($settleTimeExpr, '<=', $qEnd)
            ->field('c.cand_id, c.amount, c.source_type, ' . $settleTimeExpr . ' AS settle_time, c.reason, c.rules_version, c.batch_id')
            ->select();
        $rewardInserted = 0;
        foreach ($rewardRows as $r) {
            $sourceType = 'reward_settled';
            $sourceId = 'reward:' . (int)$r['cand_id'];
            $existFact = Db::name('performance_fact')->where(['source_type' => $sourceType, 'source_id' => $sourceId, 'period' => $period])->find();
            if ($existFact) continue;
            $settleTime = (int)$r['settle_time'];
            Db::name('performance_fact')->insertGetId([
                'perf_id' => $perfId, 'user_id' => $userId, 'period' => $period,
                'dimension' => 'collab', 'direction' => PerformanceService::DIR_POSITIVE,
                'fact_type' => 'reward',
                'title' => '奖励结算：' . ($r['reason'] ?? ('候选#' . $r['cand_id'])),
                'source_type' => $sourceType, 'source_id' => $sourceId,
                'occurred_time' => $settleTime,
                'evidence' => 'cand_id=' . (int)$r['cand_id'] . ' amount=' . $r['amount'] . ' source_type=' . $r['source_type'] . ' rules_version=' . ($r['rules_version'] ?? 'v1') . ' batch_id=' . (int)$r['batch_id'] . ' settle_time=' . date('Y-m-d H:i:s', $settleTime),
                'status' => PerformanceService::FACT_PENDING,
                'submit_user_id' => (int)$userInfo['id'],
                'create_time' => $now, 'update_time' => $now,
            ]);
            $rewardInserted++;
        }
        $factsSummary['reward_settled_inserted'] = $rewardInserted;
        $factsSummary['reward_settled_total'] = count($rewardRows);

        // 4) W/R/K：初始值、最终值和调整记录形成可追溯事实；空值不得解释为默认等级
        $wrkInserted = $this->aggregateWrkFacts($perfId, $userId, $period, $qStart, $qEnd, $userInfo['id'], $now);
        $factsSummary['wrk_inserted'] = $wrkInserted;

        // 5) 台账质量问题：仅"已确认"状态生成负向事实
        $ledgerQualityIssues = Db::name('ledger_quality_issue')->alias('qi')
            ->join('__CUSTOMER_LEDGER__ l', 'qi.ledger_id = l.ledger_id')
            ->where('l.handler_user_id', $userId)
            ->where('qi.status', PerformanceService::LEDGER_Q_CONFIRMED)
            ->where('qi.confirm_time', '>=', $qStart)->where('qi.confirm_time', '<=', $qEnd)
            ->field('qi.issue_id, qi.ledger_id, qi.issue_type, qi.issue_desc, qi.evidence, qi.confirm_time, qi.confirmer_user_id')
            ->select();
        $ledgerInserted = 0;
        foreach ($ledgerQualityIssues as $lm) {
            $sourceType = 'ledger_quality_confirmed';
            $sourceId = 'issue:' . (int)$lm['issue_id'];
            $existFact = Db::name('performance_fact')->where(['source_type' => $sourceType, 'source_id' => $sourceId, 'period' => $period])->find();
            if ($existFact) continue;
            Db::name('performance_fact')->insertGetId([
                'perf_id' => $perfId, 'user_id' => $userId, 'period' => $period,
                'dimension' => 'quality', 'direction' => PerformanceService::DIR_NEGATIVE,
                'fact_type' => 'ledger',
                'title' => '台账质量问题：' . (string)$lm['issue_type'],
                'source_type' => $sourceType, 'source_id' => $sourceId,
                'occurred_time' => (int)$lm['confirm_time'],
                'evidence' => 'issue_id=' . (int)$lm['issue_id'] . ' ledger_id=' . (int)$lm['ledger_id'] . ' confirmer_user_id=' . (int)$lm['confirmer_user_id'] . ' desc=' . (string)$lm['issue_desc'] . ' evidence=' . (string)$lm['evidence'],
                'status' => PerformanceService::FACT_PENDING,
                'submit_user_id' => (int)$userInfo['id'],
                'create_time' => $now, 'update_time' => $now,
            ]);
            $ledgerInserted++;
        }
        $factsSummary['ledger_quality_inserted'] = $ledgerInserted;
        $factsSummary['ledger_quality_total'] = count($ledgerQualityIssues);

        // 6) 项目实施/外包结果/项目任务结果
        $projectInserted = $this->aggregateProjectFacts($perfId, $userId, $period, $qStart, $qEnd, $userInfo['id'], $now);
        $factsSummary['project_result_inserted'] = $projectInserted;

        return resultArray(['data' => [
            'perf_id' => $perfId, 'period' => $period,
            'facts' => $factsSummary,
            'note' => '已幂等写入 performance_fact；台账质量问题仅已确认状态进入绩效；奖励使用真实结算时间；W/R/K 含初始/最终/调整；项目实施/外包结果已归集',
        ]]);
    }

    private function ensurePerformanceSummary($userId, $period, $operatorUserId)
    {
        $exist = Db::name('performance')->where(['user_id' => $userId, 'period' => $period])->find();
        if ($exist) return (int)$exist['perf_id'];
        $now = time();
        $base = PerformanceService::quarterlyBaseForUser($userId);
        $newRow = [
            'user_id' => $userId, 'period' => $period,
            'duty_score' => 0, 'task_score' => 0, 'quality_score' => 0, 'collab_score' => 0,
            'weighted_score' => 0, 'status' => PerformanceService::SUMMARY_PENDING, 'rules_version' => 'v2',
            'create_method' => 'auto',
            'quarterly_base' => $base,
            'reference_amount' => PerformanceService::referenceAmount($base, PerformanceService::RATING_QUALIFIED),
            'create_user_id' => (int)$operatorUserId, 'create_time' => $now, 'update_time' => $now,
        ];
        $this->filterOptionalPerfColumns($newRow);
        return (int)Db::name('performance')->insertGetId($newRow);
    }

    private function queryTestFacts($userId, $qStart, $qEnd, $reviewStatus)
    {
        // 兼容旧数据：仅对旧流程（有评定人 reviewer_user_id>0 且经过合格/不合格评定）的测试任务生成绩效事实。
        // 新流程无评定人（reviewer_user_id=0），测试人员提交反馈后直接完成，不经过评定，
        // 因此 review_status 保持 pending，不会匹配 compliant/non_compliant，不会生成错误绩效事实。
        $cols = 'ext_id, tester_user_id, reviewer_user_id, review_status, origin_task_id';
        $extra = $this->hasColumn('5kcrm_task_test_ext', 'review_time') ? ', review_time' : ', create_time AS review_time';
        return Db::name('task_test_ext')
            ->where('tester_user_id', $userId)
            ->where('review_status', $reviewStatus)
            ->where('reviewer_user_id', '<>', $userId)
            ->where('reviewer_user_id', '>', 0)
            ->field($cols . $extra)
            ->where(function ($q) use ($qStart, $qEnd) {
                $q->where('review_time', '>=', $qStart)->where('review_time', '<=', $qEnd);
            })
            ->select();
    }

    private function upsertTestFact($perfId, $userId, $period, $tr, $isCompliant, $submitterUserId, $now)
    {
        $sourceType = $isCompliant ? 'test_compliant' : 'test_non_compliant';
        $oppositeType = $isCompliant ? 'test_non_compliant' : 'test_compliant';
        $sourceId = 'test:' . (int)$tr['ext_id'];
        // 幂等：同类型+同source_id已存在则跳过
        $existFact = Db::name('performance_fact')->where(['source_type' => $sourceType, 'source_id' => $sourceId, 'period' => $period])->find();
        if ($existFact) return false;
        // 清理同一 ext_id 的旧对立事实（如先不合格后合格，删除旧的不合格事实）
        Db::name('performance_fact')
            ->where(['source_type' => $oppositeType, 'source_id' => $sourceId, 'period' => $period])
            ->delete();
        Db::name('performance_fact')->insertGetId([
            'perf_id' => $perfId, 'user_id' => $userId, 'period' => $period,
            'dimension' => 'quality', 'direction' => $isCompliant ? PerformanceService::DIR_POSITIVE : PerformanceService::DIR_NEGATIVE,
            'fact_type' => 'test',
            'title' => $isCompliant ? '测试符合要求' : '测试不符合要求',
            'source_type' => $sourceType, 'source_id' => $sourceId,
            'occurred_time' => (int)$tr['review_time'],
            'evidence' => 'task_test_ext.ext_id=' . (int)$tr['ext_id'] . ' tester_user_id=' . (int)$tr['tester_user_id'] . ' reviewer_user_id=' . (int)$tr['reviewer_user_id'] . ' review_status=' . (string)$tr['review_status'] . ' origin_task_id=' . (int)$tr['origin_task_id'] . ' review_time=' . date('Y-m-d H:i:s', (int)$tr['review_time']),
            'status' => PerformanceService::FACT_PENDING,
            'submit_user_id' => (int)$submitterUserId,
            'create_time' => $now, 'update_time' => $now,
        ]);
        return true;
    }

    /**
     * 优先使用 reward_candidate.settle_time；不存在时回退 c.update_time。
     */
    private function settleTimeExpr()
    {
        return $this->hasColumn('5kcrm_reward_candidate', 'settle_time') ? 'c.settle_time' : 'c.update_time';
    }

    /**
     * 归集 W/R/K 事实：每条 task_wrk_log 一条事实；初始值和最终值也归集。
     * 空值不得解释为默认等级。
     */
    private function aggregateWrkFacts($perfId, $userId, $period, $qStart, $qEnd, $submitterUserId, $now)
    {
        $inserted = 0;
        // W/R/K 调整记录（task_wrk_log）
        $wrkLogs = Db::name('task_wrk_log')->alias('wl')
            ->join('__TASK__ t', 'wl.task_id = t.task_id')
            ->where('t.main_user_id', $userId)
            ->where('wl.create_time', '>=', $qStart)->where('wl.create_time', '<=', $qEnd)
            ->field('wl.log_id, wl.task_id, wl.field_name, wl.old_value, wl.new_value, wl.reason, wl.user_id AS op_user_id, wl.create_time')
            ->select();
        foreach ($wrkLogs as $wl) {
            $sourceType = 'wrk_adjust';
            $sourceId = 'wrk_log:' . (int)$wl['log_id'];
            $existFact = Db::name('performance_fact')->where(['source_type' => $sourceType, 'source_id' => $sourceId, 'period' => $period])->find();
            if ($existFact) continue;
            // 空值不解释为默认等级：如果 new_value 为空，跳过本条事实
            if (trim((string)$wl['new_value']) === '') continue;
            Db::name('performance_fact')->insertGetId([
                'perf_id' => $perfId, 'user_id' => $userId, 'period' => $period,
                'dimension' => 'task', 'direction' => PerformanceService::DIR_POSITIVE,
                'fact_type' => 'task',
                'title' => 'W/R/K 调整：' . (string)$wl['field_name'] . ' ' . (string)$wl['old_value'] . ' -> ' . (string)$wl['new_value'],
                'source_type' => $sourceType, 'source_id' => $sourceId,
                'occurred_time' => (int)$wl['create_time'],
                'evidence' => 'task_id=' . (int)$wl['task_id'] . ' field=' . (string)$wl['field_name'] . ' old=' . (string)$wl['old_value'] . ' new=' . (string)$wl['new_value'] . ' reason=' . (string)$wl['reason'] . ' op_user_id=' . (int)$wl['op_user_id'],
                'status' => PerformanceService::FACT_PENDING,
                'submit_user_id' => (int)$submitterUserId,
                'create_time' => $now, 'update_time' => $now,
            ]);
            $inserted++;
        }
        // W/R/K 初始值和最终值（task_workflow.update_time 落入本季度的 init/final 写入）
        $wfRows = Db::name('task_workflow')->alias('w')
            ->join('__TASK__ t', 'w.task_id = t.task_id')
            ->where('t.main_user_id', $userId)
            ->where('w.update_time', '>=', $qStart)->where('w.update_time', '<=', $qEnd)
            ->field('w.task_id, w.init_w, w.init_r, w.init_k, w.final_w, w.final_r, w.final_k, w.update_time, w.update_user_id')
            ->select();
        foreach ($wfRows as $wf) {
            foreach (['init_w','init_r','init_k','final_w','final_r','final_k'] as $field) {
                $val = trim((string)($wf[$field] ?? ''));
                if ($val === '') continue; // 空值不解释为默认等级
                $sourceType = 'wrk_value';
                $sourceId = 'wrk_value:' . (int)$wf['task_id'] . ':' . $field;
                $existFact = Db::name('performance_fact')->where(['source_type' => $sourceType, 'source_id' => $sourceId, 'period' => $period])->find();
                if ($existFact) continue;
                Db::name('performance_fact')->insertGetId([
                    'perf_id' => $perfId, 'user_id' => $userId, 'period' => $period,
                    'dimension' => 'task', 'direction' => PerformanceService::DIR_POSITIVE,
                    'fact_type' => 'task',
                    'title' => 'W/R/K ' . $field . '=' . $val,
                    'source_type' => $sourceType, 'source_id' => $sourceId,
                    'occurred_time' => (int)$wf['update_time'],
                    'evidence' => 'task_id=' . (int)$wf['task_id'] . ' field=' . $field . ' value=' . $val . ' op_user_id=' . (int)$wf['update_user_id'],
                    'status' => PerformanceService::FACT_PENDING,
                    'submit_user_id' => (int)$submitterUserId,
                    'create_time' => $now, 'update_time' => $now,
                ]);
                $inserted++;
            }
        }
        return $inserted;
    }

    /**
     * 项目结果归集：自有产品实施、外包项目、项目任务结果。
     */
    private function aggregateProjectFacts($perfId, $userId, $period, $qStart, $qEnd, $submitterUserId, $now)
    {
        $inserted = 0;
        // 自有产品实施结果（project_implementation）
        if ($this->tableExists('5kcrm_project_implementation')) {
            try {
                $implTimeCol = $this->hasColumn('5kcrm_project_implementation', 'delivery_time') ? 'delivery_time' : 'update_time';
                $implRows = Db::name('project_implementation')->alias('pi')
                    ->where('pi.main_user_id', $userId)
                    ->where('pi.implement_result', '<>', '')
                    ->where('pi.' . $implTimeCol, '>=', $qStart)->where('pi.' . $implTimeCol, '<=', $qEnd)
                    ->field('pi.impl_id, pi.project_ref, pi.implement_result, pi.implement_level, pi.' . $implTimeCol . ' AS result_time')
                    ->select();
                foreach ($implRows as $impl) {
                    $sourceType = 'impl_result';
                    $sourceId = 'impl:' . (int)$impl['impl_id'];
                    $existFact = Db::name('performance_fact')->where(['source_type' => $sourceType, 'source_id' => $sourceId, 'period' => $period])->find();
                    if ($existFact) continue;
                    $direction = $impl['implement_result'] === '待改进' ? PerformanceService::DIR_NEGATIVE : PerformanceService::DIR_POSITIVE;
                    Db::name('performance_fact')->insertGetId([
                        'perf_id' => $perfId, 'user_id' => $userId, 'period' => $period,
                        'dimension' => 'task', 'direction' => $direction,
                        'fact_type' => 'implementation',
                        'title' => '自有产品实施：' . (string)$impl['implement_result'] . '（等级：' . (string)$impl['implement_level'] . '）',
                        'source_type' => $sourceType, 'source_id' => $sourceId,
                        'occurred_time' => (int)$impl['result_time'],
                        'evidence' => 'impl_id=' . (int)$impl['impl_id'] . ' project_ref=' . (string)$impl['project_ref'] . ' result=' . (string)$impl['implement_result'] . ' level=' . (string)$impl['implement_level'],
                        'status' => PerformanceService::FACT_PENDING,
                        'submit_user_id' => (int)$submitterUserId,
                        'create_time' => $now, 'update_time' => $now,
                    ]);
                    $inserted++;
                }
            } catch (\Exception $e) {}
        }
        // 外包项目结果（outsource_project）
        if ($this->tableExists('5kcrm_outsource_project')) {
            try {
                $outTimeCol = $this->hasColumn('5kcrm_outsource_project', 'delivery_time') ? 'delivery_time' : 'update_time';
                $outRows = Db::name('outsource_project')->alias('op')
                    ->where('op.main_user_id', $userId)
                    ->where('op.delivery_result', '<>', '')
                    ->where('op.' . $outTimeCol, '>=', $qStart)->where('op.' . $outTimeCol, '<=', $qEnd)
                    ->field('op.outsource_id, op.project_ref, op.delivery_result, op.' . $outTimeCol . ' AS result_time')
                    ->select();
                foreach ($outRows as $out) {
                    $sourceType = 'outsource_result';
                    $sourceId = 'outsource:' . (int)$out['outsource_id'];
                    $existFact = Db::name('performance_fact')->where(['source_type' => $sourceType, 'source_id' => $sourceId, 'period' => $period])->find();
                    if ($existFact) continue;
                    $direction = $out['delivery_result'] === '待改进' ? PerformanceService::DIR_NEGATIVE : PerformanceService::DIR_POSITIVE;
                    Db::name('performance_fact')->insertGetId([
                        'perf_id' => $perfId, 'user_id' => $userId, 'period' => $period,
                        'dimension' => 'task', 'direction' => $direction,
                        'fact_type' => 'outsource',
                        'title' => '外包项目：' . (string)$out['delivery_result'],
                        'source_type' => $sourceType, 'source_id' => $sourceId,
                        'occurred_time' => (int)$out['result_time'],
                        'evidence' => 'outsource_id=' . (int)$out['outsource_id'] . ' project_ref=' . (string)$out['project_ref'] . ' result=' . (string)$out['delivery_result'],
                        'status' => PerformanceService::FACT_PENDING,
                        'submit_user_id' => (int)$submitterUserId,
                        'create_time' => $now, 'update_time' => $now,
                    ]);
                    $inserted++;
                }
            } catch (\Exception $e) {}
        }
        return $inserted;
    }

    /**
     * 补录绩效事实（人工）
     */
    public function addFact()
    {
        if (!$this->factTableExists()) return resultArray(['error' => '绩效事实功能尚未启用（数据库表未创建）']);
        $param = $this->param;
        $userInfo = $this->userInfo;
        $userId = (int)($param['user_id'] ?? 0);
        $period = trim((string)($param['period'] ?? ''));
        $factType = trim((string)($param['fact_type'] ?? ''));
        $title = trim((string)($param['title'] ?? ''));
        $evidence = trim((string)($param['evidence'] ?? ''));
        $occurredTime = (string)($param['occurred_time'] ?? '');
        $relatedRef = trim((string)($param['related_ref'] ?? ''));
        $direction = trim((string)($param['direction'] ?? PerformanceService::DIR_POSITIVE));
        if (!PerformanceService::isValidDirection($direction)) {
            return resultArray(['error' => '方向必须为：正向/负向']);
        }
        if ($userId <= 0 || $period === '' || $title === '' || $occurredTime === '') {
            return resultArray(['error' => 'user_id, period, title, occurred_time required']);
        }
        $isSelf = ((int)$userInfo['id'] === $userId);
        if (!$isSelf && !$this->checkPerm('perf_fact_input')) {
            return resultArray(['error' => '无权为他人补录事实']);
        }

        $now = time();
        $id = Db::name('performance_fact')->insertGetId([
            'perf_id' => 0, 'user_id' => $userId, 'period' => $period,
            'dimension' => trim((string)($param['dimension'] ?? 'task')),
            'direction' => $direction,
            'fact_type' => $factType,
            'title' => $title,
            'source_type' => 'manual',
            'source_id' => 'manual:' . $userId . ':' . $now,
            'occurred_time' => strtotime($occurredTime) ?: $now,
            'evidence' => $evidence . ' | ref: ' . $relatedRef,
            'status' => PerformanceService::FACT_PENDING,
            'submit_user_id' => (int)$userInfo['id'],
            'create_time' => $now, 'update_time' => $now,
        ]);
        return resultArray(['data' => ['fact_id' => $id, 'note' => '事实已提交，待审核；不自动扣款']]);
    }

    /**
     * 绩效事实列表
     * 返回每条事实的完整展示信息：维度/类型中文、来源对象名称、采集方式、提交人姓名。
     */
    public function factList()
    {
        if (!$this->factTableExists()) return resultArray(['error' => '绩效事实功能尚未启用（数据库表未创建）']);
        $param = $this->param;
        $userInfo = $this->userInfo;
        $userId = (int)($param['user_id'] ?? 0);
        $period = trim((string)($param['period'] ?? ''));
        $q = Db::name('performance_fact');
        // 普通员工只能查看本人事实（除非拥有查看下属/授权范围绩效权限）
        if (!$this->checkPerm('perf_view_subordinates') && !$this->isSuperAdmin((int)$userInfo['id'])) {
            $q->where('user_id', (int)$userInfo['id']);
        } else if ($userId > 0) {
            $q->where('user_id', $userId);
        }
        if ($period !== '') $q->where('period', $period);
        if (!empty($param['dimension'])) $q->where('dimension', (string)$param['dimension']);
        if (!empty($param['direction'])) $q->where('direction', (string)$param['direction']);
        if (!empty($param['status'])) $q->where('status', (string)$param['status']);
        $list = $q->order('fact_id desc')->limit(200)->select();
        // 批量解析提交人姓名
        $submitUserIds = [];
        foreach ($list as $r) { $submitUserIds[] = (int)$r['submit_user_id']; $submitUserIds[] = (int)$r['reviewer_user_id']; }
        $userMap = PerformanceService::resolveUserInfoBatch($submitUserIds);
        foreach ($list as &$row) {
            $row = PerformanceService::decorateFactFull($row);
            $sid = (int)$row['submit_user_id'];
            $row['submit_user_name'] = isset($userMap[$sid]) ? $userMap[$sid]['realname'] : '';
            $rid = (int)$row['reviewer_user_id'];
            $row['reviewer_name'] = isset($userMap[$rid]) ? $userMap[$rid]['realname'] : '';
        }
        // 维度统计
        $dimensionStats = [];
        foreach ($list as $item) {
            $dim = (string)$item['dimension'];
            if (!isset($dimensionStats[$dim])) $dimensionStats[$dim] = ['total' => 0, 'positive' => 0, 'negative' => 0, 'pending' => 0];
            $dimensionStats[$dim]['total']++;
            if ($item['direction'] === PerformanceService::DIR_POSITIVE) $dimensionStats[$dim]['positive']++;
            if ($item['direction'] === PerformanceService::DIR_NEGATIVE) $dimensionStats[$dim]['negative']++;
            if ($item['status'] === PerformanceService::FACT_PENDING) $dimensionStats[$dim]['pending']++;
        }
        return resultArray(['data' => ['list' => $list, 'dimension_stats' => $dimensionStats]]);
    }

    /**
     * 审核绩效事实（通过/驳回）
     * 审核人必须同时满足：
     *   - 不是被考核人
     *   - 不是事实提交人
     */
    public function factReview()
    {
        if (!$this->factTableExists()) return resultArray(['error' => '绩效事实功能尚未启用（数据库表未创建）']);
        $param = $this->param;
        $userInfo = $this->userInfo;
        $factId = (int)($param['fact_id'] ?? 0);
        $decision = trim((string)($param['decision'] ?? ''));
        if ($factId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
            return resultArray(['error' => 'fact_id 和 decision(approve/reject) 必填']);
        }
        if (!$this->checkPerm('perf_fact_review')) {
            return resultArray(['error' => '无权审核绩效事实']);
        }
        $fact = Db::name('performance_fact')->where(['fact_id' => $factId])->find();
        if (!$fact) return resultArray(['error' => '事实不存在']);
        if ((int)$fact['user_id'] === (int)$userInfo['id']) {
            return resultArray(['error' => '本人回避：不能审核自己的事实']);
        }
        if ((int)$fact['submit_user_id'] === (int)$userInfo['id']) {
            return resultArray(['error' => '提交人回避：不能审核自己提交的事实']);
        }
        $newStatus = $decision === 'approve' ? PerformanceService::FACT_APPROVED : PerformanceService::FACT_REJECTED;
        Db::name('performance_fact')->where(['fact_id' => $factId])->update([
            'status' => $newStatus, 'reviewer_user_id' => (int)$userInfo['id'],
            'review_time' => time(), 'review_note' => trim((string)($param['review_note'] ?? '')),
            'update_time' => time(),
        ]);
        return resultArray(['data' => ['fact_id' => $factId, 'status' => $newStatus]]);
    }

    /**
     * 绩效事实详情：返回完整事实信息 + 来源对象解析 + 提交人/审核人姓名。
     * 用于事实中心查看详情和追溯原始业务记录。
     */
    public function factDetail()
    {
        if (!$this->factTableExists()) return resultArray(['error' => '绩效事实功能尚未启用（数据库表未创建）']);
        $param = $this->param;
        $userInfo = $this->userInfo;
        $factId = (int)($param['fact_id'] ?? 0);
        if ($factId <= 0) return resultArray(['error' => '参数错误']);
        $fact = Db::name('performance_fact')->where(['fact_id' => $factId])->find();
        if (!$fact) return resultArray(['error' => '事实不存在']);
        // 数据范围
        if ((int)$fact['user_id'] !== (int)$userInfo['id'] && !$this->checkPerm('perf_view_subordinates') && !$this->isSuperAdmin((int)$userInfo['id'])) {
            return resultArray(['error' => '无权查看他人绩效事实']);
        }
        $fact = PerformanceService::decorateFactFull($fact);
        // 关联员工信息
        $userMap = PerformanceService::resolveUserInfoBatch([(int)$fact['user_id'], (int)$fact['submit_user_id'], (int)$fact['reviewer_user_id']]);
        $uid = (int)$fact['user_id'];
        $fact['user_name'] = isset($userMap[$uid]) ? $userMap[$uid]['realname'] : '';
        $fact['user_post'] = isset($userMap[$uid]) ? $userMap[$uid]['post'] : '';
        $fact['user_structure'] = isset($userMap[$uid]) ? $userMap[$uid]['structure_name'] : '';
        $sid = (int)$fact['submit_user_id'];
        $fact['submit_user_name'] = isset($userMap[$sid]) ? $userMap[$sid]['realname'] : '';
        $rid = (int)$fact['reviewer_user_id'];
        $fact['reviewer_name'] = isset($userMap[$rid]) ? $userMap[$rid]['realname'] : '';
        // 绩效汇总关联
        $perf = Db::name('performance')->where(['perf_id' => (int)$fact['perf_id']])->find();
        $fact['perf_status'] = $perf ? (string)$perf['status'] : '';
        $fact['perf_rating'] = $perf ? (string)$perf['rating'] : '';
        return resultArray(['data' => $fact]);
    }

    /**
     * 权限校验：基于 RBAC 子权限标记。
     * 子权限码：perf_view_self/perf_view_subordinates/perf_auto_aggregate/
     *           perf_fact_input/perf_fact_review/perf_score_input/
     *           perf_final_rate/perf_responsibility
     */
    private function checkPerm($code)
    {
        $userId = (int)($this->userInfo['id'] ?? 0);
        if ($userId <= 0) return false;
        // 超级管理员（id=1 或 isSuperAdministrators）默认拥有全部子权限
        if (function_exists('isSuperAdministrators') && isSuperAdministrators($userId)) return true;
        if ($userId === 1) return true;
        return $this->hasPerformanceSubPerm($userId, $code);
    }

    private function isSuperAdmin($userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) return false;
        if (function_exists('isSuperAdministrators') && isSuperAdministrators($userId)) return true;
        return $userId === 1;
    }

    /** 返回当前用户拥有的全部 perf_* 子权限码列表（供前端按钮显示）。 */
    private function listPerms($userId)
    {
        $out = [
            'perf_view_self' => false,
            'perf_view_subordinates' => false,
            'perf_auto_aggregate' => false,
            'perf_fact_input' => false,
            'perf_fact_review' => false,
            'perf_score_input' => false,
            'perf_final_rate' => false,
            'perf_responsibility' => false,
        ];
        if ($this->isSuperAdmin($userId)) {
            foreach ($out as $k => $v) $out[$k] = true;
            return $out;
        }
        $groups = Db::name('admin_group')->alias('g')
            ->join('__ADMIN_ACCESS__ a', 'a.group_id=g.id', 'LEFT')
            ->where('a.user_id', $userId)
            ->where('g.status', 1)
            ->field('g.rules')
            ->select();
        $ruleIds = [];
        foreach ($groups as $g) {
            $rules = trim((string)$g['rules'], ',');
            if ($rules === '') continue;
            foreach (explode(',', $rules) as $rid) {
                $rid = (int)trim($rid);
                if ($rid > 0) $ruleIds[$rid] = true;
            }
        }
        if (empty($ruleIds)) {
            $out['perf_view_self'] = true;
            return $out;
        }
        $names = Db::name('admin_rule')->where('id', 'in', array_keys($ruleIds))
            ->where('name', 'like', 'perf_%')->where('status', 1)->column('name');
        foreach ($names as $name) {
            if (isset($out[$name])) $out[$name] = true;
        }
        $out['perf_view_self'] = true;
        return $out;
    }

    /**
     * 真正查询用户是否拥有 perf_* 子权限。
     * 通过 admin_group.rules 关联 admin_rule.name 实现。
     * 兜底：未配置任何组或规则时，仅 perf_view_self 视为 true。
     */
    private function hasPerformanceSubPerm($userId, $code)
    {
        $groups = Db::name('admin_group')->alias('g')
            ->join('__ADMIN_ACCESS__ a', 'a.group_id=g.id', 'LEFT')
            ->where('a.user_id', $userId)
            ->where('g.status', 1)
            ->field('g.rules')
            ->select();
        if (empty($groups)) {
            return $code === 'perf_view_self';
        }
        $ruleIds = [];
        foreach ($groups as $g) {
            $rules = trim((string)$g['rules'], ',');
            if ($rules === '') continue;
            foreach (explode(',', $rules) as $rid) {
                $rid = (int)trim($rid);
                if ($rid > 0) $ruleIds[$rid] = true;
            }
        }
        if (empty($ruleIds)) {
            return $code === 'perf_view_self';
        }
        $has = Db::name('admin_rule')->where('id', 'in', array_keys($ruleIds))->where('name', $code)->where('status', 1)->find();
        return $has ? true : ($code === 'perf_view_self');
    }

    /** 工具：检测列是否存在。 */
    private function hasColumn($table, $column)
    {
        $row = Db::query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . addslashes($table) . "' AND COLUMN_NAME='" . addslashes($column) . "'");
        return !empty($row) && (int)$row[0]['cnt'] > 0;
    }

    /**
     * 缓存检测 performance 表的可选列是否存在。
     * 这些列由后续迁移添加（20260728/20260730），执行前可能不存在。
     * @return array [columnName => bool]
     */
    private static $optionalColumnsCache = null;
    private function getOptionalPerfColumns()
    {
        if (self::$optionalColumnsCache === null) {
            $optionals = ['create_method', 'quarterly_base', 'reference_amount'];
            $cache = [];
            foreach ($optionals as $col) {
                $cache[$col] = $this->hasColumn('5kcrm_performance', $col);
            }
            self::$optionalColumnsCache = $cache;
        }
        return self::$optionalColumnsCache;
    }

    /**
     * 过滤掉 performance 表中尚不存在的可选列，避免 INSERT/UPDATE 报错。
     * 直接修改传入的 $row 数组（引用）。
     */
    private function filterOptionalPerfColumns(&$row)
    {
        $exists = $this->getOptionalPerfColumns();
        foreach ($exists as $col => $hasColumn) {
            if (!$hasColumn && array_key_exists($col, $row)) {
                unset($row[$col]);
            }
        }
    }

    /** 工具：检测表是否存在。 */
    private function tableExists($table)
    {
        $row = Db::query("SELECT COUNT(*) AS cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . addslashes($table) . "'");
        return !empty($row) && (int)$row[0]['cnt'] > 0;
    }

    /** 缓存：adjust_audit 表是否存在。 */
    private static $auditTableExists = null;
    /** 安全写入审计：表不存在时跳过，不报错。 */
    private function safeInsertAudit($data)
    {
        if (self::$auditTableExists === null) {
            self::$auditTableExists = $this->tableExists('5kcrm_performance_adjust_audit');
        }
        if (self::$auditTableExists) {
            Db::name('performance_adjust_audit')->insert($data);
        }
    }

    /** 缓存：performance_fact 表是否存在。 */
    private static $factTableExists = null;
    private function factTableExists()
    {
        if (self::$factTableExists === null) {
            self::$factTableExists = $this->tableExists('5kcrm_performance_fact');
        }
        return self::$factTableExists;
    }
}
