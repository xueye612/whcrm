<?php
//权限控制
\think\Hook::add('check_auth', 'app\\common\\behavior\\AuthenticateBehavior');

use think\Db;

/**
 * 处理相关团队
 * @param types 类型
 * @param types 类型ID
 * @param type  权限 1只读2读写
 * @param user_id [array] 协作人
 * @param is_del 1 移除操作, 2编辑操作, 3添加操作
 * @param owner_user_id 操作人
 * @param is_module 相关 1相关，不进行数据权限判断
 * @author
 */
function teamUserId($param, $types, $types_id, $type, $user_id, $is_del, $owner_user_id, $is_module = 0)
{
    $userModel = new \app\admin\model\User();
    $authIds = [];
    switch ($types) {
        case 'crm_leads' :
            $data_name = 'leads_id';
            $authIds = $userModel->getUserByPer('crm', 'leads', 'teamsave');
            break;
        case 'crm_customer' :
            $data_name = 'customer_id';
            $authIds = $userModel->getUserByPer('crm', 'customer', 'teamsave');
            break;
        case 'crm_contacts' :
            $data_name = 'contacts_id';
            $authIds = $userModel->getUserByPer('crm', 'contacts', 'teamsave');
            break;
        case 'crm_business' :
            $data_name = 'business_id';
            $authIds = $userModel->getUserByPer('crm', 'business', 'teamsave');
            break;
        case 'crm_contract' :
            $data_name = 'contract_id';
            $authIds = $userModel->getUserByPer('crm', 'contract', 'teamsave');
            break;
        case 'crm_receivables' :
            $data_name = 'receivables_id';
            $authIds = $userModel->getUserByPer('crm', 'receivables', 'teamsave');
            break;
    }
    if (!is_array($types_id) && $types_id) {
        $types_id = [$types_id];
    }
    $errorMessage = [];
    foreach ($types_id as $k => $v) {
        if ($types == 'crm_receivables') {
            $resData = db($types)->where([$data_name => $v])->field('number as name,owner_user_id,rw_user_id,ro_user_id')->find();
        } else {
            $resData = db($types)->where([$data_name => $v])->field('name,owner_user_id,rw_user_id,ro_user_id')->find();
        }
        
        if (!in_array($resData['owner_user_id'], $authIds) && $resData['owner_user_id'] && $is_module !== 1) {
            $errorMessage[] = $resData['name'] . '处理团队操作失败，错误原因：无权限';
            continue;
        }
        $type = $type ?: 1;
        $data = [];
        //读写
        $old_rw_user_id = stringToArray($resData['rw_user_id']) ?: []; //去重
        //只读
        $old_ro_user_id = stringToArray($resData['ro_user_id']) ?: []; //去重
        if ($is_del == 1) {
            $all_rw_user_id = $old_rw_user_id ? array_diff($old_rw_user_id, $user_id) : ''; // 差集
            $data['rw_user_id'] = $all_rw_user_id ? arrayToString($all_rw_user_id) : ''; //去空
            
            $all_ro_user_id = $old_ro_user_id ? array_diff($old_ro_user_id, $user_id) : ''; // 差集
            $data['ro_user_id'] = $all_ro_user_id ? arrayToString($all_ro_user_id) : ''; //去空           
        } elseif ($is_del == 2) {
            if ($type == 2) {
                $all_ro_user_id = $old_ro_user_id ? array_diff($old_ro_user_id, $user_id) : []; // 差集
                $all_rw_user_id = $old_rw_user_id ? array_merge($old_rw_user_id, $user_id) : $user_id; // 合并
            } else {
                $all_rw_user_id = $old_rw_user_id ? array_diff($old_rw_user_id, $user_id) : []; // 差集
                $all_ro_user_id = $old_ro_user_id ? array_merge($old_ro_user_id, $user_id) : $user_id; // 合并
            }
            $data['rw_user_id'] = $all_rw_user_id ? arrayToString($all_rw_user_id) : ''; //去空
            $data['ro_user_id'] = $all_ro_user_id ? arrayToString($all_ro_user_id) : ''; //去空         
        } else {
            $del_ro_user_id = []; //需要删除的只读
            $del_rw_user_id = []; //需要删除的读写
            foreach ($user_id as $key => $val) {
                if (in_array($val, $old_ro_user_id) && !in_array($val, $old_rw_user_id) && $type == 2) {
                    $del_ro_user_id[] = $val;
                }
                if (in_array($val, $old_rw_user_id) && !in_array($val, $old_ro_user_id) && $type == 1) {
                    $del_rw_user_id[] = $val;
                }
            }
            if ($type == 2) {
                $all_rw_user_id = $old_rw_user_id ? array_diff(array_merge($old_rw_user_id, $user_id), $del_rw_user_id) : $user_id; // 合并
                $all_ro_user_id = $old_ro_user_id ? array_diff($old_ro_user_id, $del_ro_user_id) : $user_id; // 合并
                $data['rw_user_id'] = $all_rw_user_id ? arrayToString($all_rw_user_id) : ''; //去空 
                if ($del_ro_user_id) {
                    $data['ro_user_id'] = $all_ro_user_id ? arrayToString($all_ro_user_id) : ''; //去空         
                }
            } else {
                $all_rw_user_id = $old_rw_user_id ? array_diff($old_rw_user_id, $del_rw_user_id) : $user_id; // 合并
                $all_ro_user_id = $old_ro_user_id ? array_diff(array_merge($old_ro_user_id, $user_id), $del_ro_user_id) : $user_id; // 合并                
                $data['ro_user_id'] = $all_ro_user_id ? arrayToString($all_ro_user_id) : ''; //去空 
                if ($del_rw_user_id) {
                    $data['rw_user_id'] = $all_rw_user_id ? arrayToString($all_rw_user_id) : ''; //去空         
                }
            }
        }
        $res = !empty($param['user_id']) ?$param['user_id']  :[];
        $types_data = ['crm_leads' => 6, 'crm_customer' => 1, 'crm_contacts' => 2, 'crm_business' => 3, 'crm_contract' => 4, 'crm_receivables' => 5];
        $target_time = $param['target_time'];
        $request = [];
        $hasFinanceAuth = array_key_exists('finance_auth', $param);
        $financeAuth = $hasFinanceAuth ? (int)$param['finance_auth'] : null;
        foreach ($res as $val) {
            $request['team_user_id'] = $val;
            $request['target_time'] = $target_time;
            $request['auth'] = $type;
            $request['target_id'] = $v;
            if ($hasFinanceAuth) {
                $request['finance_auth'] = $financeAuth;
            }
            $dataInfo = db('crm_team')->where(['target_id' => $v, 'types' => $types_data[$types],'team_user_id'=>$val])->find();
            if ($dataInfo) {
                $res = db('crm_team')->where(['target_id' => $v, 'types' => $types_data[$types],'team_user_id'=>$val])->update($request);
            } else {
                $request['types'] = $types_data[$types];
                if (!$hasFinanceAuth) {
                    $request['finance_auth'] = 1;
                }
                $res = db('crm_team')->insert($request);
            }
        }
        
        $upData = db($types)->where([$data_name => $v])->update($data);
        if (!$upData && !$res) {
            $errorMessage[] = $resData['name'] . '处理团队操作失败';
        }
    }
    return $errorMessage ?: 1;
}

//根据时间段获取所包含的年份
function getYearByTime($start_time, $end_time)
{
    $yearArr = [];
    $monthArr = monthList($start_time, $end_time);
    foreach ($monthArr as $v) {
        $yearArr[date('Y', $v)] = date('Y', $v);
    }
    return $yearArr;
}

//根据时间段获取所包含的月份
function getmonthByTime($start_time, $end_time)
{
    $monthList = [];
    $monthArr = monthList($start_time, $end_time);
    foreach ($monthArr as $v) {
        $monthList[date('Y', $v)][] = date('m', $v);
    }
    return $monthList;
}

function encrypt($data, $key)
{
    header('Content-type:text/html;charset=utf-8');
    $key = md5($key);
    $x = 0;
    $len = mb_strlen($data);
    $l = mb_strlen($key);
    for ($i = 0; $i < $len; $i++) {
        if ($x == $l) {
            $x = 0;
        }
        $char .= $key{$x};
        $x++;
    }
    for ($i = 0; $i < $len; $i++) {
        $str .= chr(ord($data{$i}) + (ord($char{$i})) % 256);
    }
    return base64_encode($str);
}

/**
 * [对加密的数据进行解密]
 * @E-mial wuliqiang_aa@163.com
 * @TIME   2017-04-07
 * @WEB    http://blog.iinu.com.cn
 * @param  [数据] $data [已经进行加密的数据]
 * @param  [密钥] $key  [解密的唯一方法]
 */
function decrypt($data, $key = '72-crm')
{
    header('Content-type:text/html;charset=utf-8');
    $key = md5($key);
    $x = 0;
    $data = base64_decode($data);
    $len = mb_strlen($data);
    $l = mb_strlen($key);
    for ($i = 0; $i < $len; $i++) {
        if ($x == $l) {
            $x = 0;
        }
        $char .= mb_substr($key, $x, 1);
        $x++;
    }
    for ($i = 0; $i < $len; $i++) {
        if (ord(mb_substr($data, $i, 1)) < ord(mb_substr($char, $i, 1))) {
            $str .= chr((ord(mb_substr($data, $i, 1)) + 256) - ord(mb_substr($char, $i, 1)));
        } else {
            $str .= chr(ord(mb_substr($data, $i, 1)) - ord(mb_substr($char, $i, 1)));
        }
    }
    return $str;
}

function getFieldData($list,$types,$user_id){
    $fieldModel  = new \app\admin\model\Field();
    $indexField = $fieldModel->getIndexField($types, $user_id, 1) ? : array('name'); // 列表展示字段
    $userField = $fieldModel->getFieldByFormType($types, 'user'); // 人员类型
    $structureField = $fieldModel->getFieldByFormType($types, 'structure'); // 部门类型
    $datetimeField = $fieldModel->getFieldByFormType($types, 'datetime'); // 日期时间类型
    $booleanField = $fieldModel->getFieldByFormType($types, 'boolean_value'); // 布尔值类型字段
    $dateIntervalField = $fieldModel->getFieldByFormType($types, 'date_interval'); // 日期区间类型字段
    $positionField = $fieldModel->getFieldByFormType($types, 'position'); // 地址类型字段
    $handwritingField = $fieldModel->getFieldByFormType($types, 'handwriting_sign'); // 手写签名类型字段
    $locationField = $fieldModel->getFieldByFormType($types, 'location'); // 定位类型字段
    $boxField = $fieldModel->getFieldByFormType($types, 'checkbox'); // 多选类型字段
    $floatField = $fieldModel->getFieldByFormType($types, 'floatnumber'); // 货币类型字段
    $db_id=substr($types,strripos($types,"_")+1).'_id';;
    $extraData = [];
    $business_id_list = !empty($list) ? array_column($list, $db_id) : [];
    $extraList = db($types.'_data')->whereIn($db_id, $business_id_list)->select();
    foreach ($extraList AS $key => $value) {
        $extraData[$value[$db_id]][$value['field']] = $value['content'];
    }
    $grantData = getFieldGrantData($user_id);
    foreach ($grantData[$types] as $key => $value) {
        foreach ($value as $ke => $va) {
            if($va['maskType']!=0){
                $fieldGrant[$ke]['maskType'] = $va['maskType'];
                $fieldGrant[$ke]['form_type'] = $va['form_type'];
                $fieldGrant[$ke]['field'] = $va['field'];
            }
        }
    }
    foreach ($list AS $k => $v) {
        # 用户类型字段
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
            $list[$k][$val] = ['url' => !empty($handwritingData) ? getFullPath($handwritingData) : null];
        }
        // 定位类型字段
        foreach ($locationField as $key => $val) {
            $list[$k][$val] = !empty($extraData[$v['customer_id']][$val]) ? json_decode($extraData[$v['customer_id']][$val], true) : null;
        }
        // 多选框类型字段
        foreach ($boxField as $key => $val) {
            $list[$k][$val] = !empty($v[$val]) ? trim($v[$val], ',') : null;
        }
        // 货币类型字段
        foreach ($floatField as $key => $val) {
            $list[$k][$val] = $v[$val] != '0.00' ? (string)$v[$val] : null;
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
    }
    return $list;
}