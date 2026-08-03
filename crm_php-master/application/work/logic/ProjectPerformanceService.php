<?php
/**
 * 项目实施-绩效归集 统一同步服务。
 *
 * 关键约束（P0 数据一致性）：
 *   - 不得 delete performance_fact：源记录不再满足归集条件时，待审核事实更新为“已驳回”，保留 fact_id/create_time/历史证据。
 *   - 已通过事实完全不可变：syncMilestone/syncContribution 检测到已通过时返回 conflict，不做任何写入。
 *   - 已驳回事实不得因普通保存或自动归集自动恢复为待审核；仅通过 resubmitMilestone/resubmitContribution 显式重新提交。
 *   - 每次同步可归集事实时都执行 ensureSummary(user_id, period)，确保 perf_id/user_id/period 一致。
 *   - 并发冲突异常仅在确认为唯一键冲突时转为幂等结果；其他 SQL 异常原样返回 error。
 *   - 服务不自行开启事务（由控制器统一控制事务边界）。
 *
 * PHP 7.0 / ThinkPHP 5.0.24 兼容。
 */
namespace app\work\logic;

use think\Db;

class ProjectPerformanceService
{
    const SRC_MILESTONE    = 'project_milestone';
    const SRC_CONTRIBUTION = 'project_contribution';

    /** 由时间戳推导季度周期，如 2026Q3。 */
    public static function periodOf($ts)
    {
        $ts = (int)$ts;
        if ($ts <= 0) return '';
        $y = (int)date('Y', $ts);
        $m = (int)date('n', $ts);
        $q = (int)ceil($m / 3);
        return $y . 'Q' . $q;
    }

    /**
     * 确保绩效汇总记录存在（仅写核心列），返回 perf_id。
     */
    private function ensureSummary($userId, $period, $operatorUserId)
    {
        $userId = (int)$userId;
        $period = (string)$period;
        if ($userId <= 0 || $period === '') return 0;
        $exist = Db::name('performance')->where(['user_id' => $userId, 'period' => $period])->lock(true)->find();
        if ($exist) return (int)$exist['perf_id'];
        $now = time();
        $row = [
            'user_id' => $userId, 'period' => $period,
            'duty_score' => 0, 'task_score' => 0, 'quality_score' => 0, 'collab_score' => 0,
            'weighted_score' => 0, 'status' => '待确认',
            'create_user_id' => (int)$operatorUserId, 'create_time' => $now, 'update_time' => $now,
        ];
        try {
            return (int)Db::name('performance')->insertGetId($row);
        } catch (\Exception $e) {
            if ($this->isUniqueViolation($e)) {
                $again = Db::name('performance')->where(['user_id' => $userId, 'period' => $period])->find();
                if ($again) return (int)$again['perf_id'];
            }
            throw $e;
        }
    }

    /**
     * 判断异常是否为唯一键冲突（而非其他 SQL 错误）。
     */
    private function isUniqueViolation(\Exception $e)
    {
        $msg = strtolower((string)$e->getMessage());
        // MySQL: Duplicate entry / SQLSTATE[23000]
        // 仅唯一键冲突才转为幂等；其他异常原样返回
        return strpos($msg, 'duplicate entry') !== false
            || strpos($msg, 'error 1062') !== false
            || strpos($msg, '[1062]') !== false;
    }

    /** 项目绩效状态变更审计；调用方事务回滚时审计同步回滚。 */
    private function writeAudit(array $fact, $action, $fromStatus, $toStatus, $note, $operatorUserId)
    {
        Db::name('project_performance_audit')->insert([
            'fact_id' => (int)($fact['fact_id'] ?? 0),
            'source_type' => (string)($fact['source_type'] ?? ''),
            'source_id' => (string)($fact['source_id'] ?? ''),
            'action' => (string)$action,
            'from_status' => (string)$fromStatus,
            'to_status' => (string)$toStatus,
            'note' => (string)$note,
            'operator_user_id' => (int)$operatorUserId,
            'create_time' => time(),
        ]);
    }

    /**
     * 同步里程碑绩效事实。
     * 不自行开启事务（由控制器控制事务边界）。
     * 返回 ['ok'=>bool,'action'=>'inserted|updated|skipped|excluded|conflict|rejected','fact_id'=>int,'error'=>'','reason'=>'']
     */
    public function syncMilestone($milestoneId, $operatorUserId = 0)
    {
        $milestoneId = (int)$milestoneId;
        if ($milestoneId <= 0) return ['ok' => false, 'action' => 'skipped', 'fact_id' => 0, 'error' => '参数错误', 'reason' => ''];
        $m = Db::name('work_milestone')->where(['milestone_id' => $milestoneId])->find();
        if (!$m) return ['ok' => false, 'action' => 'skipped', 'fact_id' => 0, 'error' => '里程碑不存在', 'reason' => ''];

        $svc = new ProjectService();
        $workId = (int)$m['work_id'];
        $rid = (int)($m['responsible_user_id'] ?? 0);
        $isMember = $rid > 0 && $svc->isProjectMember($workId, $rid);

        $sourceType = self::SRC_MILESTONE;
        $sourceId = 'milestone:' . $milestoneId;
        $exist = Db::name('performance_fact')->where(['source_type' => $sourceType, 'source_id' => $sourceId])->lock(true)->find();

        // 已通过事实完全不可变
        if ($exist && (string)$exist['status'] === '已通过') {
            return ['ok' => false, 'action' => 'conflict', 'fact_id' => (int)$exist['fact_id'], 'error' => '该里程碑绩效已通过，修改关键来源字段需先执行绩效撤回流程', 'reason' => ''];
        }

        $perf = ProjectService::milestonePerformanceStatus($m, $isMember, $exist ? $exist['status'] : null, $exist ? $exist['fact_id'] : 0);

        // 条件不满足：不得 delete；待审核事实更新为“已驳回”，保留 fact_id/create_time/历史证据
        if ($perf['status'] === ProjectService::PERF_EXCLUDED) {
            if ($exist && (string)$exist['status'] !== '已驳回') {
                $now = time();
                $rejectNote = '源记录已变更，不再满足归集条件：' . $perf['reason'];
                $this->writeAudit($exist, 'source_ineligible', (string)$exist['status'], '已驳回', $rejectNote, $operatorUserId);
                Db::name('performance_fact')->where(['fact_id' => $exist['fact_id']])->update([
                    'status' => '已驳回',
                    'review_note' => $rejectNote,
                    'reviewer_user_id' => 0,
                    'review_time' => $now,
                    'update_time' => $now,
                ]);
                return ['ok' => true, 'action' => 'rejected', 'fact_id' => (int)$exist['fact_id'], 'error' => '', 'reason' => $perf['reason']];
            }
            return ['ok' => true, 'action' => 'excluded', 'fact_id' => 0, 'error' => '', 'reason' => $perf['reason']];
        }

        // 已驳回事实不得因普通保存自动恢复为待审核（需显式 resubmitMilestone）
        if ($exist && (string)$exist['status'] === '已驳回') {
            return ['ok' => true, 'action' => 'skipped', 'fact_id' => (int)$exist['fact_id'], 'error' => '', 'reason' => '事实已驳回，需显式重新提交绩效'];
        }

        $actualTime = (int)($m['actual_time'] ?? 0);
        $period = self::periodOf($actualTime);
        $now = time();
        $row = [
            'user_id' => $rid,
            'period' => $period,
            'dimension' => 'task',
            'direction' => '正向',
            'fact_type' => 'project_milestone',
            'title' => '里程碑完成：' . (string)($m['name'] ?? ''),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'occurred_time' => $actualTime,
            'evidence' => 'milestone_id=' . $milestoneId . ' work_id=' . $workId . ' responsible_user_id=' . $rid . ' type=' . (string)($m['milestone_type'] ?? ''),
            'status' => '待审核',
            'submit_user_id' => (int)$operatorUserId,
            'update_time' => $now,
        ];

        if ($exist) {
            // 待审核：按当前源数据更新（负责人/周期/数值/证据），并重新确保 perf_id 一致
            $row['perf_id'] = $this->ensureSummary($rid, $period, $operatorUserId);
            Db::name('performance_fact')->where(['fact_id' => $exist['fact_id']])->update($row);
            return ['ok' => true, 'action' => 'updated', 'fact_id' => (int)$exist['fact_id'], 'error' => '', 'reason' => ''];
        }

        $row['perf_id'] = $this->ensureSummary($rid, $period, $operatorUserId);
        $row['create_time'] = $now;
        try {
            $newId = Db::name('performance_fact')->insertGetId($row);
            return ['ok' => true, 'action' => 'inserted', 'fact_id' => (int)$newId, 'error' => '', 'reason' => ''];
        } catch (\Exception $e) {
            if ($this->isUniqueViolation($e)) {
                $again = Db::name('performance_fact')->where(['source_type' => $sourceType, 'source_id' => $sourceId])->find();
                if ($again) {
                    if ((string)$again['status'] === '已通过') {
                        return ['ok' => false, 'action' => 'conflict', 'fact_id' => (int)$again['fact_id'], 'error' => '该里程碑绩效已通过，不能并发覆盖', 'reason' => ''];
                    }
                    if ((string)$again['status'] === '已驳回') {
                        return ['ok' => true, 'action' => 'skipped', 'fact_id' => (int)$again['fact_id'], 'error' => '', 'reason' => '事实已驳回，需显式重新提交绩效'];
                    }
                    $row['perf_id'] = $this->ensureSummary($rid, $period, $operatorUserId);
                    Db::name('performance_fact')->where(['fact_id' => $again['fact_id']])->update($row);
                    return ['ok' => true, 'action' => 'updated', 'fact_id' => (int)$again['fact_id'], 'error' => '', 'reason' => ''];
                }
            }
            return ['ok' => false, 'action' => 'error', 'fact_id' => 0, 'error' => '里程碑绩效同步失败：' . $e->getMessage(), 'reason' => ''];
        }
    }

    /**
     * 同步贡献绩效事实。仅“已确认”记录归集。
     * occurred_time 优先 confirm_time，其次 end_time。
     */
    public function syncContribution($contributionId, $operatorUserId = 0)
    {
        $contributionId = (int)$contributionId;
        if ($contributionId <= 0) return ['ok' => false, 'action' => 'skipped', 'fact_id' => 0, 'error' => '参数错误', 'reason' => ''];
        $c = Db::name('work_member_contribution')->where(['contribution_id' => $contributionId])->find();
        if (!$c) return ['ok' => false, 'action' => 'skipped', 'fact_id' => 0, 'error' => '贡献记录不存在', 'reason' => ''];

        $svc = new ProjectService();
        $workId = (int)$c['work_id'];
        $uid = (int)($c['user_id'] ?? 0);
        $isMember = $uid > 0 && $svc->isProjectMember($workId, $uid);

        $sourceType = self::SRC_CONTRIBUTION;
        $sourceId = 'contribution:' . $contributionId;
        $exist = Db::name('performance_fact')->where(['source_type' => $sourceType, 'source_id' => $sourceId])->lock(true)->find();

        if ($exist && (string)$exist['status'] === '已通过') {
            return ['ok' => false, 'action' => 'conflict', 'fact_id' => (int)$exist['fact_id'], 'error' => '该贡献绩效已通过，修改关键来源字段需先执行绩效撤回流程', 'reason' => ''];
        }

        $perf = ProjectService::contributionPerformanceStatus($c, $isMember, $exist ? $exist['status'] : null, $exist ? $exist['fact_id'] : 0);

        if ($perf['status'] === ProjectService::PERF_EXCLUDED) {
            if ($exist && (string)$exist['status'] !== '已驳回') {
                $now = time();
                $rejectNote = '源记录已变更，不再满足归集条件：' . $perf['reason'];
                $this->writeAudit($exist, 'source_ineligible', (string)$exist['status'], '已驳回', $rejectNote, $operatorUserId);
                Db::name('performance_fact')->where(['fact_id' => $exist['fact_id']])->update([
                    'status' => '已驳回',
                    'review_note' => $rejectNote,
                    'reviewer_user_id' => 0,
                    'review_time' => $now,
                    'update_time' => $now,
                ]);
                return ['ok' => true, 'action' => 'rejected', 'fact_id' => (int)$exist['fact_id'], 'error' => '', 'reason' => $perf['reason']];
            }
            return ['ok' => true, 'action' => 'excluded', 'fact_id' => 0, 'error' => '', 'reason' => $perf['reason']];
        }

        if ($exist && (string)$exist['status'] === '已驳回') {
            return ['ok' => true, 'action' => 'skipped', 'fact_id' => (int)$exist['fact_id'], 'error' => '', 'reason' => '事实已驳回，需显式重新提交绩效'];
        }

        $occurred = (int)($c['confirm_time'] ?? 0);
        if ($occurred <= 0) $occurred = (int)($c['end_time'] ?? 0);
        $period = self::periodOf($occurred);
        $now = time();
        $row = [
            'user_id' => $uid,
            'period' => $period,
            'dimension' => 'collab',
            'direction' => '正向',
            'fact_type' => 'project_contribution',
            'title' => '项目贡献：' . (string)($c['contribution_role'] ?? ''),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'occurred_time' => $occurred,
            'evidence' => 'contribution_id=' . $contributionId . ' work_id=' . $workId . ' user_id=' . $uid . ' role=' . (string)($c['contribution_role'] ?? '') . ' on_site_days=' . (string)($c['on_site_days'] ?? ''),
            'status' => '待审核',
            'submit_user_id' => (int)$operatorUserId,
            'update_time' => $now,
        ];

        if ($exist) {
            $row['perf_id'] = $this->ensureSummary($uid, $period, $operatorUserId);
            Db::name('performance_fact')->where(['fact_id' => $exist['fact_id']])->update($row);
            return ['ok' => true, 'action' => 'updated', 'fact_id' => (int)$exist['fact_id'], 'error' => '', 'reason' => ''];
        }

        $row['perf_id'] = $this->ensureSummary($uid, $period, $operatorUserId);
        $row['create_time'] = $now;
        try {
            $newId = Db::name('performance_fact')->insertGetId($row);
            return ['ok' => true, 'action' => 'inserted', 'fact_id' => (int)$newId, 'error' => '', 'reason' => ''];
        } catch (\Exception $e) {
            if ($this->isUniqueViolation($e)) {
                $again = Db::name('performance_fact')->where(['source_type' => $sourceType, 'source_id' => $sourceId])->find();
                if ($again) {
                    if ((string)$again['status'] === '已通过') {
                        return ['ok' => false, 'action' => 'conflict', 'fact_id' => (int)$again['fact_id'], 'error' => '该贡献绩效已通过，不能并发覆盖', 'reason' => ''];
                    }
                    if ((string)$again['status'] === '已驳回') {
                        return ['ok' => true, 'action' => 'skipped', 'fact_id' => (int)$again['fact_id'], 'error' => '', 'reason' => '事实已驳回，需显式重新提交绩效'];
                    }
                    $row['perf_id'] = $this->ensureSummary($uid, $period, $operatorUserId);
                    Db::name('performance_fact')->where(['fact_id' => $again['fact_id']])->update($row);
                    return ['ok' => true, 'action' => 'updated', 'fact_id' => (int)$again['fact_id'], 'error' => '', 'reason' => ''];
                }
            }
            return ['ok' => false, 'action' => 'error', 'fact_id' => 0, 'error' => '贡献绩效同步失败：' . $e->getMessage(), 'reason' => ''];
        }
    }

    /**
     * 显式重新提交已驳回的里程碑绩效事实。
     * 仅当源记录重新满足归集条件时允许；清空旧审核人/审核时间，保留驳回信息到 review_note。
     */
    public function resubmitMilestone($milestoneId, $operatorUserId = 0)
    {
        $milestoneId = (int)$milestoneId;
        if ($milestoneId <= 0) return ['ok' => false, 'action' => 'skipped', 'fact_id' => 0, 'error' => '参数错误', 'reason' => ''];
        $m = Db::name('work_milestone')->where(['milestone_id' => $milestoneId])->find();
        if (!$m) return ['ok' => false, 'action' => 'skipped', 'fact_id' => 0, 'error' => '里程碑不存在', 'reason' => ''];

        $sourceType = self::SRC_MILESTONE;
        $sourceId = 'milestone:' . $milestoneId;
        $exist = Db::name('performance_fact')->where(['source_type' => $sourceType, 'source_id' => $sourceId])->lock(true)->find();
        if (!$exist) return ['ok' => false, 'action' => 'skipped', 'fact_id' => 0, 'error' => '不存在关联绩效事实', 'reason' => ''];
        if ((string)$exist['status'] === '已通过') return ['ok' => false, 'action' => 'conflict', 'fact_id' => (int)$exist['fact_id'], 'error' => '绩效已通过，需先撤回', 'reason' => ''];
        if ((string)$exist['status'] !== '已驳回') return ['ok' => false, 'action' => 'skipped', 'fact_id' => (int)$exist['fact_id'], 'error' => '仅已驳回事实可重新提交', 'reason' => ''];

        $svc = new ProjectService();
        $workId = (int)$m['work_id'];
        $rid = (int)($m['responsible_user_id'] ?? 0);
        $isMember = $rid > 0 && $svc->isProjectMember($workId, $rid);
        $perf = ProjectService::milestonePerformanceStatus($m, $isMember, null, (int)$exist['fact_id']);
        if ($perf['status'] === ProjectService::PERF_EXCLUDED) {
            return ['ok' => false, 'action' => 'excluded', 'fact_id' => 0, 'error' => '源记录仍不满足归集条件：' . $perf['reason'], 'reason' => $perf['reason']];
        }

        $actualTime = (int)($m['actual_time'] ?? 0);
        $period = self::periodOf($actualTime);
        $now = time();
        $oldNote = (string)$exist['review_note'];
        $this->writeAudit($exist, 'resubmit', '已驳回', '待审核', '重新提交；上次驳回：' . $oldNote, $operatorUserId);
        $perfId = $this->ensureSummary($rid, $period, $operatorUserId);
        Db::name('performance_fact')->where(['fact_id' => $exist['fact_id']])->update([
            'perf_id' => $perfId,
            'user_id' => $rid,
            'period' => $period,
            'occurred_time' => $actualTime,
            'status' => '待审核',
            'reviewer_user_id' => 0,
            'review_time' => 0,
            'review_note' => '重新提交；上次驳回：' . $oldNote,
            'submit_user_id' => (int)$operatorUserId,
            'update_time' => $now,
        ]);
        $sync = $this->syncMilestone($milestoneId, $operatorUserId);
        if (!$sync['ok']) return $sync;
        $sync['action'] = 'resubmitted';
        return $sync;
    }

    /**
     * 显式重新提交已驳回的贡献绩效事实。
     */
    public function resubmitContribution($contributionId, $operatorUserId = 0)
    {
        $contributionId = (int)$contributionId;
        if ($contributionId <= 0) return ['ok' => false, 'action' => 'skipped', 'fact_id' => 0, 'error' => '参数错误', 'reason' => ''];
        $c = Db::name('work_member_contribution')->where(['contribution_id' => $contributionId])->find();
        if (!$c) return ['ok' => false, 'action' => 'skipped', 'fact_id' => 0, 'error' => '贡献记录不存在', 'reason' => ''];

        $sourceType = self::SRC_CONTRIBUTION;
        $sourceId = 'contribution:' . $contributionId;
        $exist = Db::name('performance_fact')->where(['source_type' => $sourceType, 'source_id' => $sourceId])->lock(true)->find();
        if (!$exist) return ['ok' => false, 'action' => 'skipped', 'fact_id' => 0, 'error' => '不存在关联绩效事实', 'reason' => ''];
        if ((string)$exist['status'] === '已通过') return ['ok' => false, 'action' => 'conflict', 'fact_id' => (int)$exist['fact_id'], 'error' => '绩效已通过，需先撤回', 'reason' => ''];
        if ((string)$exist['status'] !== '已驳回') return ['ok' => false, 'action' => 'skipped', 'fact_id' => (int)$exist['fact_id'], 'error' => '仅已驳回事实可重新提交', 'reason' => ''];

        $svc = new ProjectService();
        $workId = (int)$c['work_id'];
        $uid = (int)($c['user_id'] ?? 0);
        $isMember = $uid > 0 && $svc->isProjectMember($workId, $uid);
        $perf = ProjectService::contributionPerformanceStatus($c, $isMember, null, (int)$exist['fact_id']);
        if ($perf['status'] === ProjectService::PERF_EXCLUDED) {
            return ['ok' => false, 'action' => 'excluded', 'fact_id' => 0, 'error' => '源记录仍不满足归集条件：' . $perf['reason'], 'reason' => $perf['reason']];
        }

        $occurred = (int)($c['confirm_time'] ?? 0);
        if ($occurred <= 0) $occurred = (int)($c['end_time'] ?? 0);
        $period = self::periodOf($occurred);
        $now = time();
        $oldNote = (string)$exist['review_note'];
        $this->writeAudit($exist, 'resubmit', '已驳回', '待审核', '重新提交；上次驳回：' . $oldNote, $operatorUserId);
        $perfId = $this->ensureSummary($uid, $period, $operatorUserId);
        Db::name('performance_fact')->where(['fact_id' => $exist['fact_id']])->update([
            'perf_id' => $perfId,
            'user_id' => $uid,
            'period' => $period,
            'occurred_time' => $occurred,
            'status' => '待审核',
            'reviewer_user_id' => 0,
            'review_time' => 0,
            'review_note' => '重新提交；上次驳回：' . $oldNote,
            'submit_user_id' => (int)$operatorUserId,
            'update_time' => $now,
        ]);
        $sync = $this->syncContribution($contributionId, $operatorUserId);
        if (!$sync['ok']) return $sync;
        $sync['action'] = 'resubmitted';
        return $sync;
    }

    /**
     * 删除源记录前在当前事务内锁定事实并执行撤销规则。
     * 待审核事实转为已驳回并保留审计；已通过/已驳回均禁止删除源记录。
     */
    private function prepareDelete($sourceType, $sourceId, $label, $operatorUserId)
    {
        $fact = Db::name('performance_fact')->where(['source_type' => $sourceType, 'source_id' => $sourceId])->lock(true)->find();
        if (!$fact) return ['can_delete' => true, 'reason' => ''];
        $status = (string)$fact['status'];
        if ($status === '已通过') return ['can_delete' => false, 'reason' => '该' . $label . '绩效已通过，请先撤回绩效后再删除'];
        if ($status === '已驳回') return ['can_delete' => false, 'reason' => '该' . $label . '关联已驳回绩效事实，不允许删除审计来源'];
        if ($status !== '待审核') return ['can_delete' => false, 'reason' => '该' . $label . '绩效状态异常，禁止删除'];
        $now = time();
        $this->writeAudit($fact, 'source_delete', '待审核', '已驳回', '源记录删除，系统撤销待审核事实', $operatorUserId);
        Db::name('performance_fact')->where(['fact_id' => $fact['fact_id']])->update([
            'status' => '已驳回',
            'reviewer_user_id' => 0,
            'review_time' => $now,
            'review_note' => '源记录删除，系统撤销待审核事实',
            'update_time' => $now,
        ]);
        return ['can_delete' => true, 'reason' => '待审核事实已撤销并保留'];
    }

    public function prepareDeleteMilestone($milestoneId, $operatorUserId = 0)
    {
        return $this->prepareDelete(self::SRC_MILESTONE, 'milestone:' . (int)$milestoneId, '里程碑', $operatorUserId);
    }

    public function prepareDeleteContribution($contributionId, $operatorUserId = 0)
    {
        return $this->prepareDelete(self::SRC_CONTRIBUTION, 'contribution:' . (int)$contributionId, '贡献', $operatorUserId);
    }

    /** 自动归集专用原子入口；控制器保存流程已持有事务时仍调用非 Atomic 方法。 */
    public function syncMilestoneAtomic($milestoneId, $operatorUserId = 0)
    {
        Db::startTrans();
        try {
            $source = Db::name('work_milestone')->where(['milestone_id' => (int)$milestoneId])->lock(true)->find();
            if (!$source) { Db::rollback(); return ['ok' => false, 'action' => 'skipped', 'fact_id' => 0, 'error' => '里程碑不存在', 'reason' => '']; }
            $result = $this->syncMilestone($milestoneId, $operatorUserId);
            if (!$result['ok']) { Db::rollback(); return $result; }
            Db::commit();
            return $result;
        } catch (\Exception $e) {
            Db::rollback();
            return ['ok' => false, 'action' => 'error', 'fact_id' => 0, 'error' => '里程碑绩效同步失败：' . $e->getMessage(), 'reason' => ''];
        }
    }

    public function syncContributionAtomic($contributionId, $operatorUserId = 0)
    {
        Db::startTrans();
        try {
            $source = Db::name('work_member_contribution')->where(['contribution_id' => (int)$contributionId])->lock(true)->find();
            if (!$source) { Db::rollback(); return ['ok' => false, 'action' => 'skipped', 'fact_id' => 0, 'error' => '贡献记录不存在', 'reason' => '']; }
            $result = $this->syncContribution($contributionId, $operatorUserId);
            if (!$result['ok']) { Db::rollback(); return $result; }
            Db::commit();
            return $result;
        } catch (\Exception $e) {
            Db::rollback();
            return ['ok' => false, 'action' => 'error', 'fact_id' => 0, 'error' => '贡献绩效同步失败：' . $e->getMessage(), 'reason' => ''];
        }
    }

    /**
     * 为某项目批量同步里程碑与贡献绩效事实（自动归集补偿入口）。
     * 返回 ['scanned'=>,'inserted'=>,'updated'=>,'skipped'=>,'conflicts'=>,'errors'=>[]]。
     */
    public function aggregateForWork($workId, $operatorUserId = 0)
    {
        $workId = (int)$workId;
        $scanned = 0; $inserted = 0; $updated = 0; $skipped = 0; $conflicts = 0; $errors = [];
        $milestones = Db::name('work_milestone')->where(['work_id' => $workId])->field('milestone_id')->select();
        foreach ($milestones as $mRow) {
            $scanned++;
            $r = $this->syncMilestoneAtomic((int)$mRow['milestone_id'], $operatorUserId);
            if ($r['action'] === 'inserted') $inserted++;
            elseif ($r['action'] === 'updated') $updated++;
            elseif ($r['action'] === 'excluded' || $r['action'] === 'skipped' || $r['action'] === 'rejected') $skipped++;
            elseif ($r['action'] === 'conflict') { $skipped++; }
            elseif ($r['action'] === 'error') { $conflicts++; if ($r['error'] !== '') $errors[] = ['source' => 'milestone:' . (int)$mRow['milestone_id'], 'error' => $r['error']]; }
        }
        $contribs = Db::name('work_member_contribution')->where(['work_id' => $workId])->field('contribution_id')->select();
        foreach ($contribs as $cRow) {
            $scanned++;
            $r = $this->syncContributionAtomic((int)$cRow['contribution_id'], $operatorUserId);
            if ($r['action'] === 'inserted') $inserted++;
            elseif ($r['action'] === 'updated') $updated++;
            elseif ($r['action'] === 'excluded' || $r['action'] === 'skipped' || $r['action'] === 'rejected') $skipped++;
            elseif ($r['action'] === 'conflict') { $skipped++; }
            elseif ($r['action'] === 'error') { $conflicts++; if ($r['error'] !== '') $errors[] = ['source' => 'contribution:' . (int)$cRow['contribution_id'], 'error' => $r['error']]; }
        }
        return ['scanned' => $scanned, 'inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped, 'conflicts' => $conflicts, 'errors' => $errors];
    }
}
