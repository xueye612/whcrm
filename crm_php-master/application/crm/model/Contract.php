<?php
// +----------------------------------------------------------------------
// | Description: 合同
// +----------------------------------------------------------------------
// | Author:  Michael_xu | gengxiaoxu@5kcrm.com
// +----------------------------------------------------------------------
namespace app\crm\model;

use app\admin\traits\FieldVerificationTrait;
use think\Db;
use app\admin\model\Common;
use app\admin\model\Message;

class Contract extends Common
{
    use FieldVerificationTrait;

	/**
     * 为了数据库的整洁，同时又不影响Model和Controller的名称
     * 我们约定每个模块的数据表都加上相同的前缀，比如CRM模块用crm作为数据表前缀
     */
	protected $name = 'crm_contract';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
	protected $autoWriteTimestamp = true;
	private $statusArr = ['0'=>'待审核','1'=>'审核中','2'=>'审核通过','3'=>'已拒绝','4'=>'已撤回','5'=>'未提交','6'=>'已作废', '7'=>'正常'];

    /**
     * [getDataList 合同list]
     *
     * @param $request
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
	public function getDataList($request)
    {
        if ($request instanceof \think\response\Json) {
            $request = method_exists($request, 'getData') ? $request->getData() : (array)$request;
        }
        if (!is_array($request)) {
            $request = [];
        }
        // if($param['check_status'] == 2){
        //     die('11');
        // }
    	$userModel = new \app\admin\model\User();
    	$structureModel = new \app\admin\model\Structure();
    	$fieldModel = new \app\admin\model\Field();
    	//回款
    	$receivablesModel = new \app\crm\model\Receivables();
        $search = $request['search'] ?? '';
    	$user_id = $request['user_id'] ?? 0;
        $teamOnly = !empty($request['team_only']);
    	$scene_id = (int)($request['scene_id'] ?? 0);
		$order_field = $request['order_field'] ?? '';
    	$order_type = $request['order_type'] ?? '';     	
    	$is_excel = $request['is_excel'] ?? 0; //导出
        $getCount = $request['getCount'] ?? 0;
        $contractIdArray = $request['contractIdArray'] ?? []; // 待办事项提醒参数
        $isMessage = !empty($request['isMessage']);
        $is_ledger_filter = $request['is_ledger_filter'] ?? 0;

		unset($request['scene_id']);
		unset($request['search']);
		unset($request['user_id']);
        unset($request['team_only']);
		unset($request['order_field']);	
		unset($request['order_type']);		  	
		unset($request['is_excel']);
        unset($request['getCount']);
        unset($request['contractIdArray']);
		unset($request['isMessage']);
        unset($request['is_ledger_filter']);

        $request = $this->fmtRequest( $request );
        $requestMap = $request['map'] ? : [];

		$sceneModel = new \app\admin\model\Scene();
        # getCount是代办事项传来的参数，代办事项不需要使用场景
        $sceneMap = [];
        if (empty($getCount) && empty($is_ledger_filter)) {
            if ($scene_id) {
                //自定义场景
                $sceneMap = $sceneModel->getDataById($scene_id, $user_id, 'contract') ? : [];
            } else {
                //默认场景
                $sceneMap = $sceneModel->getDefaultData('crm_contract', $user_id) ? : [];
            }
        }
        $searchWhere = [];
		if ($search || $search == '0') {
            //普通筛选
		    $searchWhere = function ($query) use ($search) {
		        $query->where(function ($query) use ($search){
                    $query->whereLike('customer.name', '%' . $search . '%');
                })->whereOr(function ($query) use ($search) {
                    $query->whereLike('contract.num', '%' . $search . '%');
                })->whereOr(function ($query) use ($search) {
                    $query->whereLike('contract.name', '%' . $search . '%');
                });
            };
		}
		$partMap = [];
        $teamMap=$requestMap['team_id'];
        //团队成员 高级筛选
        if($teamMap){
            $partMap= advancedQueryFormatForTeam($teamMap,'contract','contract_id');
            unset($requestMap['team_id']);
            $map = $requestMap ? array_merge($sceneMap, $requestMap) : $sceneMap;
        } else {
            $map = $requestMap ? array_merge($sceneMap, $requestMap) : $sceneMap;
        }
		//高级筛选
		$map = advancedQuery($map, 'crm', 'contract', 'index');
		$order = ['contract.update_time desc'];	
		$authMap = [];

			$a = 'index';
			if ($is_excel) $a = 'excelExport';
			$auth_user_ids = $userModel->getUserByPer('crm', 'contract', $a);
			if (isset($map['contract.owner_user_id'])) {
				if (!is_array($map['contract.owner_user_id'][1])) {
					$map['contract.owner_user_id'][1] = [$map['contract.owner_user_id'][1]];
				}				
				if (in_array($map['contract.owner_user_id'][0], ['neq', 'notin'])) {
					$auth_user_ids = array_diff($auth_user_ids, $map['contract.owner_user_id'][1]) ? : [];	//取差集	
				} else {
					$auth_user_ids = array_intersect($map['contract.owner_user_id'][1], $auth_user_ids) ? : [];	//取交集
				}
		        unset($map['contract.owner_user_id']);
		        $auth_user_ids = array_merge(array_unique(array_filter($auth_user_ids))) ? : ['-1'];
		        $authMap['contract.owner_user_id'] = array('in',$auth_user_ids); 
		    } else {
			    // 待办事项的待审核合同不一定是自己创建的
                if (!$isMessage) {
                    $authMapData = [];
                    $authMapData['auth_user_ids'] = $auth_user_ids;
                    $authMapData['user_id'] = $user_id;
                    if ($teamOnly) {
                        $authMap = function($query) use ($authMapData){
                            $query->where('contract.owner_user_id', $authMapData['user_id'])
                                ->whereOr('contract.ro_user_id',array('like','%,'.$authMapData['user_id'].',%'))
                                ->whereOr('contract.rw_user_id',array('like','%,'.$authMapData['user_id'].',%'));
                        };
                    } else {
                        $authMap = function($query) use ($authMapData){
                            $query->where('contract.owner_user_id',array('in',$authMapData['auth_user_ids']))
                                ->whereOr('contract.ro_user_id',array('like','%,'.$authMapData['user_id'].',%'))
                                ->whereOr('contract.rw_user_id',array('like','%,'.$authMapData['user_id'].',%'));
                        };
                    }
                }
		    }

		//合同签约人 | 与高级筛选冲突，加一个is_array判断
		if ($map['contract.order_user_id'] && !is_array($map['contract.order_user_id'][1])) {
			$map['contract.order_user_id'] = ['like','%,'.$map['contract.order_user_id'][1].',%'];
		}
		//列表展示字段
		$indexField = $fieldModel->getIndexField('crm_contract', $user_id, 1) ? : array('name');
		//人员类型
		$userField = $fieldModel->getFieldByFormType('crm_contract', 'user');
		$structureField = $fieldModel->getFieldByFormType('crm_contract', 'structure');  //部门类型
        $datetimeField = $fieldModel->getFieldByFormType('crm_contract', 'datetime'); //日期时间类型
        $booleanField = $fieldModel->getFieldByFormType('crm_contract', 'boolean_value'); //布尔值
        $dateIntervalField = $fieldModel->getFieldByFormType('crm_contract', 'date_interval'); // 日期区间类型字段
        $positionField = $fieldModel->getFieldByFormType('crm_contract', 'position'); // 地址类型字段
        $handwritingField = $fieldModel->getFieldByFormType('crm_contract', 'handwriting_sign'); // 手写签名类型字段
        $locationField = $fieldModel->getFieldByFormType('crm_contract', 'location'); // 定位类型字段
        $boxField = $fieldModel->getFieldByFormType('crm_contract', 'checkbox'); // 多选类型字段
        $floatField = $fieldModel->getFieldByFormType('crm_contract', 'floatnumber'); // 货币类型字段

        # 处理人员和部门类型的排序报错问题(前端传来的是包含_name的别名字段)
        $temporaryField = str_replace('_name', '', $order_field);
        if (in_array($temporaryField, $userField) || in_array($temporaryField, $structureField)) {
            $order_field = $temporaryField;
        }
		//排序
		if ($order_type && $order_field) {
            if ($order_field == 'ledger_count') {
                $ledgerTableName = Db::name('customer_ledger')->getTable();
                $order = "(SELECT COUNT(*) FROM {$ledgerTableName} WHERE contract_id = contract.contract_id) {$order_type}, contract.create_time asc";
            } else {
			    $order = $fieldModel->getOrderByFormtype('crm_contract','contract',$order_field,$order_type);
            }
		} else {
			$order = 'contract.update_time desc';
		}

		# 待办事项查询参数
        $dealtWhere = [];
		if (!empty($contractIdArray)) $dealtWhere['contract.contract_id'] = ['in', $contractIdArray];
				
		$readAuthIds = $userModel->getUserByPer('crm', 'contract', 'read');
        $updateAuthIds = $userModel->getUserByPer('crm', 'contract', 'update');
        $deleteAuthIds = $userModel->getUserByPer('crm', 'contract', 'delete');
        $dataCount = db('crm_contract')
            ->alias('contract')
            ->join('__CRM_CUSTOMER__ customer','contract.customer_id = customer.customer_id','LEFT')
            ->join('__CRM_BUSINESS__ business','contract.business_id = business.business_id','LEFT')
            ->join('__CRM_CONTACTS__ contacts','contract.contacts_id = contacts.contacts_id','LEFT')
            // ->join('__CRM_RECEIVABLES_PLAN__ plan','contract.contract_id = plan.contract_id','LEFT')
            ->where($searchWhere)->where($map)->where($partMap)->where($authMap)->where($dealtWhere)->group('contract.contract_id')->count('contract.contract_id');
        if (!empty($getCount) && $getCount == 1) {
            $data['dataCount'] = !empty($dataCount) ? $dataCount : 0;
			$contractMoney = $this->getContractMoney($map, $partMap, $authMap);
	        $data['extraData']['money'] = [
	            'contractMoney'   => $contractMoney['contractMoney'],    # 合同总金额
	            'receivedMoney'   => $contractMoney['receivablesMoney'], # 回款总金额
	            'unReceivedMoney' => $contractMoney['arrearsMoney']      # 未回款总金额
	        ];         
            return $data;
        }
        foreach ($indexField AS $kk => $vv) {
            if ($vv == 'contract.customer_name') unset($indexField[(int)$kk]);
            if ($vv == 'contract.business_name') unset($indexField[(int)$kk]);
        }

        # zjf 20210727 添加回款绑定合同    合同列表   状态为审核通过和正常
        if($requestMap['check_status'] == 2){
            $map['contract.check_status'] = ['in', [2,7]];
        }
        
		$list = db('crm_contract')
				->alias('contract')
				->join('__CRM_CUSTOMER__ customer','contract.customer_id = customer.customer_id','LEFT')		
				->join('__CRM_BUSINESS__ business','contract.business_id = business.business_id','LEFT')	
				->join('__CRM_CONTACTS__ contacts','contract.contacts_id = contacts.contacts_id','LEFT')	
				// ->join('__CRM_RECEIVABLES_PLAN__ plan','contract.contract_id = plan.contract_id','LEFT')	
				->join('CrmReceivables receivables','receivables.contract_id = contract.contract_id AND receivables.check_status = 2','LEFT')
                ->where($searchWhere)
                ->where($map)
				->where($partMap)
				->where($authMap)
                ->where($dealtWhere)
        		->limit($request['offset'], $request['length'])
        		->field(array_merge($indexField, [
					'customer.name' => 'customer_name',
					'business.name' => 'business_name',
					'contacts.name' => 'contacts_name',
					'ifnull(SUM(receivables.money), 0)' => 'done_money',
					'(contract.money - ifnull(SUM(receivables.money), 0))' => 'un_money',
				]))
        		->orderRaw($order)
        		->group('contract.contract_id')
        		->select();
        # 扩展数据
        $extraData = [];
        $contract_id_list = !empty($list) ? array_column($list, 'contract_id') : [];
        $extraList = db('crm_contract_data')->whereIn('contract_id', $contract_id_list)->select();
        foreach ($extraList AS $key => $value) {
            $extraData[$value['contract_id']][$value['field']] = $value['content'];
        }
        $grantData = getFieldGrantData($user_id);
        foreach ($grantData['crm_contract'] as $key => $value) {
            foreach ($value as $ke => $va) {
                if($va['maskType']!=0){
                    $fieldGrant[$ke]['maskType'] = $va['maskType'];
                    $fieldGrant[$ke]['form_type'] = $va['form_type'];
                    $fieldGrant[$ke]['field'] = $va['field'];
                }
            }
        }
        foreach ($list as $k=>$v) {
        	$list[$k]['create_user_id_info'] = isset($v['create_user_id']) ? $userModel->getUserById($v['create_user_id']) : [];
        	$list[$k]['owner_user_id_info'] = isset($v['owner_user_id']) ? $userModel->getUserById($v['owner_user_id']) : [];
        	$list[$k]['create_user_name'] = !empty($list[$k]['create_user_id_info']['realname']) ? $list[$k]['create_user_id_info']['realname'] : '';
            $list[$k]['owner_user_name'] = !empty($list[$k]['owner_user_id_info']['realname']) ? $list[$k]['owner_user_id_info']['realname'] : '';
			foreach ($userField as $key => $val) {
                $usernameField  = !empty($v[$val]) ? db('admin_user')->whereIn('id', stringToArray($v[$val]))->column('realname') : [];
                $list[$k][$val] = implode($usernameField, ',');
        	}
			foreach ($structureField as $key => $val) {
                $structureNameField = !empty($v[$val]) ? db('admin_structure')->whereIn('id', stringToArray($v[$val]))->column('name') : [];
                $list[$k][$val] = implode($structureNameField, ',');
        	}
            foreach ($datetimeField as $key => $val) {
                $list[$k][$val] = !empty($v[$val]) ? date('Y-m-d H:i:s', $v[$val]) : null;
            }
            foreach ($booleanField as $key => $val) {
                $list[$k][$val] = !empty($v[$val]) ? (string)$v[$val] : '0';
            }
            // 处理日期区间类型字段的格式
            foreach ($dateIntervalField AS $key => $val) {
                $list[$k][$val] = !empty($extraData[$v['contract_id']][$val]) ? json_decode($extraData[$v['contract_id']][$val], true) : null;
            }
            // 处理地址类型字段的格式
            foreach ($positionField AS $key => $val) {
                $list[$k][$val] = !empty($extraData[$v['contract_id']][$val]) ? json_decode($extraData[$v['contract_id']][$val], true) : null;
            }
            // 手写签名类型字段
            foreach ($handwritingField AS $key => $val) {
                $handwritingData = !empty($v[$val]) ? db('admin_file')->where('file_id', $v[$val])->value('file_path') : null;
                $list[$k][$val] = ['url' => !empty($handwritingData) ? getFullPath($handwritingData) : null];
            }
            // 定位类型字段
            foreach ($locationField AS $key => $val) {
                $list[$k][$val] = !empty($extraData[$v['contract_id']][$val]) ? json_decode($extraData[$v['contract_id']][$val], true) : null;
            }
            // 多选框类型字段
            foreach ($boxField AS $key => $val) {
                $list[$k][$val] = !empty($v[$val]) ? trim($v[$val], ',') : null;
            }
            // 货币类型字段
            foreach ($floatField AS $key => $val) {
                $list[$k][$val] = $v[$val]!='0.00' ? (string)$v[$val] : null;
            }
            //掩码相关类型字段
            foreach ($fieldGrant AS $key => $val){
                //掩码相关类型字段
                if ($val['maskType']!=0 && $val['form_type'] == 'mobile') {
                    $pattern = "/(1[3458]{1}[0-9])[0-9]{4}([0-9]{4})/i";
                    $rs = preg_replace($pattern, "$1****$2", $v[$val['field']]);
                    $list[$k][$val['field']] = !empty($v[$val['field']]) ? (string)$rs : null;
                } elseif ($val['maskType']!=0 && $val['form_type'] == 'email') {
                    $email_array = explode("@", $v[$val['field']]);
                    $prevfix = (strlen($email_array[0]) < 4) ? "" : substr($v[$val['field']], 0, 2); //邮箱前缀
                    $str = preg_replace('/([\d\w+_-]{0,100})@/', "***@", $v[$val['field']], -1, $count);
                    $rs = $prevfix . $str;
                    $list[$k][$val['field']] = !empty($v[$val['field']]) ?$rs: null;
                } elseif ($val['maskType']!=0 && in_array($val['form_type'],['position','floatnumber'])) {
                    $list[$k][$val['field']] = !empty($v[$val['field']]) ? (string)substr_replace($v[$val['field']], '*****',0,strlen($v[$val['field']])) : null;
                }
            }
        	$list[$k]['business_id_info']['business_id'] = $v['business_id'];
        	$list[$k]['business_id_info']['name'] = $v['business_name'];
        	$list[$k]['customer_id_info']['customer_id'] = $v['customer_id'];
        	$list[$k]['customer_id_info']['name'] = $v['customer_name'];
			$list[$k]['contacts_id_info']['contacts_id'] = $v['contacts_id'];
        	$list[$k]['contacts_id_info']['name'] = $v['contacts_name'];

            $list[$k]['check_status_info'] = $this->statusArr[$v['check_status']];
        	$moneyInfo = [];
        	$moneyInfo = $receivablesModel->getMoneyByContractId($v['contract_id']);
        	$list[$k]['unMoney'] = $moneyInfo['doneMoney'] ? : '0.00';
        	if ($list[$k]['un_money'] < 0) $list[$k]['un_money'] = '0.00';
			$planInfo = [];
			$planInfo = db('crm_receivables_plan')->where(['contract_id' => $v['contract_id']])->find();
			$list[$k]['receivables_id'] = $planInfo['receivables_id'] ? : '';
			$list[$k]['remind_date'] = $planInfo['remind_date'] ? : '';
			$list[$k]['return_date'] = $planInfo['return_date'] ? : '';
			//权限
        	$roPre = $userModel->rwPre($user_id, $v['ro_user_id'], $v['rw_user_id'], 'read');
        	$rwPre = $userModel->rwPre($user_id, $v['ro_user_id'], $v['rw_user_id'], 'update');
			$permission = [];
			$is_read = 0;
			$is_update = 0;
			$is_delete = 0;
			if (in_array($v['owner_user_id'],$readAuthIds) || $roPre || $rwPre) $is_read = 1;
			if (in_array($v['owner_user_id'],$updateAuthIds) || $rwPre) $is_update = 1;
			if (in_array($v['owner_user_id'],$deleteAuthIds)) $is_delete = 1;	        
	        $permission['is_read'] = $is_read;
	        $permission['is_update'] = $is_update;
	        $permission['is_delete'] = $is_delete;
	        $list[$k]['permission']	= $permission;
            # 下次联系时间
            $list[$k]['next_time'] = !empty($v['next_time']) ? date('Y-m-d H:i:s', $v['next_time']) : null;
            # 日期
            $list[$k]['create_time'] = !empty($v['create_time']) ? date('Y-m-d H:i:s', $v['create_time']) : null;
            $list[$k]['update_time'] = !empty($v['update_time']) ? date('Y-m-d H:i:s', $v['update_time']) : null;
            $list[$k]['last_time']   = !empty($v['last_time'])   ? date('Y-m-d H:i:s', $v['last_time'])   : null;
            $list[$k]['order_date'] = ($v['order_date']!='0000-00-00') ? $v['order_date'] : null;
            $list[$k]['start_time'] = ($v['start_time']!='0000-00-00') ? $v['start_time'] : null;
            $list[$k]['end_time'] = ($v['end_time']!='0000-00-00') ? $v['end_time'] : null;
            # 签约人姓名
            $orderNames = Db::name('admin_user')->whereIn('id', trim($v['order_user_id'], ','))->column('realname');
            $list[$k]['order_user_name'] = implode(',', $orderNames);
            unset($list[$k]['order_user_id']);
            $list[$k]['order_user_id_name'] = $v['order_user_id'];

            # 系统字段  负责人部门   zjf  20210726
            $list[$k]['owner_user_structure_name'] = $list[$k]['owner_user_id_info']['structure_name'];
        }
        $data = [];
        $data['list'] = $list;
        $data['dataCount'] = $dataCount ? : 0;
        $contractMoney = $this->getContractMoney($map, $partMap, $authMap);
        $data['extraData']['money'] = [
            'contractMoney'   => $contractMoney['contractMoney'],    # 合同总金额
            'receivedMoney'   => $contractMoney['receivablesMoney'], # 回款总金额
            'unReceivedMoney' => $contractMoney['arrearsMoney']      # 未回款
        ];

        return $data;
    }

    /**
     * 获取合同相关金额
     *
     * @param $map
     * @param $partMap
     * @param $authMap
     * @author fanqi
     * @date 2021-03-03
     * @return array
     */
    private function getContractMoney($map, $partMap, $authMap)
    {
        $contractMoney    = 0.00; # 合同总金额
        $receivablesMoney = 0.00; # 回款总金额
        $arrearsMoney     = 0.00; # 未回款总金额

        # 过滤审核状态参数，只查询审核成功的数据。
        foreach ($map AS $key => $value) {
            if ($key === 'contract.check_status') unset($map[$key]);
        }

        $data = db('crm_contract')
            ->alias('contract')
            ->join('__CRM_CUSTOMER__ customer','contract.customer_id = customer.customer_id','LEFT')
            ->join('__CRM_BUSINESS__ business','contract.business_id = business.business_id','LEFT')
            ->join('__CRM_CONTACTS__ contacts','contract.contacts_id = contacts.contacts_id','LEFT')
            ->join('__CRM_RECEIVABLES__ receivables','receivables.contract_id = contract.contract_id','LEFT')
            ->where('contract.check_status', 2)
            ->where($map)
            ->where($partMap)
            ->where($authMap)
            ->field(['contract.contract_id', 'contract.money AS contractMoney', 'receivables.money AS receivablesMoney', 'receivables.check_status AS receivablesStatus'])
            ->select();

        # 将同一合同下的回款进行整合
        $result = [];
        foreach ($data AS $key => $value) {
            # 同属于一个合同下的回款
            if (!empty($result[$value['contract_id']])) {
                if ($value['receivablesStatus'] == 2) $result[$value['contract_id']]['receivablesMoney'] += $value['receivablesMoney'];
                continue;
            }

            $result[$value['contract_id']] = [
                'contractMoney'    => $value['contractMoney'],
                'receivablesMoney' => $value['receivablesStatus'] == 2 ? $value['receivablesMoney'] : 0,
            ];
        }

        # 统计各金额总和
        foreach ($result AS $key => $value) {
            $contractMoney    += $value['contractMoney'];    # 合同金额
            $receivablesMoney += $value['receivablesMoney']; # 回款金额

            # 未回款金额
            if ($value['contractMoney'] > $value['receivablesMoney']) $arrearsMoney += $value['contractMoney'] - $value['receivablesMoney'];
        }

        return [
            'contractMoney'    => sprintf("%.2f", $contractMoney),
            'receivablesMoney' => sprintf("%.2f", $receivablesMoney),
            'arrearsMoney'     => sprintf("%.2f", $arrearsMoney)
        ];
    }

	//根据IDs获取数组
	public function getDataByStr($idstr)
	{
		$idArr = stringToArray($idstr);
		if (!$idArr) {
			return [];
		}
		$list = Db::name('CrmContract')->where(['contract_id' => ['in',$idArr]])->select();
		return $list;
	}
	
	/**
	 * 创建合同信息
	 * @author Michael_xu
	 * @param  
	 * @return                            
	 */	
	public function createData($param)
	{
        // 合同扩展表数据
        $contractData = [];

		$fieldModel = new \app\admin\model\Field();
		$userModel = new \app\admin\model\User();
		$productModel = new \app\crm\model\Product();

        // 数据验证
        $validateResult = $this->fieldDataValidate($param, $this->name, $param['create_user_id']);
        if (!empty($validateResult)) {
            $this->error = $validateResult;
            return false;
        }

        # 处理下次联系时间
        if (!empty($param['next_time'])) $param['next_time'] = strtotime($param['next_time']);

		// 处理部门、员工、附件、多选类型字段
		$arrFieldAtt = $fieldModel->getArrayField('crm_contract');
		foreach ($arrFieldAtt as $k=>$v) {
			$param[$v] = arrayToString($param[$v]);
		}
        // 处理日期（date）类型
        $dateField = $fieldModel->getFieldByFormType('crm_contract', 'date');
        if (!empty($dateField)) {
            foreach ($param AS $key => $value) {
                if (in_array($key, $dateField) && empty($value)) $param[$key] = null;
            }
        }
        // 处理手写签名类型
        $handwritingField = $fieldModel->getFieldByFormType('crm_contract', 'handwriting_sign');
        if (!empty($handwritingField)) {
            foreach ($param AS $key => $value) {
                if (in_array($key, $handwritingField)) {
                    $param[$key] = !empty($value['file_id']) ? $value['file_id'] : '';
                }
            }
        }
        // 处理地址、定位、日期区间、明细表格类型字段
        $positionField     = $fieldModel->getFieldByFormType($this->name, 'position');
        $locationField     = $fieldModel->getFieldByFormType($this->name, 'location');
        $dateIntervalField = $fieldModel->getFieldByFormType($this->name, 'date_interval');
        $detailTableField  = $fieldModel->getFieldByFormType($this->name, 'detail_table');
        foreach ($param AS $key => $value) {
            // 处理地址类型字段数据
            if (in_array($key, $positionField)) {
                if (!empty($value)) {
                    $contractData[] = [
                        'field'       => $key,
                        'content'     => json_encode($value, JSON_NUMERIC_CHECK),
                        'create_time' => time()
                    ];
                    $positionNames = array_column($value, 'name');
                    $param[$key] = implode(',', $positionNames);
                } else {
                    $param[$key] = '';
                }
            }
            // 处理定位类型字段数据
            if (in_array($key, $locationField)) {
                if (!empty($value)) {
                    $contractData[] = [
                        'field'       => $key,
                        'content'     => json_encode($value, JSON_NUMERIC_CHECK),
                        'create_time' => time()
                    ];
                    $param[$key] = $value['address'];
                } else {
                    $param[$key] = '';
                }
            }
            // 处理日期区间类型字段数据
            if (in_array($key, $dateIntervalField)) {
                if (!empty($value)) {
                    $contractData[] = [
                        'field'       => $key,
                        'content'     => json_encode($value, JSON_NUMERIC_CHECK),
                        'create_time' => time()
                    ];
                    $param[$key] = implode('_', $value);
                } else {
                    $param[$key] = '';
                }
            }
            // 处理明细表格类型字段数据
            if (in_array($key, $detailTableField)) {
                if (!empty($value)) {
                    $contractData[] = [
                        'field'       => $key,
                        'content'     => json_encode($value, JSON_NUMERIC_CHECK),
                        'create_time' => time()
                    ];
                    $param[$key] = $key;
                } else {
                    $param[$key] = '';
                }
            }
        }

		# 下单时间
        $param['order_date'] = !empty($param['order_date']) ? $param['order_date'] : date('Y-m-d H:i:s', time());
        if (empty($param['start_time'])) $param['start_time'] = null;
        if (empty($param['end_time']))   $param['end_time']   = null;

		$this->startTrans();
		if ($this->data($param)->allowField(true)->save()) {
			if ($param['product']) {
				//产品数据处理
		        $resProduct = $productModel->createObject('crm_contract', $param, $this->contract_id);	        
		        if ($resProduct == false) {
		        	$this->error = '产品添加失败。' . $productModel->getError();
					$this->rollback();
					return false;
		        }
			}
            //站内信
            $send_user_id = stringToArray($param['check_user_id']);
            if ($send_user_id && empty($param['check_status'])) {
				(new Message())->send(
					Message::CONTRACT_TO_DO,
					[
						'title' => $param['name'],
						'action_id' => $this->contract_id
					],
					$send_user_id
				);
            }

			$data = [];
			$data['contract_id'] = $this->contract_id;
			$this->commit();

			//修改记录
			updateActionLog($param['create_user_id'], 'crm_contract', $this->contract_id, '', '', '创建了合同');
            RecordActionLog($param['create_user_id'],'crm_contract','save',$param['name'],'','','新增了合同'.$param['name']);
            # 添加活动记录
            Db::name('crm_activity')->insert([
                'type'             => 2,
                'activity_type'    => 6,
                'activity_type_id' => $data['contract_id'],
                'content'          => $param['name'],
                'create_user_id'   => $param['create_user_id'],
                'update_time'      => time(),
                'create_time'      => time(),
                'customer_ids'     => ',' . $param['customer_id'] . ',',
                'contacts_ids'     => ',' . $param['contacts_id'] . ',',
                'business_ids'     => ',' . $param['business_id'] . ','
            ]);

            # 创建待办事项的关联数据
            $checkUserIds = db('crm_contract')->where('contract_id', $data['contract_id'])->value('check_user_id');
            $checkUserIdArray = stringToArray($checkUserIds);
            $dealtData = [];
            foreach ($checkUserIdArray AS $kk => $vv) {
                $dealtData[] = [
                    'types'    => 'crm_contract',
                    'types_id' => $data['contract_id'],
                    'user_id'  => $vv
                ];
            }
            if (!empty($dealtData)) db('crm_dealt_relation')->insertAll($dealtData);

            // 添加合同扩展数据
            array_walk($contractData, function (&$val) use ($data) {
                $val['contract_id'] = $data['contract_id'];
            });
            db('crm_contract_data')->insertAll($contractData);

			return $data;
		} else {
			$this->error = '添加失败';
			$this->rollback();
			return false;
		}			
	}

	/**
	 * 编辑合同主表信息
	 * @author Michael_xu
	 * @param  
	 * @return                            
	 */	
	public function updateDataById($param, $contract_id = '')
	{
        // 合同扩展表数据
        $contractData = [];

		$productModel = new \app\crm\model\Product();
		$userModel = new \app\admin\model\User();
		$dataInfo = db('crm_contract')->where(['contract_id' => $contract_id])->find();
		//过滤不能修改的字段
		$unUpdateField = ['create_user_id','is_deleted','delete_time'];
		foreach ($unUpdateField as $v) {
			unset($param[$v]);
		}
		$param['contract_id'] = $contract_id;
		$fieldModel = new \app\admin\model\Field();

        // 数据验证
        $validateResult = $this->fieldDataValidate($param, $this->name, $param['user_id'], $param['contract_id']);
        if (!empty($validateResult)) {
            $this->error = $validateResult;
            return false;
        }

        # 处理下次联系时间
        if (!empty($param['next_time'])) $param['next_time'] = strtotime($param['next_time']);

        $param['order_date'] = !empty($param['order_date']) ? $param['order_date'] : date('Y-m-d H:i:s', time());
        if (empty($param['start_time'])) $param['start_time'] = null;
        if (empty($param['end_time']))   $param['end_time']   = null;

		// 处理部门、员工、附件、多选类型字段
		$arrFieldAtt = $fieldModel->getArrayField('crm_contract');
		foreach ($arrFieldAtt as $k=>$v) {
            if (isset($param[$v])) $param[$v] = arrayToString($param[$v]);
		}
        // 处理日期（date）类型
        $dateField = $fieldModel->getFieldByFormType('crm_contract', 'date');
        if (!empty($dateField)) {
            foreach ($param AS $key => $value) {
                if (in_array($key, $dateField) && empty($value)) $param[$key] = null;
            }
        }
        // 处理手写签名类型
        $handwritingField = $fieldModel->getFieldByFormType('crm_contract', 'handwriting_sign');
        if (!empty($handwritingField)) {
            foreach ($param AS $key => $value) {
                if (in_array($key, $handwritingField)) {
                    $param[$key] = !empty($value['file_id']) ? $value['file_id'] : '';
                }
            }
        }
        // 处理地址、定位、日期区间、明细表格类型字段
        $positionField     = $fieldModel->getFieldByFormType($this->name, 'position');
        $locationField     = $fieldModel->getFieldByFormType($this->name, 'location');
        $dateIntervalField = $fieldModel->getFieldByFormType($this->name, 'date_interval');
        $detailTableField  = $fieldModel->getFieldByFormType($this->name, 'detail_table');
        foreach ($param AS $key => $value) {
            // 处理地址类型字段数据
            if (in_array($key, $positionField)) {
                if (!empty($value)) {
                    $contractData[] = [
                        'field'       => $key,
                        'content'     => json_encode($value, JSON_NUMERIC_CHECK),
                        'create_time' => time()
                    ];
                    $positionNames = array_column($value, 'name');
                    $param[$key] = implode(',', $positionNames);
                } else {
                    $param[$key] = '';
                }
            }
            // 处理定位类型字段数据
            if (in_array($key, $locationField)) {
                if (!empty($value)) {
                    $contractData[] = [
                        'field'       => $key,
                        'content'     => json_encode($value, JSON_NUMERIC_CHECK),
                        'create_time' => time()
                    ];
                    $param[$key] = $value['address'];
                } else {
                    $param[$key] = '';
                }
            }
            // 处理日期区间类型字段数据
            if (in_array($key, $dateIntervalField)) {
                if (!empty($value)) {
                    $contractData[] = [
                        'field'       => $key,
                        'content'     => json_encode($value, JSON_NUMERIC_CHECK),
                        'create_time' => time()
                    ];
                    $param[$key] = implode('_', $value);
                } else {
                    $param[$key] = '';
                }
            }
            // 处理明细表格类型字段数据
            if (in_array($key, $detailTableField)) {
                if (!empty($value)) {
                    $contractData[] = [
                        'field'       => $key,
                        'content'     => json_encode($value, JSON_NUMERIC_CHECK),
                        'create_time' => time()
                    ];
                    $param[$key] = $key;
                } else {
                    $param[$key] = '';
                }
            }
        }

		if ($this->update($param, ['contract_id' => $contract_id], true)) {
			//产品数据处理
	        $resProduct = $productModel->createObject('crm_contract', $param, $contract_id);			
			//修改记录
			updateActionLog($param['user_id'], 'crm_contract', $contract_id, $dataInfo, $param);
            RecordActionLog($param['user_id'], 'crm_contract', 'update',$dataInfo['name'], $dataInfo, $param);
            //站内信
            $send_user_id = stringToArray($param['check_user_id']);
            if ($send_user_id && empty($param['check_status'])) {
				(new Message())->send(
					Message::CONTRACT_TO_DO,
					[
						'title' => $param['name'],
						'action_id' => $contract_id
					],
					$send_user_id
				);
            }		
			$data = [];
			$data['contract_id'] = $contract_id;

			# 删除待办事项的关联数据
            db('crm_dealt_relation')->where(['types' => ['eq', 'crm_contract'], 'types_id' => ['eq', $data['contract_id']]])->delete();
            # 创建待办事项的关联数据
            $checkUserIds = db('crm_contract')->where('contract_id', $data['contract_id'])->value('check_user_id');
            $checkUserIdArray = stringToArray($checkUserIds);
            $dealtData = [];
            foreach ($checkUserIdArray AS $kk => $vv) {
                $dealtData[] = [
                    'types'    => 'crm_contract',
                    'types_id' => $data['contract_id'],
                    'user_id'  => $vv
                ];
            }
            if (!empty($dealtData)) db('crm_dealt_relation')->insertAll($dealtData);

            // 添加合同扩展数据
            db('crm_contract_data')->where('contract_id', $contract_id)->delete();
            array_walk($contractData, function (&$val) use ($contract_id) {
                $val['contract_id'] = $contract_id;
            });
            db('crm_contract_data')->insertAll($contractData);

			return $data;
		} else {
			$this->error = '编辑失败';
			return false;
		}					
	}

    /**
     * 合同数据
     *
     * @param string $id
     * @return Common|array|bool|\PDOStatement|string|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
   	public function getDataById($id = '', $userId = 0,$model='')
   	{   
   		$receivablesModel = new \app\crm\model\Receivables();
   		$userModel = new \app\admin\model\User();	
   		$map['contract_id'] = $id;
		$dataInfo = db('crm_contract')->where($map)->find();
		if (!$dataInfo) {
			$this->error = '暂无此数据';
			return false;
		}
        if(empty($model) && $model!='update'){
            $grantData = getFieldGrantData($userId);
            foreach ($grantData['crm_contract'] as $key => $value) {
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

		$dataInfo['create_user_info'] = isset($dataInfo['create_user_id']) ? $userModel->getUserById($dataInfo['create_user_id']) : [];
		$dataInfo['owner_user_id_info'] = isset($dataInfo['owner_user_id']) ? $userModel->getUserById($dataInfo['owner_user_id']) : []; 
		$dataInfo['create_user_name'] = !empty($dataInfo['create_user_info']['realname']) ? $dataInfo['create_user_info']['realname'] : '';
        $dataInfo['owner_user_name'] = !empty($dataInfo['owner_user_id_info']['realname']) ? $dataInfo['owner_user_id_info']['realname'] : '';
		$dataInfo['business_id_info'] = $dataInfo['business_id'] ? db('crm_business')->where(['business_id' => $dataInfo['business_id']])->field('business_id,name')->find() : [];
        $dataInfo['business_name'] = !empty($dataInfo['business_id_info']['name']) ? $dataInfo['business_id_info']['name'] : '';
		$dataInfo['customer_id_info'] = $dataInfo['customer_id'] ? db('crm_customer')->where(['customer_id' => $dataInfo['customer_id']])->field('customer_id,name')->find() : [];
        $dataInfo['customer_name'] = !empty($dataInfo['customer_id_info']['name']) ? $dataInfo['customer_id_info']['name'] : '';
        //回款金额
        $receivablesMoney = $receivablesModel->getMoneyByContractId($id);
        $dataInfo['receivablesMoney'] = $receivablesMoney ? : [];
        # 签约人姓名
        $orderNames = Db::name('admin_user')->whereIn('id', trim($dataInfo['order_user_id'], ','))->column('realname');
        $dataInfo['order_user_name'] = implode(',', $orderNames);
        # 处理时间根式
        $fieldModel = new \app\admin\model\Field();
        $datetimeField = $fieldModel->getFieldByFormType('crm_contract', 'datetime'); //日期时间类型
        foreach ($datetimeField as $key => $val) {
            $dataInfo[$val] = !empty($dataInfo[$val]) ? date('Y-m-d H:i:s', $dataInfo[$val]) : null;
        }
        $dataInfo['next_time']   = !empty($dataInfo['next_time'])   ? date('Y-m-d H:i:s', $dataInfo['next_time'])   : null;
        $dataInfo['create_time'] = !empty($dataInfo['create_time']) ? date('Y-m-d H:i:s', $dataInfo['create_time']) : null;
        $dataInfo['update_time'] = !empty($dataInfo['update_time']) ? date('Y-m-d H:i:s', $dataInfo['update_time']) : null;
        $dataInfo['last_time']   = !empty($dataInfo['last_time'])   ? date('Y-m-d H:i:s', $dataInfo['last_time'])   : null;
        // 字段授权
        if (!empty($userId)) {
            $grantData = getFieldGrantData($userId);
            $userLevel = isSuperAdministrators($userId);
            foreach ($dataInfo AS $key => $value) {
                if (!$userLevel && !empty($grantData['crm_contract'])) {
                    $status = getFieldGrantStatus($key, $grantData['crm_contract']);

                    # 查看权限
                    if ($status['read'] == 0) unset($dataInfo[$key]);
                }
            }
            if (!$userLevel && !empty($grantData['crm_contract'])) {
                # 客户名称
                $customerStatus = getFieldGrantStatus('customer_id', $grantData['crm_contract']);
                if ($customerStatus['read'] == 0) {
                    $dataInfo['customer_name'] = '';
                    $dataInfo['customer_id_info'] = [];
                }
                # 回款金额
                $doneMoneyStatus = getFieldGrantStatus('done_money', $grantData['crm_contract']);
                if ($doneMoneyStatus['read'] == 0) $dataInfo['receivablesMoney'] = '';
            }
        }
		return $dataInfo;
   	}

	/**
     * [合同转移]
     * @author Michael_xu
     * @param ids 合同ID数组
     * @param owner_user_id 变更负责人
     * @param is_remove 1移出，2转为团队成员
     * @return            
     */	
    public function transferDataById($ids, $owner_user_id, $type = 1, $is_remove)
    {
	    $settingModel = new \app\crm\model\Setting();  
	    $errorMessage = [];  	
    	foreach ($ids as $id) {
    		$contractInfo = [];
    		$contractInfo = db('crm_contract')->where(['contract_id' => $id])->find();
//			if (in_array($contractInfo['check_status'],['0','1'])) {
//	            $errorMessage[] = '合同：'.$contractInfo['name'].'"转移失败，错误原因：审批中，无法转移；';
//	            continue;
//	        }
			//团队成员
	        $teamData = [];
            $teamData['type'] = $type; //权限 1只读2读写
            $teamData['user_id'] = [$contractInfo['owner_user_id']]; //协作人
            $teamData['types'] = 'crm_contract'; //类型
            $teamData['types_id'] = $id; //类型ID
            $teamData['is_del'] = ($is_remove == 1) ? 1 : '';
            $res = $settingModel->createTeamData($teamData);	        

			$data = [];
	        $data['owner_user_id'] = $owner_user_id;
	        $data['update_time'] = time(); 
	        if (!db('crm_contract')->where(['contract_id' => $id])->update($data)) {
				$errorMessage[] = '合同：'.$contractInfo['name'].'"转移失败，错误原因：数据出错；';
	            continue;				      	
	        } else {
                $contractArray = [];
                $teamContract = db('crm_contract')->field(['owner_user_id', 'ro_user_id', 'rw_user_id'])->where('contract_id', $id)->find();
                if (!empty($teamContract['ro_user_id'])) {
                    $contractRo = arrayToString(array_diff(stringToArray($teamContract['ro_user_id']), [$teamContract['owner_user_id']]));
                    $contractArray['ro_user_id'] = $contractRo;
                }
                if (!empty($teamContract['rw_user_id'])) {
                    $contractRo = arrayToString(array_diff(stringToArray($teamContract['rw_user_id']), [$teamContract['owner_user_id']]));
                    $contractArray['rw_user_id'] = $contractRo;
                }
                db('crm_contract')->where('contract_id', $id)->update($contractArray);
            }
    	}
    	if ($errorMessage) {
			return $errorMessage;
    	} else {
    		return true;
    	}
    }

	/**
	 * 根据对象ID 获取该年各个月合同金额
	 * @return [year] [哪一年]
	 * @return [owner_user_id] [哪个员工]
	 * @return [start_time] [开始时间]
	 * @return [end_time] [结束时间]
	 */
	public function getDataByUserId($param)
	{	
		if ($param['obj_type']) {
			if ($param['obj_type'] == 1) { //部门
				$userModel = new \app\admin\model\User();
			    $str = $userModel->getSubUserByStr($param['obj_id'], 1) ? : ['-1'];
				$map['owner_user_id'] = array('in',$str); 
			} else { //员工
				$map['owner_user_id'] = $param['obj_id']; 
			}
		}
		//审核状态
		$start = date('Y-m-d',$param['start_time']);
		$stop = date('Y-m-d',$param['end_time']);
		$map['check_status'] = 2;
		$data = $this->where($map)->where(['order_date' => ['between',[$start, $stop]]])->sum('money');
		return $data;
	}

    /**
     * 获取系统信息
     *
     * @param $id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
	public function getSystemInfo($id)
    {
        # 合同
        $contract = Db::name('crm_contract')->where('contract_id', $id)->find();
        # 创建人
        $realname = Db::name('admin_user')->where('id', $contract['create_user_id'])->value('realname');
        
        # zjf   20210726
        $userModel   = new \app\admin\model\User();
        $ownerUserInfo = $userModel->getUserById($contract['owner_user_id']);
        # 负责人部门
        $ownerStructureName = $ownerUserInfo['structure_name'];

        # 负责人
        $ownerUserName = $ownerUserInfo['realname'];
        
        # 回款
        $receivablesModel = new Receivables();
        $receivables = $receivablesModel->getMoneyByContractId($id);

        return [
            'create_user_id' => $realname,
            'owner_user_id' => $ownerUserName,
            'create_time' => date('Y-m-d H:i:s', $contract['create_time']),
            'update_time' => date('Y-m-d H:i:s', $contract['update_time']),
            'last_time' => !empty($contract['last_time']) ? date('Y-m-d H:i:s', $contract['last_time']) : '',
            'done_money' => $receivables['doneMoney'],
            'un_money' => $receivables['unMoney'],
            'owner_user_structure_name' => $ownerStructureName
        ];
    }

    /**
     * 拷贝合同
     *
     * @param $contractId
     * @param $userId
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function copy($contractId, $userId)
    {
        $targetContract = Db::name('crm_contract')->where('contract_id', $contractId)->find();
        $targetProduct  = Db::name('crm_contract_product')->where('contract_id', $contractId)->select();

        if (empty($targetContract['contract_id'])) return false;

        # 删除主键
        unset($targetContract['contract_id']);

        Db::startTrans();
        try{
            # 合同数据
            $targetContract['num']            = 'WKCrm#contract#num#' . date('YmdHis');
            $targetContract['name']           = 'WKCrm#contract#name#'. date('YmdHis');
            $targetContract['create_user_id'] = $userId;
            $targetContract['owner_user_id']  = $userId;
            $targetContract['create_time']    = time();
            $targetContract['update_time']    = time();
            $targetContract['is_visit']       = 2;
            $targetContract['expire_remind']  = 1;
            if (in_array($targetContract['check_status'], [1, 2, 3, 4])) {
                $checkUserId = trim($targetContract['check_user_id'], ',');
                $flowUserId  = trim($targetContract['flow_user_id'],  ',');
                $symbol      = !empty($checkUserId) ? ',' : '';
                $targetContract['check_user_id'] = ',' . $checkUserId . $symbol . $flowUserId . ',';
                $targetContract['check_status']  = 0;
            }
            Db::name('crm_contract')->insert($targetContract);
            $newContractId = Db::name('crm_contract')->getLastInsID();

            # 产品数据
            $productData = [];
            foreach ($targetProduct AS $key => $value) {
                $productData[] = [
                    'contract_id' => $newContractId,
                    'product_id'  => $value['product_id'],
                    'price'       => $value['price'],
                    'sales_price' => $value['sales_price'],
                    'num'         => $value['num'],
                    'discount'    => $value['discount'],
                    'subtotal'    => $value['subtotal'],
                    'unit'        => $value['unit']
                ];
            }
            if (!empty($productData)) Db::name('crm_contract_product')->insertAll($productData);

            Db::commit();

            return true;
        } catch (\Exception $e) {
            Db::rollback();

            return false;
        }
    }
}
