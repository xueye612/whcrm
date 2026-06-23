<?php
/**
 * 发票逻辑类
 *
 * @author qifan
 * @date 2020-12-07
 */

namespace app\crm\logic;

use app\admin\controller\ApiCommon;
use app\admin\model\Common;
use app\crm\model\Invoice;
use think\Db;

class InvoiceLogic
{
    private $invoiceType = ['增值税专用发票', '增值税普通发票', '国税通用机打发票', '地税通用机打发票', '收据'];
    private $check_status = ['待审核', '审核中', '审核通过', '审核未通过', '撤回'];

    /**
     * 列表
     *
     * @param $param
     * @param false $search
     * @return array
     * @throws \think\exception\DbException
     */
    public function index($param)
    {
        $fieldModel = new \app\admin\model\Field();
        //列表展示字段
        $field = $fieldModel->getIndexField('crm_invoice', $param['user_id'], 1) ?: array('name');
        if (!empty($param['is_excel']) && !empty($param['invoice_id'])) {
            $param['invoice_id'] = ['in', arrayToString($param['invoice_id'])];
        }
        $getCount = $param['getCount'];
        $userId = $param['user_id'];
        $invoiceIdArray = $param['invoiceIdArray']; // 待办事项提醒参数
        $dealt = $param['dealt'];
        $order_field = $param['order_field'];
        $order_type = $param['order_type'];
        $is_excel = $param['is_excel'];
        $search = $param['search'];
        $scene_id = $param['scene_id'];
        $isMessage = !empty($param['isMessage']);
        $common = new Common();

        unset($param['getCount']);
//        unset($param['limit']); 导出使用 暂未发现为何去掉分页参数
//        unset($param['page']);
        unset($param['user_id']);
        unset($param['invoiceIdArray']);
        unset($param['dealt']);
        unset($param['search']);
        unset($param['order_field']);
        unset($param['order_type']);
        unset($param['is_excel']);
        unset($param['scene_id']);
        unset($param['isMessage']);
        $request = $common->fmtRequest($param);
        $where = [];

        # 高级搜索
        $requestMap = !empty($request['map']) ? $request['map'] : [];
        unset($requestMap['search']);

        # 场景
        $sceneMap = [];
        $sceneModel = new \app\admin\model\Scene();
        if ($scene_id) {
            //自定义场景
            $sceneMap = $sceneModel->getDataById($scene_id, $userId, 'invoice') ?: [];
        } else {
            //默认场景
            $sceneMap = $sceneModel->getDefaultData('crm_invoice', $userId) ?: [];
        }
        //普通筛选
        if ($search) {
            # 处理基本参数
            $searchWhere = function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereLike('customer.name', '%' . $search . '%');
                })->whereOr(function ($query) use ($search) {
                    $query->whereLike('contract.num', '%' . $search . '%');
                })->whereOr(function ($query) use ($search) {
                    $query->whereLike('invoice.invoice_apple_number', '%' . $search . '%');
                });
            };
        }
        # 合并高级搜索和场景的查询条件
        $map = $requestMap ? array_merge($sceneMap, $requestMap) : $sceneMap;
        $map = where_arr($map, 'crm', 'invoice', 'index');
        # 替换掉字段前缀，不修改公共函数
        foreach ($map as $key => $value) {
            $k = str_replace('invoice.', '', $key);

            $where[$k] = $value;
        }
        # 待办事项查询参数
        $dealtWhere = [];
        if (!empty($invoiceIdArray)) $dealtWhere['invoice.invoice_id'] = ['in', $invoiceIdArray];

        # 权限，不是待办事项，则加上列表权限
        $auth = [];
        $userModel = new \app\admin\model\User();
        $a = 'index';
        if ($is_excel) $a = 'excelExport';
        $auth_user_ids = $userModel->getUserByPer('crm', 'invoice', $a);
        if (empty($dealt)) {
            //过滤权限
            if (isset($map['invoice.owner_user_id']) && $map['invoice.owner_user_id'][0] != 'like') {
                if (!is_array($map['invoice.owner_user_id'][1])) {
                    $map['invoice.owner_user_id'][1] = [$map['invoice.owner_user_id'][1]];
                }
                if (in_array($map['invoice.owner_user_id'][0], ['neq', 'notin'])) {
                    $auth_user_ids = array_diff($auth_user_ids, $map['invoice.owner_user_id'][1]) ?: [];    //取差集
                } else {
                    $auth_user_ids = array_intersect($map['invoice.owner_user_id'][1], $auth_user_ids) ?: [];    //取交集
                }
                unset($map['invoice.owner_user_id']);
            }
        }
        $auth_user_ids = array_merge(array_unique(array_filter($auth_user_ids))) ?: ['-1'];
        // 待办事项的待审核发票不一定是自己创建的
        if (!$isMessage) {
            $auth['invoice.owner_user_id'] = ['in', $auth_user_ids];
        }
        if ($order_type && $order_field) {
            $order = $fieldModel->getOrderByFormtype('crm_invoice', 'invoice', $order_field, $order_type);
        } else {
            $order = 'invoice.update_time desc';
        }
        $join = [
            ['__CRM_CUSTOMER__ customer', 'customer.customer_id=invoice.customer_id', 'LEFT'],
            ['__CRM_CONTRACT__ contract', 'contract.contract_id=invoice.contract_id', 'LEFT'],
            ['__ADMIN_USER__ user', 'user.id=invoice.owner_user_id', 'LEFT'],
            ['__ADMIN_USER__ u', 'u.id=invoice.create_user_id', 'LEFT'],
        ];
        # 查询数据
        $list = db('crm_invoice')
            ->alias('invoice')
            ->join($join)
            ->field(array_merge($field, [
                'customer.name' => 'customer_name',
                'user.realname' => 'owner_user_name',
                'contract.num' => 'contract_num',
                'contract.money' => 'contract_money',
                'u.realname' => 'create_user_name',
            ]))->where($auth)
            ->where($map)
            ->where($dealtWhere)
            ->where($searchWhere)
            ->limit($request['offset'], $request['length'])
            ->orderRaw($order)
            ->select();

        $dataCount = db('crm_invoice')
            ->alias('invoice')
            ->join($join)
            ->field(array_merge($field, [
                'customer.name' => 'customer_name',
                'user.realname' => 'owner_user_name',
                'contract.num' => 'contract_num',
                'contract.money' => 'contract_money',
                'u.realname' => 'create_user_name',
            ]))->where($auth)
            ->where($map)
            ->where($dealtWhere)->where($searchWhere)->count();

        $userField = $fieldModel->getFieldByFormType('crm_invoice', 'user'); //人员类型
        $structureField = $fieldModel->getFieldByFormType('crm_invoice', 'structure');  //部门类型
        $datetimeField = $fieldModel->getFieldByFormType('crm_invoice', 'datetime'); //日期时间类型
        $booleanField = $fieldModel->getFieldByFormType('crm_invoice', 'boolean_value'); //布尔值
        $dateIntervalField = $fieldModel->getFieldByFormType('crm_invoice', 'date_interval'); // 日期区间类型字段
        $positionField = $fieldModel->getFieldByFormType('crm_invoice', 'position'); // 地址类型字段
        $handwritingField = $fieldModel->getFieldByFormType('crm_invoice', 'handwriting_sign'); // 手写签名类型字段
        $locationField = $fieldModel->getFieldByFormType('crm_invoice', 'location'); // 定位类型字段
        $boxField = $fieldModel->getFieldByFormType('crm_customer', 'checkbox'); // 多选类型字段
        $floatField = $fieldModel->getFieldByFormType('crm_invoice', 'floatnumber'); // 货币类型字段
//        $fieldGrant = db('admin_field_mask')->where('types', 'invoice')->select();
        # 扩展数据
        $extraData = [];
        $invoice_id_list = !empty($list) ? array_column($list, 'invoice_id') : [];
        $extraList = db('crm_invoice_data')->whereIn('invoice_id', $invoice_id_list)->select();
        foreach ($extraList as $key => $value) {
            $extraData[$value['invoice_id']][$value['field']] = $value['content'];
        }
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
        # 处理发票类型
        foreach ($list as $key => $value) {
            $list[$key]['check_status_info'] = $this->check_status[$value['check_status']];
            $list[$key]['invoice_status'] = !empty($value['invoice_status']) ? '已开票' : '未开票';
            $list[$key]['create_time'] = !empty($value['create_time']) ? date('Y-m-d H:i:s', $value['create_time']) : null;
            $list[$key]['update_time'] = !empty($value['update_time']) ? date('Y-m-d H:i:s', $value['update_time']) : null;
            # 系统字段  负责人部门   zjf  20210726
            $ownerUserIdInfo = $userModel->getUserById($value['owner_user_id']);
            $list[$key]['owner_user_structure_name'] = $ownerUserIdInfo['structure_name'];
            foreach ($userField as $k => $val) {
                $usernameField = !empty($value[$val]) ? db('admin_user')->whereIn('id', stringToArray($value[$val]))->column('realname') : [];
                $list[$key][$val] = implode($usernameField, ',');
            }
            foreach ($structureField as $k => $val) {
                $structureNameField = !empty($value[$val]) ? db('admin_structure')->whereIn('id', stringToArray($value[$val]))->column('name') : [];
                $list[$key][$val] = implode($structureNameField, ',');
            }
            foreach ($datetimeField as $k => $val) {
                $list[$key][$val] = !empty($value[$val]) ? date('Y-m-d H:i:s', $value[$val]) : null;
            }
            foreach ($booleanField as $k => $val) {
                $list[$key][$val] = !empty($value[$val]) ? (string)$value[$val] : '0';
            }
            // 处理日期区间类型字段的格式
            foreach ($dateIntervalField as $k => $val) {
                $list[$key][$val] = !empty($extraData[$value['invoice_id']][$val]) ? json_decode($extraData[$value['invoice_id']][$val], true) : null;
            }
            // 处理地址类型字段的格式
            foreach ($positionField as $k => $val) {
                $list[$key][$val] = !empty($extraData[$value['invoice_id']][$val]) ? json_decode($extraData[$value['invoice_id']][$val], true) : null;
            }
            // 手写签名类型字段
            foreach ($handwritingField as $k => $val) {
                $handwritingData = !empty($value[$val]) ? db('admin_file')->where('file_id', $value[$val])->value('file_path') : null;
                $list[$key][$val] = ['url' => !empty($handwritingData) ? getFullPath($handwritingData) : null];
            }
            // 定位类型字段
            foreach ($locationField as $k => $val) {
                $list[$key][$val] = !empty($extraData[$value['invoice_id']][$val]) ? json_decode($extraData[$value['invoice_id']][$val], true) : null;
            }

            // 多选框类型字段
            foreach ($boxField as $k => $val) {
                $list[$key][$val] = !empty($value[$val]) ? trim($value[$val], ',') : null;
            }
            //货币类型字段
            foreach ($floatField as $k => $val) {
                $list[$key][$val] = $value[$val] != '0.00' ? (string)$value[$val] : null;
            }
            //掩码相关类型字段
            foreach ($fieldGrant AS $key => $v){
                //掩码相关类型字段
                if ($v['maskType']!=0 && $v['form_type'] == 'mobile') {
                    $pattern = "/(1[3458]{1}[0-9])[0-9]{4}([0-9]{4})/i";
                    $rs = preg_replace($pattern, "$1****$2", $value[$v['field']]);
                    $list[$k][$v['field']] = !empty($value[$v['field']]) ? (string)$rs : null;
                } elseif ($v['maskType']!=0 && $v['form_type'] == 'email') {
                    $email_array = explode("@", $value[$v['field']]);
                    $prevfix = (strlen($email_array[0]) < 4) ? "" : substr($value[$v['field']], 0, 2); //邮箱前缀
                    $str = preg_replace('/([\d\w+_-]{0,100})@/', "***@", $value[$v['field']], -1, $count);
                    $rs = $prevfix . $str;
                    $list[$k][$v['field']] = !empty($value[$v['field']]) ?$rs: null;
                } elseif ($v['maskType']!=0 && in_array($v['form_type'],['position','floatnumber'])) {
                    $list[$k][$v['field']] = !empty($value[$v['field']]) ? (string)substr_replace($value[$v['field']], '*****',0,strlen($value[$v['field']])) : null;
                }
            }
            $data = [];
            $data['list'] = $list ?: [];
            $data['dataCount'] = $dataCount ?: 0;
            return $data;
        }
    }

    /**
     * 创建
     *
     * @param $param
     * @return Invoice|int|string
     */
    public
    function save($param) 
    {
        return db('crm_invoice')->insert($param, false, true);
    }

    /**
     * 详情
     *
     * @param $invoiceId
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public
    function read($invoiceId, $isUpdate)
    {
        $apiCommon = new ApiCommon();

        $userId = $apiCommon->userInfo['id'];
        $result = [];
        $dataObject = Invoice::with(['toCustomer', 'toContract'])->where('invoice_id', $invoiceId)->find();

        if (empty($dataObject)) return $result;

        $dataArray = $dataObject->toArray();

        if (!empty($isUpdate)) return $dataArray;
        $grantData = getFieldGrantData($userId);
        foreach ($grantData['crm_leads'] as $key => $value) {
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
                $rs = preg_replace($pattern, "$1****$2", $dataArray[$val['field']]);
                $dataArray[$val['field']] = !empty($dataInfo[$val['field']]) ? (string)$rs : null;
            } elseif ($val['maskType']!=0 && $val['form_type'] == 'email') {
                $email_array = explode("@", $dataArray[$val['field']]);
                $prevfix = (strlen($email_array[0]) < 4) ? "" : substr($dataArray[$val['field']], 0, 2); //邮箱前缀
                $str = preg_replace('/([\d\w+_-]{0,100})@/', "***@", $dataArray[$val['field']], -1, $count);
                $rs = $prevfix . $str;
                $dataArray[$val['field']] = !empty($dataInfo[$val['field']]) ?$rs: null;
            } elseif ($val['maskType']!=0 && in_array($val['form_type'],['position','floatnumber'])) {
                $dataArray[$val['field']] = !empty($dataInfo[$val['field']]) ? (string)substr_replace($dataArray[$val['field']], '*****',0,strlen($dataArray[$val['field']])) : null;
            }
        }
        # 主键ID
        $result['invoice_id'] = $dataArray['invoice_id'];

        # 是否显示撤回按钮
        $result['isShowRecall'] = 0;
        if ($userId == $dataArray['owner_user_id'] && $dataArray['check_status'] == 0) $result['isShowRecall'] = 1;

        $result['customer_name'] = $dataArray['customer_name'];     # 客户名称
        $result['invoice_money'] = $dataArray['invoice_money'];     # 开票金额
        $result['invoice_number'] = $dataArray['invoice_number'];    # 发票号码
        $result['real_invoice_date'] = $dataArray['real_invoice_date']; # 开票日期
        $result['flow_id'] = $dataArray['flow_id'];           # 审核ID
        $check = ['0' => '待审核', '1' => '审核中', '2' => '审核通过', '3' => '审核未通过', '4' => '撤销', '5' => '草稿(未提交)', '6' => '作废'];
        # 基本信息
        $result['essential'] = [
            'invoice_apple_number' => $dataArray['invoice_apple_number'],
            'customer_name' => $dataArray['customer_name'],
            'contract_num' => $dataArray['contract_number'],
            'contract_money' => $dataArray['contract_money'],
            'invoice_money' => $dataArray['invoice_money'],
            'invoice_date' => $dataArray['invoice_date'],
            'invoice_type' => $dataArray['invoice_type'],
            'remark' => $dataArray['remark'],
            'create_user_name' => db('admin_user')->where('id', $dataArray['create_user_id'])->value('realname'),
            'owner_user_name' => db('admin_user')->where('id', $dataArray['owner_user_id'])->value('realname'),
            'create_time' => $dataArray['create_time'],
            'update_time' => $dataArray['update_time'],
            'invoice_number' => $dataArray['invoice_number'],
            'real_invoice_date' => $dataArray['real_invoice_date'],
            'customer_id' => $dataArray['customer_id'],
            'check_status' => $check[$dataArray['check_status']]
        ];

        # 发票信息
        $result['invoice'] = [
            'title_type' => $dataArray['title_type'],
            'deposit_bank' => $dataArray['deposit_bank'],
            'invoice_title' => $dataArray['invoice_title'],
            'tax_number' => $dataArray['tax_number'],
            'deposit_account' => $dataArray['deposit_account'],
            'deposit_address' => $dataArray['deposit_address'],
            'phone' => $dataArray['phone']
        ];

        # 邮寄信息
        $result['posting'] = [
            'contacts_name' => $dataArray['contacts_name'],
            'contacts_mobile' => $dataArray['contacts_mobile'],
            'contacts_address' => $dataArray['contacts_address']
        ];

        return $result;
    }

    /**
     * 编辑
     *
     * @param $param
     * @return Invoice
     */
    public
    function update($param)
    {
        return Invoice::update($param);
    }

    /**
     * 删除
     *
     * @param $where
     * @return int
     */
    public
    function delete($where)
    {
        return Invoice::destroy($where);
    }

    /**
     * 获取审批状态
     *
     * @param $invoiceId
     * @param false $isDelete
     * @return bool|int|mixed|\PDOStatement|string|\think\Collection|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public
    function getExamineStatus($invoiceId, $isDelete = false)
    {
        # 删除
        if ($isDelete) {
            return Invoice::field(['check_status'])->whereIn('invoice_id', $invoiceId)->select();
        }

        # 编辑
        return Invoice::where('invoice_id', $invoiceId)->value('check_status');
    }

    /**
     * 转移（变更负责人）
     *
     * @param $invoiceIds
     * @param $ownerUserId
     * @return Invoice
     */
    public
    function transfer($invoiceIds, $ownerUserId)
    {
        return Invoice::whereIn('invoice_id', $invoiceIds)->update(['owner_user_id' => $ownerUserId]);
    }

    /**
     * 设置开票
     *
     * @param $param
     * @return Invoice
     */
    public
    function setInvoice($param)
    {
        return Invoice::update($param);
    }

    /**
     * 获取发票审核信息
     *
     * @param $invoiceId
     * @return array|bool|\PDOStatement|string|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public
    function getExamineInfo($invoiceId)
    {
        $field = ['check_status', 'flow_id', 'order_id', 'check_user_id', 'flow_user_id', 'invoice_apple_number', 'owner_user_id', 'create_user_id'];

        return Invoice::field($field)->where('invoice_id', $invoiceId)->find();
    }

    /**
     * 设置审批信息
     *
     * @param $data
     * @return Invoice
     */
    public
    function setExamineInfo($data)
    {
        return Invoice::update($data);
    }

    /**
     * 添加撤销审核记录
     *
     * @param $invoiceId
     * @param $examineInfo
     * @param $realname
     * @param $content
     * @param $userId
     */
    public
    function createExamineRecord($invoiceId, $examineInfo, $realname, $content, $userId)
    {
        $data = [
            'types' => 'crm_invoice',
            'types_id' => $invoiceId,
            'flow_id' => $examineInfo['flow_id'],
            'order_id' => $examineInfo['order_id'],
            'check_user_id' => $userId,
            'check_time' => time(),
            'status' => 2,
            'content' => !empty($content) ? $content : $realname . ' 撤销了审核',
        ];

        Db::name('admin_examine_record')->insert($data);
    }

    /**
     * 检查发票编号是否重复
     *
     * @param $where
     * @return int|mixed|string|null
     */
    public
    function getInvoiceId($where)
    {
        return Db::name('crm_invoice')->where($where)->value('invoice_id');
    }
}