<?php
// +----------------------------------------------------------------------
// | Description: Finance Record
// +----------------------------------------------------------------------
namespace app\finance\controller;

use app\admin\controller\ApiCommon;
use think\Hook;
use think\Request;

class Record extends ApiCommon
{
    public function _initialize()
    {
        $action = [
            'permission' => ['index'],
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
        $model = model('FinanceRecord');
        // 手动获取请求参数，确保 $this->param 被初始化
        $request = Request::instance();
        $param = $request->param();
        // 移除 platform 参数，与 Common 类的处理保持一致
        if (isset($param['platform'])) {
            unset($param['platform']);
        }
        if (!empty($this->userInfo) && !empty($this->userInfo['id'])) {
            $param['user_id'] = (int)$this->userInfo['id'];
        }
        $this->param = $param; // 赋值给控制器属性

        try {
            $data = $model->getDataList($this->param); // 使用 $this->param
        } catch (\Throwable $e) {
            return resultArray(['error' => '收支流水加载失败：' . $e->getMessage()]);
        }
        return resultArray(['data' => $data]);
    }

    public function read()
    {
        $model = model('FinanceRecord');
        $param = $this->param;
        if (empty($param['id'])) {
            return resultArray(['error' => '参数错误']);
        }
        $data = $model->getAccessibleById($param['id'], (int)($this->userInfo['id'] ?? 0));
        if (!$data) {
            return resultArray(['error' => $model->getError() ?: '无权限']);
        }
        return resultArray(['data' => $data]);
    }

    public function save()
    {
        $model = model('FinanceRecord');
        $planModel = model('FinancePlan');
        $param = $this->param;
        $userInfo = $this->userInfo;

        if (empty($param['direction']) || !in_array($param['direction'], ['income', 'expense'])) {
            return resultArray(['error' => 'direction 仅支持 income/expense']);
        }
        if (empty($param['amount']) || !is_numeric($param['amount'])) {
            return resultArray(['error' => '请填写合法金额']);
        }
        if (empty($param['occur_date'])) {
            return resultArray(['error' => '请填写发生日期']);
        }
        // 处理日期格式：将ISO格式转换为Y-m-d格式
        if (!empty($param['occur_date'])) {
            $dateStr = $param['occur_date'];
            // 如果是ISO格式（包含T），提取日期部分
            if (strpos($dateStr, 'T') !== false) {
                $dateStr = substr($dateStr, 0, 10);
            }
            // 验证日期格式
            $date = date('Y-m-d', strtotime($dateStr));
            if ($date === '1970-01-01' && $dateStr !== '1970-01-01') {
                return resultArray(['error' => '日期格式不正确']);
            }
            $param['occur_date'] = $date;
        }

        if (!empty($param['plan_id'])) {
            $plan = db('finance_plan')->where('plan_id', $param['plan_id'])->find();
            if (!$plan) {
                return resultArray(['error' => '计划不存在']);
            }
        }

        $relationError = $this->validateRelationParams($param);
        if ($relationError) {
            return resultArray(['error' => $relationError]);
        }
        $param['create_user_id'] = $userInfo['id'];
        // 经办人默认为当前用户
        if (empty($param['handler_user_id'])) {
            $param['handler_user_id'] = $userInfo['id'];
        }
        // 记录人默认为当前用户
        if (empty($param['register_user_id'])) {
            $param['register_user_id'] = $userInfo['id'];
        }
        // 支付方式ID，如果未提供或为空则设为0
        if (!isset($param['payment_method_id']) || $param['payment_method_id'] === '' || $param['payment_method_id'] === null) {
            $param['payment_method_id'] = 0;
        } else {
            $param['payment_method_id'] = (int)$param['payment_method_id'];
        }

        $res = $model->data($param)->allowField(true)->save();
        if ($res) {
            $recordId = $model->record_id;
            if (!empty($param['plan_id'])) {
                $planModel->refreshStatus($param['plan_id']);
            }
            // 添加活动流信息
            $this->addFinanceActivity($recordId, $param, $userInfo['id'], 'create');
            return resultArray(['data' => '添加成功']);
        }
        return resultArray(['error' => '添加失败']);
    }

    public function update()
    {
        $model = model('FinanceRecord');
        $planModel = model('FinancePlan');
        $param = $this->param;

        if (empty($param['id'])) {
            return resultArray(['error' => '参数错误']);
        }
        if (!empty($param['direction']) && !in_array($param['direction'], ['income', 'expense'])) {
            return resultArray(['error' => 'direction 仅支持 income/expense']);
        }
        if (isset($param['amount']) && !is_numeric($param['amount'])) {
            return resultArray(['error' => '请填写合法金额']);
        }
        // 处理日期格式：将ISO格式转换为Y-m-d格式
        if (!empty($param['occur_date'])) {
            $dateStr = $param['occur_date'];
            // 如果是ISO格式（包含T），提取日期部分
            if (strpos($dateStr, 'T') !== false) {
                $dateStr = substr($dateStr, 0, 10);
            }
            // 验证日期格式
            $date = date('Y-m-d', strtotime($dateStr));
            if ($date === '1970-01-01' && $dateStr !== '1970-01-01') {
                return resultArray(['error' => '日期格式不正确']);
            }
            $param['occur_date'] = $date;
        }

        $recordId = $param['id'];
        unset($param['id']);

        $old = db('finance_record')->where('record_id', $recordId)->find();
        if (!$old) {
            return resultArray(['error' => '数据不存在']);
        }
        $userId = (int)($this->userInfo['id'] ?? 0);
        if (!$model->isRecordAccessible($recordId, $userId)) {
            return resultArray(['error' => '无权限']);
        }
        if (!empty($param['plan_id'])) {
            $plan = db('finance_plan')->where('plan_id', $param['plan_id'])->find();
            if (!$plan) {
                return resultArray(['error' => '计划不存在']);
            }
        }

        $relationError = $this->validateRelationParams($param, $old);
        if ($relationError) {
            return resultArray(['error' => $relationError]);
        }
        
        // 支付方式ID处理
        if (!isset($param['payment_method_id']) || $param['payment_method_id'] === '' || $param['payment_method_id'] === null) {
            $param['payment_method_id'] = isset($old['payment_method_id']) ? (int)$old['payment_method_id'] : 0;
        } else {
            $param['payment_method_id'] = (int)$param['payment_method_id'];
        }
        
        $res = $model->allowField(true)->save($param, ['record_id' => $recordId]);
        if ($res !== false) {
            $oldPlanId = (int)$old['plan_id'];
            $newPlanId = !empty($param['plan_id']) ? (int)$param['plan_id'] : $oldPlanId;
            if ($oldPlanId) {
                $planModel->refreshStatus($oldPlanId);
            }
            if ($newPlanId && $newPlanId !== $oldPlanId) {
                $planModel->refreshStatus($newPlanId);
            }
            // 添加活动流信息
            $userInfo = $this->userInfo;
            $this->addFinanceActivity($recordId, array_merge($old, $param), $userInfo['id'], 'update');
            return resultArray(['data' => '编辑成功']);
        }
        return resultArray(['error' => '编辑失败']);
    }

    public function delete()
    {
        $planModel = model('FinancePlan');
        $model = model('FinanceRecord');
        $param = $this->param;
        if (empty($param['id'])) {
            return resultArray(['error' => '参数错误']);
        }
        $ids = is_array($param['id']) ? $param['id'] : [$param['id']];
        $userId = (int)($this->userInfo['id'] ?? 0);
        foreach ($ids as $id) {
            if (!$model->isRecordAccessible($id, $userId)) {
                return resultArray(['error' => '无权限']);
            }
        }
        $planIds = db('finance_record')->where('record_id', 'in', $ids)->column('plan_id');
        $res = db('finance_record')->where('record_id', 'in', $ids)->delete();
        if ($res) {
            $planIds = array_unique(array_filter($planIds));
            foreach ($planIds as $planId) {
                $planModel->refreshStatus($planId);
            }
            return resultArray(['data' => '删除成功']);
        }
        return resultArray(['error' => '删除失败']);
    }

    protected function validateRelationParams(&$param, $old = [])
    {
        // 获取关联类型
        $relType = isset($param['rel_type']) ? $param['rel_type'] : ($old['rel_type'] ?? 'none');
        $relType = in_array($relType, ['none', 'customer', 'contract', 'business']) ? $relType : 'none';
        
        // 获取关联ID
        $customerId = isset($param['customer_id']) ? (int)$param['customer_id'] : ($old['customer_id'] ?? 0);
        $contractId = isset($param['contract_id']) ? (int)$param['contract_id'] : ($old['contract_id'] ?? 0);
        $businessId = isset($param['business_id']) ? (int)$param['business_id'] : ($old['business_id'] ?? 0);

        // 如果选择了具体的关联对象，自动推断关联类型
        if ($contractId > 0) {
            // 如果选择了合同，自动设置为合同类型
            $relType = 'contract';
            // 合同通常关联客户，尝试从合同获取客户ID
            if ($customerId <= 0) {
                $contract = db('crm_contract')->where('contract_id', $contractId)->find();
                if ($contract && !empty($contract['customer_id'])) {
                    $customerId = (int)$contract['customer_id'];
                }
            }
            $businessId = 0; // 合同和商机互斥
        } elseif ($businessId > 0) {
            // 如果商机已经成交（已有关联合同），自动归类到最新合同
            $latestContract = db('crm_contract')
                ->where('business_id', $businessId)
                ->order('create_time desc,contract_id desc')
                ->field('contract_id,customer_id')
                ->find();
            if ($latestContract && !empty($latestContract['contract_id'])) {
                $relType = 'contract';
                $contractId = (int)$latestContract['contract_id'];
                $customerId = (int)$latestContract['customer_id'];
                $businessId = 0;
            } else {
                // 未成交商机仍允许按商机关联
                $relType = 'business';
                if ($customerId <= 0) {
                    $business = db('crm_business')->where('business_id', $businessId)->find();
                    if ($business && !empty($business['customer_id'])) {
                        $customerId = (int)$business['customer_id'];
                    }
                }
                $contractId = 0;
            }
        } elseif ($customerId > 0) {
            // 如果只选择了客户，设置为客户类型
            $relType = 'customer';
            $contractId = 0;
            $businessId = 0;
        } else {
            // 都没有选择，设置为公司类型
            $relType = 'none';
            $contractId = 0;
            $businessId = 0;
        }

        // 验证关联类型和ID的一致性
        if ($relType === 'customer') {
            if ($customerId <= 0) {
                return '客户关联时需指定客户ID';
            }
            $param['contract_id'] = 0;
            $param['business_id'] = 0;
        } elseif ($relType === 'contract') {
            if ($contractId <= 0) {
                return '合同关联时需指定合同ID';
            }
            $param['business_id'] = 0;
        } elseif ($relType === 'business') {
            if ($businessId <= 0) {
                return '商机关联时需指定商机ID';
            }
            $param['contract_id'] = 0;
        } else {
            // 公司类型，清空所有关联ID
            $customerId = 0;
            $contractId = 0;
            $businessId = 0;
        }

        // 设置最终值
        $param['rel_type'] = $relType;
        $param['customer_id'] = $customerId;
        $param['contract_id'] = $contractId;
        $param['business_id'] = $businessId;

        return '';
    }

    /**
     * 添加收支活动流
     */
    protected function addFinanceActivity($recordId, $param, $userId, $action = 'create')
    {
        $customerId = isset($param['customer_id']) ? (int)$param['customer_id'] : 0;
        $contractId = isset($param['contract_id']) ? (int)$param['contract_id'] : 0;
        $businessId = isset($param['business_id']) ? (int)$param['business_id'] : 0;
        
        // 如果没有客户ID，尝试从合同或商机获取
        if ($customerId <= 0) {
            if ($contractId > 0) {
                $contract = db('crm_contract')->where('contract_id', $contractId)->field('customer_id')->find();
                if ($contract && !empty($contract['customer_id'])) {
                    $customerId = (int)$contract['customer_id'];
                }
            } elseif ($businessId > 0) {
                $business = db('crm_business')->where('business_id', $businessId)->field('customer_id')->find();
                if ($business && !empty($business['customer_id'])) {
                    $customerId = (int)$business['customer_id'];
                }
            }
        }
        
        // 构建活动内容
        $direction = isset($param['direction']) ? ($param['direction'] === 'income' ? '收入' : '支出') : '';
        $amount = isset($param['amount']) ? number_format($param['amount'], 2) : '0.00';
        $typeName = '';
        if (!empty($param['type_id'])) {
            $type = db('finance_type')->where('type_id', $param['type_id'])->field('name')->find();
            if ($type) {
                $typeName = $type['name'];
            }
        }
        
        $content = '';
        if ($action === 'create') {
            $content = "创建了{$direction}记录：{$typeName}，金额：{$amount}元";
        } else {
            $content = "更新了{$direction}记录：{$typeName}，金额：{$amount}元";
        }
        
        if (!empty($param['remark'])) {
            $remark = mb_substr($param['remark'], 0, 50, 'utf-8');
            $content .= "，备注：{$remark}";
        }
        
        // 确定activity_type，收支记录使用14（参考台账使用13）
        $activityType = 14;
        
        // 插入活动流
        $activityData = [
            'type' => 1, // 1表示跟进记录
            'activity_type' => $activityType,
            'activity_type_id' => $recordId,
            'content' => $content,
            'category' => '收支',
            'customer_ids' => $customerId > 0 ? (',' . $customerId . ',') : '',
            'contract_ids' => $contractId > 0 ? (',' . $contractId . ',') : '',
            'business_ids' => $businessId > 0 ? (',' . $businessId . ',') : '',
            'create_user_id' => $userId,
            'update_time' => time(),
            'create_time' => time()
        ];
        
        db('crm_activity')->insert($activityData);
    }
}
