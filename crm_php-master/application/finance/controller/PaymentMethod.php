<?php
// +----------------------------------------------------------------------
// | Description: Finance Payment Method
// +----------------------------------------------------------------------
namespace app\finance\controller;

use app\admin\controller\ApiCommon;
use think\Hook;
use think\Request;

class PaymentMethod extends ApiCommon
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
        $model = model('FinancePaymentMethod');
        $param = $this->param;
        $data = $model->getDataList($param);
        return resultArray(['data' => $data]);
    }

    public function read()
    {
        $model = model('FinancePaymentMethod');
        $param = $this->param;
        if (empty($param['id'])) {
            return resultArray(['error' => '参数错误']);
        }
        $data = $model->getDataById($param['id']);
        if (!$data) {
            return resultArray(['error' => '支付方式不存在']);
        }
        return resultArray(['data' => $data]);
    }

    public function save()
    {
        $model = model('FinancePaymentMethod');
        $param = $this->param;
        $userInfo = $this->userInfo;

        if (empty($param['name'])) {
            return resultArray(['error' => '请填写支付方式名称']);
        }

        $param['status'] = isset($param['status']) ? $param['status'] : 1;
        $param['sort'] = isset($param['sort']) ? $param['sort'] : 0;
        $param['create_user_id'] = $userInfo['id'];

        if ($model->data($param)->allowField(true)->save()) {
            return resultArray(['data' => '添加成功']);
        }
        return resultArray(['error' => '添加失败']);
    }

    public function update()
    {
        $model = model('FinancePaymentMethod');
        $param = $this->param;

        if (empty($param['id'])) {
            return resultArray(['error' => '参数错误']);
        }

        $data = $model->where('method_id', $param['id'])->find();
        if (!$data) {
            return resultArray(['error' => '支付方式不存在']);
        }

        $updateData = [];
        if (isset($param['name'])) {
            $updateData['name'] = $param['name'];
        }
        if (isset($param['status'])) {
            $updateData['status'] = $param['status'];
        }
        if (isset($param['sort'])) {
            $updateData['sort'] = $param['sort'];
        }

        if (empty($updateData)) {
            return resultArray(['error' => '没有要更新的数据']);
        }

        if ($model->where('method_id', $param['id'])->update($updateData)) {
            return resultArray(['data' => '更新成功']);
        }
        return resultArray(['error' => '更新失败']);
    }

    public function delete()
    {
        $model = model('FinancePaymentMethod');
        $param = $this->param;

        if (empty($param['id'])) {
            return resultArray(['error' => '参数错误']);
        }

        $ids = is_array($param['id']) ? $param['id'] : [$param['id']];
        
        // 检查是否被使用
        $usedCount = db('finance_record')->where('payment_method_id', 'in', $ids)->count();
        if ($usedCount > 0) {
            return resultArray(['error' => '该支付方式正在使用中，无法删除']);
        }

        if ($model->where('method_id', 'in', $ids)->delete()) {
            return resultArray(['data' => '删除成功']);
        }
        return resultArray(['error' => '删除失败']);
    }
}
