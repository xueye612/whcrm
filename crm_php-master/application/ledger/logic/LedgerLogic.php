<?php
// +----------------------------------------------------------------------
// | Description: Ledger business logic
// +----------------------------------------------------------------------
namespace app\ledger\logic;

use think\Db;

class LedgerLogic
{
    public function normalizeRelation(array &$param)
    {
        $contractId = !empty($param['contract_id']) ? (int)$param['contract_id'] : 0;
        if (!$contractId) {
            return '台账必须关联合同';
        }

        $contract = db('crm_contract')->where('contract_id', $contractId)->find();
        if (!$contract) {
            return '合同不存在';
        }

        $customerId = (int)$contract['customer_id'];
        if (!$customerId) {
            return '合同未关联客户';
        }

        $param['customer_id'] = $customerId;
        $param['business_id'] = 0;
        $param['contract_id'] = $contractId;
        return '';
    }

    public function assertContractAccessible($contractId, $userId)
    {
        $contractId = (int)$contractId;
        $userId = (int)$userId;
        if ($contractId <= 0 || $userId <= 0) {
            return '合同不存在';
        }
        if ($this->isAdminUser($userId)) {
            return '';
        }

        $contract = db('crm_contract')->where('contract_id', $contractId)->field('contract_id,owner_user_id')->find();
        if (!$contract) {
            return '合同不存在';
        }
        if ((int)$contract['owner_user_id'] === $userId) {
            return '';
        }

        $teamTable = Db::getConfig('prefix') . 'crm_team';
        $inTeam = Db::table($teamTable)
            ->where('team_user_id', $userId)
            ->where('types', 4)
            ->where('target_id', $contractId)
            ->count();
        if ($inTeam > 0) {
            return '';
        }

        $customerId = (int)db('crm_contract')->where('contract_id', $contractId)->value('customer_id');
        if ($customerId > 0) {
            $inCustomerTeam = Db::table($teamTable)
                ->where('team_user_id', $userId)
                ->where('types', 1)
                ->where('target_id', $customerId)
                ->count();
            if ($inCustomerTeam > 0) {
                return '';
            }
        }

        return '无权操作该合同';
    }

    public function sanitizeProgressContent($content)
    {
        $content = trim(strip_tags((string)$content));
        $content = preg_replace('/\s+/u', ' ', $content);
        if ($content === '') {
            return '';
        }
        if (mb_strlen($content, 'UTF-8') > 2000) {
            $content = mb_substr($content, 0, 2000, 'UTF-8');
        }
        return $content;
    }

    public function getAllowedCategories()
    {
        $default = ['使用指导', '操作错误', '功能完善', '系统BUG', '新增需求', '新需求', '其他问题'];
        $config = db('crm_config')->where(['name' => 'ledger_category'])->find();
        if ($config && !empty($config['value'])) {
            $value = json_decode($config['value'], true);
            if (is_array($value)) {
                $list = [];
                foreach ($value as $item) {
                    $item = trim((string)$item);
                    if ($item !== '') {
                        $list[] = $item;
                    }
                }
                $list = array_values(array_unique($list));
                if (!empty($list)) {
                    return $list;
                }
            }
        }
        return $default;
    }

    protected function isAdminUser($userId)
    {
        if (empty($userId)) {
            return false;
        }
        $adminTypes = adminGroupTypes($userId);
        return in_array(1, $adminTypes);
    }
}
