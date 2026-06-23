<?php
// +----------------------------------------------------------------------
// | Description: 审批流程
// +----------------------------------------------------------------------
// | Author: zjf
// +----------------------------------------------------------------------

namespace app\admin\controller;

use think\Hook;
use think\Request;
use think\Db;

class Examine extends ApiCommon
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
            'permission'=>[],
            'allow'=>['index','save','update','read','delete','enables','steplist','userlist','recordlist']            
        ];
        Hook::listen('check_auth',$action);
        $request = Request::instance();
        $a = strtolower($request->action());        
        if (!in_array($a, $action['permission'])) {
            parent::_initialize();
        }
        //权限判断
        $unAction = ['steplist','userlist','recordlist'];
        if (!in_array($a, $unAction) && !checkPerByAction('admin', 'examine_flow', 'index')) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code'=>102,'error'=>'无权操作']));
        }        
    } 

    /**
     * 审批流程列表
     * @author zjf
     * @return 
     */
    public function index()
    {
        $examinewModel = model('Examine');
        $param = $this->param;
        //过滤审批类型中关联的审批流
        // $param['types'] = ['neq','oa_examine'];
        $data = $examinewModel->getDataList($param);
        return resultArray(['data' => $data]);
    }

    /**
     * 审批流程详情
     * @author zjf
     * @param 
     * @return
     */
    public function read()
    {
        $examineModel = model('Examine');
        $param = $this->param;
        $res = $examineModel->getDataById($param['id']);
        if (!$res) {
            return resultArray(['error' => $examineFlowModel->getError()]);
        }
        return resultArray(['data' => $res]); 
    }

    /**
     * 添加审批流程
     *
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function save()
    {
        $examineModel = model('Examine');
        $param = $this->param;
        $userInfo = $this->userInfo;
$a = '{"examineName":"合同审批流程","label":1,"recheckType":1,"managerList":[14019],"remarks":"说明","dataList":[{"examineType":1,"name":"审批人0","examineErrorHandling":2,"roleId":null,"type":2,"userList":[32646,14019],"chooseType":null,"rangeType":null,"parentLevel":null},{"examineType":0,"name":"","conditionList":[{"conditionName":"条件1","sort":1,"conditionDataList":[{"name":"发起人","fieldName":"","type":0,"fieldId":0,"conditionType":8,"values":{"deptList":[],"roleList":[173447,173448,173449],"userList":[14019]}},{"name":"合同金额","fieldName":"money","type":6,"fieldId":1055246,"conditionType":6,"values":[1,1,1,2000]}],"examineDataList":[{"examineType":2,"name":"审批人1","examineErrorHandling":2,"roleId":null,"type":1,"userList":[14019],"chooseType":null,"rangeType":null,"parentLevel":3}]},{"conditionName":"条件2","sort":2,"conditionDataList":[{"name":"合同金额","fieldName":"money","type":6,"fieldId":1055246,"conditionType":2,"values":["2000"]}],"examineDataList":[{"examineType":0,"name":"","conditionList":[{"conditionName":"条件2-1","sort":1,"conditionDataList":[{"name":"合同金额","fieldName":"money","type":6,"fieldId":1055246,"conditionType":3,"values":["5000"]}],"examineDataList":[]},{"conditionName":"条件2-2","sort":2,"conditionDataList":[{"name":"合同金额","fieldName":"money","type":6,"fieldId":1055246,"conditionType":2,"values":["5000"]}],"examineDataList":[{"examineType":3,"name":"审批人2-2-1","examineErrorHandling":2,"roleId":173448,"type":2,"userList":[32646],"chooseType":null,"rangeType":null,"parentLevel":null}]}]},{"examineType":2,"name":"审批人2","examineErrorHandling":2,"roleId":null,"type":1,"userList":[14019],"chooseType":null,"rangeType":null,"parentLevel":3}]}]},{"examineType":2,"name":"业务审批1052","examineErrorHandling":2,"roleId":"","type":1,"userList":[],"chooseType":1,"rangeType":null,"parentLevel":3}],"examineId":1378287}';
$param = object_to_array(json_decode($a));
$param['examineId'] = 2;

        // 主表 审批表 信息
        db('examine')->where(['examine_id' => $param['examineId']])->update(['examine_name'=>$param['examineName'], 'label'=>$param['label'], 'remarks'=>$param['remarks'], 'recheck_type'=>$param['recheckType']]);
        
        // 删除 相关表数据
        $flow_ids = db('examine_flow')->where(['examine_id' => $param['examineId']])->column('flow_id');
        db('examine_flow')->where(['flow_id' => ['in', $flow_ids]])->delete();
        db('examine_condition')->where(['flow_id' => ['in', $flow_ids]])->delete();
        db('examine_condition_data')->where(['flow_id' => ['in', $flow_ids]])->delete();

        db('examine_flow_continuous_superior')->where(['flow_id' => ['in', $flow_ids]])->delete();
        db('examine_flow_member')->where(['flow_id' => ['in', $flow_ids]])->delete();
        db('examine_flow_optional')->where(['flow_id' => ['in', $flow_ids]])->delete();
        db('examine_flow_role')->where(['flow_id' => ['in', $flow_ids]])->delete();
        db('examine_flow_superior')->where(['flow_id' => ['in', $flow_ids]])->delete();
        
        // 审批管理员
        db('examine_manager_user')->where(['examine_id' => $param['examineId']])->delete();
        $managerUser = [];
        foreach ($param['managerList'] as $key => $value) {
            $arr['examine_id'] = $param['examineId'];
            $arr['user_id'] = $value;
            $arr['sort'] = $key;
            $managerUser[] = $arr;
        }
        db('examine_manager_user')->insertAll($managerUser);

        $create_time = date('Y-m-d H:i:s');
        $userId = $userInfo['id'];
        // 审批流程
        foreach ($param['dataList'] as $k => $v) {
            $condition_id = 0;
            // 处理流程   0 条件 1 指定成员 2 主管 3 角色 4 发起人自选 5 连续多级主管 7 发起人
            $flow = [];
            $flow['name'] = $v['name'];
            $flow['examine_id'] = $param['examineId'];
            $flow['examine_type'] = $v['examineType'];
            $flow['examine_error_handling'] = $v['examineErrorHandling'] ? : 1;
            $flow['condition_id'] = $condition_id;
            $flow['sort'] = $k;
            $flow['create_time'] = $create_time;
            $flow['create_user_id'] = $userId;
            $flowId = db('examine_flow')->insertGetId($flow);

            // 1 指定成员 2 主管 3 角色 4 发起人自选 5 连续多级主管 7 发起人
            $examine_flow = [];
            switch ($v['examineType']) {
                case '1' :
                    foreach ($v['userList'] as $key => $value) {
                        $examine_flow[$key]['sort'] = $key;
                        $examine_flow[$key]['type'] = $v['type'];
                        $examine_flow[$key]['user_id'] = $value;
                        $examine_flow[$key]['flow_id'] = $flowId;
                    }
                    db('examine_flow_member')->insertAll($examine_flow);
                    break;
                case '2' :
                    $examine_flow['flow_id'] = $flowId;
                    $examine_flow['parent_level'] = $v['parentLevel'];
                    $examine_flow['type'] = $v['type'];
                    db('examine_flow_superior')->insert($examine_flow);
                    break;
                case '3' :
                    $examine_flow['flow_id'] = $flowId;
                    $examine_flow['role_id'] = $v['roleId'];
                    $examine_flow['type'] = $v['type'];
                    db('examine_flow_role')->insert($examine_flow);
                    break;
                case '4' :
                    foreach ($v['userList'] as $key => $value) {
                        $examine_flow[$key]['sort'] = $key;
                        $examine_flow[$key]['type'] = $v['type'];
                        $examine_flow[$key]['user_id'] = $value;
                        $examine_flow[$key]['flow_id'] = $flowId;
                        $examine_flow[$key]['role_id'] = $v['roleId'];
                        $examine_flow[$key]['choose_type'] = $v['chooseType'];
                        $examine_flow[$key]['range_type'] = $v['rangeType'];
                    }
                    db('examine_flow_optional')->insertAll($examine_flow);
                    break;
                case '5' :
                    $examine_flow['flow_id'] = $flowId;
                    $examine_flow['role_id'] = $v['roleId'];
                    $examine_flow['max_level'] = $v['parentLevel'];
                    $examine_flow['type'] = $v['type'];
                    db('examine_flow_continuous_superior')->insert($examine_flow);
                    break;
                // case '7' :
                //     break;
            }
            
            foreach ($v['conditionList'] as $k1 => $v1) {
                // 处理条件
                $condition['condition_name'] = $v1['conditionName'];
                $condition['priority'] = $v1['sort'];
                $condition['create_time'] = $create_time;
                $condition['create_user_id'] = $userId;
                $condition['flow_id'] = $flowId;
                $condition_id = db('examine_condition')->insertGetId($condition);
                
                // 处理条件 扩展
                foreach ($v1['conditionDataList'] as $kc => $vc) {
                    $conditionDate = [];
                    $conditionDate['field_id'] = $vc['fieldId'];
                    $conditionDate['field_name'] = $vc['fieldName'] ? : '';
                    $conditionDate['condition_type'] = $vc['conditionType'];
                    $conditionDate['value'] = json_encode($vc['values']);
                    $conditionDate['name'] = $vc['name'];
                    $conditionDate['type'] = $vc['type'];
                    $conditionDate['condition_id'] = $condition_id;
                    $conditionDate['flow_id'] = $flowId;
                    $condition_data_id = db('examine_condition_data')->insertGetId($conditionDate);
                }
                if(!empty($v1['examineDataList'])){
                    $this->recursion($v1['examineDataList'], $userId, $condition_id, $param['examineId'], $create_time);
                }
            }
        }
        return resultArray(['data' => $param]); 
    }


    public function recursion ($conditionList, $userId, $condition_id, $examineId, $create_time)
    {
        foreach ($conditionList as $k => $v) {
            // 处理流程   0 条件 1 指定成员 2 主管 3 角色 4 发起人自选 5 连续多级主管 7 发起人
            $flow = [];
            $flow['name'] = $v['name'];
            $flow['examine_id'] = $examineId;
            $flow['examine_type'] = $v['examineType'];
            $flow['examine_error_handling'] = $v['examineErrorHandling'] ? : 1;
            $flow['condition_id'] = $condition_id;
            $flow['sort'] = $k;
            $flow['create_time'] = $create_time;
            $flow['create_user_id'] = $userId;
            $flowId = db('examine_flow')->insertGetId($flow);
            
            // 1 指定成员 2 主管 3 角色 4 发起人自选 5 连续多级主管 7 发起人
            $examine_flow = [];
            switch ($v['examineType']) {
                case '1' :
                    foreach ($v['userList'] as $key => $value) {
                        $examine_flow[$key]['sort'] = $key;
                        $examine_flow[$key]['type'] = $v['type'];
                        $examine_flow[$key]['user_id'] = $value;
                        $examine_flow[$key]['flow_id'] = $flowId;
                    }
                    db('examine_flow_member')->insertAll($examine_flow);
                    break;
                case '2' :
                    $examine_flow['flow_id'] = $flowId;
                    $examine_flow['parent_level'] = $v['parentLevel'];
                    $examine_flow['type'] = $v['type'];
                    db('examine_flow_superior')->insert($examine_flow);
                    break;
                case '3' :
                    $examine_flow['flow_id'] = $flowId;
                    $examine_flow['role_id'] = $v['roleId'];
                    $examine_flow['type'] = $v['type'];
                    db('examine_flow_role')->insert($examine_flow);
                    break;
                case '4' :
                    foreach ($v['userList'] as $key => $value) {
                        $examine_flow[$key]['sort'] = $key;
                        $examine_flow[$key]['type'] = $v['type'];
                        $examine_flow[$key]['user_id'] = $value;
                        $examine_flow[$key]['flow_id'] = $flowId;
                        $examine_flow[$key]['role_id'] = $v['roleId'];
                        $examine_flow[$key]['choose_type'] = $v['chooseType'];
                        $examine_flow[$key]['range_type'] = $v['rangeType'];
                    }
                    db('examine_flow_optional')->insertAll($examine_flow);
                    break;
                case '5' :
                    $examine_flow['flow_id'] = $flowId;
                    $examine_flow['role_id'] = $v['roleId'];
                    $examine_flow['max_level'] = $v['parentLevel'];
                    $examine_flow['type'] = $v['type'];
                    db('examine_flow_continuous_superior')->insert($examine_flow);
                    break;
                // case '7' :
                //     break;
            }

            foreach ($v['conditionList'] as $k1 => $v1) {
                // 处理条件
                $condition['condition_name'] = $v1['conditionName'];
                $condition['priority'] = $v1['sort'];
                $condition['create_time'] = $create_time;
                $condition['create_user_id'] = $userId;
                $condition['flow_id'] = $flowId;
                $condition_id1 = db('examine_condition')->insertGetId($condition);
                
                // 处理条件 扩展
                foreach ($v1['conditionDataList'] as $kc => $vc) {
                    $conditionDate = [];
                    $conditionDate['field_id'] = $vc['fieldId'];
                    $conditionDate['field_name'] = $vc['fieldName'] ? : '';
                    $conditionDate['condition_type'] = $vc['conditionType'];
                    $conditionDate['value'] = json_encode($vc['values']);
                    $conditionDate['name'] = $vc['name'];
                    $conditionDate['type'] = $vc['type'];
                    $conditionDate['condition_id'] = $condition_id1;
                    $conditionDate['flow_id'] = $flowId;
                    $condition_data_id = db('examine_condition_data')->insertGetId($conditionDate);
                }
                if(!empty($v1['examineDataList'])){
                    $this->recursion($v1['examineDataList'], $userId, $condition_id1, $examineId, $create_time);
                }
            }
        }
    }

    /**
     * 预览审批条件
     * @return [type] [description]
     */
    public function previewFiledName()
    {
        $param = $this->param;
        $flow_id = db('examine_flow')
                    ->where(['examine_id' => $param['id']])
                    ->where(['examine_type' => 0])
                    ->where(['condition_id' => 0])
                    ->min('flow_id');
        
        $condition_data = db('examine_condition_data')->where('flow_id', $flow_id)->where('field_name', 'money')->select();
        return resultArray(['data' => $condition_data]); 
    }

    /**
     * 预览检查流
     * @return [type] [description]
     */
    public function previewExamineFlow()
    {
        $userInfo = $this->userInfo;
        $param = $this->param;
$param['dataMap']['money'] = 10000;

        $examine_flow = db('examine_flow')
                    ->where(['examine_id' => $param['id']])
                    ->where(['condition_id' => 0])
                    ->select();
        $data = [];

        foreach ($examine_flow as $key => $value) {
            if($value['examine_type'] == 0){
                $condition = db('examine_condition')
                    ->where('flow_id', $value['flow_id'])
                    ->select();
                foreach ($condition as $ka => $va) {
                    // 处理审批条件 是否符合  符合去找流程  不符合 找下一个流程
                    $condition_data = db('examine_condition_data')->where('condition_id', $va['condition_id'])->select();
                    foreach ($condition_data as $kb => $vb) {
                        $jump_out = 1;
                        // 1 等于 2 大于 3 小于 4 大于等于 5 小于等于 6 两者之间 7 包含 8 员工 9 部门 10 角色',
                        switch ($vb['condition_type']) {
                            case '1' :
                                if($param['dataMap']['money'] != json_decode($vb['value'])[0]){
                                    $jump_out = 2;
                                }
                                break;
                            case '2' :
                                if($param['dataMap']['money'] <= json_decode($vb['value'])[0]){
                                    $jump_out = 2;
                                }
                                break;
                            case '3' :
                                if($param['dataMap']['money'] >= json_decode($vb['value'])[0]){
                                    $jump_out = 2;
                                }
                                break;
                            case '4' :
                                if($param['dataMap']['money'] < json_decode($vb['value'])[0]){
                                    $jump_out = 2;
                                }
                                break;
                            case '5' :
                                if($param['dataMap']['money'] > json_decode($vb['value'])[0]){
                                    $jump_out = 2;
                                }
                                break;
                            case '6' :
                                if(json_decode($vb['value'])[1] == 1){
                                    if(json_decode($vb['value'])[0] < $param['dataMap']['money']){
                                        $jump_out = 1;
                                    }else{
                                        $jump_out = 2;
                                    }
                                }else{
                                    if(json_decode($vb['value'])[0] <= $param['dataMap']['money']){
                                        $jump_out = 1;
                                    }else{
                                        $jump_out = 2;
                                    }
                                }

                                if(json_decode($vb['value'])[2] == 1){
                                    if(json_decode($vb['value'])[3] > $param['dataMap']['money']){
                                        $jump_out = 1;
                                    }else{
                                        $jump_out = 2;
                                    }
                                }else{
                                    if(json_decode($vb['value'])[3] >= $param['dataMap']['money']){
                                        $jump_out = 1;
                                    }else{
                                        $jump_out = 2;
                                    }
                                }
                                break;
                            case '7' :
                                break;
                            case '8' :
                                $group_id = db('admin_access')->where('user_id', $userInfo['id'])->column('group_id');
                                $userList = json_decode($vb['value'])->userList;
                                $deptList = json_decode($vb['value'])->deptList;
                                $roleList = json_decode($vb['value'])->roleList;

                                if(!is_array($userInfo['id'], $userList) && !in_array($userInfo['structure_id'], $deptList) && count(array_intersect($group_id, $roleList)) <= 0){
                                    $jump_out = 2;
                                }
                        }
                        // 该条件不符合 跳过本条件  继续下一个条件
                        if($jump_out == 2){
                            break;
                        }
                    }

                    // 符合条件  不需要继续走下一个条件  开始找流程   并跳出整个循环  
                    if($jump_out == 1){
                        $flowList = db('examine_flow')
                                        ->where(['examine_id' => $param['id']])
                                        ->where(['condition_id' => $va['condition_id']])
                                        ->select();

                        $data = $this->conditionalRecursion($flowList, $userInfo, $data, $param);
                        break;
                    }
                }
            }else{
                $where = [];
                switch ($value['examine_type']) {
                    case '1' :
                        $where['a.flow_id'] = $value['flow_id'];
                        $userList = db('examine_flow_member')
                                        ->alias('a')
                                        ->join('admin_user b','b.id = a.user_id', 'left')
                                        ->where($where)
                                        ->field('b.realname, b.img, a.user_id')
                                        ->select();
                        break;
                    case '2' :
                        $where['flow_id'] = $value['flow_id'];
                        $superior = db('examine_flow_superior')->where($where)->find();
                        $owner_user_id = getUserSuperior($userInfo['structure_id'], $superior['parent_level']);
                        
                        // 找不到主管 先是 上级主管代签 没有上级主管管理员代签（多个管理员 或签）
                        if(!$owner_user_id){
                            $owner_user_ids = db('examine_manager_user')->where('examine_id', $param['id'])->column('user_id');
                            $userList = db('admin_user')->field('realname, img, id as user_id')->where(['id'=>['in', $owner_user_ids]])->select();
                        }else{
                            $userList = db('admin_user')->field('realname, img, id as user_id')->where(['id'=>['eq', $owner_user_id]])->select();
                        }
                        break;
                    case '3' :
                        $where['a.flow_id'] = $value['flow_id'];
                        $userList = db('examine_flow_role')
                                        ->alias('a')
                                        ->join('admin_access b','b.group_id = a.role_id', 'left')
                                        ->join('admin_user c','b.user_id = c.id', 'left')
                                        ->where($where)
                                        ->field('c.realname, c.img, b.user_id')
                                        ->select();
                        break;
                     case '4' :
                        
                        // db('examine_flow_optional')->insertAll($examine_flow);
                        break;
                    case '5' :
                        
                        // db('examine_flow_continuous_superior')->insert($examine_flow);
                        break;
                    // case '7' :
                    //     break;
                }
                $value['userList'] = $userList;
                $data[] = $value;
            }
        }
        return resultArray(['data' => $data]); 
    }


    public function conditionalRecursion ($examine_flow, $userInfo, $data, $param)
    {
        foreach ($examine_flow as $key => $value) {
            if($value['examine_type'] == 0){
                $condition = db('examine_condition')
                    ->where('flow_id', $value['flow_id'])
                    ->select();
                foreach ($condition as $ka => $va) {
                    // 处理审批条件 是否符合  符合去找流程  不符合 找下一个流程
                    $condition_data = db('examine_condition_data')->where('condition_id', $va['condition_id'])->select();
                    foreach ($condition_data as $kb => $vb) {
                        $jump_out = 1;
                        // 1 等于 2 大于 3 小于 4 大于等于 5 小于等于 6 两者之间 7 包含 8 员工 9 部门 10 角色',
                        switch ($vb['condition_type']) {
                            case '1' :
                                if($param['dataMap']['money'] != json_decode($vb['value'])[0]){
                                    $jump_out = 2;
                                }
                                break;
                            case '2' :
                                if($param['dataMap']['money'] <= json_decode($vb['value'])[0]){
                                    $jump_out = 2;
                                }
                                break;
                            case '3' :

                                if($param['dataMap']['money'] >= json_decode($vb['value'])[0]){
                                    $jump_out = 2;
                                }
                                break;
                            case '4' :
                                if($param['dataMap']['money'] < json_decode($vb['value'])[0]){
                                    $jump_out = 2;
                                }
                                break;
                            case '5' :
                                if($param['dataMap']['money'] > json_decode($vb['value'])[0]){
                                    $jump_out = 2;
                                }
                                break;
                            case '6' :
                                if(json_decode($vb['value'])[1] == 1){
                                    if(json_decode($vb['value'])[0] < $param['dataMap']['money']){
                                        $jump_out = 1;
                                    }else{
                                        $jump_out = 2;
                                    }
                                }else{
                                    if(json_decode($vb['value'])[0] <= $param['dataMap']['money']){
                                        $jump_out = 1;
                                    }else{
                                        $jump_out = 2;
                                    }
                                }

                                if(json_decode($vb['value'])[2] == 1){
                                    if(json_decode($vb['value'])[3] > $param['dataMap']['money']){
                                        $jump_out = 1;
                                    }else{
                                        $jump_out = 2;
                                    }
                                }else{
                                    if(json_decode($vb['value'])[3] >= $param['dataMap']['money']){
                                        $jump_out = 1;
                                    }else{
                                        $jump_out = 2;
                                    }
                                }
                                break;
                            case '7' :
                                break;
                            case '8' :
                                $group_id = db('admin_access')->where('user_id', $userInfo['id'])->column('group_id');
                                $userList = json_decode($vb['value'])->userList;
                                $deptList = json_decode($vb['value'])->deptList;
                                $roleList = json_decode($vb['value'])->roleList;

                                if(!is_array($userInfo['id'], $userList) && !in_array($userInfo['structure_id'], $deptList) && count(array_intersect($group_id, $roleList)) <= 0){
                                    $jump_out = 2;
                                }
                        }
                        // 该条件不符合 跳过本条件  继续下一个条件
                        if($jump_out == 2){
                            break;
                        }
                    }

                    // 符合条件  不需要继续走下一个条件  开始找流程   并跳出整个循环  
                    if($jump_out == 1){
                        $flowList = db('examine_flow')
                                        ->where(['examine_id' => $param['id']])
                                        ->where(['condition_id' => $va['condition_id']])
                                        ->select();
                        $data = $this->conditionalRecursion($flowList, $userInfo, $data, $param);
                        break;
                    }
                }
            }else{
                $where = [];
                switch ($value['examine_type']) {
                    case '1' :
                        $where['a.flow_id'] = $value['flow_id'];
                        $userList = db('examine_flow_member')
                                        ->alias('a')
                                        ->join('admin_user b','b.id = a.user_id', 'left')
                                        ->where($where)
                                        ->field('b.realname, b.img, a.user_id')
                                        ->select();
                        break;
                    case '2' :
                        $where['flow_id'] = $value['flow_id'];
                        $superior = db('examine_flow_superior')->where($where)->find();
                        $owner_user_id = getUserSuperior($userInfo['structure_id'], $superior['parent_level']);
                        
                        // 找不到主管 先是 上级主管代签 没有上级主管管理员代签（多个管理员 或签）
                        if(!$owner_user_id){
                            $owner_user_ids = db('examine_manager_user')->where('examine_id', $param['id'])->column('user_id');
                            $userList = db('admin_user')->field('realname, img, id as user_id')->where(['id'=>['in', $owner_user_ids]])->select();
                        }else{
                            $userList = db('admin_user')->field('realname, img, id as user_id')->where(['id'=>['eq', $owner_user_id]])->select();
                        }
                        break;
                    case '3' :
                        $where['a.flow_id'] = $value['flow_id'];
                        $userList = db('examine_flow_role')
                                        ->alias('a')
                                        ->join('admin_access b','b.group_id = a.role_id', 'left')
                                        ->join('admin_user c','b.user_id = c.id', 'left')
                                        ->where($where)
                                        ->field('c.realname, c.img, b.user_id')
                                        ->select();
                        break;
                     case '4' :
                        
                        // db('examine_flow_optional')->insertAll($examine_flow);
                        break;
                    case '5' :
                        
                        // db('examine_flow_continuous_superior')->insert($examine_flow);
                        break;
                    // case '7' :
                    //     break;
                }
                $value['userList'] = $userList;
                $data[] = $value;
            }
        }
        return $data;
    }
}