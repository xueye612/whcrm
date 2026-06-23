<?php
// +----------------------------------------------------------------------
// | Description: Finance Type
// +----------------------------------------------------------------------
namespace app\finance\model;

use app\admin\model\Common;
use think\Db;

class FinanceType extends Common
{
    protected $name = 'finance_type';
    protected $pk = 'type_id';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $autoWriteTimestamp = true;

    public function getDataList($request)
    {
        $request = $this->fmtRequest($request);
        $map = $request['map'] ?: [];

        $where = [];
        if (!empty($map['direction'])) {
            $where['direction'] = $map['direction'];
        }
        if (isset($map['status']) && $map['status'] !== '') {
            $where['status'] = $map['status'];
        }
        if (!empty($map['name'])) {
            $where['name'] = ['like', '%' . $map['name'] . '%'];
        }

        $query = Db::name($this->name)->where($where)->order('sort asc,type_id desc');
        $dataCount = (int)$query->count($this->pk);

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
}
