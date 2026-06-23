<?php
/**
 * 发票表
 *
 * @author qifan
 * @data 2020-12-07
 */

namespace app\crm\model;

use app\admin\model\Common;

class Invoice extends Common
{
    protected $name = 'crm_invoice';
    protected $pk   = 'invoice_id';
    protected $dateFormat = "Y-m-d H:i:s";

    /**
     * 关联用户模型
     *
     * @return \think\model\relation\HasOne
     */
    public function toCustomer()
    {
        return $this->hasOne('Customer', 'customer_id', 'customer_id')->bind([
            'customer_name' => 'name'
        ]);
    }

    /**
     * 关联合同模型
     *
     * @return \think\model\relation\HasOne
     */
    public function toContract()
    {
        return $this->hasOne('Contract', 'contract_id', 'contract_id')->bind([
            'contract_number' => 'num',
        ]);
    }

    /**
     * 关联用户模型
     *
     * @return \think\model\relation\HasOne
     */
    public function toAdminUser()
    {
        return $this->hasOne('AdminUser', 'id', 'owner_user_id')->bind([
           'owner_user_name' => 'realname'
        ]);
    }

    /**
     * @param string $id
     * @param int $userId
     * @param string $model
     * @author: alvin guogaobo
     * @version: 11.1.0
     * Date: 2021/8/30 14:11
     */
    public function getDataById($id = '', $userId = 0, $model='')
    {
        $map['invoice_id'] = $id;
        $dataInfo = $this->where($map)->find();
        if (!$dataInfo) {
            $this->error = '暂无此数据';
            return false;
        }
        if(empty($model) && $model!='update'){
            $grantData = getFieldGrantData($userId);
            foreach ($grantData['crm_invoice'] as $key => $value) {
                foreach ($value as $ke => $va) {
                    if($va['maskType']!=0){
                        $fieldGrant[$ke]['maskType'] = $va['maskType'];
                        $fieldGrant[$ke]['form_type'] = $va['form_type'];
                        $fieldGrant[$ke]['field'] = $va['field'];
                    }
                }
            }
            foreach ($fieldGrant AS $key => $val){
                //掩码相关类型字段
                if ($val['maskType']!=0 && $val['form_type'] == 'mobile') {
                    $pattern = "/(1[3458]{1}[0-9])[0-9]{4}([0-9]{4})/i";
                    $rs = preg_replace($pattern, "$1****$2", $dataInfo[$val['field']]);
                    $dataInfo[$val['field']] = !empty($dataInfo[$val['field']]) ? (string)$rs : null;
                } elseif ($val['maskType']!=0 && $val['form_type'] == 'email') {
                    $email_array = explode("@", $dataInfo[$val['field']]);
                    $prevfix = (strlen($email_array[0]) < 4) ? "" : substr($dataInfo[$val['field']], 0, 2); //邮箱前缀
                    $str = preg_replace('/([\d\w+_-]{0,100})@/', "***@", $dataInfo[$val['field']], -1, $count);
                    $rs = $prevfix . $str;
                    $dataInfo[$val['field']] = !empty($dataInfo[$val['field']]) ?$rs: null;
                } elseif ($val['maskType']!=0 && in_array($val['form_type'],['position','floatnumber'])) {
                    $dataInfo[$val['field']] = !empty($dataInfo[$val['field']]) ? (string)substr_replace($dataInfo[$val['field']], '*****',0,strlen($dataInfo[$val['field']])) : null;
                }
            }
        }
//        $userModel = new \app\admin\model\User();
//        $dataInfo['create_user_id_info'] = isset($dataInfo['create_user_id']) ? $userModel->getUserById($dataInfo['create_user_id']) : [];
//        $dataInfo['owner_user_id_info'] = isset($dataInfo['owner_user_id']) ? $userModel->getUserById($dataInfo['owner_user_id']) : [];
//        $dataInfo['create_user_name'] = !empty($dataInfo['create_user_id_info']['realname']) ? $dataInfo['create_user_id_info']['realname'] : '';
//        $dataInfo['owner_user_name'] = !empty($dataInfo['owner_user_id_info']['realname']) ? $dataInfo['owner_user_id_info']['realname'] : '';
//        $dataInfo['customer_id_info'] = $dataInfo['customer_id'] ? db('crm_customer')->where(['customer_id' => $dataInfo['customer_id']])->field('customer_id,name')->find() : [];
//        $dataInfo['contract_id_info'] = $dataInfo['contract_id'] ? db('crm_contract')->where(['contract_id' => $dataInfo['contract_id']])->field('contract_id,name,money')->find() : [];
//        $dataInfo['receivables_id'] = $id;
//        $userModel = new \app\admin\model\User();
//        $dataInfo['create_user_info'] = $userModel->getUserById($dataInfo['create_user_id']);
//        $dataInfo['plan_id'] = $id;
//        # 处理时间格式
//        $fieldModel = new \app\admin\model\Field();
//        $datetimeField = $fieldModel->getFieldByFormType('crm_receivables', 'datetime'); //日期时间类型
//        foreach ($datetimeField as $key => $val) {
//            $dataInfo[$val] = !empty($dataInfo[$val]) ? date('Y-m-d H:i:s', $dataInfo[$val]) : null;
//        }
//        $dataInfo['create_time'] = !empty($dataInfo['create_time']) ? date('Y-m-d H:i:s', $dataInfo['create_time']) : null;
//        $dataInfo['update_time'] = !empty($dataInfo['update_time']) ? date('Y-m-d H:i:s', $dataInfo['update_time']) : null;
//        // 字段授权
//        if (!empty($userId)) {
//            $grantData = getFieldGrantData($userId);
//            $userLevel = isSuperAdministrators($userId);
//            foreach ($dataInfo as $key => $value) {
//                if (!$userLevel && !empty($grantData['crm_receivables'])) {
//                    $status = getFieldGrantStatus($key, $grantData['crm_receivables']);
//
//                    # 查看权限
//                    if ($status['read'] == 0) unset($dataInfo[$key]);
//                }
//            }
//            if (!$userLevel && !empty($grantData['crm_receivables'])) {
//                # 客户名称
//                $customerStatus = getFieldGrantStatus('customer_id', $grantData['crm_receivables']);
//                if ($customerStatus['read'] == 0) {
//                    $dataInfo['customer_name'] = '';
//                    $dataInfo['customer_id_info'] = [];
//                }
//                # 合同金额
//                $contractMoneyStatus = getFieldGrantStatus('contract_money', $grantData['crm_receivables']);
//                if ($contractMoneyStatus['read'] == 0) $dataInfo['contract_id_info']['money'] = '';
//                # 合同名称
//                $contractMoneyStatus = getFieldGrantStatus('contract_money', $grantData['crm_receivables']);
//                if ($contractMoneyStatus['read'] == 0) $dataInfo['contract_id_info']['money'] = '';
//            }
//        }
        return $dataInfo;
    }
}