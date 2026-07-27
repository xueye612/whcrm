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
        $action = ['permission' => [''], 'allow' => ['dictionary']];
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
}
