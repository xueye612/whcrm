<?php
// +----------------------------------------------------------------------
// | P4 外包项目：交付等级、需求基线、直接成本、毛利、两类奖金池、实施三级比例分配
// +----------------------------------------------------------------------
namespace app\work\controller;

use app\work\logic\OutsourceService;
use app\work\traits\WorkAuthTrait;
use think\Db;
use think\Request;
use think\Hook;
use app\admin\controller\ApiCommon;

class Outsource extends ApiCommon
{
    use WorkAuthTrait;

    public function _initialize()
    {
        $action = ['permission' => [''], 'allow' => ['dictionary']];
        Hook::listen('check_auth', $action);
        if (!in_array(strtolower(Request::instance()->action()), $action['permission'])) {
            parent::_initialize();
        }
    }

    private function requireManage($workId, $userId)
    {
        $workId = (int)$workId;
        if ($workId <= 0) return [false, '参数错误'];
        $work = Db::name('work')->where(['work_id' => $workId, 'ishidden' => 0])->find();
        if (!$work) return [false, '项目不存在或已删除'];
        if (!$this->checkWorkOperationAuth('setWork', $workId, (int)$userId)) return [false, '无权操作该项目'];
        return [true, $workId];
    }

    public function dictionary()
    {
        return resultArray(['data' => OutsourceService::dictionary()]);
    }

    /** 创建/更新外包项目档案（自动计算毛利与两类奖金池） */
    public function projectSave()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $workId = (int)($param['work_id'] ?? 0);
        list($ok, $err) = $this->requireManage($workId, $userInfo['id']);
        if (!$ok) return resultArray(['error' => $err]);

        $level = trim((string)($param['delivery_level'] ?? ''));
        if ($level !== '' && !OutsourceService::isValidLevel($level)) return resultArray(['error' => '交付等级必须为 一/二/三/四级']);
        $revenue = (float)($param['revenue'] ?? 0);
        $directCost = (float)($param['direct_cost'] ?? 0);
        if ($revenue < 0 || $directCost < 0) return resultArray(['error' => '收入与成本不能为负']);

        $margin = OutsourceService::computeMargin($revenue, $directCost);
        // V1.6 §45-46: 业务获取池=毛利×8%；交付池=毛利×交付等级%，且≤到账收入15%
        $bizAcqPool = OutsourceService::businessAcqPool($margin);
        $deliveryLevel = $level !== '' ? $level : OutsourceService::LEVEL_1;
        $deliveryData = OutsourceService::deliveryPool($margin, $revenue, $deliveryLevel);
        $now = time();

        $exist = Db::name('outsource_project')->where(['work_id' => $workId])->find();
        $row = [
            'work_id' => $workId, 'delivery_level' => $level,
            'requirement_baseline' => (string)($param['requirement_baseline'] ?? ''),
            'scope_change' => (string)($param['scope_change'] ?? ''),
            'revenue' => $revenue, 'direct_cost' => $directCost, 'gross_margin' => $margin,
            'reward_pct' => $deliveryData['level_pct'], 'expense_pct' => OutsourceService::BUSINESS_ACQ_PCT,
            'reward_pool' => $deliveryData['delivery_pool'], 'expense_pool' => $bizAcqPool,
            'update_time' => $now,
        ];
        if ($exist) {
            Db::name('outsource_project')->where(['outsource_id' => $exist['outsource_id']])->update($row);
            $id = $exist['outsource_id'];
        } else {
            $row['create_user_id'] = (int)$userInfo['id']; $row['create_time'] = $now;
            $id = Db::name('outsource_project')->insertGetId($row);
        }
        return resultArray(['data' => ['outsource_id' => $id, 'gross_margin' => $margin, 'delivery_pool' => $deliveryData['delivery_pool'], 'business_acq_pool' => $bizAcqPool, 'delivery_capped' => $deliveryData['capped'], 'delivery_level_pct' => $deliveryData['level_pct']]]);
    }

    public function projectRead()
    {
        $param = $this->param;
        $workId = (int)($param['work_id'] ?? 0);
        if ($workId <= 0) return resultArray(['error' => '参数错误']);
        $row = Db::name('outsource_project')->where(['work_id' => $workId])->find();
        return resultArray(['data' => ['profile' => $row, 'dictionary' => OutsourceService::dictionary()]]);
    }

    /** 保存实施三级比例分配（校验 5% 单位、≤100%）并计算各角色金额 */
    public function distributeSave()
    {
        $param = $this->param; $userInfo = $this->userInfo;
        $workId = (int)($param['work_id'] ?? 0);
        list($ok, $err) = $this->requireManage($workId, $userInfo['id']);
        if (!$ok) return resultArray(['error' => $err]);
        $ratios = $param['ratios'] ?? [];
        if (is_string($ratios)) {
            $decoded = json_decode($ratios, true);
            $ratios = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($ratios) || !$ratios) return resultArray(['error' => '请提供分配比例']);
        list($vok, $verr) = OutsourceService::validateRatios($ratios);
        if (!$vok) return resultArray(['error' => $verr]);

        $row = Db::name('outsource_project')->where(['work_id' => $workId])->find();
        if (!$row) return resultArray(['error' => '请先保存外包项目档案']);
        $pool = (float)$row['reward_pool'];
        // P4 70/30 发放节奏：交付阶段(phase1)按比例分配 70%，验收/稳定期(phase2) 30% 待验收后发放
        $payout = OutsourceService::payoutSplit($pool);
        $dist = OutsourceService::distribute($payout['phase1_deliver'], $ratios);
        $now = time();
        // 覆盖式写入：先删旧再插新（同一来源）—— 本次按 70% 交付阶段计入
        Db::name('reward_distribution')->where(['source_type' => '外包项目', 'source_id' => $workId])->delete();
        $insert = [];
        foreach ($dist['rows'] as $r) {
            $insert[] = ['source_type' => '外包项目', 'source_id' => $workId, 'role_name' => $r['role'], 'percentage' => $r['percentage'], 'amount' => $r['amount'], 'create_user_id' => (int)$userInfo['id'], 'create_time' => $now];
        }
        if ($insert) Db::name('reward_distribution')->insertAll($insert);
        return resultArray(['data' => [
            'rows' => $dist['rows'], 'unallocated' => $dist['unallocated'], 'allocated_pct' => $dist['allocated_pct'],
            'payout_rhythm' => $payout,
            'note' => '本次按交付阶段(70%)计入分配；验收/稳定期30%待验收后发放',
        ]]);
    }

    public function distributeRead()
    {
        $param = $this->param;
        $workId = (int)($param['work_id'] ?? 0);
        $rows = Db::name('reward_distribution')->where(['source_type' => '外包项目', 'source_id' => $workId])->order('dist_id asc')->select();
        return resultArray(['data' => ['rows' => $rows]]);
    }
}
