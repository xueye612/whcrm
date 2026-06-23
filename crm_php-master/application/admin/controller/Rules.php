<?php
// +----------------------------------------------------------------------
// | Description: 规则
// +----------------------------------------------------------------------
// | Author:  Michael_xu | gengxiaoxu@5kcrm.com
// +----------------------------------------------------------------------

namespace app\admin\controller;

use think\Hook;
use think\Request;
use think\Db;

class Rules extends ApiCommon
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
            'permission'=>[''],
            'allow'=>['index','groupauth','upgroupauth','getgroupauth','groupauthid']            
        ];
        Hook::listen('check_auth',$action);
        $request = Request::instance();
        $a = strtolower($request->action());        
        if (!in_array($a, $action['permission'])) {
            parent::_initialize();
        }  

        $m = $this->m;
        $c = $this->c;
        $a = $this->a;
    }    

    public function index()
    {   
        $ruleModel = model('Rule');
        $param = $this->param;
        $data = $ruleModel->getDataList($param);
        return resultArray(['data' => $data]);
    }

    /**
     * 新建规则
     * @param
     * @return
     */    
    public function save()
    {
        $ruleModel = model('Rule');
        $param = $this->param;
        $data = $ruleModel->createData($param);
        if (!$data) {
            return resultArray(['error' => $ruleModel->getError()]);
        } 
        return resultArray(['data' => '添加成功']);
    }

    /**
     * 编辑规则
     * @param
     * @return
     */
    public function update()
    {
        $ruleModel = model('Rule');
        $param = $this->param;
        $data = $ruleModel->updateDataById($param, $param['id']);
        if (!$data) {
            return resultArray(['error' => $ruleModel->getError()]);
        } 
        return resultArray(['data' => '编辑成功']);
    }

    /**
     * 配置角色查看范围列表
     * @author zjf
     */
    public function groupauth()
    {
        $param = $this->param;

        $data = ['0' => ['name' => '系统管理角色','pid' => 1],'1' => ['name' => '办公管理角色','pid' => 6],'2' => ['name' => '客户管理角色','pid' => 2],'3' => ['name' => '项目管理角色','pid' => '9']];
        $list = db('admin_group')->field('id, pid, title')->select();
//        $userInfo=$this->userInfo;
        $authList = db('admin_group_auth')->where('group_id', $param['group_id'])->column('auth_group_id');
        foreach ($data as $key => $value) {
            foreach ($list as $k => $v) {
                $v['is_true'] = in_array($v['id'], $authList) ? 1 : 0;

                if($v['pid'] == $value['pid']){
                    if($v['id']==1){
                        continue;
                    }elseif($v['id']==2){
                        continue;
                    }else{
                        $data[$key]['item'][] = $v;
                    }

                }
            }
        }

        return resultArray(['data' => $data]);
    }

    /**
     * 配置角色查看范围列表
     * @author zjf
     */
    public function groupauthid()
    {
        $param = $this->param;

        $authList = db('admin_group_auth')->where('group_id', $param['group_id'])->column('auth_group_id');

        return resultArray(['data' => array_map('intval', $authList)]);
    }

    /**
     * 编辑配置角色查看范围 
     */
    public function upgroupauth()
    {
        $param = $this->param;
        $group_id = $param['group_id'];
        $auth_group_id = $param['auth_group_id'];

        $data = [];
        foreach ($auth_group_id as $key => $value) {
            $data[] = [
                'group_id' => $param['group_id'],
                'auth_group_id' => $value
            ];
        }

        // 启动事务
        Db::startTrans();
        try{
            db('admin_group_auth')->where('group_id', $param['group_id'])->delete();
            db('admin_group_auth')->insertAll($data);
            // 提交事务
            Db::commit();    
            return resultArray(['data' => '编辑成功']);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return resultArray(['data' => '编辑失败']);
        }
    }

    /**
     * 配置角色查看范围列表
     * @author zjf
     */
    public function getgroupauth()
    {
        $userInfo = $this->userInfo;
        $userId = $userInfo['id'];

        $groupIds = db('admin_access')->where('user_id', $userId)->column('group_id');
        $rules = db('admin_group')->where('id', 'in', $groupIds)->column('rules');

        $arr = [];
        foreach ($rules as $key => $value) {
            if($arr){
                $arr = array_merge(explode(",", trim($value, ",")), $arr);
            }else{
                $arr = explode(",", trim($value, ","));
            }
        }
        $data = ['0' => ['name' => '系统管理角色','pid' => 1],'1' => ['name' => '办公管理角色','pid' => 6],'2' => ['name' => '客户管理角色','pid' => 2],'3' => ['name' => '项目管理角色','pid' => '9']];

        # 角色权限查看  配置范围对应id
        $rule_authority_id =  db('admin_rule')->where(['title' => '角色权限设置', 'name' => 'update'])->value('id');
        if(!in_array($rule_authority_id, $arr) && $userId != 1){
            $auth_group_ids = db('admin_group_auth')->where('group_id', 'in', $groupIds)->column('auth_group_id');
            $list = db('admin_group')->where('id', 'in', $auth_group_ids)->select();
            $arrData = [];
            foreach ($data as $key => $value) {
                $item = [];
                foreach ($list as $k => $v) {
                    if($v['pid'] == $value['pid']){

                        if($v['pid'] == $value['pid']){
                            if($userId!=1 && $v['id']==1){
                                continue;
                            }else{
                                $item[] = $v;
                            }
                        }
                    }
                }
                $items = [];
                if(!empty($item)){
                    $items = [
                        'name' => $value['name'],
                        'pid' => $value['pid'],
                        'list' => $item,
                    ];
                    $arrData[] = $items;
                }
            }
            return resultArray(['data' => $arrData]);
        }else{
            $list = db('admin_group')->select();
            foreach ($data as $key => $value) {
                foreach ($list as $k => $v) {
                    if($v['pid'] == $value['pid']){
                        if($v['id']==1){
                            continue;
                        }else{
                            $data[$key]['list'][] = $v;
                        }
                    }
                }
            }
            return resultArray(['data' => $data]);
        }
    }
}
 