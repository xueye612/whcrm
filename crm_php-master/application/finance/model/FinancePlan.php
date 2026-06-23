<?php
// +----------------------------------------------------------------------
// | Description: Finance Plan
// +----------------------------------------------------------------------
namespace app\finance\model;

use app\admin\model\Common;
use think\Db;

class FinancePlan extends Common
{
    protected $name = 'finance_plan';
    protected $pk = 'plan_id';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $autoWriteTimestamp = true;

    public function getDataList($request)
    {
        $request = $this->fmtRequest($request);
        $map = $request['map'] ?: [];

        $where = [];
        if (!empty($map['direction'])) {
            $where['plan.direction'] = $map['direction'];
        }
        if (!empty($map['customer_id'])) {
            $where['plan.customer_id'] = $map['customer_id'];
        }
        if (!empty($map['contract_id'])) {
            $where['plan.contract_id'] = $map['contract_id'];
        }
        if (!empty($map['type_id'])) {
            $where['plan.type_id'] = $map['type_id'];
        }
        if (isset($map['status']) && $map['status'] !== '') {
            $where['plan.status'] = $map['status'];
        }
        if (!empty($map['start_date']) || !empty($map['end_date'])) {
            if (!empty($map['start_date']) && !empty($map['end_date'])) {
                $where['plan.plan_date'] = ['between', [$map['start_date'], $map['end_date']]];
            } elseif (!empty($map['start_date'])) {
                $where['plan.plan_date'] = ['egt', $map['start_date']];
            } else {
                $where['plan.plan_date'] = ['elt', $map['end_date']];
            }
        }

        $query = Db::name($this->name)
            ->alias('plan')
            ->join('__FINANCE_TYPE__ type', 'plan.type_id = type.type_id', 'LEFT')
            ->join('__CRM_CUSTOMER__ customer', 'plan.customer_id = customer.customer_id', 'LEFT')
            ->join('__CRM_CONTRACT__ contract', 'plan.contract_id = contract.contract_id', 'LEFT')
            ->field('plan.*,type.name as type_name,customer.name as customer_name,contract.num as contract_num,contract.name as contract_name')
            ->where($where)
            ->order('plan.plan_date desc,plan.plan_id desc');

        $dataCount = (int)Db::name($this->name)->alias('plan')->where($where)->count($this->pk);

        if ($request['limit'] > 0) {
            $list = $query->limit($request['offset'], $request['length'])->select();
        } else {
            $list = $query->select();
        }

        foreach ($list as &$item) {
            $item['create_time'] = !empty($item['create_time']) ? date('Y-m-d H:i:s', $item['create_time']) : null;
            $item['update_time'] = !empty($item['update_time']) ? date('Y-m-d H:i:s', $item['update_time']) : null;
        }

        return [
            'list' => $list,
            'dataCount' => $dataCount,
        ];
    }

    public function refreshStatus($planId)
    {
        if (empty($planId)) {
            return false;
        }
        $plan = Db::name($this->name)->where($this->pk, $planId)->find();
        if (!$plan) {
            return false;
        }
        $total = (float)Db::name('finance_record')->where('plan_id', $planId)->sum('amount');
        $planAmount = (float)$plan['plan_amount'];

        $status = 0;
        if ($total > 0 && $total < $planAmount) {
            $status = 1;
        } elseif ($planAmount > 0 && $total >= $planAmount) {
            $status = 2;
        }

        Db::name($this->name)->where($this->pk, $planId)->update(['status' => $status]);
        return true;
    }
}
