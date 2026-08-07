<?php
// +----------------------------------------------------------------------
// | P5 奖励候选：统一候选、规则版本、证据、人工审核(本人回避)、结算批次、冲销；商务费用独立
// +----------------------------------------------------------------------
namespace app\crm\controller;

use app\crm\logic\RewardService;
use think\Db;
use think\Request;
use think\Hook;
use app\admin\controller\ApiCommon;

class Reward extends ApiCommon
{
    public function _initialize()
    {
        $action = ['permission' => [''], 'allow' => ['dictionary', 'candidatesave', 'candidatelist', 'candidateread', 'candidateupdate', 'candidatedelete', 'candidateauditlist', 'review', 'batchcreate', 'batchsettle', 'offset', 'configsave', 'expensesave', 'expenselist', 'approvalrequest', 'approvaldecide', 'approvalcheck', 'stageoffsetcalc', 'paymentrecord', 'paymentconfirm', 'rulelist', 'rulesave', 'ruletoggle', 'ruledelete', 'businesstypestagelist', 'manualrulelist', 'manualrulesave', 'manualruledelete']];
        Hook::listen('check_auth', $action);
        if (!in_array(strtolower(Request::instance()->action()), $action['permission'])) {
            parent::_initialize();
        }
    }

    public function dictionary()
    {
        $s = new RewardService();
        $isRewardAdmin = $this->isRewardVisibilityAdmin($this->userInfo);
        return resultArray(['data' => [
            'fixed_amounts' => RewardService::FIXED_AMOUNTS,
            'statuses' => ['待审核','已通过','已驳回','已结算','已冲销','已作废'],
            'summary' => $isRewardAdmin ? $s->summary() : $this->relatedCandidateSummary((int)$this->userInfo['id']),
            'config' => RewardService::configStatus(),
            'is_reward_admin' => $isRewardAdmin,
        ]]);
    }

    /** 创建奖励候选（金额优先取固定字典，可自定义） */
    /**
     * 人工新建奖惩候选
     * 只接收 manual_rule_id、user_id、occurred_date、reason/evidence_note
     * 后端按规则计算方向和金额，忽略前端提交的 direction 和 amount
     */
    public function candidateSave()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $manualRuleId = (int)($param['manual_rule_id'] ?? 0);
        $userId = (int)($param['user_id'] ?? 0);
        if ($manualRuleId <= 0) return resultArray(['error' => '请选择奖惩项目']);
        if ($userId <= 0) return resultArray(['error' => '请选择人员']);

        // 查询启用的奖惩项目
        try {
            $rule = Db::name('reward_manual_rule')->where(['manual_rule_id' => $manualRuleId, 'is_enabled' => 1])->find();
        } catch (\Exception $e) {
            \think\Log::record('candidateSave查询规则失败（表未创建）: ' . $e->getMessage(), 'error');
            return resultArray(['error' => '奖惩项目配置功能需要数据库迁移，请联系管理员执行迁移']);
        }
        if (!$rule) return resultArray(['error' => '奖惩项目不存在或已停用']);

        // 验证员工有效
        $user = db('admin_user')->where(['id' => $userId, 'status' => 1])->field('id,realname')->find();
        if (!$user) return resultArray(['error' => '所选员工不存在或已禁用']);

        // 后端计算方向和金额
        $direction = $rule['direction']; // reward 或 penalty
        $calcMode = (string)($rule['calc_mode'] ?? 'fixed');
        if ($calcMode === 'range') {
            // 区间模式：用户填写金额，后端校验范围
            $amount = abs((float)($param['amount'] ?? 0));
            $minVal = (float)($rule['amount_min'] ?? 0);
            $maxVal = (float)($rule['amount_max'] ?? 0);
            if ($amount <= 0) return resultArray(['error' => '请填写金额']);
            if ($minVal > 0 && $amount < $minVal) return resultArray(['error' => "金额不能低于{$minVal}元"]);
            if ($maxVal > 0 && $amount > $maxVal) return resultArray(['error' => "金额不能超过{$maxVal}元"]);
        } elseif ($calcMode === 'pool') {
            // 奖金池比例模式：需要传入基数
            $baseAmount = abs((float)($param['base_amount'] ?? 0));
            $poolPct = (float)($rule['pool_pct'] ?? 0);
            if ($poolPct <= 0 || $baseAmount <= 0) return resultArray(['error' => '奖金池比例计算缺少基数']);
            $amount = round($baseAmount * $poolPct / 100, 2);
        } else {
            // 固定金额模式
            $amount = abs((float)$rule['amount']);
            if ($amount <= 0) return resultArray(['error' => '奖惩项目金额必须大于0']);
        }
        if ($direction === 'penalty') $amount = -$amount;

        $now = time();
        $occurredTime = !empty($param['occurred_date']) ? strtotime((string)$param['occurred_date']) : $now;

        // 原始数据批次仍在线下底稿审核，不搬入CRM；同一人员同月只保留达到的最高档。
        $batchRuleCodes = ['raw_data_batch_basic' => 100.00, 'raw_data_batch_premium' => 200.00];
        $isDataBatch = isset($batchRuleCodes[(string)($rule['rule_code'] ?? '')]);
        $batchSourceRef = '';
        if ($isDataBatch) {
            $evidenceNote = trim((string)($param['evidence_note'] ?? ''));
            if ($evidenceNote === '') return resultArray(['error' => '数据批次奖励必须填写去重、来源、业务匹配及后续核实依据']);
            $month = date('Y-m', $occurredTime);
            $batchSourceRef = 'raw_batch:user:' . $userId . ':month:' . $month;
            $existBatch = Db::name('reward_candidate')->where([
                'source_type' => '高质量原始数据批次',
                'source_ref' => $batchSourceRef,
            ])->find();
            if ($existBatch) {
                if ((float)$existBatch['amount'] >= (float)$amount) {
                    return resultArray(['error' => '该人员本月已存在同档或更高档数据批次奖励，不重复叠加']);
                }
                if (in_array((string)$existBatch['status'], [RewardService::ST_SETTLED, RewardService::ST_OFFSET], true)) {
                    return resultArray(['error' => '该人员本月较低档奖励已结算，不能直接升级，请走冲销或补差审核']);
                }
                $upgrade = [
                    'amount' => $amount,
                    'reason' => trim((string)($param['reason'] ?? $rule['description'])),
                    'evidence_note' => $evidenceNote,
                    'status' => RewardService::ST_PENDING,
                    'reviewer_user_id' => 0,
                    'review_time' => 0,
                    'review_note' => '',
                    'update_user_id' => (int)$userInfo['id'],
                    'update_time' => $now,
                ];
                if ($this->rewardCandidateHasColumn('manual_rule_id')) $upgrade['manual_rule_id'] = $manualRuleId;
                if (!$this->rewardCandidateHasColumn('update_user_id')) unset($upgrade['update_user_id']);
                Db::name('reward_candidate')->where('cand_id', (int)$existBatch['cand_id'])->update($upgrade);
                Db::name('reward_candidate_audit')->insert([
                    'cand_id' => (int)$existBatch['cand_id'],
                    'operation_type' => 'batch_tier_upgrade',
                    'old_data_json' => json_encode($existBatch, JSON_UNESCAPED_UNICODE),
                    'new_data_json' => json_encode($upgrade, JSON_UNESCAPED_UNICODE),
                    'change_reason' => '同一人员同月数据批次奖励按最高档升级',
                    'operator_user_id' => (int)$userInfo['id'],
                    'operator_name' => $userInfo['realname'] ?? '',
                    'operation_time' => $now,
                    'request_ip' => Request::instance()->ip(),
                    'create_time' => $now,
                ]);
                return resultArray(['data' => [
                    'cand_id' => (int)$existBatch['cand_id'],
                    'amount' => $amount,
                    'direction' => $direction,
                    'status' => RewardService::ST_PENDING,
                    'upgraded' => true,
                ]]);
            }
        }

        $insertData = [
            'source_type' => $isDataBatch ? '高质量原始数据批次' : ('manual:' . $rule['rule_name']),
            'source_ref' => $isDataBatch ? $batchSourceRef : ('manual_rule:' . $manualRuleId . ':' . $userId . ':' . $occurredTime),
            'user_id' => $userId,
            'amount' => $amount,
            'reason' => trim((string)($param['reason'] ?? $rule['description'])),
            'evidence_note' => trim((string)($param['evidence_note'] ?? '')),
            'rules_version' => 'manual',
            'status' => RewardService::ST_PENDING,
            'occurred_time' => $occurredTime,
            'create_user_id' => (int)$userInfo['id'],
            'create_time' => $now, 'update_time' => $now,
        ];
        if ($this->rewardCandidateHasColumn('manual_rule_id')) {
            $insertData['manual_rule_id'] = $manualRuleId;
        }
        $id = Db::name('reward_candidate')->insertGetId($insertData);

        // 写审计
        Db::name('reward_candidate_audit')->insert([
            'cand_id' => $id,
            'operation_type' => 'manual_create',
            'old_data_json' => '',
            'new_data_json' => json_encode($insertData, JSON_UNESCAPED_UNICODE),
            'change_reason' => '人工新建：' . $rule['rule_name'],
            'operator_user_id' => (int)$userInfo['id'],
            'operator_name' => $userInfo['realname'] ?? '',
            'operation_time' => $now,
            'request_ip' => Request::instance()->ip(),
            'create_time' => $now,
        ]);

        return resultArray(['data' => ['cand_id' => $id, 'amount' => $amount, 'direction' => $direction, 'status' => RewardService::ST_PENDING]]);
    }

    /**
     * 人工奖惩项目列表
     */
    public function manualRuleList()
    {
        $param = $this->param;
        try {
            $q = Db::name('reward_manual_rule');
            if (isset($param['is_enabled']) && $param['is_enabled'] !== '') {
                $q->where('is_enabled', (int)$param['is_enabled']);
            }
            if (!empty($param['direction'])) $q->where('direction', $param['direction']);
            $list = $q->order('sort_order asc, manual_rule_id asc')->select();
            foreach ($list as &$row) {
                $row['manual_rule_id'] = (int)$row['manual_rule_id'];
                $row['amount'] = (float)$row['amount'];
                $row['amount_min'] = (float)$row['amount_min'];
                $row['amount_max'] = (float)$row['amount_max'];
                $row['pool_pct'] = (float)$row['pool_pct'];
                $row['is_enabled'] = (int)$row['is_enabled'];
                $row['sort_order'] = (int)$row['sort_order'];
            }
            unset($row);
        } catch (\Exception $e) {
            \think\Log::record('manualRuleList查询失败（表可能未创建）: ' . $e->getMessage(), 'error');
            $list = [];
        }
        return resultArray(['data' => $list]);
    }

    /**
     * 人工奖惩项目新增/编辑
     */
    public function manualRuleSave()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $id = (int)($param['manual_rule_id'] ?? 0);
        $old = [];
        if ($id > 0) {
            $old = Db::name('reward_manual_rule')->where('manual_rule_id', $id)->find();
            if (!$old) return resultArray(['error' => '奖惩项目不存在或已删除']);
        }

        // 编辑接口允许只提交需要修改的字段；缺失字段必须保留原值，不能回落到 fixed/停用等默认值。
        $value = function ($key, $default = null) use ($param, $old) {
            if (array_key_exists($key, $param)) return $param[$key];
            if (array_key_exists($key, $old)) return $old[$key];
            return $default;
        };
        $ruleName = trim((string)$value('rule_name', ''));
        $direction = trim((string)$value('direction', ''));
        $calcMode = trim((string)$value('calc_mode', 'fixed'));
        $amount = abs((float)$value('amount', 0));
        $amountMin = abs((float)$value('amount_min', 0));
        $amountMax = abs((float)$value('amount_max', 0));
        $poolPct = abs((float)$value('pool_pct', 0));
        $isEnabled = (int)$value('is_enabled', 1) === 1 ? 1 : 0;
        if ($ruleName === '') return resultArray(['error' => '项目名称必填']);
        if (!in_array($direction, ['reward', 'penalty'], true)) return resultArray(['error' => '类型只允许奖励或处罚']);
        if (!in_array($calcMode, ['fixed', 'range', 'pool'], true)) return resultArray(['error' => '计算方式只允许 fixed/range/pool']);
        if ($calcMode === 'fixed' && $amount <= 0) return resultArray(['error' => '固定金额必须大于0']);
        if ($calcMode === 'range' && $amountMax <= 0) return resultArray(['error' => '区间最大金额必须大于0']);
        if ($calcMode === 'range' && $amountMin > $amountMax) return resultArray(['error' => '区间最小金额不能大于最大金额']);
        if ($calcMode === 'pool' && ($poolPct <= 0 || $poolPct > 100)) return resultArray(['error' => '奖金池比例必须大于0且不超过100']);
        $now = time();
        $data = [
            'rule_name' => $ruleName,
            'direction' => $direction,
            'amount' => $amount,
            'calc_mode' => $calcMode,
            'amount_min' => $amountMin,
            'amount_max' => $amountMax,
            'pool_pct' => $poolPct,
            'category' => trim((string)$value('category', '')),
            'description' => trim((string)$value('description', '')),
            'is_enabled' => $isEnabled,
            'sort_order' => (int)$value('sort_order', 0),
            'update_user_id' => (int)$userInfo['id'],
            'update_time' => $now,
        ];
        // 编辑时 rule_code 不可修改
        if ($id === 0 && !empty($param['rule_code'])) {
            $data['rule_code'] = trim((string)$param['rule_code']);
        }
        try {
            if ($id > 0) {
                unset($data['rule_code']); // 编辑时不修改 rule_code
                Db::name('reward_manual_rule')->where('manual_rule_id', $id)->update($data);
            } else {
                $data['create_user_id'] = (int)$userInfo['id'];
                $data['create_time'] = $now;
                $id = Db::name('reward_manual_rule')->insertGetId($data);
            }
        } catch (\Exception $e) {
            \think\Log::record('manualRuleSave失败（表可能未创建）: ' . $e->getMessage(), 'error');
            return resultArray(['error' => '奖惩项目配置功能需要数据库迁移，请联系管理员执行迁移']);
        }
        SystemActionLog($userInfo['id'], 'crm_reward', 'manual_rule', $id, 'manualRuleSave', '人工奖惩项目配置', '', '', $ruleName . ' ' . $direction . ' ' . $amount);
        return resultArray(['data' => ['manual_rule_id' => $id]]);
    }

    /**
     * 人工奖惩项目删除（已被引用的只能停用）
     */
    public function manualRuleDelete()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $id = (int)($param['manual_rule_id'] ?? 0);
        if ($id <= 0) return resultArray(['error' => '参数错误']);
        // 检查是否被候选引用
        $refCount = Db::name('reward_candidate')->where('manual_rule_id', $id)->count();
        if ($refCount > 0) {
            Db::name('reward_manual_rule')->where('manual_rule_id', $id)->update(['is_enabled' => 0, 'update_time' => time()]);
            return resultArray(['data' => '该项目已被引用，已自动停用']);
        }
        Db::name('reward_manual_rule')->where('manual_rule_id', $id)->delete();
        SystemActionLog($userInfo['id'], 'crm_reward', 'manual_rule', $id, 'manualRuleDelete', '删除人工奖惩项目', '', '', 'ID:' . $id);
        return resultArray(['data' => '删除成功']);
    }

    public function candidateList()
    {
        $param = $this->param;
        $canDeleteCandidate = $this->canDeleteCandidate((int)$this->userInfo['id']);
        $scopeUserId = $this->isRewardVisibilityAdmin($this->userInfo) ? 0 : (int)$this->userInfo['id'];
        $hasUpdateUserCol = $this->rewardCandidateHasColumn('update_user_id');
        $page = max(1, (int)($param['page'] ?? 1));
        $limit = max(1, min(200, (int)($param['limit'] ?? 50)));

        // 显式列出 reward_candidate 字段，不使用 r.*
        $baseFields = 'r.cand_id,r.source_type,r.source_ref,r.user_id,r.amount,r.reason,'
            . 'r.evidence_note,r.rules_version,r.status,r.occurred_time,'
            . 'r.create_user_id,r.create_time,r.update_time,'
            . 'r.batch_id,r.reviewer_user_id,r.review_time,r.review_note,'
            . 'r.customer_id,r.business_id,rb.status as batch_status';
        if ($this->rewardCandidateHasColumn('stage_name')) $baseFields .= ',r.stage_name';
        if ($this->rewardCandidateHasColumn('rule_id')) $baseFields .= ',r.rule_id';
        if ($hasUpdateUserCol) $baseFields .= ',r.update_user_id';
        $joinFields = $baseFields
            . ',u.realname as user_name,c.name as customer_name,b.name as business_name,cu.realname as create_user_name';
        if ($hasUpdateUserCol) $joinFields .= ',uu.realname as update_user_name';

        // COUNT 查询（独立 Query 对象）
        $countQuery = $this->buildCandidateQuery($param, $scopeUserId);
        $total = $countQuery->count();

        // 列表查询（独立 Query 对象，不复用 $q）
        $listQuery = $this->buildCandidateQuery($param, $scopeUserId);
        if ($hasUpdateUserCol) {
            $listQuery->join('__ADMIN_USER__ uu', 'r.update_user_id=uu.id', 'LEFT');
        }
        $list = $listQuery->field($joinFields)
            ->order('r.cand_id desc')
            ->page($page, $limit)
            ->select();

        foreach ($list as &$row) {
            $row['occurred_date'] = !empty($row['occurred_time']) ? date('Y-m-d', $row['occurred_time']) : '';
            $row['direction'] = (float)$row['amount'] < 0 ? '处罚' : '奖励';
            $row['create_time_str'] = !empty($row['create_time']) ? date('Y-m-d H:i:s', $row['create_time']) : '';
            $row['update_time_str'] = !empty($row['update_time']) ? date('Y-m-d H:i:s', $row['update_time']) : '';
            if (!$hasUpdateUserCol) {
                $row['update_user_id'] = 0;
                $row['update_user_name'] = '';
            } elseif (!isset($row['update_user_name'])) {
                $row['update_user_name'] = '';
            }
            $row['can_edit'] = in_array($row['status'], [RewardService::ST_PENDING, RewardService::ST_REJECTED, RewardService::ST_SPECIAL], true)
                || ($row['status'] === RewardService::ST_APPROVED && (int)($row['batch_id'] ?? 0) === 0);
            $row['can_delete'] = $canDeleteCandidate
                && (string)($row['batch_status'] ?? '') !== '已结算'
                && in_array($row['status'], [RewardService::ST_PENDING, RewardService::ST_REJECTED, RewardService::ST_SPECIAL, RewardService::ST_APPROVED, RewardService::ST_OFFSET], true);
        }
        unset($row);
        return resultArray(['data' => ['list' => $list, 'dataCount' => $total]]);
    }

    /**
     * 构建奖励候选查询（每次返回新的 Query 对象，避免状态污染）
     */
    private function buildCandidateQuery($param, $scopeUserId = 0)
    {
        $q = Db::name('reward_candidate')->alias('r')
            ->join('__ADMIN_USER__ u', 'r.user_id=u.id', 'LEFT')
            ->join('__CRM_CUSTOMER__ c', 'r.customer_id=c.customer_id', 'LEFT')
            ->join('__CRM_BUSINESS__ b', 'r.business_id=b.business_id', 'LEFT')
            ->join('__ADMIN_USER__ cu', 'r.create_user_id=cu.id', 'LEFT')
            ->join('__REWARD_BATCH__ rb', 'r.batch_id=rb.batch_id', 'LEFT');
        if ((int)$scopeUserId > 0) {
            $scopeUserId = (int)$scopeUserId;
            $q->where(function($query) use ($scopeUserId) {
                $query->where('r.user_id', $scopeUserId)
                    ->whereOr('r.create_user_id', $scopeUserId)
                    ->whereOr('r.reviewer_user_id', $scopeUserId);
            });
        }
        if (!empty($param['status'])) $q->where(['r.status' => $param['status']]);
        if (!empty($param['user_id'])) $q->where(['r.user_id' => (int)$param['user_id']]);
        if (!empty($param['direction'])) {
            if ($param['direction'] === 'reward') $q->where('r.amount', '>=', 0);
            elseif ($param['direction'] === 'penalty') $q->where('r.amount', '<', 0);
        }
        if (!empty($param['source_type'])) $q->where('r.source_type', 'like', '%' . $param['source_type'] . '%');
        if (!empty($param['keyword'])) {
            $kw = '%' . $param['keyword'] . '%';
            $q->where(function($query) use ($kw) {
                $query->where('u.realname', 'like', $kw)
                    ->whereOr('c.name', 'like', $kw)
                    ->whereOr('b.name', 'like', $kw)
                    ->whereOr('r.reason', 'like', $kw);
            });
        }
        if (!empty($param['date_start'])) $q->where('r.occurred_time', '>=', strtotime((string)$param['date_start']));
        if (!empty($param['date_end'])) $q->where('r.occurred_time', '<', strtotime((string)$param['date_end'] . ' +1 day'));
        return $q;
    }

    /** 检测 reward_candidate 表是否有某列（兼容迁移未执行的环境） */
    private function rewardCandidateHasColumn($column)
    {
        $prefix = config('database.prefix') ?: '';
        $tableName = $prefix . 'reward_candidate';
        $row = Db::query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . addslashes($tableName) . "' AND COLUMN_NAME='" . addslashes($column) . "'");
        return !empty($row) && (int)$row[0]['cnt'] > 0;
    }

    /** 当 update_user_id 列存在时，向更新数组注入操作人ID */
    private function rewardMergeUpdateUser(array &$update, $userId)
    {
        if ($this->rewardCandidateHasColumn('update_user_id')) {
            $update['update_user_id'] = (int)$userId;
        }
    }

    /** 超级管理员或拥有 crm/reward/update 权限的人员可以管理奖惩候选。 */
    private function canManageCandidate($userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) return false;
        if (isSuperAdministrators($userId)) return true;
        $userModel = new \app\admin\model\User();
        $authIds = (array)$userModel->getUserByPer('crm', 'reward', 'update');
        return in_array($userId, $authIds);
    }

    /** 超级管理员或角色拥有“删除候选”权限时可以删除奖惩候选。 */
    private function canDeleteCandidate($userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) return false;
        if (isSuperAdministrators($userId)) return true;
        $userModel = new \app\admin\model\User();
        $authIds = (array)$userModel->getUserByPer('crm', 'reward', 'candidatedelete');
        return in_array($userId, $authIds);
    }

    /** 指定奖励管理员账号可查看全部奖惩，其余账号仅查看本人相关数据。 */
    private function isRewardVisibilityAdmin($userInfo)
    {
        $adminLogin = '15628812133';
        if ((string)($userInfo['username'] ?? '') === $adminLogin || (string)($userInfo['mobile'] ?? '') === $adminLogin) {
            return true;
        }
        $userId = (int)($userInfo['id'] ?? 0);
        if ($userId <= 0) return false;
        $account = Db::name('admin_user')->where('id', $userId)->field('username,mobile')->find();
        return $account && ((string)$account['username'] === $adminLogin || (string)$account['mobile'] === $adminLogin);
    }

    /** 判断候选记录是否与当前账号相关。 */
    private function candidateVisibleToUser(array $candidate, $userInfo)
    {
        if ($this->isRewardVisibilityAdmin($userInfo)) return true;
        $userId = (int)($userInfo['id'] ?? 0);
        return $userId > 0 && in_array($userId, [
            (int)($candidate['user_id'] ?? 0),
            (int)($candidate['create_user_id'] ?? 0),
            (int)($candidate['reviewer_user_id'] ?? 0),
        ], true);
    }

    /** 普通账号的汇总也必须按本人相关范围统计，不能泄露全员数据。 */
    private function relatedCandidateSummary($userId)
    {
        $out = [];
        $statuses = [RewardService::ST_PENDING, RewardService::ST_SPECIAL, RewardService::ST_APPROVED, RewardService::ST_REJECTED, RewardService::ST_SETTLED, RewardService::ST_OFFSET, RewardService::ST_VOIDED];
        foreach ($statuses as $status) {
            $out[$status] = (int)$this->buildCandidateQuery(['status' => $status], (int)$userId)->count();
        }
        $out['approved_amount'] = (float)$this->buildCandidateQuery(['status' => RewardService::ST_APPROVED], (int)$userId)->sum('r.amount');
        return $out;
    }

    /** 审核：approve/reject；本人回避；仅待审核可审 */
    public function review()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $candId = (int)($param['cand_id'] ?? 0);
        $decision = trim((string)($param['decision'] ?? ''));
        if ($candId <= 0) return resultArray(['error' => '参数错误']);
        if (!in_array($decision, ['approve', 'reject'], true)) return resultArray(['error' => '请选择审核操作（通过或驳回）']);
        $c = Db::name('reward_candidate')->where(['cand_id' => $candId])->find();
        if (!$c) return resultArray(['error' => '候选记录不存在']);
        if (!$this->candidateVisibleToUser($c, $userInfo)) return resultArray(['error' => '无权操作该奖惩候选']);
        if (!in_array($c['status'], [RewardService::ST_PENDING, RewardService::ST_SPECIAL], true)) {
            return resultArray(['error' => '仅待审核/待专项审批的候选可审核']);
        }

        // 审核权限规则：
        // 1. 管理员/拥有审核权限的人，可以审核自己创建的候选（create_user_id == 审核人），不再禁止
        // 2. 普通员工不能审核自己作为奖惩对象的记录（user_id == 审核人）
        // 3. 仅超级管理员可以审核自己作为奖惩对象的记录（user_id == 审核人），必须填写审核意见
        $isSuperAdmin = isSuperAdministrators($userInfo['id']);
        $isSelfCandidate = ((int)$c['user_id'] === (int)$userInfo['id']);
        if ($isSelfCandidate && !$isSuperAdmin) {
            return resultArray(['error' => '本人回避：不能审核自己作为奖惩对象的记录']);
        }
        if ($isSelfCandidate && $isSuperAdmin && trim((string)($param['review_note'] ?? '')) === '') {
            return resultArray(['error' => '审核自己作为奖惩对象的记录，必须填写审核意见']);
        }

        $newStatus = $decision === 'approve' ? RewardService::ST_APPROVED : RewardService::ST_REJECTED;
        $now = time();
        $reviewUpdate = [
            'status' => $newStatus, 'reviewer_user_id' => (int)$userInfo['id'],
            'review_time' => $now, 'review_note' => trim((string)($param['review_note'] ?? '')), 'update_time' => $now,
        ];
        $this->rewardMergeUpdateUser($reviewUpdate, (int)$userInfo['id']);
        Db::name('reward_candidate')->where(['cand_id' => $candId])->update($reviewUpdate);

        // 写审计
        $this->safeInsertAudit($candId, 'review_' . $decision, $c, $reviewUpdate,
            '审核' . ($decision === 'approve' ? '通过' : '驳回') . ($isSelfCandidate ? '（管理员自审）' : ''),
            $userInfo, $now, Request::instance()->ip());

        // 系统日志
        SystemActionLog($userInfo['id'], 'crm_reward', 'reward', $candId, 'review',
            '奖惩审核', '', '',
            '审核人' . ($userInfo['realname'] ?? '') . ($decision === 'approve' ? '通过' : '驳回') .
            '候选 RC-' . str_pad((string)$candId, 6, '0', STR_PAD_LEFT) .
            ($isSelfCandidate ? '（自审）' : ''));

        return resultArray(['data' => ['cand_id' => $candId, 'status' => $newStatus]]);
    }

    /** 创建结算批次：汇总所有已通过候选进入批次 */
    public function batchCreate()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        if (!$this->isRewardVisibilityAdmin($userInfo)) return resultArray(['error' => '仅奖励管理员可以生成结算批次']);
        $period = trim((string)($param['period'] ?? date('Ym')));
        $approved = Db::name('reward_candidate')->where(['status' => RewardService::ST_APPROVED])->select();
        if (!$approved) return resultArray(['error' => '没有已通过的候选可结算']);
        $total = round((float)array_sum(array_column($approved, 'amount')), 2);
        $now = time();
        Db::startTrans();
        try {
            $batchId = Db::name('reward_batch')->insertGetId([
                'name' => trim((string)($param['name'] ?? ('结算批次 ' . $period))),
                'period' => $period, 'total_amount' => $total, 'status' => '待结算',
                'create_user_id' => (int)$userInfo['id'], 'create_time' => $now,
            ]);
            Db::name('reward_candidate')->where(['status' => RewardService::ST_APPROVED])->update(['batch_id' => $batchId]);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => '创建结算批次失败：' . $e->getMessage()]);
        }
        return resultArray(['data' => ['batch_id' => $batchId, 'total_amount' => $total, 'count' => count($approved)]]);
    }

    /** 批次结算：标记批次已结算，候选 → 已结算（仅导出给财务，不自动发放） */
    public function batchSettle()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        if (!$this->isRewardVisibilityAdmin($userInfo)) return resultArray(['error' => '仅奖励管理员可以执行结算']);
        $batchId = (int)($param['batch_id'] ?? 0);
        $b = Db::name('reward_batch')->where(['batch_id' => $batchId])->find();
        if (!$b) return resultArray(['error' => '批次不存在']);
        Db::startTrans();
        try {
            Db::name('reward_batch')->where(['batch_id' => $batchId])->update(['status' => '已结算']);
            $settleUpdate = ['status' => RewardService::ST_SETTLED, 'update_time' => time()];
            $this->rewardMergeUpdateUser($settleUpdate, (int)($userInfo['id'] ?? 0));
            Db::name('reward_candidate')->where(['batch_id' => $batchId])->update($settleUpdate);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => '结算失败：' . $e->getMessage()]);
        }
        return resultArray(['data' => ['batch_id' => $batchId, 'status' => '已结算', 'note' => '已生成结算建议，不自动发放，请导出给财务']]);
    }

    /** 冲销：对某候选记录冲销金额（事务） */
    public function offset()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $candId = (int)($param['cand_id'] ?? 0);
        $offsetAmount = round((float)($param['offset_amount'] ?? 0), 2);
        if ($candId <= 0 || $offsetAmount <= 0) return resultArray(['error' => '参数错误']);
        $c = Db::name('reward_candidate')->where(['cand_id' => $candId])->find();
        if (!$c) return resultArray(['error' => '候选不存在']);
        if (!$this->candidateVisibleToUser($c, $userInfo)) return resultArray(['error' => '无权操作该奖惩候选']);
        $now = time();
        Db::startTrans();
        try {
            Db::name('reward_offset')->insert([
                'cand_id' => $candId, 'offset_amount' => $offsetAmount,
                'reason' => trim((string)($param['reason'] ?? '')),
                'create_user_id' => (int)$userInfo['id'], 'create_time' => $now,
            ]);
            $offsetUpdate = ['status' => RewardService::ST_OFFSET, 'update_time' => $now];
            $this->rewardMergeUpdateUser($offsetUpdate, (int)$userInfo['id']);
            Db::name('reward_candidate')->where(['cand_id' => $candId])->update($offsetUpdate);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => '冲销失败：' . $e->getMessage()]);
        }
        return resultArray(['data' => ['cand_id' => $candId, 'offset_amount' => $offsetAmount]]);
    }

    /** 配置项读写（未确认金额/上限/拆分；未配置=待配置） */
    public function configSave()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $key = trim((string)($param['config_key'] ?? ''));
        $allowKeys = ['dealer_first_payment_reward', 'outsource_business_pool_pct', 'outsource_revenue_cap'];
        if (!in_array($key, $allowKeys, true)) return resultArray(['error' => '不支持的配置项']);
        $rawValue = $param['config_value'] ?? null;
        // 空值表示待配置，允许保存 null
        if ($rawValue === null || $rawValue === '') {
            RewardService::setConfig($key, null, $userInfo['id']);
            return resultArray(['data' => ['config_key' => $key, 'config' => RewardService::configStatus()]]);
        }
        // 数值校验
        $numVal = is_numeric($rawValue) ? (float)$rawValue : null;
        if ($numVal === null) return resultArray(['error' => '配置值必须为数字']);
        if ($key === 'dealer_first_payment_reward') {
            if ($numVal < 0) return resultArray(['error' => '奖励金额必须大于等于0']);
        } elseif ($key === 'outsource_revenue_cap') {
            if ($numVal < 0) return resultArray(['error' => '收入上限必须大于等于0']);
        } elseif ($key === 'outsource_business_pool_pct') {
            // 比例采用百分数（0-100），不是小数
            if ($numVal < 0 || $numVal > 100) return resultArray(['error' => '比例必须在0-100之间（百分数）']);
        }
        RewardService::setConfig($key, (string)$numVal, $userInfo['id']);
        return resultArray(['data' => ['config_key' => $key, 'config' => RewardService::configStatus()]]);
    }

    /** 商务费用（独立流程，与奖励物理分离） */
    public function expenseSave()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        if (trim((string)($param['subject'] ?? '')) === '') return resultArray(['error' => '事项不能为空']);
        $now = time();
        $id = Db::name('business_expense')->insertGetId([
            'source_ref' => (string)($param['source_ref'] ?? ''),
            'subject' => trim((string)$param['subject']),
            'amount' => (float)($param['amount'] ?? 0),
            'external_party' => trim((string)($param['external_party'] ?? '')),
            'agreement_status' => trim((string)($param['agreement_status'] ?? '')),
            'compliance_confirmed' => empty($param['compliance_confirmed']) ? 0 : 1,
            'status' => '待审批',
            'create_user_id' => (int)$userInfo['id'], 'create_time' => $now, 'update_time' => $now,
        ]);
        return resultArray(['data' => ['expense_id' => $id]]);
    }

    public function expenseList()
    {
        $list = Db::name('business_expense')->order('expense_id desc')->limit(200)->select();
        return resultArray(['data' => ['list' => $list]]);
    }

    // ===== 审批型功能（四级/经销商后续/年度经营/阶段抵扣/付款跟踪） =====

    /** 1-2,5,6. 制度审批申请（实施四级/外包四级/经销商后续/年度经营） */
    public function approvalRequest()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $type = trim((string)($param['approval_type'] ?? ''));
        $allowTypes = ['impl_level_4','outsource_level_4','dealer_followup','annual_bonus'];
        if (!in_array($type, $allowTypes, true)) return resultArray(['error' => '不支持的审批类型']);
        $val = (float)($param['requested_value'] ?? 0);
        $ref = (string)($param['source_ref'] ?? '');
        if ($ref === '') return resultArray(['error' => '必须关联项目或结算对象']);

        // 范围校验（制度硬约束）
        $ranges = [
            'impl_level_4' => [10, 12, '实施四级只允许10%-12%'],
            'outsource_level_4' => [0, 30, '外包四级最高30%'],
            'dealer_followup' => [0, 1, '经销商后续只允许0.5%/0.8%/1%'],
            'annual_bonus' => [8, 12, '年度经营奖金池只允许8%-12%'],
        ];
        $r = $ranges[$type];
        if ($val < $r[0] || $val > $r[1]) return resultArray(['error' => $r[2]]);
        if ($type === 'dealer_followup' && !in_array($val, [0.5, 0.8, 1.0], true)) {
            return resultArray(['error' => '经销商后续只允许0.5%/0.8%/1%']);
        }

        // 重复防护：同一对象同一类型不得存在已通过记录
        $existApproved = Db::name('policy_approval')->where(['approval_type' => $type, 'source_ref' => $ref, 'result' => '已通过'])->find();
        if ($existApproved) return resultArray(['error' => '该对象已有通过的同类型审批，不得重复']);

        $now = time();
        $id = Db::name('policy_approval')->insertGetId([
            'approval_type' => $type, 'source_ref' => $ref,
            'requested_value' => $val, 'basis_note' => trim((string)($param['basis_note'] ?? '')),
            'applicant_user_id' => (int)$userInfo['id'], 'result' => '待审批',
            'create_time' => $now, 'update_time' => $now,
        ]);
        return resultArray(['data' => ['approval_id' => $id, 'note' => '未审批不得据此计算奖金']]);
    }

    /** 审批决定（approve/reject；本人回避） */
    public function approvalDecide()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $id = (int)($param['approval_id'] ?? 0);
        $decision = trim((string)($param['decision'] ?? ''));
        if ($id <= 0 || !in_array($decision, ['approve','reject'], true)) return resultArray(['error' => '参数错误']);
        $a = Db::name('policy_approval')->where(['approval_id' => $id])->find();
        if (!$a) return resultArray(['error' => '审批记录不存在']);
        if ($a['result'] !== '待审批') return resultArray(['error' => '该审批已处理']);
        if ((int)$a['applicant_user_id'] === (int)$userInfo['id']) return resultArray(['error' => '本人回避：不能审批自己提交的申请']);
        $newResult = $decision === 'approve' ? '已通过' : '已驳回';
        Db::name('policy_approval')->where(['approval_id' => $id])->update([
            'result' => $newResult, 'approver_user_id' => (int)$userInfo['id'],
            'approve_note' => trim((string)($param['approve_note'] ?? '')), 'approve_time' => time(), 'update_time' => time(),
        ]);
        return resultArray(['data' => ['approval_id' => $id, 'result' => $newResult]]);
    }

    /** 检查某项目某类型审批是否已通过（门禁） */
    public function approvalCheck()
    {
        $param = $this->param;
        $type = trim((string)($param['approval_type'] ?? ''));
        $ref = trim((string)($param['source_ref'] ?? ''));
        $approved = Db::name('policy_approval')->where(['approval_type' => $type, 'source_ref' => $ref, 'result' => '已通过'])->find();
        return resultArray(['data' => ['approved' => $approved ? true : false, 'note' => $approved ? '审批已通过' : '未审批通过，不得据此计算']]);
    }

    /** 3. 阶段奖励抵扣结算：按 user_id + project_ref 查询，禁止跨项目抵扣 */
    public function stageOffsetCalc()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $userId = (int)($param['user_id'] ?? 0);
        $finalShare = (float)($param['final_share'] ?? 0);
        $projectRef = trim((string)($param['project_ref'] ?? ''));
        $batchId = (int)($param['batch_id'] ?? 0);
        if ($userId <= 0 || $finalShare < 0 || $projectRef === '') return resultArray(['error' => 'user_id/project_ref/final_share 必填']);

        // 重复防护：同一批次+人员+项目只能计算一次
        $existOffset = Db::name('stage_offset')->where(['batch_id' => $batchId, 'user_id' => $userId, 'project_ref' => $projectRef])->find();
        if ($existOffset) return resultArray(['error' => '该批次/人员/项目已计算抵扣，不得重复']);

        // 仅查询该项目已实际领取或已结算的预发阶段奖励（按source_ref匹配项目）
        $stageTypes = ['经销商有效联系','经销商正式交流','经销商明确项目','医院有效联系','医院正式演示或拜访','医院明确项目','外包正式需求沟通','外包方案或报价'];
        $candidates = Db::name('reward_candidate')
            ->where('user_id', $userId)
            ->whereIn('source_type', $stageTypes)
            ->whereIn('status', ['已通过','已结算'])
            ->where('source_ref', 'like', '%' . $projectRef . '%')
            ->select();
        $offsetTotal = 0.0;
        $candIds = [];
        foreach ($candidates as $c) { $offsetTotal += (float)$c['amount']; $candIds[] = $c['cand_id']; }
        $offsetTotal = round($offsetTotal, 2);
        $netPayable = max(0, round($finalShare - $offsetTotal, 2));
        $now = time();
        $id = Db::name('stage_offset')->insertGetId([
            'batch_id' => $batchId, 'user_id' => $userId, 'project_ref' => $projectRef,
            'final_share' => $finalShare, 'offset_total' => $offsetTotal, 'net_payable' => $netPayable,
            'detail_json' => json_encode(['candidate_ids' => $candIds, 'offset' => $offsetTotal, 'net' => $netPayable], JSON_UNESCAPED_UNICODE),
            'create_user_id' => (int)$userInfo['id'], 'create_time' => $now,
        ]);
        return resultArray(['data' => ['offset_id' => $id, 'final_share' => $finalShare, 'offset_total' => $offsetTotal, 'net_payable' => $netPayable, 'candidate_ids' => $candIds, 'note' => '仅抵扣本项目阶段奖励，最低0']]);
    }

    /** 4. 付款到账记录 */
    public function paymentRecord()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $projectRef = trim((string)($param['project_ref'] ?? ''));
        if ($projectRef === '') return resultArray(['error' => '项目引用不能为空']);
        $now = time();
        $id = Db::name('payment_tracking')->insertGetId([
            'project_ref' => $projectRef, 'payment_type' => trim((string)($param['payment_type'] ?? '')),
            'amount' => (float)($param['amount'] ?? 0), 'received_amount' => 0, 'status' => '待确认',
            'create_time' => $now, 'update_time' => $now,
        ]);
        return resultArray(['data' => ['payment_id' => $id]]);
    }

    /** 确认付款到账（确认后允许暂发50%） */
    public function paymentConfirm()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $id = (int)($param['payment_id'] ?? 0);
        if ($id <= 0) return resultArray(['error' => '参数错误']);
        $pm = Db::name('payment_tracking')->where(['payment_id' => $id])->find();
        if (!$pm) return resultArray(['error' => '付款记录不存在']);
        Db::name('payment_tracking')->where(['payment_id' => $id])->update([
            'received_amount' => (float)($param['received_amount'] ?? $pm['amount']),
            'received_time' => time(), 'confirmed_by' => (int)$userInfo['id'],
            'status' => '已到账', 'update_time' => time(),
        ]);
        $canRelease50 = ($pm['payment_type'] === '首付款' && (float)($param['received_amount'] ?? 0) > 0);
        return resultArray(['data' => ['payment_id' => $id, 'status' => '已到账', 'can_release_50pct' => $canRelease50, 'note' => $canRelease50 ? '首付款到账，可暂发业务获取奖励50%' : '']]);
    }

    // ===== 管理员编辑与审计 =====

    /** 读取单个奖惩候选详情 */
    public function candidateRead()
    {
        $param = $this->param;
        $candId = (int)($param['cand_id'] ?? 0);
        if ($candId <= 0) return resultArray(['error' => '参数错误']);
        $readFields = 'r.cand_id,r.source_type,r.source_ref,r.user_id,r.amount,r.reason,'
            . 'r.evidence_note,r.rules_version,r.status,r.occurred_time,'
            . 'r.create_user_id,r.create_time,r.update_time,'
            . 'r.batch_id,r.reviewer_user_id,r.review_time,r.review_note,'
            . 'r.customer_id,r.business_id'
            . ',u.realname as user_name,c.name as customer_name,b.name as business_name';
        if ($this->rewardCandidateHasColumn('stage_name')) $readFields .= ',r.stage_name';
        if ($this->rewardCandidateHasColumn('rule_id')) $readFields .= ',r.rule_id';
        if ($this->rewardCandidateHasColumn('manual_rule_id')) $readFields .= ',r.manual_rule_id';
        $row = Db::name('reward_candidate')->alias('r')
            ->join('__ADMIN_USER__ u', 'r.user_id=u.id', 'LEFT')
            ->join('__CRM_CUSTOMER__ c', 'r.customer_id=c.customer_id', 'LEFT')
            ->join('__CRM_BUSINESS__ b', 'r.business_id=b.business_id', 'LEFT')
            ->field($readFields)
            ->where(['r.cand_id' => $candId])->find();
        if (!$row) return resultArray(['error' => '候选不存在']);
        if (!$this->candidateVisibleToUser($row, $this->userInfo)) return resultArray(['error' => '无权查看该奖惩候选']);
        $row['occurred_date'] = !empty($row['occurred_time']) ? date('Y-m-d', $row['occurred_time']) : '';
        $row['direction'] = (float)$row['amount'] < 0 ? '处罚' : '奖励';
        return resultArray(['data' => $row]);
    }

    /** 管理员编辑奖惩候选（带审计事务） */
    public function candidateUpdate()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $candId = (int)($param['cand_id'] ?? 0);
        if ($candId <= 0) return resultArray(['error' => '参数错误']);
        $changeReason = trim((string)($param['change_reason'] ?? ''));
        if ($changeReason === '') return resultArray(['error' => '必须填写修改原因']);

        // 权限校验：超级管理员或拥有 reward update 权限
        if (!$this->canManageCandidate((int)$userInfo['id'])) {
            return resultArray(['error' => '无权编辑奖惩候选']);
        }

        Db::startTrans();
        try {
            // 锁定候选行
            $c = Db::name('reward_candidate')->where(['cand_id' => $candId])->lock(true)->find();
            if (!$c) throw new \Exception('候选不存在');
            if (!$this->candidateVisibleToUser($c, $userInfo)) throw new \Exception('无权操作该奖惩候选');

            // 状态规则：已结算/已冲销/已进入结算批次禁止编辑
            $forbiddenStatuses = [RewardService::ST_SETTLED, RewardService::ST_OFFSET];
            if (in_array($c['status'], $forbiddenStatuses, true)) {
                throw new \Exception('当前状态（' . $c['status'] . '）不允许直接编辑，请通过冲销或更正流程处理');
            }
            // 已通过且已进入批次（batch_id > 0）禁止编辑
            if ($c['status'] === RewardService::ST_APPROVED && (int)($c['batch_id'] ?? 0) > 0) {
                throw new \Exception('已进入结算批次的记录不允许编辑');
            }

            // 构建更新数据
            $update = [];

            // 如果修改了奖惩项目，从规则重新计算方向和金额
            $manualRuleId = (int)($param['manual_rule_id'] ?? 0);
            if ($manualRuleId > 0) {
                $rule = Db::name('reward_manual_rule')->where(['manual_rule_id' => $manualRuleId, 'is_enabled' => 1])->find();
                if (!$rule) throw new \Exception('所选奖惩项目不存在或已停用');
                $amount = abs((float)$rule['amount']);
                if ($rule['direction'] === 'penalty') $amount = -$amount;
                $update['manual_rule_id'] = $manualRuleId;
                $update['amount'] = round($amount, 2);
                $update['source_type'] = 'manual:' . $rule['rule_name'];
                $update['source_ref'] = 'manual_rule:' . $manualRuleId . ':' . (int)($param['user_id'] ?? $c['user_id']) . ':' . time();
            }

            // 如果修改了候选人员，验证员工有效
            if (isset($param['user_id']) && (int)$param['user_id'] > 0) {
                $userId = (int)$param['user_id'];
                $user = db('admin_user')->where(['id' => $userId, 'status' => 1])->field('id')->find();
                if (!$user) throw new \Exception('所选员工不存在或已禁用');
                $update['user_id'] = $userId;
            }

            // 允许编辑的字段（不再允许前端直接修改 amount 和 direction）
            $allowedFields = ['reason', 'evidence_note', 'occurred_time'];
            foreach ($allowedFields as $f) {
                if (array_key_exists($f, $param)) {
                    if ($f === 'occurred_time') {
                        $update[$f] = !empty($param[$f]) ? (is_numeric($param[$f]) ? (int)$param[$f] : strtotime((string)$param[$f])) : time();
                    } else {
                        $update[$f] = trim((string)$param[$f]);
                    }
                }
            }

            // 已通过未进入批次：编辑后重置为待审核
            $resetReview = false;
            if ($c['status'] === RewardService::ST_APPROVED && (int)($c['batch_id'] ?? 0) === 0) {
                $resetReview = true;
                $update['status'] = RewardService::ST_PENDING;
                $update['reviewer_user_id'] = 0;
                $update['review_time'] = 0;
                $update['review_note'] = '';
            }

            // 记录修改前数据用于审计
            $oldData = [
                'user_id' => (int)$c['user_id'], 'amount' => (float)$c['amount'],
                'source_type' => $c['source_type'], 'source_ref' => $c['source_ref'],
                'customer_id' => (int)($c['customer_id'] ?? 0), 'business_id' => (int)($c['business_id'] ?? 0),
                'reason' => $c['reason'], 'evidence_note' => $c['evidence_note'],
                'occurred_time' => (int)($c['occurred_time'] ?? 0), 'status' => $c['status'],
            ];

            $update['update_time'] = time();
            $this->rewardMergeUpdateUser($update, (int)$userInfo['id']);
            Db::name('reward_candidate')->where(['cand_id' => $candId])->update($update);

            // 新数据用于审计
            $newData = array_merge($oldData, $update);

            // 写审计日志
            $auditId = Db::name('reward_candidate_audit')->insertGetId([
                'cand_id' => $candId,
                'operation_type' => $resetReview ? 'edit_and_reset' : 'edit',
                'old_data_json' => json_encode($oldData, JSON_UNESCAPED_UNICODE),
                'new_data_json' => json_encode($newData, JSON_UNESCAPED_UNICODE),
                'change_reason' => $changeReason,
                'operator_user_id' => (int)$userInfo['id'],
                'operator_name' => $userInfo['realname'] ?? '',
                'operation_time' => time(),
                'request_ip' => Request::instance()->ip(),
                'create_time' => time(),
            ]);

            // 系统操作日志
            $changes = [];
            if (isset($update['user_id']) && (int)$update['user_id'] !== $oldData['user_id']) {
                $oldName = Db::name('admin_user')->where('id', $oldData['user_id'])->value('realname') ?: ('用户#' . $oldData['user_id']);
                $newName = Db::name('admin_user')->where('id', $update['user_id'])->value('realname') ?: ('用户#' . $update['user_id']);
                $changes[] = '候选人由' . $oldName . '改为' . $newName;
            }
            if (isset($update['amount']) && (float)$update['amount'] !== $oldData['amount']) {
                $changes[] = '金额由' . $oldData['amount'] . '改为' . $update['amount'];
            }
            $logContent = '管理员' . ($userInfo['realname'] ?? '') . '修改奖惩候选 RC-' . str_pad((string)$candId, 6, '0', STR_PAD_LEFT);
            if ($changes) $logContent .= '：' . implode('，', $changes);
            $logContent .= '，原因：' . $changeReason;
            SystemActionLog($userInfo['id'], 'crm_reward', 'reward', $candId, 'update', '奖惩候选编辑', '', '', $logContent);

            Db::commit();
            return resultArray(['data' => ['cand_id' => $candId, 'reset_to_pending' => $resetReview, 'audit_id' => $auditId]]);
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => $e->getMessage()]);
        }
    }

    /**
     * 删除未结算奖惩候选。
     * 已结算批次中的记录必须保留；待结算批次中的记录可删除并同步重算批次。
     * 删除前完整写入候选、批次及冲销快照，确保人员、金额、来源和删除原因可追溯。
     */
    public function candidateDelete()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $candId = (int)($param['cand_id'] ?? 0);
        $deleteReason = trim((string)($param['delete_reason'] ?? ''));
        if ($candId <= 0) return resultArray(['error' => '参数错误']);
        if ($deleteReason === '') return resultArray(['error' => '必须填写删除原因']);
        if (!$this->canDeleteCandidate((int)$userInfo['id'])) {
            return resultArray(['error' => '无权删除奖惩候选']);
        }

        Db::startTrans();
        try {
            $candidate = Db::name('reward_candidate')->where(['cand_id' => $candId])->lock(true)->find();
            if (!$candidate) throw new \Exception('候选不存在或已被删除');
            if (!$this->candidateVisibleToUser($candidate, $userInfo)) throw new \Exception('无权操作该奖惩候选');
            $batch = [];
            $batchId = (int)($candidate['batch_id'] ?? 0);
            if ($batchId > 0) {
                $batch = Db::name('reward_batch')->where(['batch_id' => $batchId])->lock(true)->find();
                if ($batch && (string)$batch['status'] === '已结算') {
                    throw new \Exception('已结算批次中的记录不能直接删除，请保留冲销审计');
                }
            }
            $allowedStatuses = [RewardService::ST_PENDING, RewardService::ST_SPECIAL, RewardService::ST_REJECTED, RewardService::ST_APPROVED, RewardService::ST_OFFSET];
            if (!in_array((string)$candidate['status'], $allowedStatuses, true)) {
                throw new \Exception('当前状态（' . $candidate['status'] . '）不能删除，请使用冲销或更正流程');
            }

            $now = time();
            $offsets = Db::name('reward_offset')->where(['cand_id' => $candId])->select();
            $auditSnapshot = $candidate;
            $auditSnapshot['_batch'] = $batch ?: [];
            $auditSnapshot['_offsets'] = $offsets ?: [];
            Db::name('reward_candidate_audit')->insert([
                'cand_id' => $candId,
                'operation_type' => 'delete',
                'old_data_json' => json_encode($auditSnapshot, JSON_UNESCAPED_UNICODE),
                'new_data_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                'change_reason' => $deleteReason,
                'operator_user_id' => (int)$userInfo['id'],
                'operator_name' => $userInfo['realname'] ?? '',
                'operation_time' => $now,
                'request_ip' => Request::instance()->ip(),
                'create_time' => $now,
            ]);
            if ($offsets) Db::name('reward_offset')->where(['cand_id' => $candId])->delete();
            $deleted = Db::name('reward_candidate')->where(['cand_id' => $candId])->delete();
            if ((int)$deleted !== 1) throw new \Exception('删除失败，请刷新后重试');
            if ($batchId > 0 && $batch) {
                $remainingCount = (int)Db::name('reward_candidate')->where(['batch_id' => $batchId])->count();
                if ($remainingCount === 0) {
                    Db::name('reward_batch')->where(['batch_id' => $batchId])->delete();
                } else {
                    $remainingAmount = (float)Db::name('reward_candidate')->where(['batch_id' => $batchId])->sum('amount');
                    Db::name('reward_batch')->where(['batch_id' => $batchId])->update(['total_amount' => round($remainingAmount, 2)]);
                }
            }

            SystemActionLog($userInfo['id'], 'crm_reward', 'reward', $candId, 'delete',
                '删除奖惩候选', '', '',
                '删除 RC-' . str_pad((string)$candId, 6, '0', STR_PAD_LEFT) .
                '，候选人ID=' . (int)$candidate['user_id'] .
                '，金额=' . (float)$candidate['amount'] .
                '，原因：' . $deleteReason);
            Db::commit();
            return resultArray(['data' => ['cand_id' => $candId, 'deleted' => true]]);
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => $e->getMessage()]);
        }
    }

    /** 奖惩候选修改记录列表 */
    public function candidateAuditList()
    {
        $param = $this->param;
        $candId = (int)($param['cand_id'] ?? 0);
        if ($candId <= 0) return resultArray(['error' => '参数错误']);
        $candidate = Db::name('reward_candidate')->where(['cand_id' => $candId])->field('cand_id,user_id,create_user_id,reviewer_user_id')->find();
        if (!$candidate) return resultArray(['error' => '候选不存在']);
        if (!$this->candidateVisibleToUser($candidate, $this->userInfo)) return resultArray(['error' => '无权查看该奖惩候选审计']);
        try {
            $list = Db::name('reward_candidate_audit')
                ->where(['cand_id' => $candId])
                ->order('audit_id desc')
                ->limit(50)
                ->select();
        } catch (\Exception $e) {
            \think\Log::record('candidateAuditList查询失败（审计表可能未创建）: ' . $e->getMessage(), 'error');
            return resultArray(['data' => ['list' => []]]);
        }
        if (!$list) $list = [];
        foreach ($list as &$row) {
            $row['old_data'] = json_decode($row['old_data_json'] ?? '{}', true);
            $row['new_data'] = json_decode($row['new_data_json'] ?? '{}', true);
            $row['operation_time_str'] = !empty($row['operation_time']) ? date('Y-m-d H:i:s', $row['operation_time']) : '';
        }
        unset($row);
        return resultArray(['data' => ['list' => $list]]);
    }

    /**
     * 安全写入审计日志（审计表不存在时记录日志但不中断主流程）
     */
    private function safeInsertAudit($candId, $operationType, $oldData, $newData, $reason, $userInfo, $time, $ip)
    {
        try {
            Db::name('reward_candidate_audit')->insert([
                'cand_id' => (int)$candId,
                'operation_type' => $operationType,
                'old_data_json' => json_encode($oldData, JSON_UNESCAPED_UNICODE),
                'new_data_json' => json_encode($newData, JSON_UNESCAPED_UNICODE),
                'change_reason' => (string)$reason,
                'operator_user_id' => (int)$userInfo['id'],
                'operator_name' => $userInfo['realname'] ?? '',
                'operation_time' => (int)$time,
                'request_ip' => (string)$ip,
                'create_time' => (int)$time,
            ]);
        } catch (\Exception $e) {
            \think\Log::record('审计日志写入失败: ' . $e->getMessage(), 'error');
        }
    }

    // ===== 商机阶段奖励规则管理 =====
    // 统一数据源：商机类型(crm_business_type) + 商机阶段(crm_business_status) + 阶段奖励规则(business_stage_reward_rule)
    // 三者通过稳定 ID(type_id/status_id) 关联；名称仅用于展示，核心匹配不依赖名称。

    /**
     * 商机阶段奖励规则列表（关联类型与阶段真实名称，区分启用/历史组）
     */
    public function ruleList()
    {
        try {
            $list = RewardService::stageRewardRuleList(false);
        } catch (\Exception $e) {
            \think\Log::record('ruleList查询失败（表可能未创建）: ' . $e->getMessage(), 'error');
            $list = [];
        }
        return resultArray(['data' => ['list' => $list]]);
    }

    /**
     * 商机类型 + 阶段 + 是否已配置奖励 的树（用于配置页选择器/总览）
     */
    public function businessTypeStageList()
    {
        try {
            $list = RewardService::businessTypeStageTree();
        } catch (\Exception $e) {
            \think\Log::record('businessTypeStageList查询失败: ' . $e->getMessage(), 'error');
            $list = [];
        }
        return resultArray(['data' => ['list' => $list]]);
    }

    /** 校验阶段奖励规则编辑权限（超级管理员或拥有 crm/reward/update） */
    private function assertRewardConfigAuth($userInfo)
    {
        if (isSuperAdministrators($userInfo['id'])) return true;
        $userModel = new \app\admin\model\User();
        $authIds = $userModel->getUserByPer('crm', 'reward', 'update');
        return in_array($userInfo['id'], $authIds);
    }

    /**
     * 商机阶段奖励规则保存（新增/编辑）
     * - 关联校验：type_id 与 status_id 必须属于同一组，且阶段非终态
     * - 写变更审计（reward_rule_audit）：记录变更前后内容
     * - 历史候选不受影响：reward_candidate 在创建时已保存 amount/rules_version/rule_id 快照
     */
    public function ruleSave()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        if (!$this->assertRewardConfigAuth($userInfo)) return resultArray(['error' => '无权管理阶段奖励规则']);

        $ruleId = (int)($param['rule_id'] ?? 0);
        $typeId = (int)($param['type_id'] ?? 0);
        $statusId = (int)($param['status_id'] ?? 0);
        $ruleName = trim((string)($param['rule_name'] ?? ''));
        if ($typeId <= 0 || $statusId <= 0) return resultArray(['error' => '请选择商机类型与阶段']);
        // 关联一致性校验
        $stage = db('crm_business_status')->where(['status_id' => $statusId, 'type_id' => $typeId])->find();
        if (!$stage) return resultArray(['error' => '所选阶段不属于所选商机类型']);
        // 终态阶段（赢单/输单/无效，order_id>=99）不配置推进奖励
        if ((int)$stage['order_id'] >= 99) return resultArray(['error' => '终态阶段（赢单/输单/无效）不配置推进阶段奖励']);

        $typeName = (string)db('crm_business_type')->where('type_id', $typeId)->value('name');
        if ($ruleName === '') $ruleName = $typeName . '-' . $stage['name'];

        $data = [
            'rule_name'   => $ruleName,
            'direction'   => in_array(($param['direction'] ?? ''), ['reward', 'penalty'], true) ? $param['direction'] : 'reward',
            'source_type' => trim((string)($param['source_type'] ?? '商机阶段奖励')) ?: '商机阶段奖励',
            'type_id'     => $typeId,
            'status_id'   => $statusId,
            'calc_method' => in_array(($param['calc_method'] ?? ''), ['fixed', 'percent'], true) ? $param['calc_method'] : 'fixed',
            'amount'      => round((float)($param['amount'] ?? 0), 2),
            'single_cap'  => round((float)($param['single_cap'] ?? 0), 2),
            'monthly_cap' => round((float)($param['monthly_cap'] ?? 0), 2),
            'need_review' => empty($param['need_review']) ? 0 : 1,
            'auto_generate' => empty($param['auto_generate']) ? 0 : 1,
            'effective_date' => trim((string)($param['effective_date'] ?? '')),
            'expiry_date'  => trim((string)($param['expiry_date'] ?? '')),
            'is_enabled'   => empty($param['is_enabled']) ? 0 : 1,
            'description'  => trim((string)($param['description'] ?? '')),
            'rules_version'=> trim((string)($param['rules_version'] ?? 'v1')),
            'update_time'  => time(),
        ];
        if ($data['calc_method'] === 'fixed' && $data['amount'] <= 0) {
            return resultArray(['error' => '固定奖励金额必须大于0（如阶段不奖励请删除规则而非置0）']);
        }

        try {
            $ip = Request::instance()->ip();
            if ($ruleId > 0) {
                $old = db('business_stage_reward_rule')->where(['rule_id' => $ruleId])->find();
                if (!$old) return resultArray(['error' => '规则不存在']);
                // 编辑时不允许变更所属类型/阶段（保持历史可追溯）
                unset($data['type_id'], $data['status_id']);
                if (RewardService::ruleHasColumn('update_user_id')) $data['update_user_id'] = (int)$userInfo['id'];
                db('business_stage_reward_rule')->where(['rule_id' => $ruleId])->update($data);
                RewardService::logRuleAudit($ruleId, 'update', $old, array_merge($old, $data),
                    trim((string)($param['change_reason'] ?? '')), $userInfo, $ip);
                SystemActionLog($userInfo['id'], 'crm_reward', 'reward_rule', $ruleId, 'update', $ruleName, '', '', '编辑阶段奖励规则：' . $ruleName . ' 金额=' . $data['amount']);
            } else {
                // 幂等：同 type_id+status_id 已存在规则则改为编辑
                $exist = db('business_stage_reward_rule')->where(['type_id' => $typeId, 'status_id' => $statusId])->find();
                if ($exist) return resultArray(['error' => '该商机类型与阶段已存在奖励规则，请直接编辑']);
                $data['create_time'] = time();
                if (RewardService::ruleHasColumn('create_user_id')) $data['create_user_id'] = (int)$userInfo['id'];
                $ruleId = db('business_stage_reward_rule')->insertGetId($data);
                RewardService::logRuleAudit($ruleId, 'create', [], $data, '新增阶段奖励规则', $userInfo, $ip);
                SystemActionLog($userInfo['id'], 'crm_reward', 'reward_rule', $ruleId, 'save', $ruleName, '', '', '新增阶段奖励规则：' . $ruleName . ' 金额=' . $data['amount']);
            }
        } catch (\Exception $e) {
            \think\Log::record('ruleSave失败（表可能未创建）: ' . $e->getMessage(), 'error');
            return resultArray(['error' => '阶段奖励配置需要数据库迁移，请联系管理员执行迁移']);
        }
        return resultArray(['data' => ['rule_id' => $ruleId]]);
    }

    /**
     * 启用/停用阶段奖励规则
     */
    public function ruleToggle()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        if (!$this->assertRewardConfigAuth($userInfo)) return resultArray(['error' => '无权管理阶段奖励规则']);
        $ruleId = (int)($param['rule_id'] ?? 0);
        if ($ruleId <= 0) return resultArray(['error' => '参数错误']);
        try {
            $old = db('business_stage_reward_rule')->where(['rule_id' => $ruleId])->find();
            if (!$old) return resultArray(['error' => '规则不存在']);
            $enable = empty($param['is_enabled']) ? 0 : 1;
            $update = ['is_enabled' => $enable, 'update_time' => time()];
            if (RewardService::ruleHasColumn('update_user_id')) $update['update_user_id'] = (int)$userInfo['id'];
            db('business_stage_reward_rule')->where(['rule_id' => $ruleId])->update($update);
            RewardService::logRuleAudit($ruleId, $enable ? 'enable' : 'disable', $old, array_merge($old, $update),
                $enable ? '启用规则' : '停用规则', $userInfo, Request::instance()->ip());
            SystemActionLog($userInfo['id'], 'crm_reward', 'reward_rule', $ruleId, 'update', $old['rule_name'], '', '',
                ($enable ? '启用' : '停用') . '阶段奖励规则：' . $old['rule_name']);
        } catch (\Exception $e) {
            return resultArray(['error' => '操作失败：' . $e->getMessage()]);
        }
        return resultArray(['data' => ['rule_id' => $ruleId, 'is_enabled' => empty($param['is_enabled']) ? 0 : 1]]);
    }

    /**
     * 删除阶段奖励规则（已被奖励候选引用时仅停用，不物理删除，避免历史失真）
     */
    public function ruleDelete()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        if (!$this->assertRewardConfigAuth($userInfo)) return resultArray(['error' => '无权管理阶段奖励规则']);
        $ruleId = (int)($param['rule_id'] ?? 0);
        if ($ruleId <= 0) return resultArray(['error' => '参数错误']);
        try {
            $old = db('business_stage_reward_rule')->where(['rule_id' => $ruleId])->find();
            if (!$old) return resultArray(['error' => '规则不存在']);
            $refCount = 0;
            if ($this->rewardCandidateHasColumn('rule_id')) {
                $refCount = (int)db('reward_candidate')->where('rule_id', $ruleId)->count();
            }
            if ($refCount > 0) {
                $update = ['is_enabled' => 0, 'update_time' => time()];
                if (RewardService::ruleHasColumn('update_user_id')) $update['update_user_id'] = (int)$userInfo['id'];
                db('business_stage_reward_rule')->where(['rule_id' => $ruleId])->update($update);
                RewardService::logRuleAudit($ruleId, 'disable_on_delete', $old, array_merge($old, $update),
                    '规则已被奖励记录引用，删除操作转为停用', $userInfo, Request::instance()->ip());
                SystemActionLog($userInfo['id'], 'crm_reward', 'reward_rule', $ruleId, 'update', $old['rule_name'], '', '',
                    '规则已被引用，删除转为停用：' . $old['rule_name']);
                return resultArray(['data' => '该规则已被奖励记录引用，已自动停用（保留历史）']);
            }
            db('business_stage_reward_rule')->where(['rule_id' => $ruleId])->delete();
            RewardService::logRuleAudit($ruleId, 'delete', $old, [], '删除规则', $userInfo, Request::instance()->ip());
            SystemActionLog($userInfo['id'], 'crm_reward', 'reward_rule', $ruleId, 'delete', $old['rule_name'], '', '', '删除阶段奖励规则：' . $old['rule_name']);
        } catch (\Exception $e) {
            return resultArray(['error' => '删除失败：' . $e->getMessage()]);
        }
        return resultArray(['data' => '删除成功']);
    }
}
