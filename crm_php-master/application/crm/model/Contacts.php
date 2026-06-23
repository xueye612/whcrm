<?php
// +----------------------------------------------------------------------
// | Description: 联系人
// +----------------------------------------------------------------------
// | Author:  Michael_xu | gengxiaoxu@5kcrm.com
// +----------------------------------------------------------------------
namespace app\crm\model;

use app\admin\traits\FieldVerificationTrait;
use think\Db;
use app\admin\model\Common;
use think\Validate;

class Contacts extends Common
{
    use FieldVerificationTrait;
    
    /**
     * 为了数据库的整洁，同时又不影响Model和Controller的名称
     * 我们约定每个模块的数据表都加上相同的前缀，比如CRM模块用crm作为数据表前缀
     */
    protected $name = 'crm_contacts';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $autoWriteTimestamp = true;
    
    /**
     * [getDataList 联系人list]
     * @param     [string]                   $map [查询条件]
     * @param     [number]                   $page     [当前页数]
     * @param     [number]                   $limit    [每页数量]
     * @return    [array]                    [description]
     * @author Michael_xu
     */
    public function getDataList($request)
    {
        $userModel = new \app\admin\model\User();
        $structureModel = new \app\admin\model\Structure();
        $fieldModel = new \app\admin\model\Field();
        $customerModel = new \app\crm\model\Customer();
        $search = $request['search'];
        $user_id = $request['user_id'];
        $scene_id = (int)$request['scene_id'];
        $is_excel = $request['is_excel']; //导出
        $business_id = $request['business_id'];
        $order_field = $request['order_field'];
        $order_type = $request['order_type'];
        $pageType = $request['pageType'];
        $getCount = $request['getCount'];
        //需要过滤的参数
        $unsetRequest = ['scene_id', 'search', 'user_id', 'is_excel', 'action', 'order_field', 'order_type', 'is_remind', 'getCount', 'type', 'otherMap', 'business_id', 'check_status'];
        foreach ($unsetRequest as $v) {
            unset($request[$v]);
        }
        
        $request = $this->fmtRequest($request);
        $requestMap = $request['map'] ?: [];
        
        $sceneModel = new \app\admin\model\Scene();
        if ($scene_id) {
            //自定义场景
            $sceneMap = $sceneModel->getDataById($scene_id, $user_id, 'contacts') ?: [];
        } else {
            //默认场景
            $sceneMap = $sceneModel->getDefaultData('crm_contacts', $user_id) ?: [];
        }
        $searchMap = [];
        if ($search || $search == '0') {
            //普通筛选
            $searchMap = function ($query) use ($search) {
                $query->where('contacts.name', array('like', '%' . $search . '%'))
                    ->whereOr('contacts.mobile', array('like', '%' . $search . '%'))
                    ->whereOr('contacts.telephone', array('like', '%' . $search . '%'));
            };
        }
        $partMap = [];
        //优先级：普通筛选>高级筛选>场景
        $teamMap=$requestMap['team_id'];
        //团队成员 高级筛选
        if($teamMap){
            $partMap= advancedQueryFormatForTeam($teamMap,'contacts','contacts_id');
            unset($requestMap['team_id']);
            $map = $requestMap ? array_merge($sceneMap, $requestMap) : $sceneMap;
        } else {
            $map = $requestMap ? array_merge($sceneMap, $requestMap) : $sceneMap;
        }
        //高级筛选
        $map = advancedQuery($map, 'crm', 'contacts', 'index');
        //权限

            $a = 'index';
            if ($is_excel) $a = 'excelExport';
            $authMap = [];
            $auth_user_ids = $userModel->getUserByPer('crm', 'contacts', $a);
            if (isset($map['contacts.owner_user_id'])) {
                if (!is_array($map['contacts.owner_user_id'][1])) {
                    $map['contacts.owner_user_id'][1] = [$map['contacts.owner_user_id'][1]];
                }
                if (in_array($map['contacts.owner_user_id'][0], ['neq', 'notin'])) {
                    $auth_user_ids = array_diff($auth_user_ids, $map['contacts.owner_user_id'][1]) ?: [];    //取差集
                } else {
                    $auth_user_ids = array_intersect($map['contacts.owner_user_id'][1], $auth_user_ids) ?: [];    //取交集
                }
                unset($map['contacts.owner_user_id']);
                $auth_user_ids = array_merge(array_unique(array_filter($auth_user_ids))) ?: ['-1'];
                //负责人、相关团队
                $authMap['contacts.owner_user_id'] = ['in', $auth_user_ids];
            } else {
                $authMapData = [];
                $authMapData['auth_user_ids'] = $auth_user_ids;
                $authMapData['user_id'] = $user_id;
                $authMap = function ($query) use ($authMapData) {
                    $query->where('contacts.owner_user_id', array('in', $authMapData['auth_user_ids']))
                        ->whereOr('contacts.ro_user_id', array('like', '%,' . $authMapData['user_id'] . ',%'))
                        ->whereOr('contacts.rw_user_id', array('like', '%,' . $authMapData['user_id'] . ',%'));
                };
            }

        
        
        //联系人商机
        if ($business_id) {
            $contacts_id = Db::name('crm_contacts_business')->where(['business_id' => $business_id])->column('contacts_id');
            if ($contacts_id) {
                $map['contacts.contacts_id'] = array('in', $contacts_id);
            } else {
                $map['contacts.contacts_id'] = array('eq', -1);
            }
        }
        //列表展示字段
        $indexField = $fieldModel->getIndexField('crm_contacts', $user_id, 1) ?: array('name');
        $userField = $fieldModel->getFieldByFormType('crm_contacts', 'user'); //人员类型
        $structureField = $fieldModel->getFieldByFormType('crm_contacts', 'structure');  //部门类型
        $datetimeField = $fieldModel->getFieldByFormType('crm_contacts', 'datetime'); //日期时间类型
        $booleanField = $fieldModel->getFieldByFormType('crm_contacts', 'boolean_value'); //布尔值
        $dateIntervalField = $fieldModel->getFieldByFormType('crm_contacts', 'date_interval'); // 日期区间类型字段
        $positionField = $fieldModel->getFieldByFormType('crm_contacts', 'position'); // 地址类型字段
        $handwritingField = $fieldModel->getFieldByFormType('crm_contacts', 'handwriting_sign'); // 手写签名类型字段
        $locationField = $fieldModel->getFieldByFormType('crm_contacts', 'location'); // 定位类型字段
        $boxField = $fieldModel->getFieldByFormType('crm_contacts', 'checkbox'); // 多选类型字段
        $floatField = $fieldModel->getFieldByFormType('crm_contacts', 'floatnumber'); // 货币类型字段
//        $fieldGrant = db('admin_field_mask')->where('types', 'contacts')->select();
        # 处理人员和部门类型的排序报错问题(前端传来的是包含_name的别名字段)
        $temporaryField = str_replace('_name', '', $order_field);
        if (in_array($temporaryField, $userField) || in_array($temporaryField, $structureField)) {
            $order_field = $temporaryField;
        }
        //排序
        if ($order_type && $order_field) {
            $order = $fieldModel->getOrderByFormtype('crm_contacts', 'contacts', $order_field, $order_type);
        } else {
            $order = 'contacts.update_time desc';
        }
        $readAuthIds = $userModel->getUserByPer('crm', 'contacts', 'read');
        $updateAuthIds = $userModel->getUserByPer('crm', 'contacts', 'update');
        $deleteAuthIds = $userModel->getUserByPer('crm', 'contacts', 'delete');
        $customerWhere = [];
        if ($pageType == !'all') {
            //非客户池条件
            $customerWhere = $customerModel->getWhereByCustomer();
        }
        $dataCount = db('crm_contacts')
            ->alias('contacts')
            ->join('__CRM_CUSTOMER__ customer', 'contacts.customer_id = customer.customer_id', 'LEFT')
            ->where($map)
            ->where($searchMap)
            ->where($authMap)
            ->where($partMap)
            ->where($customerWhere)
            ->count('contacts_id');
        if ($getCount == 1) {
            $data['dataCount'] = $dataCount ?: 0;
            return $data;
        }
        $list = db('crm_contacts')
            ->alias('contacts')
            ->join('__CRM_CUSTOMER__ customer', 'contacts.customer_id = customer.customer_id', 'LEFT')
            ->where($map)
            ->where($searchMap)
            ->where($partMap)
            ->where($authMap)
            ->where($customerWhere)
            ->limit($request['offset'], $request['length'])
            ->field('contacts.*,customer.name as customer_name')
            ->orderRaw($order)
            ->select();
        # 扩展数据
        $extraData = [];
        $contacts_id_list = !empty($list) ? array_column($list, 'contacts_id') : [];
        $extraList = db('crm_contacts_data')->whereIn('contacts_id', $contacts_id_list)->select();
        foreach ($extraList as $key => $value) {
            $extraData[$value['contacts_id']][$value['field']] = $value['content'];
        }
        $grantData = getFieldGrantData($user_id);
        foreach ($grantData['crm_contacts'] as $key => $value) {
            foreach ($value as $ke => $va) {
                if($va['maskType']!=0){
                    $fieldGrant[$ke]['maskType'] = $va['maskType'];
                    $fieldGrant[$ke]['form_type'] = $va['form_type'];
                    $fieldGrant[$ke]['field'] = $va['field'];
                }
            }
        }
        foreach ($list as $k => $v) {
            $list[$k]['create_user_id_info'] = isset($v['create_user_id']) ? $userModel->getUserById($v['create_user_id']) : [];
            $list[$k]['owner_user_id_info'] = isset($v['owner_user_id']) ? $userModel->getUserById($v['owner_user_id']) : [];
            $list[$k]['customer_id_info']['customer_id'] = $v['customer_id'] ?: '';
            $list[$k]['customer_id_info']['name'] = $v['customer_name'] ?: '';
            foreach ($userField as $key => $val) {
                $usernameField = !empty($v[$val]) ? db('admin_user')->whereIn('id', stringToArray($v[$val]))->column('realname') : [];
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
            foreach ($dateIntervalField as $key => $val) {
                $list[$k][$val] = !empty($extraData[$v['contacts_id']][$val]) ? json_decode($extraData[$v['contacts_id']][$val], true) : null;
            }
            // 处理地址类型字段的格式
            foreach ($positionField as $key => $val) {
                $list[$k][$val] = !empty($extraData[$v['contacts_id']][$val]) ? json_decode($extraData[$v['contacts_id']][$val], true) : null;
            }
            // 手写签名类型字段
            foreach ($handwritingField as $key => $val) {
                $handwritingData = !empty($v[$val]) ? db('admin_file')->where('file_id', $v[$val])->value('file_path') : null;
                $list[$k][$val] = ['url' => !empty($handwritingData) ? getFullPath($handwritingData) : null];
            }
            // 定位类型字段
            foreach ($locationField AS $key => $val) {
                $list[$k][$val] = !empty($extraData[$v['contacts_id']][$val]) ? json_decode($extraData[$v['contacts_id']][$val], true) : null;
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
            //权限
            $permission = [];
            $is_read = 0;
            $is_update = 0;
            $is_delete = 0;
            if (in_array($v['owner_user_id'], $readAuthIds)) $is_read = 1;
            if (in_array($v['owner_user_id'], $updateAuthIds)) $is_update = 1;
            if (in_array($v['owner_user_id'], $deleteAuthIds)) $is_delete = 1;
            $permission['is_read'] = $is_read;
            $permission['is_update'] = $is_update;
            $permission['is_delete'] = $is_delete;
            $list[$k]['permission'] = $permission;
            
            # 关注
            $starWhere = ['user_id' => $user_id, 'target_id' => $v['contacts_id'], 'type' => 'crm_contacts'];
            $star = Db::name('crm_star')->where($starWhere)->value('star_id');
            $list[$k]['star'] = !empty($star) ? 1 : 0;
            # 日期
            $list[$k]['create_time'] = !empty($v['create_time']) ? date('Y-m-d H:i:s', $v['create_time']) : null;
            $list[$k]['update_time'] = !empty($v['update_time']) ? date('Y-m-d H:i:s', $v['update_time']) : null;
            $list[$k]['last_time'] = !empty($v['last_time']) ? date('Y-m-d H:i:s', $v['last_time']) : null;
            # 创建人
            $list[$k]['create_user_name'] = !empty($list[$k]['create_user_id_info']['realname']) ? $list[$k]['create_user_id_info']['realname'] : '';
            # 负责人
            $list[$k]['owner_user_name'] = !empty($list[$k]['owner_user_id_info']['realname']) ? $list[$k]['owner_user_id_info']['realname'] : '';

            # 系统字段  负责人部门   zjf  20210726
            $list[$k]['owner_user_structure_name'] = $list[$k]['owner_user_id_info']['structure_name'];
        }
        $data = [];
        $data['list'] = $list;
        $data['dataCount'] = $dataCount ?: 0;
        return $data;
    }
    
    /**
     * 创建联系人主表信息
     * @param
     * @return
     * @author Michael_xu
     */
    public function createData($param)
    {
        unset($param['excel']);
        
        // 联系人扩展表数据
        $contactsData = [];
        
        $businessId = $param['business_id'];
        unset($param['business_id']);
        $fieldModel = new \app\admin\model\Field();
        
        // 数据验证
        $validateResult = $this->fieldDataValidate($param, $this->name, $param['create_user_id']);
        if (!empty($validateResult)) {
            $this->error = $validateResult;
            return false;
        }
        
        # 处理客户首要联系人
        $primaryStatus = Db::name('crm_contacts')->where('customer_id', $param['customer_id'])->value('contacts_id');
        if (!empty($param['primary']) && $param['primary'] == 1 && !empty($primaryStatus)) {
            # 设置首要联系人，去除其他首要联系人状态
            Db::name('crm_contacts')->where('customer_id', $param['customer_id'])->update(['primary' => 0]);
        }
        if (!empty($param['customer_id']) && empty($primaryStatus)) {
            # 为客户添加第一个联系人默认设置成首要联系人
            $param['primary'] = 1;
        }
        
        // 处理部门、员工、附件、多选类型字段
        $arrFieldAtt = $fieldModel->getArrayField('crm_contacts');
        foreach ($arrFieldAtt as $k => $v) {
            $param[$v] = arrayToString($param[$v]);
        }
        // 处理日期（date）类型
        $dateField = $fieldModel->getFieldByFormType('crm_contacts', 'date');
        if (!empty($dateField)) {
            foreach ($param as $key => $value) {
                if (in_array($key, $dateField) && empty($value)) $param[$key] = null;
            }
        }
        // 处理手写签名类型
        $handwritingField = $fieldModel->getFieldByFormType('crm_contacts', 'handwriting_sign');
        if (!empty($handwritingField)) {
            foreach ($param as $key => $value) {
                if (in_array($key, $handwritingField)) {
                    $param[$key] = !empty($value['file_id']) ? $value['file_id'] : '';
                }
            }
        }
        // 处理地址、定位、日期区间、明细表格类型字段
        $positionField = $fieldModel->getFieldByFormType($this->name, 'position');
        $locationField = $fieldModel->getFieldByFormType($this->name, 'location');
        $dateIntervalField = $fieldModel->getFieldByFormType($this->name, 'date_interval');
        $detailTableField = $fieldModel->getFieldByFormType($this->name, 'detail_table');
        foreach ($param as $key => $value) {
            // 处理地址类型字段数据
            if (in_array($key, $positionField)) {
                if (!empty($value)) {
                    $contactsData[] = [
                        'field' => $key,
                        'content' => json_encode($value, JSON_NUMERIC_CHECK),
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
                    $contactsData[] = [
                        'field' => $key,
                        'content' => json_encode($value, JSON_NUMERIC_CHECK),
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
                    $contactsData[] = [
                        'field' => $key,
                        'content' => json_encode($value, JSON_NUMERIC_CHECK),
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
                    $contactsData[] = [
                        'field' => $key,
                        'content' => json_encode($value, JSON_NUMERIC_CHECK),
                        'create_time' => time()
                    ];
                    $param[$key] = $key;
                } else {
                    $param[$key] = '';
                }
            }
        }
        
        if ($this->data($param)->allowField(true)->isUpdate(false)->save()) {
            updateActionLog($param['create_user_id'], 'crm_contacts', $this->contacts_id, '', '', '创建了联系人');
            RecordActionLog($param['create_user_id'], 'crm_contacts', 'save', $param['name'], '', '', '新增了联系人' . $param['name']);
            $data = [];
            $data['contacts_id'] = $this->contacts_id;
            
            # 添加活动记录
            Db::name('crm_activity')->insert([
                'type' => 2,
                'activity_type' => 3,
                'activity_type_id' => $data['contacts_id'],
                'content' => $param['name'],
                'create_user_id' => $param['create_user_id'],
                'update_time' => time(),
                'create_time' => time(),
                'customer_ids' => ',' . $param['customer_id'] . ','
            ]);
            
            # 处理商机首要联系人
            if (!empty($businessId)) {
                Db::name('crm_business')->where('business_id', $businessId)->update(['contacts_id' => $data['contacts_id']]);
            }
            
            // 添加联系人扩展数据
            array_walk($contactsData, function (&$val) use ($data) {
                $val['contacts_id'] = $data['contacts_id'];
            });
            db('crm_contacts_data')->insertAll($contactsData);
            
            return $data;
        } else {
            $this->error = '添加失败';
            return false;
        }
    }
    
    //根据IDs获取数组
    public function getDataByStr($idstr)
    {
        $idArr = stringToArray($idstr);
        if (!$idArr) {
            return [];
        }
        $list = Db::name('CrmContacts')->where(['contacts_id' => ['in', $idArr]])->select();
        return $list;
    }
    
    /**
     * 编辑联系人主表信息
     *
     * @param $param
     * @param string $contacts_id
     * @return array|bool
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function updateDataById($param, $contacts_id = '')
    {
        // 联系人扩展表数据
        $contactsData = [];
        
        $userModel = new \app\admin\model\User();
        $dataInfo = $this->getDataById($contacts_id);
        if (!$dataInfo) {
            $this->error = '数据不存在或已删除';
            return false;
        }
        //判断权限
        $auth_user_ids = $userModel->getUserByPer('crm', 'contacts', 'update');
        if (!in_array($dataInfo['owner_user_id'], $auth_user_ids)) {
            $this->error = '无权操作';
            return false;
        }
        
        $param['contacts_id'] = $contacts_id;
        //过滤不能修改的字段
        $unUpdateField = ['create_user_id', 'is_deleted', 'delete_time'];
        foreach ($unUpdateField as $v) {
            unset($param[$v]);
        }
        $fieldModel = new \app\admin\model\Field();
        
        // 数据验证
        $validateResult = $this->fieldDataValidate($param, $this->name, $param['user_id'], $param['contacts_id']);
        if (!empty($validateResult)) {
            $this->error = $validateResult;
            return false;
        }
        
        // 处理部门、员工、附件、多选类型字段
        $arrFieldAtt = $fieldModel->getArrayField('crm_contacts');
        foreach ($arrFieldAtt as $k => $v) {
            if (isset($param[$v])) $param[$v] = arrayToString($param[$v]);
        }
        // 处理日期（date）类型
        $dateField = $fieldModel->getFieldByFormType('crm_contacts', 'date');
        if (!empty($dateField)) {
            foreach ($param as $key => $value) {
                if (in_array($key, $dateField) && empty($value)) $param[$key] = null;
            }
        }
        // 处理手写签名类型
        $handwritingField = $fieldModel->getFieldByFormType('crm_contacts', 'handwriting_sign');
        if (!empty($handwritingField)) {
            foreach ($param as $key => $value) {
                if (in_array($key, $handwritingField)) {
                    $param[$key] = !empty($value['file_id']) ? $value['file_id'] : '';
                }
            }
        }
        // 处理地址、定位、日期区间、明细表格类型字段
        $positionField = $fieldModel->getFieldByFormType($this->name, 'position');
        $locationField = $fieldModel->getFieldByFormType($this->name, 'location');
        $dateIntervalField = $fieldModel->getFieldByFormType($this->name, 'date_interval');
        $detailTableField = $fieldModel->getFieldByFormType($this->name, 'detail_table');
        foreach ($param as $key => $value) {
            // 处理地址类型字段数据
            if (in_array($key, $positionField)) {
                if (!empty($value)) {
                    $contactsData[] = [
                        'field' => $key,
                        'content' => json_encode($value, JSON_NUMERIC_CHECK),
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
                    $contactsData[] = [
                        'field' => $key,
                        'content' => json_encode($value, JSON_NUMERIC_CHECK),
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
                    $contactsData[] = [
                        'field' => $key,
                        'content' => json_encode($value, JSON_NUMERIC_CHECK),
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
                    $contactsData[] = [
                        'field' => $key,
                        'content' => json_encode($value, JSON_NUMERIC_CHECK),
                        'create_time' => time()
                    ];
                    $param[$key] = $key;
                } else {
                    $param[$key] = '';
                }
            }
        }
        
        # 处理首要联系人
        $primaryStatus = Db::name('crm_contacts')->where('customer_id', $param['customer_id'])->value('contacts_id');
        if (!empty($param['primary']) && $param['primary'] == 1 && !empty($primaryStatus)) {
            # 设置首要联系人，去除其他首要联系人状态
            Db::name('crm_contacts')->where('customer_id', $param['customer_id'])->update(['primary' => 0]);
        }
        if (!empty($param['customer_id']) && empty($primaryStatus)) {
            # 为客户添加第一个联系人默认设置成首要联系人
            $param['primary'] = 1;
        }
        
        if ($this->update($param, ['contacts_id' => $contacts_id], true)) {
            $data['contacts_id'] = $contacts_id;
            //修改记录
            updateActionLog($param['user_id'], 'crm_contacts', $contacts_id, $dataInfo, $param);
            RecordActionLog($param['user_id'], 'crm_contacts', 'update', $dataInfo['name'], $dataInfo, $param);
            // 添加联系人扩展数据
            db('crm_contacts_data')->where('contacts_id', $contacts_id)->delete();
            array_walk($contactsData, function (&$val) use ($contacts_id) {
                $val['contacts_id'] = $contacts_id;
            });
            db('crm_contacts_data')->insertAll($contactsData);
            
            return $data;
        } else {
            $this->error = '编辑失败';
            return false;
        }
    }
    
    /**
     * 联系人数据
     *
     * @param string $id
     * @param int $userId
     * @return Common|array|bool|\PDOStatement|string|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getDataById($id = '', $userId = 0,$model='')
    {
        $map['contacts_id'] = $id;
        $dataInfo = db('crm_contacts')->where($map)->find();
        if (!$dataInfo) {
            $this->error = '暂无此数据';
            return false;
        }
        if(empty($model) && $model!='update'){
            $grantData = getFieldGrantData($userId);
            foreach ($grantData['crm_contacts'] as $key => $value) {
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

        $userModel = new \app\admin\model\User();
        $dataInfo['create_user_id_info'] = isset($dataInfo['create_user_id']) ? $userModel->getUserById($dataInfo['create_user_id']) : [];
        $dataInfo['owner_user_id_info'] = isset($dataInfo['owner_user_id']) ? $userModel->getUserById($dataInfo['owner_user_id']) : [];
        $dataInfo['customer_id_info'] = db('crm_customer')->where(['customer_id' => $dataInfo['customer_id']])->field('customer_id,name,mobile,telephone,deal_status')->find();
        $dataInfo['customer_name'] = !empty($dataInfo['customer_id_info']['name']) ? $dataInfo['customer_id_info']['name'] : '';
        $dataInfo['create_user_name'] = !empty($dataInfo['create_user_id_info']['realname']) ? $dataInfo['create_user_id_info']['realname'] : '';
        $dataInfo['owner_user_name'] = !empty($dataInfo['owner_user_id_info']['realname']) ? $dataInfo['owner_user_id_info']['realname'] : '';
        # 关注
        $starId = empty($userId) ? 0 : Db::name('crm_star')->where(['user_id' => $userId, 'target_id' => $id, 'type' => 'crm_contacts'])->value('star_id');
        $dataInfo['star'] = !empty($starId) ? 1 : 0;
        # 处理决策人显示问题
        $dataInfo['decision'] = !empty($dataInfo['decision']) && $dataInfo['decision'] == '是' ? '是' : '否';
        # 处理时间格式
        $fieldModel = new \app\admin\model\Field();
        $datetimeField = $fieldModel->getFieldByFormType('crm_contacts', 'datetime'); //日期时间类型
        foreach ($datetimeField as $key => $val) {
            $dataInfo[$val] = !empty($dataInfo[$val]) ? date('Y-m-d H:i:s', $dataInfo[$val]) : null;
        }
        $dataInfo['create_time'] = !empty($dataInfo['create_time']) ? date('Y-m-d H:i:s', $dataInfo['create_time']) : null;
        $dataInfo['update_time'] = !empty($dataInfo['update_time']) ? date('Y-m-d H:i:s', $dataInfo['update_time']) : null;
        $dataInfo['last_time'] = !empty($dataInfo['last_time']) ? date('Y-m-d H:i:s', $dataInfo['last_time']) : null;
        // 字段授权
        if (!empty($userId)) {
            $grantData = getFieldGrantData($userId);
            $userLevel = isSuperAdministrators($userId);
            foreach ($dataInfo as $key => $value) {
                if (!$userLevel && !empty($grantData['crm_contacts'])) {
                    $status = getFieldGrantStatus($key, $grantData['crm_contacts']);
                    
                    # 查看权限
                    if ($status['read'] == 0) unset($dataInfo[$key]);
                }
            }
        }
        return $dataInfo;
    }
    
    /**
     * [联系人转移]
     * @param ids 联系人ID数组
     * @param owner_user_id 变更负责人
     * @param is_remove 1移出，2转为团队成员
     * @return
     * @author Michael_xu
     */
    public function transferDataById($ids, $owner_user_id, $type = 1, $is_remove)
    {
        $settingModel = new \app\crm\model\Setting();
        foreach ($ids as $id) {
            $data = [];
            $data['owner_user_id'] = $owner_user_id;
            $data['update_time'] = time();
            db('crm_contacts')->where(['contacts_id' => $id])->update($data);
        }
        return true;
    }
    
    /**
     * 设置首要联系人
     *
     * @param $customerId
     * @param $contactsId
     * @return bool
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function setPrimary($customerId, $contactsId)
    {
        Db::name('crm_contacts')->where('customer_id', $customerId)->update(['primary' => 0]);
        Db::name('crm_contacts')->where(['customer_id' => $customerId, 'contacts_id' => $contactsId])->update(['primary' => 1]);
        
        return true;
    }
    
    /**
     * 获取跟进记录联系人
     *
     * @param $customerId
     * @return bool|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getContactsList($customerId)
    {
        return Db::name('crm_contacts')->field(['contacts_id', 'name', 'mobile', 'telephone', 'detail_address'])->where('customer_id', $customerId)->order('primary', 'desc')->select();
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
        # 联系人
        $contacts = Db::name('crm_contacts')->field(['create_user_id','owner_user_id' , 'create_time', 'update_time', 'last_time'])->where('contacts_id', $id)->find();
        # 创建人
        $realname = Db::name('admin_user')->where('id', $contacts['create_user_id'])->value('realname');

        # zjf   20210726
        $userModel   = new \app\admin\model\User();
        $ownerUserInfo = $userModel->getUserById($contacts['owner_user_id']);
        # 负责人部门
        $ownerStructureName = $ownerUserInfo['structure_name'];

        # 负责人
        $ownerUserName = $ownerUserInfo['realname'];
        return [
            'create_user_id' => $realname,
            'owner_user_id' => $ownerUserName,
            'create_time' => date('Y-m-d H:i:s', $contacts['create_time']),
            'update_time' => date('Y-m-d H:i:s', $contacts['update_time']),
            'last_time' => !empty($contacts['last_time']) ? date('Y-m-d H:i:s', $contacts['last_time']) : '',
            'owner_user_structure_name' => $ownerStructureName
        ];
    }
}