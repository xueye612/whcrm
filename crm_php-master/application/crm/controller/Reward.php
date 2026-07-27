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
        $action = ['permission' => [''], 'allow' => ['dictionary', 'candidatesave', 'candidatelist', 'review', 'batchcreate', 'batchsettle', 'offset', 'configsave', 'expensesave', 'expenselist', 'approvalrequest', 'approvaldecide', 'approvalcheck', 'stageoffsetcalc', 'paymentrecord', 'paymentconfirm']];
        Hook::listen('check_auth', $action);
        if (!in_array(strtolower(Request::instance()->action()), $action['permission'])) {
            parent::_initialize();
        }
    }

    public function dictionary()
    {
        $s = new RewardService();
        return resultArray(['data' => [
            'fixed_amounts' => RewardService::FIXED_AMOUNTS,
            'statuses' => ['待审核','已通过','已驳回','已结算','已冲销'],
            'summary' => $s->summary(),
            'config' => RewardService::configStatus(),
        ]]);
    }

    /** 创建奖励候选（金额优先取固定字典，可自定义） */
    public function candidateSave()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $sourceType = trim((string)($param['source_type'] ?? ''));
        $userId = (int)($param['user_id'] ?? 0);
        if ($sourceType === '' || $userId <= 0) return resultArray(['error' => '来源类型与候选人为必填']);
        $amount = ($param['amount'] ?? '') !== '' ? (float)$param['amount'] : RewardService::fixedAmount($sourceType);
        if ($amount <= 0) return resultArray(['error' => '金额必须大于0（或使用制度固定金额来源）']);
        // 制度口径：仅基础核实类(经销商30/医院50/外包50)合计超过800元/人/月→待专项审批
        // 900/900/200/200、阶段奖励、项目奖金等不得触发该专项审批
        $s = new RewardService();
        $basicVerifyTypes = ['经销商基础核实', '医院基础核实', '外包基础核实'];
        $needSpecial = false;
        if (in_array($sourceType, $basicVerifyTypes, true)) {
            list($needSpecial, $usedMonth) = $s->checkMonthlyCap($userId, $amount);
        }
        $status = $needSpecial ? RewardService::ST_SPECIAL : RewardService::ST_PENDING;
        $now = time();
        $id = Db::name('reward_candidate')->insertGetId([
            'source_type' => $sourceType,
            'source_ref' => (string)($param['source_ref'] ?? ''),
            'user_id' => $userId,
            'amount' => $amount,
            'reason' => trim((string)($param['reason'] ?? '')),
            'evidence_note' => trim((string)($param['evidence_note'] ?? '')),
            'rules_version' => (string)($param['rules_version'] ?? 'v1'),
            'status' => $status,
            'create_user_id' => (int)$userInfo['id'], 'create_time' => $now, 'update_time' => $now,
        ]);
        return resultArray(['data' => ['cand_id' => $id, 'amount' => $amount, 'status' => $status, 'monthly_used' => $usedMonth, 'monthly_cap' => RewardService::MONTHLY_CAP]]);
    }

    public function candidateList()
    {
        $param = $this->param;
        $q = Db::name('reward_candidate');
        if (!empty($param['status'])) $q->where(['status' => $param['status']]);
        if (!empty($param['user_id'])) $q->where(['user_id' => (int)$param['user_id']]);
        $list = $q->order('cand_id desc')->limit(200)->select();
        return resultArray(['data' => ['list' => $list]]);
    }

    /** 审核：approve/reject；本人回避；仅待审核可审 */
    public function review()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $candId = (int)($param['cand_id'] ?? 0);
        $decision = trim((string)($param['decision'] ?? ''));
        if ($candId <= 0) return resultArray(['error' => '参数错误']);
        if (!in_array($decision, ['approve', 'reject'], true)) return resultArray(['error' => 'decision 必须为 approve/reject']);
        $c = Db::name('reward_candidate')->where(['cand_id' => $candId])->find();
        if (!$c) return resultArray(['error' => '候选不存在']);
        if (!in_array($c['status'], [RewardService::ST_PENDING, RewardService::ST_SPECIAL], true)) {
            return resultArray(['error' => '仅待审核/待专项审批候选可审核']);
        }
        if (!RewardService::assertNotSelf($c['user_id'], $userInfo['id'])) return resultArray(['error' => '本人回避：不能审核自己的奖励候选']);
        $newStatus = $decision === 'approve' ? RewardService::ST_APPROVED : RewardService::ST_REJECTED;
        Db::name('reward_candidate')->where(['cand_id' => $candId])->update([
            'status' => $newStatus, 'reviewer_user_id' => (int)$userInfo['id'],
            'review_time' => time(), 'review_note' => trim((string)($param['review_note'] ?? '')), 'update_time' => time(),
        ]);
        return resultArray(['data' => ['cand_id' => $candId, 'status' => $newStatus]]);
    }

    /** 创建结算批次：汇总所有已通过候选进入批次 */
    public function batchCreate()
    {
        $param = $this->param; $userInfo = $this->userInfo;
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
        $param = $this->param;
        $batchId = (int)($param['batch_id'] ?? 0);
        $b = Db::name('reward_batch')->where(['batch_id' => $batchId])->find();
        if (!$b) return resultArray(['error' => '批次不存在']);
        Db::startTrans();
        try {
            Db::name('reward_batch')->where(['batch_id' => $batchId])->update(['status' => '已结算']);
            Db::name('reward_candidate')->where(['batch_id' => $batchId])->update(['status' => RewardService::ST_SETTLED, 'update_time' => time()]);
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
        $now = time();
        Db::startTrans();
        try {
            Db::name('reward_offset')->insert([
                'cand_id' => $candId, 'offset_amount' => $offsetAmount,
                'reason' => trim((string)($param['reason'] ?? '')),
                'create_user_id' => (int)$userInfo['id'], 'create_time' => $now,
            ]);
            Db::name('reward_candidate')->where(['cand_id' => $candId])->update(['status' => RewardService::ST_OFFSET, 'update_time' => $now]);
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
        RewardService::setConfig($key, $param['config_value'] ?? null, $userInfo['id']);
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

    // ===== 7项审批型功能 =====

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
}
