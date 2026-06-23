<?php
// +----------------------------------------------------------------------
// | Description: Finance Type
// +----------------------------------------------------------------------
namespace app\finance\controller;

use app\admin\controller\ApiCommon;
use think\Hook;
use think\Request;

class Type extends ApiCommon
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
        $model = model('FinanceType');
        $param = $this->param;
        $data = $model->getDataList($param);
        return resultArray(['data' => $data]);
    }

    public function save()
    {
        $model = model('FinanceType');
        $param = $this->param;
        $userInfo = $this->userInfo;

        if (empty($param['name'])) {
            return resultArray(['error' => '请填写类型名称']);
        }
        if (empty($param['direction']) || !in_array($param['direction'], ['income', 'expense'])) {
            return resultArray(['error' => 'direction 仅支持 income/expense']);
        }

        $param['status'] = isset($param['status']) ? $param['status'] : 1;
        $param['sort'] = isset($param['sort']) ? $param['sort'] : 0;
        $param['parent_id'] = isset($param['parent_id']) ? (int)$param['parent_id'] : 0;
        $param['create_user_id'] = $userInfo['id'];

        if ($model->data($param)->allowField(true)->save()) {
            return resultArray(['data' => '添加成功']);
        }
        return resultArray(['error' => '添加失败']);
    }

    public function update()
    {
        $model = model('FinanceType');
        $param = $this->param;

        if (empty($param['id'])) {
            return resultArray(['error' => '参数错误']);
        }
        if (!empty($param['direction']) && !in_array($param['direction'], ['income', 'expense'])) {
            return resultArray(['error' => 'direction 仅支持 income/expense']);
        }

        $typeId = $param['id'];
        unset($param['id']);

        $res = $model->allowField(true)->save($param, ['type_id' => $typeId]);
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
        $typeId = $param['id'];

        if (db('finance_record')->where('type_id', $typeId)->find()) {
            return resultArray(['error' => '该类型已被流水使用，不能删除']);
        }
        if (db('finance_plan')->where('type_id', $typeId)->find()) {
            return resultArray(['error' => '该类型已被计划使用，不能删除']);
        }

        $res = db('finance_type')->where('type_id', $typeId)->delete();
        if ($res) {
            return resultArray(['data' => '删除成功']);
        }
        return resultArray(['error' => '删除失败']);
    }
}
