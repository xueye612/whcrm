<?php
// +----------------------------------------------------------------------
// | P3 经销商/医院/外包机会：中文阶段、阶段证据、固定阶段奖励
// +----------------------------------------------------------------------
namespace app\crm\controller;

use app\crm\logic\OpportunityService;
use think\Db;
use think\Request;
use think\Hook;
use app\admin\controller\ApiCommon;

class Opportunity extends ApiCommon
{
    public function _initialize()
    {
        $action = ['permission' => [''], 'allow' => ['dictionary', 'hospitalpoolset']];
        Hook::listen('check_auth', $action);
        if (!in_array(strtolower(Request::instance()->action()), $action['permission'])) {
            parent::_initialize();
        }
    }

    public function dictionary()
    {
        return resultArray(['data' => OpportunityService::dictionary()]);
    }

    /** 创建机会 */
    public function oppSave()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $type = trim((string)($param['source_type'] ?? ''));
        $name = trim((string)($param['name'] ?? ''));
        if (!OpportunityService::isValidType($type)) return resultArray(['error' => '机会类型必须为：经销商/医院/外包']);
        if ($name === '') return resultArray(['error' => '机会名称不能为空']);
        $stages = OpportunityService::stagesOfType($type);
        $now = time();
        $id = Db::name('opportunity')->insertGetId([
            'source_type' => $type, 'name' => $name,
            'customer_id' => (int)($param['customer_id'] ?? 0),
            'current_stage' => $stages ? $stages[0] : '',
            'owner_user_id' => (int)($param['owner_user_id'] ?? $userInfo['id']),
            'status' => '进行中',
            'create_user_id' => (int)$userInfo['id'], 'create_time' => $now, 'update_time' => $now,
        ]);
        return resultArray(['data' => ['opp_id' => $id]]);
    }

    /** 读取机会与阶段历史 */
    public function oppRead()
    {
        $param = $this->param;
        $oppId = (int)($param['opp_id'] ?? 0);
        if ($oppId <= 0) return resultArray(['error' => '参数错误']);
        $opp = Db::name('opportunity')->where(['opp_id' => $oppId])->find();
        if (!$opp) return resultArray(['error' => '机会不存在']);
        $stages = Db::name('opportunity_stage')->where(['opp_id' => $oppId])->order('stage_id asc')->select();
        $opp['reward_total'] = (float)Db::name('opportunity_stage')->where(['opp_id' => $oppId])->sum('reward_amount');
        return resultArray(['data' => ['opportunity' => $opp, 'stages' => $stages, 'dictionary' => OpportunityService::dictionary()]]);
    }

    /** 机会列表 */
    public function oppList()
    {
        $param = $this->param;
        $q = Db::name('opportunity');
        if (!empty($param['source_type'])) $q->where(['source_type' => $param['source_type']]);
        if (!empty($param['status'])) $q->where(['status' => $param['status']]);
        $list = $q->order('opp_id desc')->limit(200)->select();
        return resultArray(['data' => ['list' => $list, 'dictionary' => OpportunityService::dictionary()]]);
    }

    /** 推进阶段：记录证据、计入固定奖励，重复阶段被唯一约束拦截 */
    public function stageAdvance()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $oppId = (int)($param['opp_id'] ?? 0);
        $stage = trim((string)($param['stage'] ?? ''));
        if ($oppId <= 0 || $stage === '') return resultArray(['error' => '参数错误']);
        $opp = Db::name('opportunity')->where(['opp_id' => $oppId])->find();
        if (!$opp) return resultArray(['error' => '机会不存在']);
        if (!OpportunityService::isValidStage($opp['source_type'], $stage)) {
            return resultArray(['error' => '阶段必须为：' . implode(' / ', OpportunityService::stagesOfType($opp['source_type']))]);
        }
        $service = new OpportunityService();
        list($ok, $err) = $service->canAdvanceStage($oppId, $stage);
        if (!$ok) return resultArray(['error' => $err]);

        $reward = OpportunityService::stageReward($opp['source_type'], $stage);
        $now = time();
        Db::startTrans();
        try {
            Db::name('opportunity_stage')->insert([
                'opp_id' => $oppId, 'stage' => $stage,
                'evidence_note' => trim((string)($param['evidence_note'] ?? '')),
                'reward_amount' => $reward, 'reward_claimed' => $reward > 0 ? 1 : 0,
                'operator' => (int)$userInfo['id'], 'create_time' => $now,
            ]);
            Db::name('opportunity')->where(['opp_id' => $oppId])->update(['current_stage' => $stage, 'update_time' => $now]);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            // 唯一索引兜底：并发重复推进
            return resultArray(['error' => '该阶段已推进，不可重复计入奖励']);
        }
        return resultArray(['data' => ['opp_id' => $oppId, 'stage' => $stage, 'reward_amount' => $reward]]);
    }

    /**
     * 自主签单医院 5%综合池分账：按到账收入生成 2% 内部奖励候选 + 最高3% 合规商务费用（事务）。
     * 制度已确认：2% 进 reward_candidate 人工审核；3% 进独立 business_expense 需凭据/审批；不向医院工作人员支付私人费用。
     */
    public function hospitalPoolSet()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $oppId = (int)($param['opp_id'] ?? 0);
        $revenue = (float)($param['actual_revenue'] ?? 0);
        if ($oppId <= 0) return resultArray(['error' => '参数错误']);
        if ($revenue <= 0) return resultArray(['error' => '到账收入必须大于0']);
        $opp = Db::name('opportunity')->where(['opp_id' => $oppId])->find();
        if (!$opp) return resultArray(['error' => '机会不存在']);
        if ($opp['source_type'] !== OpportunityService::TYPE_HOSPITAL) return resultArray(['error' => '仅医院机会适用 5%综合池分账']);
        $pool = OpportunityService::hospitalPool($revenue);
        $now = time();
        $userId = (int)($param['reward_user_id'] ?? $opp['owner_user_id']);
        // 月度800元专项审批判定（复用规则）
        $rewardStatus = '待审核';
        $used = (float)Db::name('reward_candidate')->where(['user_id' => $userId])->whereIn('status', ['待审核','待专项审批','已通过'])->sum('amount');
        if ($used + $pool['reward_amount'] > \app\crm\logic\RewardService::MONTHLY_CAP) $rewardStatus = '待专项审批';

        Db::startTrans();
        try {
            $candId = Db::name('reward_candidate')->insertGetId([
                'source_type' => '医院自主签单奖励', 'source_ref' => 'opp:' . $oppId, 'user_id' => $userId,
                'amount' => $pool['reward_amount'], 'reason' => '医院5%综合池-2%内部业务获取奖励',
                'evidence_note' => trim((string)($param['evidence_note'] ?? '')), 'rules_version' => 'v1',
                'status' => $rewardStatus, 'create_user_id' => (int)$userInfo['id'], 'create_time' => $now, 'update_time' => $now,
            ]);
            // 3% 仅为合规商务费用预算上限，不自动生成实际费用。
            // 实际费用须通过 expenseSave 单独申请，附预算/事项/审批/外部主体/协议凭据/合规确认。
            // 未发生部分不得转为员工奖励。严禁向医院工作人员/采购/决策人员设计私人利益输送。
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => '综合池分账失败：' . $e->getMessage()]);
        }
        return resultArray(['data' => [
            'opp_id' => $oppId, 'reward_candidate_id' => $candId,
            'pool' => $pool, 'reward_status' => $rewardStatus,
            'expense_budget_ceiling' => $pool['expense_max'],
            'note' => '3%仅为合规商务费用预算上限，不自动生成实际费用；实际费用须单独申请并附凭据；未发生不得转奖金',
        ]]);
    }
}
