<?php
// +----------------------------------------------------------------------  
// | Description: Finance Payment Method
// +----------------------------------------------------------------------  
namespace app\finance\model;

use app\admin\model\Common;
use think\Db;

class FinancePaymentMethod extends Common
{
    protected $name = 'finance_payment_method';
    protected $pk = 'method_id';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $autoWriteTimestamp = true;

    public function getDataList($request)
    {
        $request = $this->fmtRequest($request);
        $map = [];
        
        if (!empty($request['map']['status'])) {
            $map['status'] = $request['map']['status'];
        }
        if (!empty($request['map']['keyword'])) {
            $map['name'] = ['like', '%' . $request['map']['keyword'] . '%'];
        }

        $where = [];
        foreach ($map as $key => $value) {
            if (is_array($value)) {
                $where[$key] = $value;
            } else {
                $where[$key] = ['eq', $value];
            }
        }

        $query = Db::name($this->name)->where($where)->order('sort asc, method_id asc');

        $dataCount = (int)Db::name($this->name)->where($where)->count($this->pk);

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

    public function getDataById($id)
    {
        $data = Db::name($this->name)->where($this->pk, $id)->find();
        if ($data) {
            $data['create_time'] = !empty($data['create_time']) ? date('Y-m-d H:i:s', $data['create_time']) : null;
            $data['update_time'] = !empty($data['update_time']) ? date('Y-m-d H:i:s', $data['update_time']) : null;
        }
        return $data;
    }
}
