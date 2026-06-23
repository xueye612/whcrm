<?php
// +----------------------------------------------------------------------
// | Description: 消息模块
// +----------------------------------------------------------------------
// | Author: Michael_xu | gengxiaoxu@5kcrm.com 
// +----------------------------------------------------------------------

namespace app\crm\controller;

use app\admin\controller\ApiCommon;
use app\crm\logic\InvoiceLogic;
use app\crm\logic\MessageLogic;
use think\Cache;
use think\cache\driver\Redis;
use think\Db;
use think\Hook;
use think\Request;

class Message extends ApiCommon
{
    /**
     * 用于判断权限
     * @permission 无限制
     * @allow 登录用户可访问
     * @other 其他根据系统设置
     **/
    public function _initialize()
    {
        $action = [
            'permission' => [],
            'allow'      => [
                'num',
                'todayleads',
                'todaycustomer',
                'todaybusiness',
                'followleads',
                'followcustomer',
                'checkcontract',
                'checkreceivables',
                'remindreceivablesplan',
                'endcontract',
                'remindcustomer',
                'checkinvoice',
                'visitcontract',
                'alldeal'
            ]
        ];
        Hook::listen('check_auth',$action);
        $request = Request::instance();
        $a = strtolower($request->action());
        if (!in_array($a, $action['permission'])) {
            parent::_initialize();
        }
    }
    
    /**
     * 系统通知
     *
     * @author Michael_xu
     * @return
     */
    public function index()
    {
        $messageModel = model('Message');
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['user_id'] = $userInfo['id'];
        $param['module_name'] = 'crm';
        $data = $messageModel->getDataList($param);
        return resultArray(['data' => $data]);
    }
    
    /**
     * 消息数
     *
     * @return \think\response\Json
     * @throws \think\exception\DbException
     */
    public function num()
    {
        $userInfo = $this->userInfo;
        $configDataModel = model('ConfigData');
        $configData = $configDataModel->getData() ?: [];
        $data = [];
        # 今日需联系线索
        $todayLeadsTime  = cache('todayLeadsTime'.$userInfo['id']);
        $todayLeadsCount = cache('todayLeadsCount'.$userInfo['id']);
        if (time() <= $todayLeadsTime) {
            $data['todayLeads'] = (int)$todayLeadsCount;
        } else {
            $todayLeads = $this->todayLeads(true);
            $todayLeadsData = $this->normalizeCountData($todayLeads);
            $data['todayLeads'] = !empty($todayLeadsData['dataCount']) ? $todayLeadsData['dataCount'] : 0;
            cache('todayLeadsCount'.$userInfo['id'], $data['todayLeads']);
            cache('todayLeadsTime'.$userInfo['id'], time() + 180);
        }
        # 今日需联系客户
        $todayCustomerTime  = cache('todayCustomerTime'.$userInfo['id']);
        $todayCustomerCount = cache('todayCustomerCount'.$userInfo['id']);
        if (time() <= $todayCustomerTime) {
            $data['todayCustomer'] = (int)$todayCustomerCount;
        } else {
            $todayCustomer = $this->todayCustomer(true);
            $todayCustomerData = $this->normalizeCountData($todayCustomer);
            $data['todayCustomer'] = !empty($todayCustomerData['dataCount']) ? $todayCustomerData['dataCount'] : 0;
            cache('todayCustomerCount'.$userInfo['id'], $data['todayCustomer']);
            cache('todayCustomerTime'.$userInfo['id'], time() + 180);
        }
        # 今日需联系商机
        $todayBusinessTime  = cache('todayBusinessTime'.$userInfo['id']);
        $todayBusinessCount = cache('todayBusinessCount'.$userInfo['id']);
        if (time() <= $todayBusinessTime) {
            $data['todayBusiness'] = (int)$todayBusinessCount;
        } else {
            $todayBusiness = $this->todayBusiness(true);
            $todayBusinessData = $this->normalizeCountData($todayBusiness);
            $data['todayBusiness'] = !empty($todayBusinessData['dataCount']) ? $todayBusinessData['dataCount'] : 0;
            cache('todayBusinessCount'.$userInfo['id'], $data['todayBusiness']);
            cache('todayBusinessTime'.$userInfo['id'], time() + 180);
        }
        # 分配给我的线索
        $followLeadsTime  = cache('followLeadsTime'.$userInfo['id']);
        $followLeadsCount = cache('followLeadsCount'.$userInfo['id']);
        if (time() <= $followLeadsTime) {
            $data['followLeads'] = (int)$followLeadsCount;
        } else {
            $followLeads = $this->followLeads(true);
            $followLeadsData = $this->normalizeCountData($followLeads);
            $data['followLeads'] = !empty($followLeadsData['dataCount']) ? $followLeadsData['dataCount'] : 0;
            cache('followLeadsCount'.$userInfo['id'], $data['followLeads']);
            cache('followLeadsTime'.$userInfo['id'], time() + 180);
        }
        # 分配给我的客户
        $followCustomerTime  = cache('followCustomerTime'.$userInfo['id']);
        $followCustomerCount = cache('followCustomerCount'.$userInfo['id']);
        if (time() <= $followCustomerTime) {
            $data['followCustomer'] = (int)$followCustomerCount;
        } else {
            $followCustomer = $this->followCustomer(true);
            $followCustomerData = $this->normalizeCountData($followCustomer);
            $data['followCustomer'] = !empty($followCustomerData['dataCount']) ? $followCustomerData['dataCount'] : 0;
            cache('followCustomerCount'.$userInfo['id'], $data['followCustomer']);
            cache('followCustomerTime'.$userInfo['id'], time() + 180);
        }
        # 待审核合同
        $checkContractTime  = cache('checkContractTime'.$userInfo['id']);
        $checkContractCount = cache('checkContractCount'.$userInfo['id']);
        if (time() <= $checkContractTime) {
            $data['checkContract'] = (int)$checkContractCount;
        } else {
            $checkContract = $this->checkContract(true);
            $checkContractData = $this->normalizeCountData($checkContract);
            $data['checkContract'] = !empty($checkContractData['dataCount']) ? $checkContractData['dataCount'] : 0;
            cache('checkContractCount'.$userInfo['id'], $data['checkContract']);
            cache('checkContractTime'.$userInfo['id'], time() + 180);
        }
        # 待审核回款
        $checkReceivablesTime  = cache('checkReceivablesTime'.$userInfo['id']);
        $checkReceivablesCount = cache('checkReceivablesCount'.$userInfo['id']);
        if (time() <= $checkReceivablesTime) {
            $data['checkReceivables'] = (int)$checkReceivablesCount;
        } else {
            $checkReceivables = $this->checkReceivables(true);
            $checkReceivablesData = $this->normalizeCountData($checkReceivables);
            $data['checkReceivables'] = !empty($checkReceivablesData['dataCount']) ? $checkReceivablesData['dataCount'] : 0;
            cache('checkReceivablesCount'.$userInfo['id'], $data['checkReceivables']);
            cache('checkReceivablesTime'.$userInfo['id'], time() + 180);
        }
        # 待审核发票
        $checkInvoiceTime  = cache('checkInvoiceTime'.$userInfo['id']);
        $checkInvoiceCount = cache('checkInvoiceCount'.$userInfo['id']);
        if (time() <= $checkInvoiceTime) {
            $data['checkInvoice'] = (int)$checkInvoiceCount;

        } else {
            $checkInvoice = $this->checkInvoice(true);
            $checkInvoiceData = $this->normalizeCountData($checkInvoice);
            $data['checkInvoice'] = !empty($checkInvoiceData['dataCount']) ? $checkInvoiceData['dataCount'] : 0;

            cache('checkInvoiceCount'.$userInfo['id'], $data['checkInvoice']);
            cache('checkInvoiceTime'.$userInfo['id'], time() + 180);
        }
        # 待回款提醒
        $remindReceivablesPlanTime  = cache('remindReceivablesPlanTime'.$userInfo['id']);
        $remindReceivablesPlanCount = cache('remindReceivablesPlanCount'.$userInfo['id']);
        if (time() <= $remindReceivablesPlanTime) {
            $data['remindReceivablesPlan'] = (int)$remindReceivablesPlanCount;
        } else {
            $remindReceivablesPlan = $this->remindReceivablesPlan(true);
            $remindReceivablesPlanData = $this->normalizeCountData($remindReceivablesPlan);
            $data['remindReceivablesPlan'] = !empty($remindReceivablesPlanData['dataCount']) ? $remindReceivablesPlanData['dataCount'] : 0;
            cache('remindReceivablesPlanCount'.$userInfo['id'], $data['remindReceivablesPlan']);
            cache('remindReceivablesPlanTime'.$userInfo['id'], time() + 180);
        }
        if (!empty($configData['visit_config'])) {
            # 待回访合同
            $visitContractTime  = cache('visitContractTime'.$userInfo['id']);
            $visitContractCount = cache('visitContractCount'.$userInfo['id']);
            if (time() <= $visitContractTime) {
                $data['returnVisitRemind'] = (int)$visitContractCount;
            } else {
                $visitContract = $this->visitContract(true);
                $visitContractData = $this->normalizeCountData($visitContract);
                $data['returnVisitRemind'] = !empty($visitContractData['dataCount']) ? $visitContractData['dataCount'] : 0;
                cache('visitContractCount'.$userInfo['id'], $data['returnVisitRemind']);
                cache('visitContractTime'.$userInfo['id'], time() + 180);
            }
        }
        # 即将到期合同
        if (!empty($configData['contract_config'])) {
            $endContractTime  = cache('endContractTime'.$userInfo['id']);
            $endContractCount = cache('endContractCount'.$userInfo['id']);
            if (time() <= $endContractTime) {
                $data['endContract'] = (int)$endContractCount;
            } else {
                $endContract = $this->endContract(true);
                $endContractData = $this->normalizeCountData($endContract);
                $data['endContract'] = !empty($endContractData['dataCount']) ? $endContractData['dataCount'] : 0;
                cache('endContractCount'.$userInfo['id'], $data['endContract']);
                cache('endContractTime'.$userInfo['id'], time() + 180);
            }
        }
        # 待进入公海提醒
        $pool = db('crm_customer_pool')->where(['status' => 1, 'remind_conf' => 1])->count();
        if (!empty($pool)) {
            $remindCustomerTime  = cache('remindCustomerTime'.$userInfo['id']);
            $remindCustomerCount = cache('remindCustomerCount'.$userInfo['id']);
            if (time() <= $remindCustomerTime) {
                $data['putInPoolRemind'] = (int)$remindCustomerCount;
            } else {
                $remindCustomer = $this->remindCustomer(true);
                $remindCustomerData = $this->normalizeCountData($remindCustomer);
                $data['putInPoolRemind'] = !empty($remindCustomerData['dataCount']) ? $remindCustomerData['dataCount'] : 0;
                cache('remindCustomerCount'.$userInfo['id'], $data['putInPoolRemind']);
                cache('remindCustomerTime'.$userInfo['id'], time() + 180);
            }
        }
        
        return resultArray(['data' => $data]);
    }

    protected function normalizeCountData($result)
    {
        if ($result instanceof \think\response\Json) {
            $payload = $result->getData();
            if (isset($payload['data']) && is_array($payload['data'])) {
                return $payload['data'];
            }
            return [];
        }
        if (is_array($result) && isset($result['data']) && is_array($result['data'])) {
            return $result['data'];
        }
        return is_array($result) ? $result : [];
    }
    
    /**
     * 今日需联系线索
     *
     * @param false $getCount
     * @return array|\think\response\Json
     */
    public function todayLeads($getCount = false)
    {
        $param  = $this->param;
        $userId = $this->userInfo['id'];
        $types  = isset($param['types']) ? $param['types'] : 'list';
        if (isset($param['types'])) {
            unset($param['types']);
        }
        $param['user_id'] = $userId;
        if ($getCount == true) $param['getCount'] = 1;
        $messageLogic= new MessageLogic();

        $data = $messageLogic->todayLeads($param);
      
        if ($types == 'list') return resultArray(['data' => $data]);
        
        return $data;
    }
    
    /**
     * 今日需联系客户
     *
     * @param string $getCount
     * @return \think\response\Json
     */
    public function todayCustomer($getCount = false)
    {
        $param = $this->param;
        $userId = $this->userInfo['id'];
        $types = isset($param['types']) ? $param['types'] : 'list';
        if ($getCount == true) {
            $param['getCount'] = 1;
        }
        if (isset($param['types'])) {
            unset($param['types']);
        }
        $param['user_id'] = $userId;
        $messageLogic= new MessageLogic();
        $data = $messageLogic->remindCustomer($param);
        if ($types == 'list') {
            return resultArray(['data' => $data]);
        }
        return $data;
    }
    
    /**
     * 今日需联系商机
     *
     * @param false $getCount
     * @return array|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function todayBusiness($getCount = false)
    {
        $param     = $this->param;
        $userId    = $this->userInfo['id'];
        $types     = isset($param['types']) ? $param['types'] : 'list';
        if (isset($param['types'])) {
            unset($param['types']);
        }
        if ($getCount == true) $param['getCount'] = 1;
        $messageLogic= new MessageLogic();
        $param['user_id'] = $userId;
        $data = $messageLogic->todayBusiness($param);
        
        if ($types == 'list') return resultArray(['data' => $data]);
        
        return $data;
    }
    
    /**
     * 分配给我的线索
     * @author Michael_xu
     * @return
     */
    public function followLeads($getCount = false)
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $types = isset($param['types']) ? $param['types'] : 'list';
        if (isset($param['types'])) {
            unset($param['types']);
        }
        if ($getCount == true) $param['getCount'] = 1;
        $param['user_id'] = $userInfo['id'];
        $messageLogic=new MessageLogic();
        $data = $messageLogic->followLeads($param);
        if ($types == 'list') {
            return resultArray(['data' => $data]);
        }
        return $data;
    }
    
    /**
     * 分配给我的客户
     * @author Michael_xu
     * @return
     */
    public function followCustomer($getCount = false)
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $types = isset($param['types']) ? $param['types'] : 'list';
        if ($getCount == true) {
            $param['getCount'] = 1;
        }
        if (isset($param['types'])) {
            unset($param['types']);
        }
        $messageLogic=new MessageLogic();
        $param['user_id'] = $userInfo['id'];
        $data = $messageLogic->followCustomer($param);
        if ($types == 'list') {
            return resultArray(['data' => $data]);
        }
        return $data;
    }
    
    /**
     * 待审核合同
     *
     * @param false $getCount
     * @return \think\response\Json
     */
    public function checkContract($getCount = false)
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $types = isset($param['types']) ? $param['types'] : 'list';
        if (isset($param['types'])) {
            unset($param['types']);
        }
        if ($getCount == true) {
            $param['getCount'] = 1;
        }
        $messageLogic=new MessageLogic();
        $param['user_id'] = $userInfo['id'];
        $data = $messageLogic->checkContract($param);
        if ($types == 'list') {
            return resultArray(['data' => $data]);
        }
        return $data;
    }
    
    /**
     * 待审核回款
     * @author Michael_xu
     * @return
     */
    public function checkReceivables($getCount = false)
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $types = isset($param['types']) ? $param['types'] : 'list';
        if (isset($param['types'])) {
            unset($param['types']);
        }
        if ($getCount == true) $param['getCount'] = 1;
        $messageLogic=new MessageLogic();
        $param['user_id'] = $userInfo['id'];
        $data = $messageLogic->checkReceivables($param);
        if ($types == 'list') {
            return resultArray(['data' => $data]);
        }
        return $data;
    }
    
    /**
     * 待审核发票
     *
     * @param InvoiceLogic $invoiceLogic
     * @return array|\think\response\Json
     * @throws \think\exception\DbException
     */
    public function checkInvoice($getCount = false)
    {
        $param  = $this->param;
        $userId = $this->userInfo['id'];
        $types  = isset($param['types']) ? $param['types'] : 'list';
        if ($getCount == true) $param['getCount'] = 1;
        # 清除与模型无关的数据
        if (isset($param['types'])) {
            unset($param['types']);
        }
        $param['user_id'] = $userId;
        $messageLogic=new MessageLogic();
        $data = $messageLogic->checkInvoice($param);
        
        if ($types == 'list') return resultArray(['data' => $data]);
        
        return $data;
    }
    
    /**
     * 待回款提醒
     * @author Michael_xu
     * @return
     */
    public function remindReceivablesPlan($getCount = false)
    {
        $param    = $this->param;
        $userInfo = $this->userInfo;
        $types    = isset($param['types']) ? $param['types'] : 'list';
        $type     = $param['type']  ? : 1;
        $isSub    = $param['isSub'] ? : '';
        if (isset($param['types'])) {
            unset($param['types']);
        }
        unset($param['type']);
        unset($param['isSub']);
        $receivablesPlanModel = model('ReceivablesPlan');
        
        if ($getCount == true) $param['getCount'] = 1;
        
        $param['owner_user_id'] = $userInfo['id'];
        if ($isSub) {
            $param['owner_user_id'] = ['in', getSubUserId(false)];
        }
        switch ($type) {
            case '1' :
                $param['receivables_id'] = 0;
                $param['check_status'] = ['lt', 2];
                $param['remind_date'] = ['elt', date('Y-m-d', time())];
                $param['return_date'] = ['egt', date('Y-m-d', time())];
                $param['types'] = 1;
                $param['is_dealt'] = 0;
                break;
            case '2' :
                $param['receivables_id'] = ['gt', 0];
                $param['check_status'] = 2;
                $param['dealt'] = 1;
                break;
            case '3' :
                $param['receivables_id'] = 0;
                $param['return_date'] = ['lt', date('Y-m-d', time())];
                break;
        }
        $data = $receivablesPlanModel->getDataList($param);
        if ($types == 'list') {
            return resultArray(['data' => $data]);
        }
        return $data;
    }
    
    /**
     * 即将到期合同
     * @author Michael_xu
     * @return
     */
    public function endContract($getCount = false)
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $types = isset($param['types']) ? $param['types'] : 'list';
        $type = $param['type'] ? : 1;
        $isSub = $param['isSub'] ? : '';
        if ($getCount == true) $param['getCount'] = 1;
        if (isset($param['types'])) {
            unset($param['types']);
        }
        unset($param['type']);
        unset($param['isSub']);
        $contractModel = model('Contract');
        $configModel = new \app\crm\model\ConfigData();
        $configInfo = $configModel->getData();
        $expireDay = $configInfo['contract_day'] ? : '7';
        // 合同到期不提醒
        if (empty($configInfo['contract_config'])) return resultArray(['data' => []]);
        $param['owner_user_id'] = $userInfo['id'];
        if ($isSub) {
            $param['owner_user_id'] = array('in',getSubUserId(false));
        }
        switch ($type) {
            case '1' :
                $param['end_time'] = array('between',array(date('Y-m-d',time()),date('Y-m-d',time()+86400*$expireDay)));
                $param['expire_remind'] = 0;
                break;
            case '2' : $param['end_time'] = array('lt',date('Y-m-d',time())); break;
        }
        $data = $contractModel->getDataList($param);
//        p($contractModel->getLastSql());
        if ($types == 'list') {
            return resultArray(['data' => $data]);
        }
        return $data;
    }
    
    /**
     * 待进入客户池
     * @author Michael_xu
     * @return
     */
    public function remindCustomer($getCount = false)
    {
        $customerModel = model('Customer');
        
        $param = $this->param;
        $userInfo = $this->userInfo;
        $types = isset($param['types']) ? $param['types'] : 'list';
        $isSub = $param['isSub'] ? : '';
        if ($getCount == true) $param['getCount'] = 1;
        if (isset($param['types'])) {
            unset($param['types']);
        }
        unset($param['type']);
        unset($param['isSub']);
        unset($param['deal_status']);
        unset($param['owner_user_id']);
        
        # 负责人
        $param['owner_user_id'] = !empty($isSub) ? ['in', getSubUserId(false, 0, $userInfo['id'])] : $userInfo['id'];
        
        # 是否提醒
        $data = [];
        $remind = db('crm_customer_pool')->where(['status' => 1, 'remind_conf' => 1])->count();
        if (!empty($remind)) {
            $whereData = $param ? : [];
            $whereData['is_remind'] = 1;
            $whereData['user_id'] = $userInfo['id'];
            $whereData['pool_remain'] = 0;
            $whereData['scene_id'] = db('admin_scene')->where(['types' => 'crm_customer','bydata' => 'me'])->value('scene_id');
            if ($isSub) {
                $whereData['scene_id'] = db('admin_scene')->where(['types' => 'crm_customer','bydata' => 'sub'])->value('scene_id');
            }
            $data = $customerModel->getDataList($whereData);
        }
        if ($types == 'list') {
            return resultArray(['data' => $data]);
        }
        return $data;
    }
    
    /**
     * 待回访合同
     *
     * @param false $getCount
     * @return array|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function visitContract($getCount = false)
    {
        $param     = $this->param;
        $userId    = $this->userInfo['id'];
        $isSub     = !empty($param['isSub']) ? $param['isSub'] : 0;
        $types     = !empty($param['types']) ? $param['types'] : '';
        if ($getCount == true) $param['getCount'] = 1;
        unset($param['isSub']);
        unset($param['types']);
        
        $param['is_visit']     = 0; # 未回访
        $param['check_status'] = 2; # 审核通过
        
        $contractModel = new \app\crm\model\Contract();
        
        # 负责人
        $param['owner_user_id'] = !empty($isSub) ? ['in', getSubUserId(false)] : $userId;
        
        $param['user_id'] = $userId;
        $data = $contractModel->getDataList($param);
        
        if ($types == 'list') return resultArray(['data' => $data]);
        
        return $data;
    }
    
    /**
     * 全部标记已处理
     *
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function allDeal()
    {
        $type   = $this->param['type'];
        $typeId = !empty($this->param['type_id']) ? $this->param['type_id'] : '';
        $isSub  = !empty($this->param['isSub'])   ? $this->param['isSub']   : 0;
        $userId = $this->userInfo['id'];
        
        if (empty($type)) return resultArray(['error' => '缺少模块类型参数']);
        
        # 获得今日开始和结束时间戳
        $todayTime = getTimeByType('today');
        
        # 处理今日需联系线索、客户、商机
        if (in_array($type, ['todayLeads', 'todayCustomer', 'todayBusiness'])) {
            # 负责人
            $where['owner_user_id'] = !empty($isSub) ? ['in', getSubUserId(false)] : $userId;
            # 下次联系时间
            $where['next_time'] = ['between', [$todayTime[0], $todayTime[1]]];
            # 是否已处理（联系）
            $where['is_dealt'] = 0;
            
            # 线索
            if ($type == 'todayLeads') {
                $leadsId = !empty($typeId) ? $typeId : Db::name('crm_leads')->where($where)->column('leads_id');
                Db::name('crm_leads')->whereIn('leads_id', $leadsId)->update([
                    'last_time' => time(),
                    'is_dealt'  => 1,
                    'follow'    => '已跟进'
                ]);
            }
            # 客户
            if ($type == 'todayCustomer') {
                $customerId = !empty($typeId) ? $typeId : Db::name('crm_customer')->where($where)->column('customer_id');
                Db::name('crm_customer')->whereIn('customer_id', $customerId)->update([
                    'last_time' => time(),
                    'is_dealt'  => 1,
                    'follow'    => '已跟进'
                ]);
            }
            # 商机
            if ($type == 'todayBusiness') {
                $businessId = !empty($typeId) ? $typeId : Db::name('crm_business')->where($where)->column('business_id');
                Db::name('crm_business')->whereIn('business_id', $businessId)->update([
                    'last_time' => time(),
                    'is_dealt'  => 1
                ]);
            }
        }
        
        # 处理分配给我的线索、客户
        if (in_array($type, ['followLeads', 'followCustomer'])) {
            $where['owner_user_id'] = $userId;
            $where['follow']        = [['neq','已跟进'], null, 'or'];
            $where['is_allocation'] = 1;
            
            # 线索
            if ($type == 'followLeads') {
                $leadsId = !empty($typeId) ? $typeId : Db::name('crm_leads')->where($where)->column('leads_id');
                Db::name('crm_leads')->whereIn('leads_id', $leadsId)->update(['follow' => '已跟进']);
            }
            # 客户
            if ($type == 'followCustomer') {
                $customerId = !empty($typeId) ? $typeId : Db::name('crm_customer')->where($where)->column('customer_id');
                Db::name('crm_customer')->whereIn('customer_id', $customerId)->update(['follow' => '已跟进']);
            }
        }
        
        # 处理待审核合同、回款、发票
        if (in_array($type, ['checkContract', 'checkReceivables', 'checkInvoice'])) {
            $where['check_status']  = ['lt','2'];
            $where['check_user_id'] = ['like',',%' . $userId . '%,'];
            
            # 合同
            if ($type == 'checkContract') {
                $contractId = !empty($typeId) ? $typeId : Db::name('crm_contract')->where($where)->column('contract_id');
                db('crm_dealt_relation')->where('user_id', $userId)->where('types', 'crm_contract')->whereIn('types_id', $contractId)->delete();
            }
            # 回款
            if ($type == 'checkReceivables') {
                $receivablesId = !empty($typeId) ? $typeId : Db::name('crm_receivables')->where($where)->column('receivables_id');
                db('crm_dealt_relation')->where('user_id', $userId)->where('types', 'crm_receivables')->whereIn('types_id', $receivablesId)->delete();
            }
            # 发票
            if ($type == 'checkInvoice') {
                $invoiceId = !empty($typeId) ? $typeId : Db::name('crm_invoice')->where($where)->column('invoice_id');
                db('crm_dealt_relation')->where('user_id', $userId)->where('types', 'crm_invoice')->whereIn('types_id', $invoiceId)->delete();
            }
            
        }
        
        # 处理到期合同
        if ($type == 'endContract') {
            $configModel = new \app\crm\model\ConfigData();
            $configInfo  = $configModel->getData();
            $expireDay   = $configInfo['contract_day'] ? : '7';
            
            $where['owner_user_id'] = $userId;
            $where['end_time']      = ['between', [date('Y-m-d',time()), date('Y-m-d',time()+86400*$expireDay)]];
            $where['expire_remind'] = 1;
            
            $contractId = !empty($typeId) ? $typeId : Db::name('crm_contract')->where($where)->column('contract_id');
            Db::name('crm_contract')->whereIn('contract_id', $contractId)->update(['expire_remind' => 0]);
        }
        
        # 处理待回访合同
        if ($type == 'returnVisitRemind') {
            $where['owner_user_id'] = !empty($isSub) ? ['in', getSubUserId(false)] : $userId; # 负责人
            $where['is_visit']      = 0; # 未回访
            $where['check_status']  = 2; # 审核通过
            
            $contractId = !empty($typeId) ? $typeId : Db::name('crm_contract')->where($where)->column('contract_id');
            Db::name('crm_contract')->whereIn('contract_id', $contractId)->update(['is_visit' => 1]);
        }
        
        # 处理待进入公海
        if ($type == 'putInPoolRemind') {
            if (!empty($typeId)) {
                Db::name('crm_customer')->whereIn('customer_id', $typeId)->update(['pool_remain' => 1]);
            } else {
                $poolConfig = db('crm_customer_pool')->where(['status' => 1, 'remind_conf' => 1])->count();
                if (!empty($poolConfig)) {
                    $whereData['page'] = 1;
                    $whereData['limit'] = 100;
                    $whereData['is_remind'] = 1;
                    $whereData['user_id'] = $userId;
                    $whereData['pool_remain'] = 0;
                    $whereData['scene_id'] = db('admin_scene')->where(['types' => 'crm_customer','bydata' => empty($isSub) ? 'me' : 'sub'])->value('scene_id');
                    $whereData['owner_user_id'] = !empty($isSub) ? ['in', getSubUserId(false, 0, $userId)] : $userId;
                    $poolCustomers = (new \app\crm\model\Customer())->getDataList($whereData);
                    $ids = [];
                    foreach ($poolCustomers['list'] AS $key => $value) {
                        if (!empty($value['customer_id'])) $ids[] = $value['customer_id'];
                    }
                    if (!empty($ids)) Db::name('crm_customer')->whereIn('customer_id', $ids)->update(['pool_remain' => 1]);
                }
            }
        }
        
        # 带回款提醒
        if ($type == 'remindReceivablesPlan') {
            $planId = [];
            if (!empty($typeId)) {
                $planId = $typeId;
            } else {
                $param['owner_user_id']  = $isSub ? ['in',getSubUserId(false)] : $userId;
                $param['receivables_id'] = 0;
                $param['check_status']   = ['lt', 2];
                $param['remind_date']    = ['elt', date('Y-m-d',time())];
                $param['return_date']    = ['egt', date('Y-m-d',time())];
                $param['types']          = 1;
                $param['page']           = 1;
                $param['limit']          = 1000;
                $receivablesPlanModel    = model('ReceivablesPlan');
                $data = $receivablesPlanModel->getDataList($param);
                foreach ($data['list'] AS $key => $value) {
                    $planId[] = $value['plan_id'];
                }
            }
            if (!empty($planId)) db('crm_receivables_plan')->whereIn('plan_id', $planId)->update(['is_dealt' => 1]);
        }
        
            cache::rm('todayLeadsCount'.$userId);
            cache::rm('todayCustomerCount'.$userId);
            cache::rm('todayBusinessCount'.$userId);
            cache::rm('followLeadsCount'.$userId);
            cache::rm('followCustomerCount'.$userId);
            cache::rm('checkContractCount'.$userId);
            cache::rm('checkReceivablesCount'.$userId);
            cache::rm('checkInvoiceCount'.$userId);
            cache::rm('remindReceivablesPlanCount'.$userId);
            cache::rm('visitContractCount'.$userId);
            cache::rm('endContractCount'.$userId);
            cache::rm('remindCustomerCount'.$userId);

            cache::rm('todayLeadsTime'.$userId);
            cache::rm('todayCustomerTime'.$userId);
            cache::rm('todayBusinessTime'.$userId);
            cache::rm('followLeadsTime'.$userId);
            cache::rm('followCustomerTime'.$userId);
            cache::rm('checkContractTime'.$userId);
            cache::rm('checkReceivablesTime'.$userId);
            cache::rm('checkInvoiceTime'.$userId);
            cache::rm('remindReceivablesPlanTime'.$userId);
            cache::rm('visitContractTime'.$userId);
            cache::rm('endContractTime'.$userId);
            cache::rm('remindCustomerTime'.$userId);
        return resultArray(['data' => '操作成功！']);
    }
}
