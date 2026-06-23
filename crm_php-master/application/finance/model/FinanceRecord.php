<?php
// +----------------------------------------------------------------------  
// | Description: Finance Record
// +----------------------------------------------------------------------  
namespace app\finance\model;

use app\admin\model\Common;
use think\Db;

class FinanceRecord extends Common
{
    protected $name = 'finance_record';
    protected $pk = 'record_id';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $autoWriteTimestamp = true;

    protected static $tableFieldsCache = [];

    protected function getTableFields()
    {
        if (!isset(self::$tableFieldsCache[$this->name])) {
            try {
                $fields = Db::name($this->name)->getTableInfo('fields');
            } catch (\Throwable $e) {
                \think\Log::error('FinanceRecord getTableFields error: ' . $e->getMessage());
                $fields = [];
            }
            self::$tableFieldsCache[$this->name] = $fields ?: [];
        }
        return self::$tableFieldsCache[$this->name];
    }

    protected function hasColumn($column)
    {
        return in_array($column, $this->getTableFields());
    }

    public function getDataList($request)
    {
        $request = $this->fmtRequest($request);
        $map = $request['map'] ?: [];
        $map = $this->normalizeRequestMap($map, $request);

        $userId = isset($request['user_id']) ? (int)$request['user_id'] : 0;
        
        // debug logs removed
        
        $hasBusiness = $this->hasColumn('business_id');
        $hasRelType = $this->hasColumn('rel_type');

        $where = [];
        
        // 先处理客户相关的查询条件
        // 注意：ThinkPHP的闭包条件需要特殊处理，这里改用更可靠的方式
        if (!empty($map['customer_id'])) {
            $customerId = intval($map['customer_id']); // 确保是整数
            // 如果指定了rel_type，则只查询该类型的记录
            if (!empty($map['rel_type'])) {
                if ($map['rel_type'] === 'customer') {
                    $where['record.customer_id'] = $customerId;
                } elseif ($map['rel_type'] === 'contract') {
                    // Filter records where record.contract_id is in the list of contracts associated with customerId
                    $contractIds = Db::name('crm_contract')->where('customer_id', $customerId)->column('contract_id');
                    if (!empty($contractIds)) {
                        $where['record.contract_id'] = ['in', $contractIds];
                    } else {
                        // If no contracts found for this customer, ensure no records are returned
                        $where['record.contract_id'] = 0; // Condition that yields no results
                    }
                } elseif ($map['rel_type'] === 'business') {
                    // Filter records where record.business_id is in the list of businesses associated with customerId
                    $businessIds = Db::name('crm_business')->where('customer_id', $customerId)->column('business_id');
                    if (!empty($businessIds)) {
                        $where['record.business_id'] = ['in', $businessIds];
                    } else {
                        $where['record.business_id'] = 0; // Condition that yields no results
                    }
                }
            } else {
                // 没有指定rel_type，查询所有关联类型的记录
                // 使用OR条件：直接关联客户，或通过合同关联，或通过商机关联
                $where['_customer_or'] = function($query) use ($customerId) {
                    $query->where('record.customer_id', $customerId)
                          ->whereOr('record.contract_id', 'in', function($subQuery) use ($customerId) {
                              $subQuery->table('5kcrm_crm_contract')->where('customer_id', $customerId)->field('contract_id');
                          })
                          ->whereOr('record.business_id', 'in', function($subQuery) use ($customerId) {
                              $subQuery->table('5kcrm_crm_business')->where('customer_id', $customerId)->field('business_id');
                          });
                };
            }
        }
        
        // 处理其他筛选条件
        if (!empty($map['direction'])) {
            // debug logs removed
            $where['record.direction'] = $map['direction'];
        }
        if (!empty($map['contract_id'])) {
            $where['record.contract_id'] = intval($map['contract_id']);
        }
        if ($hasBusiness && !empty($map['business_id'])) {
            $where['record.business_id'] = intval($map['business_id']);
        }
        if (!empty($map['type_id'])) {
            $where['record.type_id'] = intval($map['type_id']);
        }
        // 支持多选类型（确保类型转换）
        if (!empty($map['type_ids']) && is_array($map['type_ids'])) {
            // debug logs removed
            $typeIds = array_map('intval', $map['type_ids']); // 转换为整数数组
            $where['record.type_id'] = ['in', $typeIds];
        }
        if (!empty($map['handler_user_id'])) {
            $where['record.handler_user_id'] = intval($map['handler_user_id']);
        }
        if (!empty($map['plan_id'])) {
            $where['record.plan_id'] = intval($map['plan_id']);
        }
        if (!empty($map['payment_method_id'])) {
            $where['record.payment_method_id'] = intval($map['payment_method_id']);
        }
        // 如果已经通过customer_id和rel_type设置了where条件，不再重复设置rel_type
        if ($hasRelType && !empty($map['rel_type']) && empty($map['customer_id'])) {
            $where['record.rel_type'] = $map['rel_type'];
        }
        if (!empty($map['start_date']) || !empty($map['end_date'])) {
            if (!empty($map['start_date']) && !empty($map['end_date'])) {
                $where['record.occur_date'] = ['between', [$map['start_date'], $map['end_date']]];
            } elseif (!empty($map['start_date'])) {
                $where['record.occur_date'] = ['egt', $map['start_date']];
            } else {
                $where['record.occur_date'] = ['elt', $map['end_date']];
            }
        }
        if (!empty($map['keyword'])) {
            $where['record.remark'] = ['like', '%' . $map['keyword'] . '%'];
        }
        if (!empty($map['min_amount']) || !empty($map['max_amount'])) {
            if (!empty($map['min_amount']) && !empty($map['max_amount'])) {
                $where['record.amount'] = ['between', [(float)$map['min_amount'], (float)$map['max_amount']]];
            } elseif (!empty($map['min_amount'])) {
                $where['record.amount'] = ['egt', (float)$map['min_amount']];
            } else {
                $where['record.amount'] = ['elt', (float)$map['max_amount']];
            }
        }

        // 收支权限控制（可选：按合同/商机/客户团队的 finance_auth 控制）
        if ($userId > 0 && !$this->isAdminUser($userId)) {
            $financeAuthEnabled = $this->teamHasFinanceAuth();
            $scopeWhere = $this->buildFinanceScopeWhere($map, $userId, $financeAuthEnabled);
            if ($scopeWhere === false) {
                $where['record.record_id'] = 0;
            } elseif (!empty($scopeWhere)) {
                $where = array_merge($where, $scopeWhere);
            }
        }

        // 调试：记录构建的where条件
        $whereForLog = [];
        foreach ($where as $key => $value) {
            if (is_callable($value)) {
                $whereForLog[$key] = '[closure function]';
            } elseif (is_array($value)) {
                $whereForLog[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
            } else {
                $whereForLog[$key] = $value;
            }
        }
        // debug logs removed

        $query = Db::name($this->name)
            ->alias('record')
            ->join('__FINANCE_TYPE__ type', 'record.type_id = type.type_id', 'LEFT')
            ->join('__CRM_CUSTOMER__ customer', 'record.customer_id = customer.customer_id', 'LEFT')
            ->join('__CRM_CONTRACT__ contract', 'record.contract_id = contract.contract_id', 'LEFT')
            ->join('__ADMIN_USER__ handler', 'record.handler_user_id = handler.id', 'LEFT')
            ->join('__ADMIN_USER__ creator', 'record.create_user_id = creator.id', 'LEFT')
            ->join('__ADMIN_USER__ register', 'record.register_user_id = register.id', 'LEFT')
            ->join('__FINANCE_PAYMENT_METHOD__ payment', 'record.payment_method_id = payment.method_id', 'LEFT');

        // 始终JOIN business表以确保business_name字段可用
        $query->join('__CRM_BUSINESS__ business', 'record.business_id = business.business_id', 'LEFT');

        $fields = 'record.*,type.name as type_name,type.direction as type_direction,customer.name as customer_name,contract.num as contract_num,contract.name as contract_name,handler.realname as handler_user_name,creator.realname as create_user_name,register.realname as register_user_name,payment.name as payment_method_name';
        $fields .= ',business.name as business_name';
        if ($hasRelType) {
            $fields .= ',record.rel_type';
        }
        $query = $query->field($fields);

        // 支持按操作时间排序
        $orderBy = 'record.occur_date desc,record.record_id desc';
        if (!empty($map['order_by']) && $map['order_by'] === 'create_time') {
            $orderBy = 'record.create_time desc,record.record_id desc';
        }

        // 应用where条件
        // 统一处理where条件，包括闭包
        foreach ($where as $key => $value) {
            if ($key === '_customer_or' || $key === '_finance_scope_or') {
                $query = $query->where($value);
            } else {
                $query = $query->where($key, $value);
            }
        }
        
        $query = $query->order($orderBy);

        // 调试：记录主查询SQL
        // buildSql() 会重置查询选项，使用克隆避免影响后续真实查询
        // debug logs removed

        // 计算总数时也需要使用相同的WHERE条件和JOIN
        $countQuery = Db::name($this->name)
            ->alias('record')
            ->join('__CRM_CUSTOMER__ customer', 'record.customer_id = customer.customer_id', 'LEFT')
            ->join('__CRM_CONTRACT__ contract', 'record.contract_id = contract.contract_id', 'LEFT')
            ->join('__CRM_BUSINESS__ business', 'record.business_id = business.business_id', 'LEFT'); // 始终JOIN business表

        // 应用where条件到统计查询
        foreach ($where as $key => $value) {
            if ($key === '_customer_or' || $key === '_finance_scope_or') {
                $countQuery = $countQuery->where($value);
            } else {
                $countQuery = $countQuery->where($key, $value);
            }
        }
        
        // 调试：记录统计查询SQL
        // buildSql() 会重置查询选项，使用克隆避免影响后续真实查询
        // debug logs removed
        
        $dataCount = (int)$countQuery->count($this->pk);

        if ($request['limit'] > 0) {
            $list = $query->limit($request['offset'], $request['length'])->select();
        } else {
            $list = $query->select();
        }
        
        // 调试：记录查询结果
        // debug logs removed

        foreach ($list as &$item) {
            $item['create_time'] = !empty($item['create_time']) ? date('Y-m-d H:i:s', $item['create_time']) : null;
            $item['update_time'] = !empty($item['update_time']) ? date('Y-m-d H:i:s', $item['update_time']) : null;
        }

        // 计算统计信息
        $statistics = $this->calculateStatistics($where);

        return [
            'list' => $list,
            'dataCount' => $dataCount,
            'statistics' => $statistics
        ];
    }

    public function getDataById($id = '')
    {
        if (empty($id)) {
            $this->error = '参数错误';
            return false;
        }

        $hasBusiness = $this->hasColumn('business_id');
        $hasRelType = $this->hasColumn('rel_type');

        $query = Db::name($this->name)
            ->alias('record')
            ->join('__FINANCE_TYPE__ type', 'record.type_id = type.type_id', 'LEFT')
            ->join('__CRM_CUSTOMER__ customer', 'record.customer_id = customer.customer_id', 'LEFT')
            ->join('__CRM_CONTRACT__ contract', 'record.contract_id = contract.contract_id', 'LEFT')
            ->join('__ADMIN_USER__ handler', 'record.handler_user_id = handler.id', 'LEFT')
            ->join('__ADMIN_USER__ register', 'record.register_user_id = register.id', 'LEFT');

        $fields = 'record.*,type.name as type_name,customer.name as customer_name,contract.num as contract_num,contract.name as contract_name,handler.realname as handler_user_name,register.realname as register_user_name';
        if ($hasBusiness) {
            $query->join('__CRM_BUSINESS__ business', 'record.business_id = business.business_id', 'LEFT');
            $fields .= ',business.name as business_name';
        }
        if ($hasRelType) {
            $fields .= ',record.rel_type';
        }

        $data = $query->field($fields)
            ->where('record.' . $this->pk, $id)
            ->find();

        if (!$data) {
            $this->error = '数据不存在';
            return false;
        }

        $data['create_time'] = !empty($data['create_time']) ? date('Y-m-d H:i:s', $data['create_time']) : null;
        $data['update_time'] = !empty($data['update_time']) ? date('Y-m-d H:i:s', $data['update_time']) : null;

        return $data;
    }

    /**
     * 标准化请求携带的 map 参数，保证 direction 始终合法
     */
    protected function normalizeRequestMap($map, array $request)
    {
        if (is_string($map)) {
            $decodedMap = json_decode($map, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedMap)) {
                $map = $decodedMap;
            } else {
                $map = [];
            }
        }
        if (!is_array($map)) {
            $map = [];
        }
        if (!isset($map['direction']) && isset($request['direction']) && $request['direction'] !== '') {
            $map['direction'] = $request['direction'];
        }
        $direction = $this->normalizeDirection($map['direction'] ?? '');
        if ($direction) {
            $map['direction'] = $direction;
        } else {
            unset($map['direction']);
        }
        return $map;
    }

    /**
     * 仅接受 income/expense 两个方向，并返回小写值
     */
    protected function normalizeDirection($direction)
    {
        $value = trim((string)$direction);
        $value = strtolower($value);
        return $value === 'income' || $value === 'expense' ? $value : '';
    }

    /**
     * 计算统计信息
     */
    protected function calculateStatistics($where)
    {
        // 统一处理where条件，包括闭包
        $applyWhere = function($query, $conditions) {
            foreach ($conditions as $key => $value) {
                if ($key === '_customer_or' || $key === '_finance_scope_or') {
                    $query = $query->where($value);
                } else {
                    $query = $query->where($key, $value);
                }
            }
            return $query;
        };

        // 总收入
        $totalIncomeQuery = Db::name($this->name)
            ->alias('record');
        // 仅当传入的where条件中不包含direction时，才添加direction条件
        // 否则，使用传入的where条件中的direction
        $incomeWhere = $where;
        if (!isset($incomeWhere['record.direction'])) {
            $incomeWhere['record.direction'] = 'income';
        } else if (isset($incomeWhere['record.direction']) && $incomeWhere['record.direction'] !== 'income') {
            // 如果传入的direction与当前统计的direction不符，则强制设置为当前统计的direction
            // 避免统计错误，例如在筛选支出时，统计收入
            $incomeWhere['record.direction'] = 'income';
        }
        $totalIncome = (float)$applyWhere($totalIncomeQuery, $incomeWhere)->sum('record.amount');

        // 总支出
        $totalExpenseQuery = Db::name($this->name)
            ->alias('record');
        // 仅当传入的where条件中不包含direction时，才添加direction条件
        // 否则，使用传入的where条件中的direction
        $expenseWhere = $where;
        if (!isset($expenseWhere['record.direction'])) {
            $expenseWhere['record.direction'] = 'expense';
        } else if (isset($expenseWhere['record.direction']) && $expenseWhere['record.direction'] !== 'expense') {
            // 如果传入的direction与当前统计的direction不符，则强制设置为当前统计的direction
            $expenseWhere['record.direction'] = 'expense';
        }
        $totalExpense = (float)$applyWhere($totalExpenseQuery, $expenseWhere)->sum('record.amount');

        // 盈亏
        $profit = $totalIncome - $totalExpense;

        return [
            'totalIncome' => number_format($totalIncome, 2, '.', ''),
            'totalExpense' => number_format($totalExpense, 2, '.', ''),
            'profit' => number_format($profit, 2, '.', '')
        ];
    }

    protected function isAdminUser($userId)
    {
        if (empty($userId)) {
            return false;
        }
        $adminTypes = adminGroupTypes($userId);
        return in_array(1, $adminTypes);
    }

    protected function teamHasFinanceAuth()
    {
        try {
            $fields = Db::name('crm_team')->getTableInfo('fields');
            return in_array('finance_auth', $fields, true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function buildFinanceScopeWhere(array $map, int $userId, bool $financeAuthEnabled)
    {
        $where = [];
        $teamTable = Db::getConfig('prefix') . 'crm_team';
        $customerTable = Db::getConfig('prefix') . 'crm_customer';
        $businessTable = Db::getConfig('prefix') . 'crm_business';
        $contractTable = Db::getConfig('prefix') . 'crm_contract';

        $contractId = !empty($map['contract_id']) ? (int)$map['contract_id'] : 0;
        $businessId = !empty($map['business_id']) ? (int)$map['business_id'] : 0;
        $customerId = !empty($map['customer_id']) ? (int)$map['customer_id'] : 0;

        if ($contractId > 0) {
            if ($this->isContractFinanceVisible($contractId, $userId, $financeAuthEnabled)) {
                $where['record.contract_id'] = $contractId;
                return $where;
            }
            return false;
        }

        if ($businessId > 0) {
            if ($this->isBusinessFinanceVisible($businessId, $userId, $financeAuthEnabled)) {
                $where['record.business_id'] = $businessId;
                return $where;
            }
            return false;
        }

        if ($customerId > 0) {
            if ($this->isCustomerFinanceVisible($customerId, $userId, $financeAuthEnabled)) {
                $where['record.customer_id'] = $customerId;
                return $where;
            }
            return false;
        }

        if ($financeAuthEnabled) {
            $where['_finance_scope_or'] = function($query) use ($userId, $teamTable, $customerTable, $businessTable, $contractTable) {
                $query->where('record.customer_id', 'in', function($subQuery) use ($userId, $customerTable) {
                    $subQuery->table($customerTable)
                        ->where('owner_user_id', $userId)
                        ->field('customer_id');
                })
                ->whereOr('record.customer_id', 'in', function($subQuery) use ($userId, $teamTable) {
                    $subQuery->table($teamTable)
                        ->where('team_user_id', $userId)
                        ->where('types', 1)
                        ->where('finance_auth', 1)
                        ->field('target_id');
                })
                ->whereOr('record.contract_id', 'in', function($subQuery) use ($userId, $contractTable) {
                    $subQuery->table($contractTable)
                        ->where('owner_user_id', $userId)
                        ->field('contract_id');
                })
                ->whereOr('record.contract_id', 'in', function($subQuery) use ($userId, $teamTable) {
                    $subQuery->table($teamTable)
                        ->where('team_user_id', $userId)
                        ->where('types', 4)
                        ->where('finance_auth', 1)
                        ->field('target_id');
                })
                ->whereOr('record.business_id', 'in', function($subQuery) use ($userId, $businessTable) {
                    $subQuery->table($businessTable)
                        ->where('owner_user_id', $userId)
                        ->field('business_id');
                })
                ->whereOr('record.business_id', 'in', function($subQuery) use ($userId, $teamTable) {
                    $subQuery->table($teamTable)
                        ->where('team_user_id', $userId)
                        ->where('types', 3)
                        ->where('finance_auth', 1)
                        ->field('target_id');
                });
            };
            return $where;
        }

        $where['_finance_scope_or'] = function($query) use ($userId, $teamTable, $customerTable, $businessTable, $contractTable) {
            $query->where('record.customer_id', 'in', function($subQuery) use ($userId, $customerTable) {
                $subQuery->table($customerTable)
                    ->where('owner_user_id', $userId)
                    ->field('customer_id');
            })
            ->whereOr('record.customer_id', 'in', function($subQuery) use ($userId, $teamTable) {
                $subQuery->table($teamTable)
                    ->where('team_user_id', $userId)
                    ->where('types', 1)
                    ->field('target_id');
            })
            ->whereOr('record.contract_id', 'in', function($subQuery) use ($userId, $contractTable) {
                $subQuery->table($contractTable)
                    ->where('owner_user_id', $userId)
                    ->field('contract_id');
            })
            ->whereOr('record.contract_id', 'in', function($subQuery) use ($userId, $teamTable) {
                $subQuery->table($teamTable)
                    ->where('team_user_id', $userId)
                    ->where('types', 4)
                    ->field('target_id');
            })
            ->whereOr('record.business_id', 'in', function($subQuery) use ($userId, $businessTable) {
                $subQuery->table($businessTable)
                    ->where('owner_user_id', $userId)
                    ->field('business_id');
            })
            ->whereOr('record.business_id', 'in', function($subQuery) use ($userId, $teamTable) {
                $subQuery->table($teamTable)
                    ->where('team_user_id', $userId)
                    ->where('types', 3)
                    ->field('target_id');
            });
        };
        return $where;
    }

    protected function isContractFinanceVisible(int $contractId, int $userId, bool $financeAuthEnabled)
    {
        $contract = Db::name('crm_contract')->where('contract_id', $contractId)->field('owner_user_id')->find();
        if ($contract && (int)$contract['owner_user_id'] === $userId) {
            return true;
        }
        $teamQuery = Db::name('crm_team')
            ->where('team_user_id', $userId)
            ->where('types', 4)
            ->where('target_id', $contractId);
        if ($financeAuthEnabled) {
            $teamQuery->where('finance_auth', 1);
        }
        return (bool)$teamQuery->value('team_id');
    }

    protected function isBusinessFinanceVisible(int $businessId, int $userId, bool $financeAuthEnabled)
    {
        $business = Db::name('crm_business')->where('business_id', $businessId)->field('owner_user_id')->find();
        if ($business && (int)$business['owner_user_id'] === $userId) {
            return true;
        }
        $teamQuery = Db::name('crm_team')
            ->where('team_user_id', $userId)
            ->where('types', 3)
            ->where('target_id', $businessId);
        if ($financeAuthEnabled) {
            $teamQuery->where('finance_auth', 1);
        }
        return (bool)$teamQuery->value('team_id');
    }

    protected function isCustomerFinanceVisible(int $customerId, int $userId, bool $financeAuthEnabled)
    {
        $customer = Db::name('crm_customer')->where('customer_id', $customerId)->field('owner_user_id')->find();
        if ($customer && (int)$customer['owner_user_id'] === $userId) {
            return true;
        }
        $teamQuery = Db::name('crm_team')
            ->where('team_user_id', $userId)
            ->where('types', 1)
            ->where('target_id', $customerId);
        if ($financeAuthEnabled) {
            $teamQuery->where('finance_auth', 1);
        }
        return (bool)$teamQuery->value('team_id');
    }
}
