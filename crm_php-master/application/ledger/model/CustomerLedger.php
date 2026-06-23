<?php
// +----------------------------------------------------------------------
// | Description: Customer Ledger
// +----------------------------------------------------------------------
namespace app\ledger\model;

use app\admin\model\Common;
use think\Db;

class CustomerLedger extends Common
{
    protected $name = 'customer_ledger';
    protected $pk = 'ledger_id';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $autoWriteTimestamp = true;

    public function getDataList($request, $userId)
    {
        $request = $this->fmtRequest($request);
        $map = $request['map'] ?: [];
        $recordTable = Db::getConfig('prefix') . 'customer_ledger_record';

        $where = [];
        if (!empty($map['customer_id'])) {
            $where['ledger.customer_id'] = $map['customer_id'];
        }
        if (!empty($map['customer_name'])) {
            $where['customer.name'] = ['like', '%' . $map['customer_name'] . '%'];
        }
        if (!empty($map['status'])) {
            $where['ledger.status'] = $map['status'];
        }
        if (!empty($map['category'])) {
            $where['ledger.category'] = $map['category'];
        }
        if (!empty($map['handler_user_id'])) {
            $where['ledger.handler_user_id'] = $map['handler_user_id'];
        }
        if (!empty($map['business_id'])) {
            $where['ledger.business_id'] = $map['business_id'];
        }
        if (!empty($map['contract_id'])) {
            $where['ledger.contract_id'] = $map['contract_id'];
        }
        if (!empty($map['start_date']) || !empty($map['end_date'])) {
            if (!empty($map['start_date']) && !empty($map['end_date'])) {
                $where['ledger.feedback_time'] = ['between', [strtotime($map['start_date']), strtotime($map['end_date']) + 86399]];
            } elseif (!empty($map['start_date'])) {
                $where['ledger.feedback_time'] = ['egt', strtotime($map['start_date'])];
            } else {
                $where['ledger.feedback_time'] = ['elt', strtotime($map['end_date']) + 86399];
            }
        }
        $keywordWhere = null;
        if (!empty($map['keyword'])) {
            $keyword = $map['keyword'];
            $keywordWhere = function ($query) use ($keyword) {
                $query->where('ledger.title', 'like', '%' . $keyword . '%')
                    ->whereOr('ledger.description', 'like', '%' . $keyword . '%')
                    ->whereOr('ledger.feedback_user', 'like', '%' . $keyword . '%')
                    ->whereOr('ledger.remark', 'like', '%' . $keyword . '%')
                    ->whereOr('customer.name', 'like', '%' . $keyword . '%')
                    ->whereOr('ledger.customer_id', 'like', '%' . $keyword . '%')
                    ->whereOr('ledger.ledger_id', 'like', '%' . $keyword . '%');
            };
        }

        $query = Db::name($this->name)
            ->alias('ledger')
            ->join('__CRM_CUSTOMER__ customer', 'ledger.customer_id = customer.customer_id', 'LEFT')
            ->join('__ADMIN_USER__ register_user', 'ledger.register_user_id = register_user.id', 'LEFT')
            ->join('__ADMIN_USER__ handler_user', 'ledger.handler_user_id = handler_user.id', 'LEFT')
            ->join('__CRM_BUSINESS__ business', 'ledger.business_id = business.business_id', 'LEFT')
            ->join('__CRM_CONTRACT__ contract', 'ledger.contract_id = contract.contract_id', 'LEFT')
            ->field("ledger.*,customer.name as customer_name,customer.crm_qpmlfv as customer_short_name,register_user.realname as register_user_name,handler_user.realname as handler_user_name,business.name as business_name,contract.num as contract_num,contract.name as contract_name,contract.crm_defqwa as contract_short_name,contract.end_time as contract_end_time,(SELECT record.content FROM {$recordTable} record WHERE record.ledger_id = ledger.ledger_id AND record.new_status = '已完成' ORDER BY record.create_time DESC,record.record_id DESC LIMIT 1) as completed_reply,(SELECT COUNT(1) FROM {$recordTable} record WHERE record.ledger_id = ledger.ledger_id) as record_count")
            ->where($where);
        if ($keywordWhere) {
            $query->where($keywordWhere);
        }

        $query = $this->applyDataScope($query, $userId)->order('ledger.feedback_time desc,ledger.ledger_id desc');
        $countQuery = Db::name($this->name)
            ->alias('ledger')
            ->join('__CRM_CUSTOMER__ customer', 'ledger.customer_id = customer.customer_id', 'LEFT')
            ->where($where);
        if ($keywordWhere) {
            $countQuery->where($keywordWhere);
        }
        $dataCount = (int)$this->applyDataScope($countQuery, $userId)->count($this->pk);

        if ($request['limit'] > 0) {
            $list = $query->limit($request['offset'], $request['length'])->select();
        } else {
            $list = $query->select();
        }

        foreach ($list as &$item) {
            if (empty($item['feedback_time']) && !empty($item['register_time'])) {
                $item['feedback_time'] = $item['register_time'];
            }
            if (empty($item['feedback_channel'])) {
                $item['feedback_channel'] = '微信';
            }
            $item['feedback_time'] = !empty($item['feedback_time']) ? date('Y-m-d H:i:s', $item['feedback_time']) : null;
            $item['register_time'] = !empty($item['register_time']) ? date('Y-m-d H:i:s', $item['register_time']) : null;
            $item['finish_time'] = !empty($item['finish_time']) ? date('Y-m-d H:i:s', $item['finish_time']) : null;
            $item['create_time'] = !empty($item['create_time']) ? date('Y-m-d H:i:s', $item['create_time']) : null;
            $item['update_time'] = !empty($item['update_time']) ? date('Y-m-d H:i:s', $item['update_time']) : null;
        }

        return [
            'list' => $list,
            'dataCount' => $dataCount,
        ];
    }

    public function getAccessibleById($ledgerId, $userId)
    {
        if (empty($ledgerId) || empty($userId)) {
            return null;
        }
        $query = Db::name($this->name)
            ->alias('ledger')
            ->join('__CRM_CUSTOMER__ customer', 'ledger.customer_id = customer.customer_id', 'LEFT')
            ->join('__ADMIN_USER__ register_user', 'ledger.register_user_id = register_user.id', 'LEFT')
            ->join('__ADMIN_USER__ handler_user', 'ledger.handler_user_id = handler_user.id', 'LEFT')
            ->join('__CRM_BUSINESS__ business', 'ledger.business_id = business.business_id', 'LEFT')
            ->join('__CRM_CONTRACT__ contract', 'ledger.contract_id = contract.contract_id', 'LEFT')
            ->field('ledger.*,customer.name as customer_name,customer.crm_qpmlfv as customer_short_name,register_user.realname as register_user_name,handler_user.realname as handler_user_name,business.name as business_name,contract.num as contract_num,contract.name as contract_name,contract.crm_defqwa as contract_short_name,contract.end_time as contract_end_time')
            ->where('ledger.' . $this->pk, $ledgerId);
        $data = $this->applyDataScope($query, $userId)->find();
        if (!$data) {
            return null;
        }
        if (empty($data['feedback_time']) && !empty($data['register_time'])) {
            $data['feedback_time'] = $data['register_time'];
        }
        if (empty($data['feedback_channel'])) {
            $data['feedback_channel'] = '微信';
        }
        $data['feedback_time'] = !empty($data['feedback_time']) ? date('Y-m-d H:i:s', $data['feedback_time']) : null;
        $data['register_time'] = !empty($data['register_time']) ? date('Y-m-d H:i:s', $data['register_time']) : null;
        $data['finish_time'] = !empty($data['finish_time']) ? date('Y-m-d H:i:s', $data['finish_time']) : null;
        $data['create_time'] = !empty($data['create_time']) ? date('Y-m-d H:i:s', $data['create_time']) : null;
        $data['update_time'] = !empty($data['update_time']) ? date('Y-m-d H:i:s', $data['update_time']) : null;
        return $data;
    }

    public function filterIdsByScope(array $ledgerIds, $userId)
    {
        if (empty($ledgerIds)) {
            return [];
        }
        if ($this->isAdminUser($userId)) {
            return array_values(array_unique($ledgerIds));
        }
        $query = Db::name($this->name)->where($this->pk, 'in', $ledgerIds);
        return $this->applyDataScope($query, $userId, '')
            ->column($this->pk) ?: [];
    }

    protected function applyDataScope($query, $userId, $alias = 'ledger')
    {
        if ($this->isAdminUser($userId)) {
            return $query;
        }
        $fieldPrefix = $alias ? $alias . '.' : '';
        $teamTable = Db::getConfig('prefix') . 'crm_team';
        $contractTable = Db::getConfig('prefix') . 'crm_contract';
        return $query->where(function ($q) use ($userId, $fieldPrefix, $teamTable, $contractTable) {
            $q->where($fieldPrefix . 'register_user_id', $userId)
                ->whereOr($fieldPrefix . 'handler_user_id', $userId)
                ->whereOr($fieldPrefix . 'customer_id', 'in', function ($subQuery) use ($userId, $teamTable) {
                    $subQuery->table($teamTable)
                        ->where('team_user_id', $userId)
                        ->where('types', 1)
                        ->field('target_id');
                })
                ->whereOr($fieldPrefix . 'contract_id', 'in', function ($subQuery) use ($userId, $contractTable) {
                    $subQuery->table($contractTable)
                        ->where('owner_user_id', $userId)
                        ->field('contract_id');
                })
                ->whereOr($fieldPrefix . 'contract_id', 'in', function ($subQuery) use ($userId, $teamTable) {
                    $subQuery->table($teamTable)
                        ->where('team_user_id', $userId)
                        ->where('types', 4)
                        ->field('target_id');
                });
        });
    }

    protected function isAdminUser($userId)
    {
        if (empty($userId)) {
            return false;
        }
        $adminTypes = adminGroupTypes($userId);
        return in_array(1, $adminTypes);
    }

    public function addProgressRecord($ledgerId, $customerId, $content, $oldStatus, $newStatus, $userId)
    {
        $recordModel = model('CustomerLedgerRecord');
        $data = [
            'ledger_id' => $ledgerId,
            'customer_id' => $customerId,
            'content' => $content ?: '',
            'old_status' => $oldStatus ?: '',
            'new_status' => $newStatus ?: '',
            'create_user_id' => $userId
        ];
        return $recordModel->addRecord($data);
    }

    public function listProgressRecord($ledgerId)
    {
        $recordModel = model('CustomerLedgerRecord');
        return $recordModel->getList($ledgerId);
    }

    public function getCustomerList($userId, $keyword = '')
    {
        $query = Db::name($this->name)
            ->alias('ledger')
            ->join('__CRM_CUSTOMER__ customer', 'ledger.customer_id = customer.customer_id', 'LEFT')
            ->where('ledger.customer_id', 'gt', 0);

        if (!empty($keyword)) {
            if (is_numeric($keyword)) {
                $query->where('ledger.customer_id', intval($keyword));
            } else {
                $query->where('customer.name', 'like', '%' . $keyword . '%');
            }
        }

        $query = $this->applyDataScope($query, $userId);

        return $query->field('ledger.customer_id,customer.name as customer_name,customer.crm_qpmlfv as customer_short_name')
            ->group('ledger.customer_id')
            ->order('customer.name asc')
            ->limit(200)
            ->select();
    }
}
