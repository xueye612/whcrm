<?php
// +----------------------------------------------------------------------
// | Description: 通讯录
// +----------------------------------------------------------------------
// | Author: yyk
// +----------------------------------------------------------------------

namespace app\oa\controller;

use app\admin\controller\ApiCommon;
use think\Hook;
use think\Request;
use think\Db;
use app\crm\traits\StarTrait;

use app\oa\logic\UserLogic;
class Addresslist extends ApiCommon
{
    use StarTrait;
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
            'allow'=>[
                'userstar',
                'querylist',
                'starlist'
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
     * 通讯录列表
     * @return mixed
     */
    public function queryList(){
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['user_id']=$userInfo['id'];
        $userLogic=new UserLogic();
        $data=$userLogic->getDataList($param);
        return resultArray(['data' => $data]);

    }

    /**
     * 关注的通讯录列表
     * @return mixed
     */
    public function starList(){
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['user_id']=$userInfo['id'];
        $userLogic=new UserLogic();
        $data=$userLogic->queryList($param);
        return resultArray(['data' => $data]);
    }
    /**
     * 设置关注
     *
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function userStar()
    {
        $userInfo = $this->userInfo;
        $userId   =  $userInfo['id'];
        $targetId = $this->param['target_id'];
        $type     = $this->param['type'];

        if (empty($userId) || empty($targetId) || empty($type)) return resultArray(['error' => '缺少必要参数！']);

        if (!$this->setStar($type, $userId, $targetId)) {
            return resultArray(['error' => '设置关注失败！']);
        }

        return resultArray(['data' => '设置关注成功！']);
    }
}
