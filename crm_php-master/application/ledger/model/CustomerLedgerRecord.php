<?php
// +----------------------------------------------------------------------
// | Description: Customer Ledger Record
// +----------------------------------------------------------------------
namespace app\ledger\model;

use app\admin\model\Common;
use think\Db;

class CustomerLedgerRecord extends Common
{
    protected $name = 'customer_ledger_record';
    protected $pk = 'record_id';
    protected $createTime = 'create_time';
    protected $updateTime = false;
    protected $autoWriteTimestamp = false;

    public function addRecord($data)
    {
        $data['create_time'] = $data['create_time'] ?? time();
        return Db::name($this->name)->insert($data);
    }

    public function getList($ledgerId)
    {
        $query = Db::name($this->name)
            ->alias('record')
            ->join('__ADMIN_USER__ user', 'user.id = record.create_user_id', 'LEFT')
            ->field('record.*,user.realname as create_user_name,user.thumb_img')
            ->where('record.ledger_id', $ledgerId);

        try {
            $fields = Db::name($this->name)->getTableInfo('fields');
            if (is_array($fields) && in_array('deleted', $fields)) {
                $query->where('record.deleted', 0);
            }
        } catch (\Exception $e) {
        }

        $list = $query->order('record.create_time desc,record.record_id desc')->select();

        foreach ($list as &$item) {
            $item['create_time'] = !empty($item['create_time']) ? date('Y-m-d H:i:s', $item['create_time']) : null;
        }
        return $list;
    }
}
