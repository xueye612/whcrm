<?php
// +----------------------------------------------------------------------
// | Description: Finance Plan
// +----------------------------------------------------------------------
namespace app\finance\controller;

use app\admin\controller\ApiCommon;
use think\Hook;
use think\Request;

class Plan extends ApiCommon
{
    public function _initialize()
    {
        $action = [
            'permission' => [],
            'allow' => []
        ];
        Hook::listen('check_auth', $action);
        $request = Request::instance();
        $a = strtolower($request->action());
        if (!in_array($a, $action['permission'])) {
            parent::_initialize();
        }
    }

    public function index()
    {
        $model = model('FinancePlan');
        $param = $this->param;
        $data = $model->getDataList($param);
        return resultArray(['data' => $data]);
    }

    public function read()
    {
        $model = model('FinancePlan');
        $param = $this->param;
        if (empty($param['id'])) {
            return resultArray(['error' => '参数错误']);
        }
        $data = $model->getDataById($param['id']);
        if (!$data) {
            return resultArray(['error' => $model->getError()]);
        }
        return resultArray(['data' => $data]);
    }

    public function save()
    {
        $model = model('FinancePlan');
        $param = $this->param;
        $userInfo = $this->userInfo;

        if (empty($param['direction']) || !in_array($param['direction'], ['income', 'expense'])) {
            return resultArray(['error' => 'direction 仅支持 income/expense']);
        }
        if (empty($param['plan_amount']) || !is_numeric($param['plan_amount'])) {
            return resultArray(['error' => '请填写合法计划金额']);
        }
        if (empty($param['plan_date'])) {
            return resultArray(['error' => '请填写计划日期']);
        }

        $param['status'] = isset($param['status']) ? $param['status'] : 0;
        $param['create_user_id'] = $userInfo['id'];

        $res = $model->data($param)->allowField(true)->save();
        if ($res) {
            return resultArray(['data' => '添加成功']);
        }
        return resultArray(['error' => '添加失败']);
    }

    public function update()
    {
        $model = model('FinancePlan');
        $param = $this->param;

        if (empty($param['id'])) {
            return resultArray(['error' => '参数错误']);
        }
        if (!empty($param['direction']) && !in_array($param['direction'], ['income', 'expense'])) {
            return resultArray(['error' => 'direction 仅支持 income/expense']);
        }
        if (isset($param['plan_amount']) && !is_numeric($param['plan_amount'])) {
            return resultArray(['error' => '请填写合法计划金额']);
        }

        $planId = $param['id'];
        unset($param['id']);

        $res = $model->allowField(true)->save($param, ['plan_id' => $planId]);
        if ($res !== false) {
            return resultArray(['data' => '编辑成功']);
        }
        return resultArray(['error' => '编辑失败']);
    }

    public function delete()
    {
        $param = $this->param;
        if (empty($param['id'])) {
            return resultArray(['error' => '参数错误']);
        }
        $ids = is_array($param['id']) ? $param['id'] : [$param['id']];

        if (db('finance_record')->where('plan_id', 'in', $ids)->find()) {
            return resultArray(['error' => '计划下存在流水，不能删除']);
        }

        $res = db('finance_plan')->where('plan_id', 'in', $ids)->delete();
        if ($res) {
            return resultArray(['data' => '删除成功']);
        }
        return resultArray(['error' => '删除失败']);
    }
}
