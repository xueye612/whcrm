<?php

namespace app\crm\logic;

use app\admin\model\Common;
use app\crm\model\Customer;
use think\Db;
use function foo\func;

class MessageLogic extends Common
{
    /**
     *
     * 今日续联系线索列表
     * @param $param
     *
     * @author      alvin guogaobo
     * @version     1.0 版本号
     * @since       2021/5/24 0024 11:43
     */
    public function todayLeads($param)
    {
        $type = !empty($param['type']) ? $param['type'] : 1;
        $isSub = !empty($param['isSub']) ? $param['isSub'] : 0;
        $todayTime = getTimeByType('today');
        unset($param['type']);
        unset($param['isSub']);
        $request = $this->where($param, $type, $isSub, $todayTime);
        $leadsModel = new \app\crm\model\Leads();
        $data = $leadsModel->getDataList($request);
        return $data;
    }
    
    /**
     * 客户
     * @param $request
     *
     * @author      alvin guogaobo
     * @version     1.0 版本号
     * @since       2021/5/25 0025 09:17
     */
    public function remindCustomer($param)
    {
        
        $type = $param['type'] ?: 1;
        $isSub = $param['isSub'] ?: '';
        $todayTime = getTimeByType('today');
        unset($param['type']);
        unset($param['isSub']);
        $request = $this->where($param, $type, $isSub, $todayTime);
        $customerModel = model('Customer');
        $data = $customerModel->getDataList($request);
        return $data;
    }
    
    /**
     *
     * @param $param
     *
     * @author      alvin guogaobo
     * @version     1.0 版本号
     * @since       2021/5/26 0026 10:13
     */
    public function todayBusiness($param)
    {
        $type = !empty($param['type']) ? $param['type'] : 1;
        $isSub = !empty($param['isSub']) ? $param['isSub'] : 0;
        $todayTime = getTimeByType('today');
        unset($param['type']);
        unset($param['isSub']);
        $request = $this->where($param, $type, $isSub, $todayTime);
        $businessModel = new \app\crm\model\Business();
        $data = $businessModel->getDataList($request);
        return $data;
    }
    
    /**
     * 分配给我的线索
     * @param $param
     *
     * @author      alvin guogaobo
     * @version     1.0 版本号
     * @since       2021/5/26 0026 10:32
     */
    public function followLeads($param)
    {
        $type = $param['type'] ?: 1;
        unset($param['type']);
        $request = $this->where($param, $type, '', '');
        $leadsModel = model('Leads');
        $data = $leadsModel->getDataList($request);
        return $data;
    }
    
    /**
     * 分配给我的客户
     * @param $param
     *
     * @author      alvin guogaobo
     * @version     1.0 版本号
     * @since       2021/5/26 0026 10:36
     */
    public function followCustomer($param)
    {
        $type = $param['type'] ?: 1;
        $isSub = $param['isSub'] ?: '';
        unset($param['type']);
        unset($param['isSub']);
        $request = $this->where($param, $type, $isSub, '');
        unset($param['user_id']);
        $customerModel = model('Customer');
        $data = $customerModel->getDataList($request);
        return $data;
    }
    
    /**
     * @param $param
     *
     * @author      alvin guogaobo
     * @version     1.0 版本号
     * @since       2021/5/26 0026 11:42
     */
    public function checkContract($param)
    {
        $type = $param['type'] ?: 1;
        unset($param['type']);
        $contractModel = model('Contract');
        $request = $this->whereCheck($param, $type);
        $request['isMessage'] = true;
        $data = $contractModel->getDataList($request);
        return $data;
    }
    
    /**
     * 待审核回款
     * @param $param
     *
     * @author      alvin guogaobo
     * @version     1.0 版本号
     * @since       2021/5/26 0026 11:48
     */
    public function checkReceivables($param){
        $type = $param['type'] ? : 1;
        $isSub = 1;
        unset($param['type']);
        $receivablesModel = model('Receivables');
        $request = $this->whereCheck($param, $type,$isSub);
        $request['isMessage'] = true;
        $data = $receivablesModel->getDataList($request);
        return $data;
    }
    
    /**
     *待审核发票
     *
     * @author      alvin guogaobo
     * @version     1.0 版本号
     * @since       2021/5/26 0026 13:35
     */
    public function checkInvoice($param){
        $type   = !empty($param['type'])  ? $param['type']  : 1;
        $isSub  = 2;
        # 清除与模型无关的数据
        unset($param['type']);
        $request = $this->whereCheck($param, $type,$isSub);
        $request['isMessage'] = true;
        $data = (new InvoiceLogic())->index($request);
        return $data;
    }
    /**
     * 审批查询条件
     * @param $param
     * @param $type
     *
     * @author      alvin guogaobo
     * @version     1.0 版本号
     * @since       2021/5/26 0026 11:43
     */
    public function whereCheck($param, $type,$isSub='')
    {
        if(empty($isSub)){
            switch ($type) {
                case '1' :
                    $param['check_status'] = ['lt', '2'];
                    $param['check_user_id'] = ['like', '%,' . $param['user_id'] . ',%'];
                    # 要提醒的合同ID
                    $contractIdArray = db('crm_dealt_relation')->where(['types' => ['eq', 'crm_contract'], 'user_id' => ['eq', $param['user_id']]])->column('types_id');
                    $param['contractIdArray'] = !empty($contractIdArray) ? $contractIdArray : -1;
                    break;
                case '2' :
                    $param['flow_user_id'] = ['like', '%,' . $param['user_id'] . ',%'];
                    break;
            }
        }else if($isSub==1){
            switch ($type) {
                case '1' :
                    # 待审核、审核中
                    $param['check_status'] = ['lt','2'];
                    $param['check_user_id'] = ['like','%,'.$param['user_id'].',%'];
                    # 要提醒的回款ID
                    $receivablesIdArray = db('crm_dealt_relation')->where(['types' => ['eq', 'crm_receivables'], 'user_id' => ['eq', $param['user_id']]])->column('types_id');
                    $param['receivablesIdArray'] = !empty($receivablesIdArray) ? $receivablesIdArray : -1;
                    break;
                case '2' :
                    # 全部
                    $param['flow_user_id'] = ['like','%,'.$param['user_id'].',%'];
                    break;
            }
        }elseif($isSub==2){
            switch ($type) {
                case '1' :
                    # 待审核、审核中
                    $param['check_status']  = ['lt', 2];
                    $param['check_user_id'] = ['like', '%,'. $param['user_id'] .',%'];
                    # 要提醒的发票ID
                    $invoiceIdArray = db('crm_dealt_relation')->where(['types' => ['eq', 'crm_invoice'], 'user_id' => ['eq', $param['user_id']]])->column('types_id');
                    $param['invoiceIdArray'] = !empty($invoiceIdArray) ? $invoiceIdArray : -1;
                    $param['dealt'] = 1;
                    break;
                case '2' :
                    # 全部
                    $param['flow_user_id'] = ['like', '%,'. $param['user_id'] .',%'];
                    $param['dealt'] = 1;
                    break;
            }
        }
        return $param;
    }
    
    /**
     * 负责人查询条件
     * @param $param
     *
     * @author      alvin guogaobo
     * @version     1.0 版本号
     * @since       2021/5/24 0024 09:46
     */
    public function where($param, $type, $isSub, $todayTime)
    {
        # 负责人
        $param['owner_user_id'] = !empty($isSub) ? ['in', getSubUserId(false, 0, $param['user_id'])] : ['eq', $param['user_id']];
        # 类型：1今日需联系；2已逾期；3已联系
        if (empty($isSub) && empty($todayTime)) {
            switch ($type) {
                case '1' :
                    $param['follow'] = [['neq', '已跟进'], null, 'or'];
                    $param['is_allocation'] = 1;
                    break;
                case '2' :
                    $param['follow'] = ['eq', '已跟进'];
                    $param['is_allocation'] = 1;
                    break;
            }
        } else {
            switch ($type) {
                case '1' :
                    $param['next_time'] = ['between', [$todayTime[0], $todayTime[1]]];
                    $param['is_dealt'] = ['neq', 1];
                    break;
                case '2' :
                    $param['next_time'] = ['between', [1, time()]];
                    $param['overdue'] = true;
                    $param['is_dealt'] = ['neq', 1];
                    break;
                case '3' :
                    $param['last_time'] = ['between', [$todayTime[0], $todayTime[1]]];
                    break;
            }
        }
        return $param;
    }
}
