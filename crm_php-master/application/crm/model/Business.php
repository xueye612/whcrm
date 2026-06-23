<?php
// +----------------------------------------------------------------------
// | Description: 商机
// +----------------------------------------------------------------------
// | Author:  Michael_xu | gengxiaoxu@5kcrm.com
// +----------------------------------------------------------------------
namespace app\crm\model;

use app\admin\traits\FieldVerificationTrait;
use think\Db;
use app\admin\model\Common;
use app\crm\model\Business as CrmBusinessModel;

class Business extends Common
{
    use FieldVerificationTrait;

    /**
     * 为了数据库的整洁，同时又不影响Model和Controller的名称
     * 我们约定每个模块的数据表都加上相同的前缀，比如CRM模块用crm作为数据表前缀
     */
    protected $name = 'crm_business';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $autoWriteTimestamp = true;

    /**
     * [getDataList 商机list]
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
        $userModel = new \app\admin\model\User();
        $structureModel = new \app\admin\model\Structure();
        $fieldModel = new \app\admin\model\Field();
        $search = $request['search'] ?? '';
        $user_id = $request['user_id'] ?? 0;
        $scene_id = (int)($request['scene_id'] ?? 0);
        $contacts_id = $request['contacts_id'] ?? 0;
        $order_field = $request['order_field'] ?? '';
        $order_type = $request['order_type'] ?? '';
        $is_excel = $request['is_excel'] ?? 0; //导出
        $getCount = $request['getCount'] ?? 0;
        $businessTypeId = $request['typesId'] ?? 0; // 针对mobile
        $businessStatusId = $request['statusId'] ?? 0; // 针对mobile
        $overdue = $request['overdue'] ?? 0; // 待办事项下需联系商机(逾期)
        unset($request['scene_id']);
        unset($request['search']);
        unset($request['user_id']);
        unset($request['contacts_id']);
        unset($request['order_field']);
        unset($request['order_type']);
        unset($request['is_excel']);
        unset($request['getCount']);
        unset($request['typesId']);
        unset($request['statusId']);
        unset($request['overdue']);
        $request = $this->fmtRequest($request);
        $requestMap = $request['map'] ?: [];
        $sceneModel = new \app\admin\model\Scene();
        # getCount是代办事项传来的参数，代办事项不需要使用场景
        $sceneMap = [];
        if (empty($getCount)) {
            if ($scene_id) {
                //自定义场景
                $sceneMap = $sceneModel->getDataById($scene_id, $user_id, 'business') ?: [];
            } else {
                //默认场景
                $sceneMap = $sceneModel->getDefaultData('crm_business', $user_id) ?: [];
            }
        }
        if ($search || $search == '0') {
            //普通筛选
            $sceneMap['name'] = ['condition' => 'contains', 'value' => $search, 'form_type' => 'text', 'name' => '商机名称'];
        }
        if (isset($requestMap['type_id'])) {
            $requestMap['type_id']['value'] = $requestMap['type_id']['type_id'];
            if(in_array($requestMap['type_id']['status_id'],[1,2,3])){
                $requestMap['is_end']=$requestMap['type_id']['status_id'];
            }else{
                if ($requestMap['type_id']['status_id']) $requestMap['status_id']['value'] = $requestMap['type_id']['status_id'];
                $requestMap['is_end']=0;
            }
        }
        if ($sceneMap['type_id']) {
            $requestMap['type_id']['value'] = $sceneMap['type_id']['type_id'];
            if(in_array($sceneMap['type_id']['status_id'],[1,2,3])){
                $sceneMap['is_end']=$sceneMap['type_id']['status_id'];
            }else{
                if ($sceneMap['type_id']['status_id']) $requestMap['status_id']['value'] = $sceneMap['type_id']['status_id'];
                $sceneMap['is_end']=0;
            }
            unset($sceneMap['type_id']);
        }
        $partMap = [];
        $teamMap=$requestMap['team_id'];
        //团队成员 高级筛选
        if($teamMap){
            $partMap= advancedQueryFormatForTeam($teamMap,'business','');
            unset($requestMap['team_id']);
            $map = $requestMap ? array_merge($sceneMap, $requestMap) : $sceneMap;
        } else {
            $map = $requestMap ? array_merge($sceneMap, $requestMap) : $sceneMap;
        }
        //高级筛选
        $map = advancedQuery($map, 'crm', 'business', 'index');
        $authMap = [];
            $a = 'index';
            if ($is_excel) $a = 'excelExport';
            $auth_user_ids = $userModel->getUserByPer('crm', 'business', $a);
            if (isset($map['business.owner_user_id'])) {
                if (!is_array($map['business.owner_user_id'][1])) {
                    $map['business.owner_user_id'][1] = [$map['business.owner_user_id'][1]];
                }
                if (in_array($map['business.owner_user_id'][0], ['neq', 'notin'])) {
                    $auth_user_ids = array_diff($auth_user_ids, $map['business.owner_user_id'][1]) ?: [];    //取差集
                } else {
                    $auth_user_ids = array_intersect($map['business.owner_user_id'][1], $auth_user_ids) ?: [];    //取交集
                }
                unset($map['business.owner_user_id']);
                $auth_user_ids = array_merge(array_unique(array_filter($auth_user_ids))) ?: ['-1'];
                $authMap['business.owner_user_id'] = array('in', $auth_user_ids);
            } else {
                $authMapData = [];
                $authMapData['auth_user_ids'] = $auth_user_ids;
                $authMapData['user_id'] = $user_id;
                $authMap = function ($query) use ($authMapData) {
                    $query->where('business.owner_user_id', array('in', $authMapData['auth_user_ids']))
                        ->whereOr('business.ro_user_id', array('like', '%,' . $authMapData['user_id'] . ',%'))
                        ->whereOr('business.rw_user_id', array('like', '%,' . $authMapData['user_id'] . ',%'));
                };
            }

        //联系人商机
        if ($contacts_id) {
            $business_id = Db::name('crm_contacts_business')->where(['contacts_id' => $contacts_id])->column('business_id');
            if ($business_id) {
                $map['business.business_id'] = array('in', $business_id);
            } else {
                $map['business.business_id'] = array('eq', -1);
            }
        }
        //列表展示字段
        $indexField = $fieldModel->getIndexField('crm_business', $user_id, 1) ?: array('name');
        if (!empty($indexField)) {
            foreach ($indexField as $key => $value) {
                if ($value == 'business.customer_name') unset($indexField[(int)$key]);
            }
        }
        $userField = $fieldModel->getFieldByFormType('crm_business', 'user'); //人员类型
        $structureField = $fieldModel->getFieldByFormType('crm_business', 'structure');  //部门类型

//        $fieldGrant = db('admin_field_mask')->where('types', 'business')->select();
        # 处理人员和部门类型的排序报错问题(前端传来的是包含_name的别名字段)
        $temporaryField = str_replace('_name', '', $order_field);
        if (in_array($temporaryField, $userField) || in_array($temporaryField, $structureField)) {
            $order_field = $temporaryField;
        }
        //排序
        if ($order_type && $order_field) {
            $order = $fieldModel->getOrderByFormtype('crm_business', 'business', $order_field, $order_type);
        } else {
            $order = 'business.update_time desc';
        }
    
        # 商机组和商机状态搜索
        if (!empty($businessTypeId))   $map['business.type_id']   = ['eq', $businessTypeId];
        if (!empty($businessStatusId)) {
            if(preg_match("/^[1-9][0-9]*$/" ,$businessStatusId)){
                $map['is_end']=0;
                $map['business.status_id'] = ['eq', $businessStatusId];
            }else{
                $map['is_end']=abs($businessStatusId);
            }
        }

        // 待办事项下需联系商机(逾期)
        $overdueWhere = '';
        if (!empty($overdue)) {
            $overdueWhere = "(FROM_UNIXTIME(`business`.`last_time`,'%Y-%m-%d') < FROM_UNIXTIME(`business`.`next_time`,'%Y-%m-%d') OR (ISNULL(`business`.`last_time`) AND `business`.`next_time` < ".time()."))";
        }

        $readAuthIds = $userModel->getUserByPer('crm', 'business', 'read');
        $updateAuthIds = $userModel->getUserByPer('crm', 'business', 'update');
        $deleteAuthIds = $userModel->getUserByPer('crm', 'business', 'delete');
        $dataCount = db('crm_business')
            ->alias('business')
            ->join('__CRM_CUSTOMER__ customer', 'business.customer_id = customer.customer_id', 'LEFT')
            ->where($map)->where($partMap)->where($authMap)->where($overdueWhere)->count('business_id');
        if (!empty($getCount) && $getCount == 1) {
            $data['dataCount'] = !empty($dataCount) ? $dataCount : 0;
            # 商机总金额
            $sumMoney = Db::name('crm_business')->alias('business')
                ->whereIn('is_end', [0, 1])->where($map)->where($partMap)->where($authMap)->sum('money');
            $data['extraData']['money'] = ['businessSumMoney' => !empty($sumMoney) ? sprintf("%.2f", $sumMoney) : 0.00];
            return $data;
        }
        $list = db('crm_business')
            ->alias('business')
            ->join('__CRM_CUSTOMER__ customer', 'business.customer_id = customer.customer_id', 'LEFT')
            ->where($map)
            ->where($partMap)
            ->where($authMap)
            ->where($overdueWhere)
            ->limit($request['offset'], $request['length'])
            ->field('business.*,customer.name as customer_name')
            ->orderRaw($order)
            ->select();
        $endStatus = ['1' => '赢单', '2' => '输单', '3' => '无效'];
        # 扩展数据
        $extraData = [];

        $list=getFieldData($list,'crm_business',$user_id);
        foreach ($list as $k => $v) {
            $list[$k]['customer_id_info']['customer_id'] = $v['customer_id'];
            $list[$k]['customer_id_info']['name'] = $v['customer_name'];
            $list[$k]['create_user_id_info'] = isset($v['create_user_id']) ? $userModel->getUserById($v['create_user_id']) : [];
            $list[$k]['owner_user_id_info'] = isset($v['owner_user_id']) ? $userModel->getUserById($v['owner_user_id']) : [];
            $list[$k]['create_user_name'] = !empty($list[$k]['create_user_id_info']['realname']) ? $list[$k]['create_user_id_info']['realname'] : '';
            $list[$k]['owner_user_name'] = !empty($list[$k]['owner_user_id_info']['realname']) ? $list[$k]['owner_user_id_info']['realname'] : '';

            $statusInfo = [];
            $status_count = 0;
            if (!$v['is_end']) {
                $statusInfo = db('crm_business_status')->where('status_id', $v['status_id'])->find();
                if ($statusInfo['order_id'] < 99) {
                    $status_count = db('crm_business_status')->where('type_id', ['eq', $v['type_id']])->count();
                }
                //进度
                $list[$k]['status_progress'] = [$statusInfo['order_id'], $status_count + 1];
            } else {
                $statusInfo['name'] = $endStatus[$v['is_end']];
            }
            $list[$k]['status_id_info'] = $statusInfo['name'];//销售阶段
            $list[$k]['type_id_info'] = db('crm_business_type')->where('type_id', $v['type_id'])->value('name');//商机状态组

            //权限
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
            # 下次联系时间
            $list[$k]['next_time'] = !empty($v['next_time']) ? date('Y-m-d H:i:s', $v['next_time']) : null;
            # 关注
            $starWhere = ['user_id' => $user_id, 'target_id' => $v['business_id'], 'type' => 'crm_business'];
            $star = Db::name('crm_star')->where($starWhere)->value('star_id');
            $list[$k]['star'] = !empty($star) ? 1 : 0;
            # 日期
            $list[$k]['create_time'] = !empty($v['create_time']) ? date('Y-m-d H:i:s', $v['create_time']) : null;
            $list[$k]['update_time'] = !empty($v['update_time']) ? date('Y-m-d H:i:s', $v['update_time']) : null;
            $list[$k]['last_time'] = !empty($v['last_time']) ? date('Y-m-d H:i:s', $v['last_time']) : null;

            # 系统字段  负责人部门   zjf  20210726
            $list[$k]['owner_user_structure_name'] = $list[$k]['owner_user_id_info']['structure_name'];
        }
        $data = [];
        $data['list'] = $list ?: [];
        $data['dataCount'] = $dataCount ?: 0;
        # 商机总金额
        $sumMoney = Db::name('crm_business')->alias('business')
            ->join('__CRM_CUSTOMER__ customer', 'business.customer_id = customer.customer_id', 'LEFT')
            ->whereIn('is_end', [0, 1])->where($map)->where($partMap)->where($authMap)->sum('money');
        $data['extraData']['money'] = ['businessSumMoney' => !empty($sumMoney) ? sprintf("%.2f", $sumMoney) : 0.00];
        return $data;
    }

    /**
     * 创建商机主表信息
     * @param
     * @return
     * @author Michael_xu
     */
    public function createData($param)
    {
        // 商机扩展表数据
        $businessData = [];

        $fieldModel = new \app\admin\model\Field();
        $productModel = new \app\crm\model\Product();
        // 数据验证
        $validateResult = $this->fieldDataValidate($param, $this->name, $param['create_user_id']);
        if (!empty($validateResult)) {
            $this->error = $validateResult;
            return false;
        }
        if (!$param['customer_id']) {
            $this->error = '请选择相关客户';
            return false;
        }

        // 处理部门、员工、附件、多选类型字段
        $arrFieldAtt = $fieldModel->getArrayField('crm_business');
        foreach ($arrFieldAtt as $k => $v) {
            $param[$v] = arrayToString($param[$v]);
        }
        // 处理日期（date）类型
        $dateField = $fieldModel->getFieldByFormType('crm_business', 'date');
        if (!empty($dateField)) {
            foreach ($param AS $key => $value) {
                if (in_array($key, $dateField) && empty($value)) $param[$key] = null;
            }
        }
        // 处理手写签名类型
        $handwritingField = $fieldModel->getFieldByFormType('crm_business', 'handwriting_sign');
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
                    $businessData[] = [
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
                    $businessData[] = [
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
                    $businessData[] = [
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
                    $businessData[] = [
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

        # 设置今日需联系商机
        if (!empty($param['next_time']) && $param['next_time'] >= strtotime(date('Y-m-d 00:00:00'))) $param['is_dealt'] = 0;

        $param['money'] = $param['money'] ?: '0.00';
        $param['discount_rate'] = $param['discount_rate'] ?: '0.00';
        if ($this->data($param)->allowField(true)->save()) {
            updateActionLog($param['create_user_id'], 'crm_business', $this->business_id, '', '', '创建了商机');
            RecordActionLog($param['create_user_id'],'crm_business','save',$param['name'],'','','新增了商机'.$param['name']);
            $business_id = $this->business_id;
            $data['business_id'] = $business_id;
            if ($param['product']) {
                //产品数据处理
                $resProduct = $productModel->createObject('crm_business', $param, $business_id);
                if ($resProduct == false) {
                    $this->error = '产品添加失败';
                    return false;
                }
            }
            //添加商机日志
            $data_log['business_id'] = $business_id;
            $data_log['is_end'] = 0;
            $data_log['status_id'] = $param['status_id'];
            $data_log['create_time'] = time();
            $data_log['owner_user_id'] = $param['owner_user_id'];
            $data_log['remark'] = '新建商机';
            Db::name('CrmBusinessLog')->insert($data_log);
            # 添加活动记录
            Db::name('crm_activity')->insert([
                'type' => 2,
                'activity_type' => 5,
                'activity_type_id' => $data['business_id'],
                'content' => $param['name'],
                'create_user_id' => $param['create_user_id'],
                'update_time' => time(),
                'create_time' => time(),
                'customer_ids' => ',' . $param['customer_id'] . ','
            ]);
            // 添加商机扩展数据
            array_walk($businessData, function (&$val) use ($data) {
                $val['business_id'] = $data['business_id'];
            });
            db('crm_business_data')->insertAll($businessData);

            return $data;
        } else {
            $this->error = '添加失败';
            return false;
        }
    }

    /**
     * 编辑商机主表信息
     * @param
     * @return
     * @author Michael_xu
     */
    public function updateDataById($param, $business_id = '')
    {
        // 商机扩展表数据
        $businessData = [];

        $productModel = new \app\crm\model\Product();
        $dataInfo = $this->getDataById($business_id);
        if (!$dataInfo) {
            $this->error = '数据不存在或已删除';
            return false;
        }
        $param['business_id'] = $business_id;
        //过滤不能修改的字段
        $unUpdateField = ['create_user_id', 'is_deleted', 'delete_time'];
        foreach ($unUpdateField as $v) {
            unset($param[$v]);
        }

        $fieldModel = new \app\admin\model\Field();

        // 数据验证
        $validateResult = $this->fieldDataValidate($param, $this->name, $param['user_id'], $param['business_id']);
        if (!empty($validateResult)) {
            $this->error = $validateResult;
            return false;
        }

        # 商机金额小数处理
        if (!empty($param['money']) && is_numeric($param['money']) && strpos($param['money'], ".") === false) {
            $param['money'] .= '.00';
        }

        // 处理部门、员工、附件、多选类型字段
        $arrFieldAtt = $fieldModel->getArrayField('crm_business');
        foreach ($arrFieldAtt as $k => $v) {
            if (isset($param[$v])) $param[$v] = arrayToString($param[$v]);
        }
        // 处理日期（date）类型
        $dateField = $fieldModel->getFieldByFormType('crm_business', 'date');
        if (!empty($dateField)) {
            foreach ($param AS $key => $value) {
                if (in_array($key, $dateField) && empty($value)) $param[$key] = null;
            }
        }
        // 处理手写签名类型
        $handwritingField = $fieldModel->getFieldByFormType('crm_business', 'handwriting_sign');
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
                    $businessData[] = [
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
                    $businessData[] = [
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
                    $businessData[] = [
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
                    $businessData[] = [
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

        # 设置今日需联系商机
        if (!empty($param['next_time']) && $param['next_time'] >= strtotime(date('Y-m-d 00:00:00'))) $param['is_dealt'] = 0;

        $param['money'] = $param['money'] ?: '0.00';
        $param['discount_rate'] = $param['discount_rate'] ?: '0.00';
        //商机状态改变
        $statusInfo = db('crm_business_status')->where(['status_id' => $param['status_id']])->find();
        if ($statusInfo['type_id']) {
            $param['is_end'] = 0;
        } else {
            $param['is_end'] = $param['status_id'];
        }
        if ($this->update($param, ['business_id' => $business_id], true)) {
            $data['business_id'] = $business_id;
            //产品数据处理
            $resProduct = $productModel->createObject('crm_business', $param, $business_id);
            //修改记录
            updateActionLog($param['user_id'], 'crm_business', $business_id, $dataInfo, $param);
            RecordActionLog($param['user_id'], 'crm_business', 'update',$dataInfo['name'], $dataInfo, $param);
            // 添加商机扩展数据
            db('crm_business_data')->where('business_id', $business_id)->delete();
            array_walk($businessData, function (&$val) use ($business_id) {
                $val['business_id'] = $business_id;
            });
            db('crm_business_data')->insertAll($businessData);

            return $data;
        } else {
            $this->rollback();
            $this->error = '编辑失败';
            return false;
        }
    }

    /**
     * 商机数据
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
        $dataInfo = db('crm_business')->where('business_id', $id)->find();
        if (!$dataInfo) {
            $this->error = '暂无此数据';
            return false;
        }
        if(empty($model) && $model!='update'){
            $grantData = getFieldGrantData($userId);
            foreach ($grantData['crm_business'] as $key => $value) {
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
        $dataInfo['create_user_name'] = !empty($dataInfo['create_user_id_info']['realname']) ? $dataInfo['create_user_id_info']['realname'] : '';
        $dataInfo['owner_user_name'] = !empty($dataInfo['owner_user_id_info']['realname']) ? $dataInfo['owner_user_id_info']['realname'] : '';
        $dataInfo['type_id_info'] = db('crm_business_type')->where(['type_id' => $dataInfo['type_id']])->value('name');
        $dataInfo['status_id_info'] = db('crm_business_status')->where(['status_id' => $dataInfo['status_id']])->value('name');
        $dataInfo['customer_id_info'] = db('crm_customer')->where(['customer_id' => $dataInfo['customer_id']])->field('customer_id,name')->find();
        $dataInfo['customer_name'] = !empty($dataInfo['customer_id_info']['name']) ? $dataInfo['customer_id_info']['name'] : '';
        # 关注
        $starId = empty($userId) ? 0 : Db::name('crm_star')->where(['user_id' => $userId, 'target_id' => $id, 'type' => 'crm_business'])->value('star_id');
        $dataInfo['star'] = !empty($starId) ? 1 : 0;
        # 首要联系人
        $primaryId = Db::name('crm_contacts')->where(['contacts_id' => $dataInfo['contacts_id']])->value('contacts_id');
        $dataInfo['contacts_id'] = !empty($primaryId) && $this->getContactsAuth($primaryId) ? $primaryId : 0;
        # 处理日期格式
        $fieldModel = new \app\admin\model\Field();
        $datetimeField = $fieldModel->getFieldByFormType('crm_business', 'datetime'); //日期时间类型
        foreach ($datetimeField as $key => $val) {
            $dataInfo[$val] = !empty($dataInfo[$val]) ? date('Y-m-d H:i:s', $dataInfo[$val]) : null;
        }
        if($dataInfo['is_end']!=1){
            $dataInfo['statusRemark']=db('crm_business_log')->where(['business_id'=>$id,'is_end'=>$dataInfo['is_end']])->value('remark');
        }
        $dataInfo['next_time'] = !empty($dataInfo['next_time']) ? date('Y-m-d H:i:s', $dataInfo['next_time']) : null;
        $dataInfo['create_time'] = !empty($dataInfo['create_time']) ? date('Y-m-d H:i:s', $dataInfo['create_time']) : null;
        $dataInfo['update_time'] = !empty($dataInfo['update_time']) ? date('Y-m-d H:i:s', $dataInfo['update_time']) : null;
        $dataInfo['last_time'] = !empty($dataInfo['last_time']) ? date('Y-m-d H:i:s', $dataInfo['last_time']) : null;
        // 字段授权
        if (!empty($userId)) {
            $grantData = getFieldGrantData($userId);
            $userLevel = isSuperAdministrators($userId);
            foreach ($dataInfo AS $key => $value) {
                if (!$userLevel && !empty($grantData['crm_business'])) {
                    $status = getFieldGrantStatus($key, $grantData['crm_business']);

                    # 查看权限
                    if ($status['read'] == 0) unset($dataInfo[$key]);
                }
            }
        }
        return $dataInfo;
    }

    //根据IDs获取数组
    public function getDataByStr($idstr)
    {
        $idArr = stringToArray($idstr);
        if (!$idArr) {
            return [];
        }
        $list = Db::name('CrmBusiness')->where(['business_id' => ['in', $idArr]])->select();
        return $list;
    }

    /**
     * [商机漏斗]
     * @param
     * @return
     * @author Michael_xu
     */
    public function getFunnel($request)
    {
        $merge = $request['merge'] ?: 0;
        $perUserIds = $request['perUserIds'] ?: [];
        $adminModel = new \app\admin\model\Admin();
        $whereArr = $adminModel->getWhere($request, $merge, $perUserIds); //统计查询
        $userIds = $whereArr['userIds'];
        $between_time = $whereArr['between_time'];
        $where['owner_user_id'] = array('in', $userIds);
        $where['create_time'] = array('between', $between_time);
        //商机状态组
        $default_type_id = db('crm_business_type')->order('type_id asc')->value('type_id');
        $type_id = $request['type_id'] ? $request['type_id'] : $default_type_id;
        $statusList = db('crm_business_status')->where(['type_id' => $type_id])->select();
        $map = [];
        $map['create_time'] = $where['create_time'];
        $map['owner_user_id'] = ['in', $userIds];
        $map['type_id'] = $type_id;
        
        $sql_a = CrmBusinessModel::field([
            'SUM(CASE WHEN is_end = 1 THEN money ELSE 0 END) AS sum_ying',
            'SUM(CASE WHEN is_end = 2 THEN money ELSE 0 END) AS sum_shu',
            'type_id'
        ])
            ->where($map)
            ->fetchSql()
            ->find();
        $res_a = queryCache($sql_a, 200);
        $sql = CrmBusinessModel::field([
            "status_id",
            'COUNT(*)' => 'count',
            'SUM(`money`)' => 'sum'
        ])
            ->where($where)
            ->whereNotIn('is_end', '1,2,3')
            ->group('status_id')
            ->fetchSql()
            ->select();
        $res = queryCache($sql, 200);
        $res = array_column($res, null, 'status_id');
        
        $sum_money = 0;
        $count = 0; # 商机数总和
        $moneyCount = 0; # 金额总和
        foreach ($statusList as $k => $v) {
            $v['count'] = $res[$v['status_id']]['count'] ?: 0;
            $v['money'] = $res[$v['status_id']]['sum'] ?: 0;
            $v['status_name'] = $v['name'];
            
            $statusList[$k] = $v;
            
            $sum_money += $v['money'];
            $moneyCount += $v['money'];
            $count += $v['count'];
        }
        
        $data['list'] = $statusList;
        $data['sum_ying'] = $res_a[0]['sum_ying'] ?: 0;
        $data['sum_shu'] = $res_a[0]['sum_shu'] ?: 0;
        $data['sum_money'] = $sum_money ?: 0;
        $data['total'] = ['name' => '合计', 'money_count' => $moneyCount, 'count' => $count];
        
        return $data ?: [];
    }

    /**
     * [商机转移]
     * @param ids 商机ID数组
     * @param owner_user_id 变更负责人
     * @param is_remove 1移出，2转为团队成员
     * @return
     * @author Michael_xu
     */
    public function transferDataById($ids, $owner_user_id, $type = 1, $is_remove)
    {
        $settingModel = new \app\crm\model\Setting();
        $errorMessage = [];
        foreach ($ids as $id) {
            $businessInfo = db('crm_business')->where(['business_id' => $id])->find();
            //团队成员
            $teamData = [];
            $teamData['type'] = $type; //权限 1只读2读写
            $teamData['user_id'] = [$businessInfo['owner_user_id']]; //协作人
            $teamData['types'] = 'crm_business'; //类型
            $teamData['types_id'] = $id; //类型ID
            $teamData['is_del'] = ($is_remove == 1) ? 1 : '';
            $res = $settingModel->createTeamData($teamData);

            $data = [];
            $data['owner_user_id'] = $owner_user_id;
            $data['update_time'] = time();
            if (!db('crm_business')->where(['business_id' => $id])->update($data)) {
                $errorMessage[] = '商机：' . $businessInfo['name'] . '"转移失败，错误原因：数据出错；';
                continue;
            } else {
                $businessArray = [];
                $teamBusiness = db('crm_business')->field(['owner_user_id', 'ro_user_id', 'rw_user_id'])->where('business_id', $id)->find();
                if (!empty($teamBusiness['ro_user_id'])) {
                    $businessRo = arrayToString(array_diff(stringToArray($teamBusiness['ro_user_id']), [$teamBusiness['owner_user_id']]));
                    $businessArray['ro_user_id'] = $businessRo;
                }
                if (!empty($teamBusiness['rw_user_id'])) {
                    $businessRo = arrayToString(array_diff(stringToArray($teamBusiness['rw_user_id']), [$teamBusiness['owner_user_id']]));
                    $businessArray['rw_user_id'] = $businessRo;
                }
                db('crm_business')->where('business_id', $id)->update($businessArray);
            }
        }
        if ($errorMessage) {
            return $errorMessage;
        } else {
            return true;
        }
    }

    /**
     * [商机统计]
     * @param
     * @return
     */
    public function getTrendql($map)
    {
        $prefix = config('database.prefix');
        $sql = "SELECT
					'{$map['type']}' AS type,
					'{$map['start_time']}' AS start_time,
					'{$map['end_time']}' AS end_time,
					IFNULL(
						(
							SELECT
								sum(money)
							FROM
								{$prefix}crm_business
							WHERE
								create_time BETWEEN {$map['start_time']} AND {$map['end_time']}
							AND owner_user_id IN ({$map['owner_user_id']})
						),
						0
					) AS business_money,
					IFNULL(
						count(business_id),
						0
					) AS business_num
				FROM
					{$prefix}crm_business
				WHERE
					create_time BETWEEN {$map['start_time']} AND {$map['end_time']}
					AND owner_user_id IN ({$map['owner_user_id']})";
        return $sql;
    }

    /**
     * [赢单机会转化率趋势分析]
     * @param
     * @return
     */
    public function getWinSql($map)
    {
        $prefix = config('database.prefix');
        $sql = "SELECT
					'{$map['type']}' AS type,
					'{$map['start_time']}' AS start_time,
					'{$map['end_time']}' AS end_time,
					IFNULL(
						(
							SELECT
								count(business_id)
							FROM
								{$prefix}crm_business
							WHERE
								create_time BETWEEN {$map['start_time']} AND {$map['end_time']}
							AND owner_user_id IN ({$map['owner_user_id']})
							AND is_end = 1 
						),
						0
					) AS business_end,
					IFNULL(
						count(business_id),
						0
					) AS business_num
				FROM
					{$prefix}crm_business
				WHERE
					create_time BETWEEN {$map['start_time']} AND {$map['end_time']}
					AND owner_user_id IN ({$map['owner_user_id']})";
        return $sql;
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
        # 商机
        $business = Db::name('crm_business')->field(['create_user_id' ,'owner_user_id', 'create_time', 'update_time', 'last_time'])->where('business_id', $id)->find();
        # 创建人
        $realname = Db::name('admin_user')->where('id', $business['create_user_id'])->value('realname');
        
        # zjf   20210726
        $userModel   = new \app\admin\model\User();
        $ownerUserInfo = $userModel->getUserById($business['owner_user_id']);
        # 负责人部门
        $ownerStructureName = $ownerUserInfo['structure_name'];
        # 负责人
        $ownerUserName = $ownerUserInfo['realname'];
        return [
            'create_user_id' => $realname,
            'owner_user_id' => $ownerUserName,
            'create_time' => date('Y-m-d H:i:s', $business['create_time']),
            'update_time' => date('Y-m-d H:i:s', $business['update_time']),
            'last_time' => !empty($business['last_time']) ? date('Y-m-d H:i:s', $business['last_time']) : '',
            'owner_user_structure_name' => $ownerStructureName
        ];
    }

    /**
     * 判断联系人详情权限 todo 客户模块也在用，以后抽成一个公共的方法
     *
     * @param $contactsId
     * @return bool
     */
    private function getContactsAuth($contactsId)
    {
        $ownerUserId = db('crm_contacts')->where('contacts_id', $contactsId)->value('owner_user_id');

        $authUserIds = (new \app\admin\model\User())->getUserByPer('crm', 'contacts', 'read');

        return in_array($ownerUserId, $authUserIds);
    }
}
