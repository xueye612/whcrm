<?php
// +----------------------------------------------------------------------
// | Description: 回款计划计划
// +----------------------------------------------------------------------
// | Author:  Michael_xu | gengxiaoxu@5kcrm.com
// +----------------------------------------------------------------------
namespace app\crm\model;

use app\admin\traits\FieldVerificationTrait;
use think\composer\LibraryInstaller;
use think\Db;
use app\admin\model\Common;
use app\crm\model\Contract as ContractModel;
use think\Request;
use think\Validate;

class ReceivablesPlan extends Common
{
    use FieldVerificationTrait;
	/**
     * 为了数据库的整洁，同时又不影响Model和Controller的名称
     * 我们约定每个模块的数据表都加上相同的前缀，比如CRM模块用crm作为数据表前缀
     */
    protected $name = 'crm_receivables_plan';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $autoWriteTimestamp = true;
    protected $statusArr = [0 =>'待回款', 1=>'完成', 2=>'部分回款', 3=>'已作废', 4=>'已逾期', 5=>'待生效'];
    
    /**
     * [getDataList 回款计划list]
     * @param     [string]                   $map [查询条件]
     * @param     [number]                   $page     [当前页数]
     * @param     [number]                   $limit    [每页数量]
     * @param     [string]                   $types    1 未使用的回款计划
     * @return    [array]                    [description]
     * @author Michael_xu
     */
    public function getDataList($request)
    {
        $userModel = new \app\admin\model\User();
        $fieldModel = new \app\admin\model\Field();
        $search = $request['search'];
        $user_id = $request['user_id'];
        $scene_id = (int)$request['scene_id'];
        $check_status = $request['check_status'];
        $types = $request['types'];
        $getCount = $request['getCount'];
        $status = isset($request['status']) ? $request['status'] : 1;
        $dealt = $request['dealt']; # 待办事项
        $order_field = $request['order_field'];
        $order_type = $request['order_type'];
        $is_excel = $request['is_excel'];
        unset($request['scene_id']);
        unset($request['search']);
        unset($request['user_id']);
        unset($request['check_status']);
        unset($request['types']);
        unset($request['getCount']);
        unset($request['status']);
        unset($request['dealt']);
        unset($request['order_field']);
        unset($request['order_type']);
        unset($request['is_excel']);
        
        $request = $this->fmtRequest($request);
        $map = $request['map'] ?: [];
        $sceneModel = new \app\admin\model\Scene();
        $sceneMap = [];
        if (empty($getCount)) {
            if ($scene_id) {
                //自定义场景
                $sceneMap = $sceneModel->getDataById($scene_id, $user_id, 'receivables_plan') ?: [];
            } else {
                //默认场景
                $sceneMap = $sceneModel->getDefaultData('crm_receivables_plan', $user_id) ?: [];
            }
        }
        if (isset($map['search'])) {
            
            //普通筛选
            $map['name'] = ['like', '%' . $map['search'] . '%'];
            unset($map['search']);
        } else {
            // 高级筛选
            $map = advancedQuery($map, 'crm', 'receivables_plan', 'index');
        }
        if ($map['receivables_plan.owner_user_id']) {
            $map['contract.owner_user_id'] = $map['receivables_plan.owner_user_id'];
            unset($map['receivables_plan.owner_user_id']);
        }
        $whereData = [];
        if ($check_status) {
            unset($map['receivables_plan.check_status']);
            if ($check_status == 2) {
                $map['receivables.check_status'] = $check_status;
            } else {
                unset($map['receivables_plan.receivables_id']);
                $data = [];
                $data['check_status'] = $check_status;
                $whereData = function ($query) use ($data) {
                    $query->where(['receivables_plan.receivables_id' => ['eq', 0]])
                        ->whereOr(['receivables.check_status' => $data['check_status']]);
                };
            }
        }
        // @ymob 2019-12-11 17:51:54
        // 修改回款时，回款计划选项列表应该包含该回款对应的回款计划 不能过滤
        // 将types改为status，status：可用的回款计划 fanqi
        if (empty($dealt)) { # 不是待办事项
            if ($request['map']['receivables_id']) {
                if (!empty($request['map']['contract_id'])) {
                    $map = " 
                    (`receivables_plan`.`contract_id` = {$request['map']['contract_id']} AND `receivables_plan`.`receivables_id` = {$request['map']['receivables_id']}) 
                    OR 
                    (`receivables_plan`.`contract_id` = {$request['map']['contract_id']} AND `receivables_plan`.`receivables_id` = 0)
                ";
                } else {
                    $map = " (`receivables_plan`.`receivables_id` = 0 )";
                }
            } elseif ($status == 0) {
                $map['receivables_plan.receivables_id'] = 0;
            }
        }
        
        
        $dataCount = db('crm_receivables_plan')
            ->alias('receivables_plan')
            ->join('__CRM_CONTRACT__ contract', 'receivables_plan.contract_id = contract.contract_id', 'LEFT')
            ->join('__CRM_CUSTOMER__ customer', 'receivables_plan.customer_id = customer.customer_id', 'LEFT')
            ->join('__CRM_RECEIVABLES__ receivables', 'receivables_plan.plan_id = receivables.plan_id', 'LEFT')
            ->where($map)
            ->where($sceneMap)
            ->where($whereData)
            ->count('receivables_plan.plan_id');
        
        $indexField = $fieldModel->getIndexField('crm_receivables_plan', $user_id, 1) ?: array('name'); // 列表展示字段
        $userField = $fieldModel->getFieldByFormType('crm_receivables_plan', 'user'); // 人员类型
        $structureField = $fieldModel->getFieldByFormType('crm_receivables_plan', 'structure'); // 部门类型
        $datetimeField = $fieldModel->getFieldByFormType('crm_receivables_plan', 'datetime'); // 日期时间类型
        $booleanField = $fieldModel->getFieldByFormType('crm_receivables_plan', 'boolean_value'); // 布尔值类型字段
        $dateIntervalField = $fieldModel->getFieldByFormType('crm_receivables_plan', 'date_interval'); // 日期区间类型字段
        $positionField = $fieldModel->getFieldByFormType('crm_receivables_plan', 'position'); // 地址类型字段
        $handwritingField = $fieldModel->getFieldByFormType('crm_receivables_plan', 'handwriting_sign'); // 手写签名类型字段
        if (!empty($getCount) && $getCount == 1) {
            $data['dataCount'] = !empty($dataCount) ? $dataCount : 0;
            return $data;
        }
        # 处理人员和部门类型的排序报错问题(前端传来的是包含_name的别名字段)
        $temporaryField = str_replace('_name', '', $order_field);
        if (in_array($temporaryField, $userField) || in_array($temporaryField, $structureField)) {
            $order_field = $temporaryField;
        }
        # 排序
        if ($order_type && $order_field) {
            $order = $fieldModel->getOrderByFormtype('crm_receivables_plan', 'receivables_plan', $order_field, $order_type);
        } else {
            $order = 'receivables_plan.num asc';
        }
        $list = db('crm_receivables_plan')
            ->alias('receivables_plan')
            ->join('__CRM_CONTRACT__ contract', 'receivables_plan.contract_id = contract.contract_id', 'LEFT')
            ->join('__CRM_CUSTOMER__ customer', 'receivables_plan.customer_id = customer.customer_id', 'LEFT')
            ->join('__CRM_RECEIVABLES__ receivables', 'receivables_plan.plan_id = receivables.plan_id', 'LEFT')
            ->limit($request['offset'], $request['length'])
//            ->field(array_merge($indexField, [
//                'customer.name' => 'customer_name',
//                'receivables.receivables_id' => 'receivables_id',
//                'receivables.check_status' => 'check_status',
//                'contract.num ' => 'contract_name',
//                'ifnull(SUM(receivables_plan.money), 0)' => 'done_money',//计划回款总金额
//                '(ifnull(SUM(receivables.money), 0)-SUM( real_money))' => 'un_money',//未回款总金额
//                'SUM(real_money) AS real_money'//实际回款总金额
//            ]))
            ->field('receivables_plan.*,customer.name as customer_name,contract.num as contract_name,receivables.receivables_id,receivables.check_status')
            ->where($map)
            ->where($sceneMap)
            ->where($whereData)
//            ->group('receivables_plan.contract_id')
            ->orderRaw($order)
            ->select();
        $grantData = getFieldGrantData($user_id);
        foreach ($grantData['crm_visit_'] as $key => $value) {
            foreach ($value as $ke => $va) {
                if($va['maskType']!=0){
                    $fieldGrant[$ke]['maskType'] = $va['maskType'];
                    $fieldGrant[$ke]['form_type'] = $va['form_type'];
                    $fieldGrant[$ke]['field'] = $va['field'];
                }
            }
        }
        $readAuthIds = $userModel->getUserByPer('crm', 'receivables_plan', 'read');
        $updateAuthIds = $userModel->getUserByPer('crm', 'receivables_plan', 'update');
        $deleteAuthIds = $userModel->getUserByPer('crm', 'receivables_plan', 'delete');
        $real_money=0.00;
        $receivedMoney=0.00;
        $unReceivedMoney=0.00;
        foreach ($list as $k => $v) {
            $list[$k]['create_user_id_info'] = isset($v['create_user_id']) ? $userModel->getUserById($v['create_user_id']) : [];
            $list[$k]['owner_user_id_info'] = isset($v['owner_user_id']) ? $userModel->getUserById($v['owner_user_id']) : [];
            $list[$k]['create_user_name'] = !empty($list[$k]['create_user_id_info']['realname']) ? $list[$k]['create_user_id_info']['realname'] : '';
            $list[$k]['owner_user_name'] = !empty($list[$k]['owner_user_id_info']['realname']) ? $list[$k]['owner_user_id_info']['realname'] : '';
            foreach ($userField as $key => $val) {
                if (in_array($val, $indexField)) {
                    $usernameField = !empty($v[$val]) ? db('admin_user')->whereIn('id', stringToArray($v[$val]))->column('realname') : [];
                    $list[$k][$val] = implode($usernameField, ',');
                }
            }
            # 部门类型字段
            foreach ($structureField as $key => $val) {
                if (in_array($val, $indexField)) {
                    $structureNameField = !empty($v[$val]) ? db('admin_structure')->whereIn('id', stringToArray($v[$val]))->column('name') : [];
                    $list[$k][$val] = implode($structureNameField, ',');
                }
            }
            # 日期时间类型字段
            foreach ($datetimeField as $key => $val) {
                $list[$k][$val] = !empty($v[$val]) ? date('Y-m-d H:i:s', $v[$val]) : null;
            }
            // 布尔值类型字段
            foreach ($booleanField as $key => $val) {
                $list[$k][$val] = !empty($v[$val]) ? (string)$v[$val] : '0';
            }
            // 处理日期区间类型字段的格式
            foreach ($dateIntervalField as $key => $val) {
                $list[$k][$val] = !empty($extraData[$v['customer_id']][$val]) ? json_decode($extraData[$v['customer_id']][$val], true) : null;
            }
            // 处理地址类型字段的格式
            foreach ($positionField as $key => $val) {
                $list[$k][$val] = !empty($extraData[$v['customer_id']][$val]) ? json_decode($extraData[$v['customer_id']][$val], true) : null;
            }
            // 手写签名类型字段
            foreach ($handwritingField as $key => $val) {
                $handwritingData = !empty($v[$val]) ? db('admin_file')->where('file_id', $v[$val])->value('file_path') : null;
                $list[$k][$val] = ['url' => !empty($handwritingData) ? getFullPath($handwritingData) : null
                ];
            }
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
            // 状态
            if(strtotime($v['return_date'])>strtotime(date("Y-m-d",strtotime("+1 day")))){
                $list[$k]['status']=4;
            }
            # 时间格式
            $list[$k]['create_time']=!empty($v['create_time'])?date('Y-m-d H:i:s',$v['create_time']):null;
            $list[$k]['update_time']=!empty($v['update_time'])?date('Y-m-d H:i:s',$v['update_time']):null;
            # 权限
            $roPre = $userModel->rwPre($user_id, $v['ro_user_id'], $v['rw_user_id'], 'read');
            $rwPre = $userModel->rwPre($user_id, $v['ro_user_id'], $v['rw_user_id'], 'update');
            $permission = [];
            $is_read = 0;
            $is_update = 0;
            $is_delete = 0;
            if (in_array($v['owner_user_id'], $readAuthIds) || $roPre || $rwPre) $is_read = 1;
            if (in_array($v['owner_user_id'], $updateAuthIds) || $rwPre) $is_update = 1;
            if (in_array($v['owner_user_id'], $deleteAuthIds)) $is_delete = 1;
            $permission['is_read'] = $is_read;
            $permission['is_update'] = $is_update;
            $permission['is_delete'] = $is_delete;
            $list[$k]['permission'] = $permission;
            $real_money += $v['real_money'];   //实际回款总金额
            $receivedMoney += $v['done_money'];// 回款总金额
            $unReceivedMoney += $v['un_money'];      // 未回款
        }
        
        $data = [];
        $data['list'] = $list;
        $data['dataCount'] = $dataCount ?: 0;
        $data['extraData']['money'] = [
            'real_money' => $real_money,    # 实际回款总金额
            'receivedMoney' => $receivedMoney, # 回款总金额
            'unReceivedMoney' => $unReceivedMoney      # 未回款
        ];
        return $data ?: [];
    }
    

	/**
	 * 创建回款计划信息
	 * @author Michael_xu
	 * @param  
	 * @return                            
	 */
	public function createData($param)
	{
	    $userId = $param['user_id'];
	    unset($param['user_id']);
		if (!$param['contract_id']) {
			$this->error = '请先选择合同';
			return false;
		} else {
			$res = ContractModel::where(['contract_id' => $param['contract_id']])->value('check_status');
			if (6 == $res) {
				$this->error = '合同已作废';
				return false;
			}
			if (!in_array($res,['2'])) {
				$this->error = '当前合同未审核通过，不能添加回款';
				return false;
			}			
		}
		if ($param['remind'] > 90) {
			$this->error = '提前提醒最大时间为 90 天';
			return false;
		}
//		// 自动验证
//		$validate = validate($this->name);
//		if (!$validate->check($param)) {
//			$this->error = $validate->getError();
//			return false;
//		}
        // 数据验证
        $validateResult = $this->fieldDataValidate($param, 'crm_receivables_plan', $userId);
        if (!empty($validateResult)) {
            $this->error = $validateResult;
            return false;
        }
		if ($param['file_ids']) $param['file'] = arrayToString($param['file_ids']); //附件
		//期数规则（1,2,3..）
		$maxNum = db('crm_receivables_plan')->where(['contract_id' => $param['contract_id']])->max('num');
		$param['num'] = $maxNum ? $maxNum+1 : 1;
		//提醒日期
		$param['remind_date'] = $param['remind'] ? date('Y-m-d',strtotime($param['return_date'])-86400*$param['remind']) : $param['return_date'];

        $fieldModel = new \app\admin\model\Field();

        // 处理部门、员工、附件、多选类型字段
        $arrFieldAtt = $fieldModel->getArrayField('crm_receivables_plan');
        foreach ($arrFieldAtt AS $key => $value) {
            $param[$value] = !empty($param[$value]) ? arrayToString($param[$value]) : '';
        }

        // 处理日期（date）类型
        $dateField = $fieldModel->getFieldByFormType('crm_receivables_plan', 'date');
        foreach ($dateField AS $key => $value) {
            $param[$value] = !empty($param[$value]) ? $param[$value] : null;
        }

        // 处理手写签名类型
        $handwritingField = $fieldModel->getFieldByFormType('crm_receivables_plan', 'handwriting_sign');
        foreach ($handwritingField AS $key => $value) {
            $param[$value] = !empty($param[$value]['file_id']) ? $param[$value]['file_id'] : '';
        }

        // 处理地址、定位、日期区间、明细表格类型字段
        $receivablesPlanData = [];
        $positionField       = $fieldModel->getFieldByFormType('crm_receivables_plan', 'position');
        $locationField       = $fieldModel->getFieldByFormType('crm_receivables_plan', 'location');
        $dateIntervalField   = $fieldModel->getFieldByFormType('crm_receivables_plan', 'date_interval');
        $detailTableField    = $fieldModel->getFieldByFormType('crm_receivables_plan', 'detail_table');
        foreach ($param AS $key => $value) {
            // 处理地址类型字段数据
            if (in_array($key, $positionField)) {
                if (!empty($value)) {
                    $receivablesPlanData[] = [
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
                    $receivablesPlanData[] = [
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
                    $receivablesPlanData[] = [
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
                    $receivablesPlanData[] = [
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
        switch ($res){
            case 1:
                $param['status']=5;
                break;
            case 2:
                $param['status']=0;
                break;
            case 3:
                $param['status']=3;
                break;
            case 6:
                $param['status']=0;
                break;
        }
		if ($this->data($param)->allowField(true)->save()) {
			$data = [];
			$data['plan_id'] = $this->plan_id;
            // 添加回款计划扩展数据
            array_walk($receivablesPlanData, function (&$val) use ($data) {
                $val['plan_id'] = $data['plan_id'];
            });
            db('crm_receivables_plan_data')->insertAll($receivablesPlanData);
			return $data;
		} else {
			$this->error = '添加失败';
			return false;
		}			
	}

	/**
	 * 编辑回款计划
	 * @author Michael_xu
	 * @param  
	 * @return                            
	 */	
	public function updateDataById($param, $plan_id = '')
	{
	    $userId = $param['user_id'];
	    unset($param['user_id']);
		$dataInfo = $this->getDataById($plan_id);
		if (!$dataInfo) {
			$this->error = '数据不存在或已删除';
			return false;
		}
		$param['plan_id'] = $plan_id;
		//过滤不能修改的字段
		$unUpdateField = ['num','create_user_id','is_deleted','delete_time','delete_user_id'];
		foreach ($unUpdateField as $v) {
			unset($param[$v]);
		}
		
//		// 自动验证
//		$validate = validate($this->name);
//		if (!$validate->check($param)) {
//			$this->error = $validate->getError();
//			return false;
//		}
        // 数据验证
        $validateResult = $this->fieldDataValidate($param, 'crm_receivables_plan', $userId, $plan_id);
        if (!empty($validateResult)) {
            $this->error = $validateResult;
            return false;
        }
		if ($param['file_ids']) $param['file'] = arrayToString($param['file_ids']); //附件
		//提醒日期
		$param['remind_date'] = $param['remind'] ? date('Y-m-d',strtotime($param['return_date'])-86400*$param['remind']) : $param['return_date'];

        $fieldModel = new \app\admin\model\Field();

        // 处理部门、员工、附件、多选类型字段
        $arrFieldAtt = $fieldModel->getArrayField('crm_receivables_plan');
        foreach ($arrFieldAtt AS $key => $value) {
            $param[$value] = !empty($param[$value]) ? arrayToString($param[$value]) : '';
        }

        // 处理日期（date）类型
        $dateField = $fieldModel->getFieldByFormType('crm_receivables_plan', 'date');
        foreach ($dateField AS $key => $value) {
            $param[$value] = !empty($param[$value]) ? $param[$value] : null;
        }

        // 处理手写签名类型
        $handwritingField = $fieldModel->getFieldByFormType('crm_receivables_plan', 'handwriting_sign');
        foreach ($handwritingField AS $key => $value) {
            $param[$value] = !empty($param[$value]['file_id']) ? $param[$value]['file_id'] : '';
        }


        // 处理地址、定位、日期区间、明细表格类型字段
        $receivablesPlanData = [];
        $positionField       = $fieldModel->getFieldByFormType('crm_receivables_plan', 'position');
        $locationField       = $fieldModel->getFieldByFormType('crm_receivables_plan', 'location');
        $dateIntervalField   = $fieldModel->getFieldByFormType('crm_receivables_plan', 'date_interval');
        $detailTableField    = $fieldModel->getFieldByFormType('crm_receivables_plan', 'detail_table');
        foreach ($param AS $key => $value) {
            // 处理地址类型字段数据
            if (in_array($key, $positionField)) {
                if (!empty($value)) {
                    $receivablesPlanData[] = [
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
                    $receivablesPlanData[] = [
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
                    $receivablesPlanData[] = [
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
                    $receivablesPlanData[] = [
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

		if ($this->allowField(true)->save($param, ['plan_id' => $plan_id])) {
			$data = [];
			$data['plan_id'] = $plan_id;
            // 添加回款计划扩展数据
            db('crm_receivables_plan_data')->where('plan_id', $data['plan_id'])->delete();
            array_walk($receivablesPlanData, function (&$val) use ($data) {
                $val['plan_id'] = $data['plan_id'];
            });
            db('crm_receivables_plan_data')->insertAll($receivablesPlanData);
			return $data;
		} else {
			$this->error = '编辑失败';
			return false;
		}					
	}

	/**
     * 回款计划数据
     * @param  $id 回款计划ID
     * @return
     */
    public function getDataById($id = '', $userId = 0, $model='')
    {
        $map['plan_id'] = $id;
        $dataInfo = $this->where($map)->find();
        if (!$dataInfo) {
            $this->error = '暂无此数据';
            return false;
        }
        if(empty($model) && $model!='update'){
            $grantData = getFieldGrantData($userId);
            foreach ($grantData['crm_receivables_plan'] as $key => $value) {
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