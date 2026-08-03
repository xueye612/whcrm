<?php
// +----------------------------------------------------------------------
// | Description: 项目控制器
// +----------------------------------------------------------------------
// | Author:  yykun
// +----------------------------------------------------------------------

namespace app\work\controller;

use app\crm\model\Activity;
use app\work\logic\WorkLogic;
use app\work\traits\WorkAuthTrait;
use think\Request;
use think\Hook;
use app\admin\controller\ApiCommon;
use think\Db;

class work extends ApiCommon
{
    # 项目权限
    use WorkAuthTrait;
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
                'index',
                'filelist',
                'delete',
                'read',
                'archive',
                'owneradd',
                'ownerdel',
                'ownerlist',
                'leave',
                'archivelist',
                'arrecover',
                'statistic',
                'grouplist',
                'addusergroup',
                'update',
                'follow',
                'updateWorkOrder',
                // P1 项目实施扩展（登录可访问；写操作内部用 checkWorkOperationAuth('setWork') 校验）
                'implementationread',
                'profileupdate',
                'milestonesave',
                'milestonedelete',
                'contributionsave',
                'contributiondelete',
                'knowledgesave',
                'knowledgedelete'
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
     * 项目列表
     *
     * @param WorkLogic $workLogic
     * @return \think\response\Json
     */
    public function index(WorkLogic $workLogic)
    {
        $this->param['user_id'] = $this->userInfo['id'];

        $data = $workLogic->index($this->param);

        return resultArray(['data' => $data]);
    }

    /**
     * 创建项目
     *
     * @return \think\response\Json
     */
    public function save()
    {
        $userId    = $this->userInfo['id'];
        $param     = $this->param;
        $workModel = model('Work');

        if (empty($param['name']))      return resultArray(['error' => '项目名称不能为空']);
        if (empty($param['cover_url'])) return resultArray(['error' => '请选择项目封面！']);

        # 设置项目创建人和成员
		$param['create_user_id'] = $userId;
        $ownerUserId = !empty($param['owner_user_id']) ? $param['owner_user_id'] : [$userId];
        if (!in_array($userId, $ownerUserId)) $owner_user_id[] = $userId;
        $param['owner_user_id'] = $ownerUserId;

        $workId = $workModel->createData($param);
        if (!$workId) return resultArray(['error' => $workModel->getError()]);

        # 更新项目排序表
        $workModel->updateWorkOrder($workId, $userId);

        return resultArray(['data' => '操作成功！']);
    }

    /**
     * 编辑项目
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function update()
    {
        if (empty($this->param['work_id']))   return resultArray(['error' => '缺少项目ID！']);
        if (empty($this->param['name']))      return resultArray(['error' => '项目名称不能为空']);
        if (!empty($this->param['is_open']) && empty($this->param['group_id'])) {
            return resultArray(['error' => '请选择公开项目成员权限！']);
        }
//        if (empty($this->param['cover_url'])) return resultArray(['error' => '请选择项目封面！']);

        $workModel = model('Work');
        $userId = $this->userInfo['id'];

        # 权限判断
        if (!$this->checkWorkOperationAuth('setWork', $this->param['work_id'], $userId)) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }

        $this->param['user_id'] = $userId;
        if (!$workModel->updateDataById($this->param)) return resultArray(['error' => $workModel->getError()]);

        return resultArray(['data' => '操作成功！']);
    }

    /**
     * 项目详情
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function read()
    {
        if (empty($this->param['work_id'])) return resultArray(['error' => '请选择要查看的项目！']);

        $workModel = model('Work');

        $workInfo = $workModel->getDataById($this->param['work_id']);

        # 是否是公开项目
        $userId  = $this->userInfo['id'];
        $groupId = !empty($workInfo['is_open']) ? $workInfo['group_id'] : 0;

        # 项目成员
        $workInfo['ownerUser'] = db('admin_user')->field(['id', 'realname'])->whereIn('id', trim($workInfo['owner_user_id'], ','))->select();

        $workInfo['auth'] = $this->getRuleList($this->param['work_id'], $userId, $groupId);
        # 下次升级
        $userInfo=$this->userInfo;
        $rule=db('work_user')
            ->where('user_id',$userInfo['id'])
            ->value('group_id');
        $list=db('admin_rule')->where('name','manageTaskOwnerUser')->value('id');
        $groupList = db('admin_group')->where(['pid' => 5, 'types' => 7, 'type' => 0,'id'=>$rule])->order('system desc')->value('rules');
        if(!in_array($list,stringToArray($groupList))){
            $workInfo['is_open']=1;
        }
       
        return resultArray(['data' => $workInfo]);
    }

    /**
     * 删除项目
     *
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function delete()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $workModel = model('Work');
        if (empty($param['work_id'])) return resultArray(['error' => '请选择要删除的项目！']);
        $dataInfo=db('work')->where('work_id',$param['work_id'])->find();
        # 权限判断
        if (!$this->checkWorkOperationAuth('setWork', $param['work_id'], $userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
//        if (!$workModel->isCheck('work','work','update',$param['work_id'],$userInfo['id'])) {
//            header('Content-Type:application/json; charset=utf-8');
//            exit(json_encode(['code'=>102,'error'=>'无权操作']));
//        }
		$param['create_user_id'] = $userInfo['id']; 
        $resWork = $workModel->delWorkById($param);
        if ($resWork) {
            // 删除项目下所有任务
            db('task')->where(['work_id' => $param['work_id']])->delete();
            // 删除项目排序
            db('work_order')->where('work_id', $param['work_id'])->delete();
            RecordActionLog($userInfo['id'], 'work', 'delete',$dataInfo['name'], '','','删除了项目：'.$dataInfo['name']);
            return resultArray(['data'=>'删除成功']);
        } else {
            return resultArray(['error'=>$workModel->getError()]);
        }
    }

    /**
     * 归档项目
     * @author yykun
     * @return
     */
    public function archive()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $workModel = model('Work');
        if (!$param['work_id']) {
            return resultArray(['error'=>'参数错误']);
        }
        # 权限判断
        if (!$this->checkWorkOperationAuth('setWork', $param['work_id'], $userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
//        if (!$workModel->isCheck('work','work','update',$param['work_id'],$userInfo['id'])) {
//            header('Content-Type:application/json; charset=utf-8');
//            exit(json_encode(['code'=>102,'error'=>'无权操作']));
//        }
		$param['create_user_id'] = $userInfo['id']; 
        $flag = $workModel->archiveData($param);
        if ($flag) {
            return resultArray(['data'=>'归档成功']);
        } else {
            return resultArray(['error'=>$workModel->getError()]);
        }
    }

    /**
     * 参与人添加
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function ownerAdd()
    {   
        $param = $this->param;
        $userInfo = $this->userInfo;
        if (!$param['work_id'] || !$param['owner_user_id']) {
            return resultArray(['error'=>'参数错误']);
        }
        $dataInfo=db('work')->where('work_id',$param['work_id'])->find();
        $workModel = model('Work');
        # 权限判断
        if (!$this->checkWorkOperationAuth('setWork', $param['work_id'], $userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
//        if (!$workModel->isCheck('work','work','update',$param['work_id'],$userInfo['id'])) {
//            header('Content-Type:application/json; charset=utf-8');
//            exit(json_encode(['code'=>102,'error'=>'无权操作']));
//        }
        $res = $workModel->addOwner($param);
        $user= new \app\admin\model\User();
        if ($res) { 
            $temp['work_id'] = $param['work_id'];
            $list = $workModel->ownerList($temp); //获取参与人列表
            foreach ($param['owner_user_id'] as $value){
                $user_info=$user->getUserById($value);
                RecordActionLog($userInfo['id'], 'work', 'save',$dataInfo['name'], '','','增加了项目成员：'.$user_info['realname']);
            }
            return resultArray(['data'=>$list]);
        } else {
            return resultArray(['error'=>'操作失败']);
        }
    }

    /**
     * 参与人删除
     * @author yykun
     * @return
     */
    public function ownerDel()
    {   
        $param = $this->param;
        $userInfo = $this->userInfo;
        $userId=$userInfo['id'];
        if (!$param['work_id'] || !$param['owner_user_id']) {
            return resultArray(['error'=>'参数错误']);
        }
        $workModel = model('Work');
        # 权限判断
        if (!$this->checkWorkOperationAuth('setWork', $param['work_id'], $userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
//        if (!$workModel->isCheck('work','work','update',$param['work_id'],$userInfo['id'])) {
//            header('Content-Type:application/json; charset=utf-8');
//            exit(json_encode(['code'=>102,'error'=>'无权操作']));
//        }
        $res = $workModel->delOwner($param,$userId);
        if ($res) {
            
            return resultArray(['data'=>'操作成功']);
        } else {
            return resultArray(['error'=>$workModel->getError()]);
        }
    }

    /**
     * 参与人列表
     * @author yykun
     * @return
     */
    public function ownerList()
    {   
        $param = $this->param;
        $workModel = model('Work');
        $list = $workModel->ownerList($param);
        return resultArray(['data'=>$list]);
    }

    /**
     * 退出项目
     * @author yykun
     * @return
     */
    public function leave()
    {
        $param = $this->param;
        $userInfo   = $this->userInfo;
        $workModel = model('Work');
        if (!$param['work_id']) {
            return resultArray(['error'=>'参数错误']);
        }
        $ret = $workModel->leaveById($param['work_id'],$userInfo['id']);
        if ($ret) {
            return resultArray(['data'=>'操作成功']);
        } else {
            return resultArray(['error'=>$workModel->getError()]);
        }
    }

    /**
     * 归档项目列表
     * @author yykun
     * @return
     */
    public function archiveList()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['user_id'] = $userInfo['id'];
        $workModel = model('Work');
        $list = $workModel->archiveList($param);
        return resultArray(['data'=>$list]);
    }

    /**
     * 恢复归档项目
     * @author yykun
     * @return
     */
    public function arRecover()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        if (!$param['work_id']) {
           return resultArray(['error'=>'参数错误']); 
        }
        $workModel = Model('Work');
        # 权限判断
        if (!$this->checkWorkOperationAuth('setWork', $param['work_id'], $userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
//        if (!$workModel->isCheck('work','work','update',$param['work_id'],$userInfo['id'])) {
//            header('Content-Type:application/json; charset=utf-8');
//            exit(json_encode(['code'=>102,'error'=>'无权操作']));
//        }
        $ret = $workModel->arRecover($param['work_id'],$userInfo['id']);
        if ($ret) {
            return resultArray(['data'=>'操作成功']);
        } else {
            return resultArray(['error'=>$workModel->getError()]);
        }
    }

    /**
     * 项目任务统计
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function statistic()
    {
        if (empty($this->param['work_id'])) return resultArray(['error'=>'参数错误']);

        $userModel = new \app\admin\model\User();
        $workModel = model('work');
        $param     = $this->param;
        $work_id   = $param['work_id'];
        $userInfo  = $this->userInfo;

        $dataCount = [];
        if ($work_id !== 'all') $workInfo = Db::name('Work')->where(['work_id' => $work_id])->find();
        $lableary       = []; # 标签
        $main_user_arr  = []; # 成员
        $allNum         = 0;  # 总任务数
        $undoneNum      = 0;  # 总未完成数
        $doneNum        = 0;  # 总完成数
        $overtimeNum    = 0;  # 总延期数
        $archiveNum     = 0;  # 总归档数
        $completionRate = 0;  # 总完成率
        $delayRate      = 0;  # 总延期率

        $workIds      = [];
        $groupIds     = db('admin_access')->where('user_id', $userInfo['id'])->column('group_id');
        $isSuperAdmin = $userInfo['id'] == 1 || in_array(1, (array)$groupIds); # 是否是超管
        if (empty($isSuperAdmin)) $workIds = db('work_user')->where('user_id', $userInfo['id'])->column('work_id');


        //公开项目
        if ($work_id !== 'all') {
            $taskList = Db::name('Task')->where(['work_id' => $work_id, 'ishidden' => 0, 'pid' => 0])->field('task_id,main_user_id,lable_id,status,owner_user_id,stop_time,is_archive')->select();
        } else {
            $where['work_id']  = !empty($isSuperAdmin) ? ['gt', 0] : ['in', $workIds];
            $where['ishidden'] = 0;
            $where['pid']      = 0;
            $taskList = Db::name('Task')->where($where)->field('task_id,main_user_id,lable_id,status,owner_user_id,stop_time,is_archive')->select();
        }
        foreach ($taskList as $key => $value) {
            if (empty($value['is_archive'])) {
                $allNum += 1;
                if ($value['status'] == 1) {
                    $undoneNum += 1;
                }
                if ($value['status'] == 1 && $value['stop_time'] && ($value['stop_time'] < time())) {
                    $overtimeNum += 1;
                }                
            }
            if ($value['is_archive'] == 1) $archiveNum += 1;
            if ($value['status'] == 5) $doneNum += 1;            
            //获取项目下成员ID
//            if ($value['owner_user_id'] && $workInfo['is_open'] == 1) $main_user_arr[] = $value['main_user_id']; //负责人
//            if ($work_id == 'all') $main_user_arr[] = $value['main_user_id']; //负责人
            $main_user_arr[] = $value['main_user_id'];
            $lableArray = [];
            $lableArray = $value['lable_id'] ? stringToArray($value['lable_id']) : []; //标签
            $lableary = $lableArray ? array_merge($lableary,$lableArray) : $lableary;
        }
        $main_user_arr = $main_user_arr ? array_filter(array_unique($main_user_arr)) : [];
        $lableary = array_filter(array_unique($lableary));            

        $completionRate = $allNum ? round(($doneNum / $allNum) * 100,2)     : 0;
        $delayRate      = $allNum ? round(($overtimeNum / $allNum) * 100,2) : 0;

        $dataCount['allNum']         = !empty($allNum)         ? $allNum                : 0;
        $dataCount['undoneNum']      = !empty($undoneNum)      ? $undoneNum             : 0;
        $dataCount['doneNum']        = !empty($doneNum)        ? $doneNum               : 0;
        $dataCount['overtimeNum']    = !empty($overtimeNum)    ? $overtimeNum           : 0;
        $dataCount['archiveNum']     = !empty($archiveNum)     ? $archiveNum            : 0;
        $dataCount['completionRate'] = !empty($completionRate) ? round($completionRate) : 0;
        $dataCount['delayRate']      = !empty($delayRate)      ? $delayRate             : 0;

        //项目负责人
        $ownerArr = [];
        if ($workInfo && $workInfo['is_open'] == 0) {
            //私有项目
//            $main_user_arr = db('work_user')->where(['work_id' => $work_id])->column('user_id');
            $ownerArr = db('work_user')->where(['work_id' => $work_id,'types' => 1])->column('user_id');
        } elseif ($work_id !== 'all') {
            $ownerArr[] = $workInfo['create_user_id'];
        }
        $ownerList = [];
        foreach ($ownerArr as $k3=>$v3) {
            $ownerList[] = $userModel->getUserById($v3);
        }
        $dataAry['ownerList'] = $ownerList ? : [];
        // $dataAry['workInfo'] = $workInfo ? : [];

        //成员统计
        $list = [];
        $i = 0;
        $main_user_arr = $main_user_arr ? array_merge($main_user_arr) : [];
        foreach ($main_user_arr as $key => $value) {
            //参与项目数量
            $userInfo = [];
            $userInfo = $userModel->getUserById($value);
            if (!$userInfo) continue;
            $list[$i]['userInfo'] = $userInfo ? : [];
            // $workCount = 0; //项目总数
            $allCount = 0; //任务总数
            $undoneCount = 0; //待完成任务总数
            $doneCount = 0; //已完成任务总数
            $overtimeCount = 0; //延期任务总数
            $archiveCount = 0; //归档任务总数
            $completionRate = 0; //完成率
            $taskArr = [];
            if ($work_id == 'all') {
                $taskWhere['work_id']      = !empty($isSuperAdmin) ? ['gt', 0] : ['in', $workIds];
                $taskWhere['main_user_id'] = $value;
                $taskWhere['ishidden']     = 0;
                $taskWhere['pid']          = 0;
                $taskArr = db('task')->where($taskWhere)->field('status,stop_time,is_archive,task_id')->select();
            } else {
                $taskArr = db('task')->where(['work_id' => $work_id, 'main_user_id' => $value, 'ishidden' => 0, 'pid' => 0])->field('status,stop_time,is_archive,task_id')->select();
            }
            foreach ($taskArr as $v) {
                $allCount += 1;
                if ($v['status'] == 1 && empty($v['is_archive'])) $undoneCount += 1;
                if (($v['status'] == 1 && empty($v['is_archive'])) && $v['stop_time'] && ($v['stop_time'] < time())) $overtimeCount += 1;
                if ($v['is_archive'] == 1) $archiveCount += 1;
                if ($v['status'] == 5) $doneCount += 1;
            }
            $completionRate = $allCount ? round(($doneCount/$allCount),2)*100 : 0;
            $list[$i]['allCount'] = $allCount ? : 0;
            $list[$i]['undoneCount'] = $undoneCount ? : 0;
            $list[$i]['doneCount'] = $doneCount ? : 0;
            $list[$i]['overtimeCount'] = $overtimeCount ? : 0;
            $list[$i]['archiveCount'] = $archiveCount ? : 0;
            $list[$i]['completionRate'] = $completionRate ? : 0;
            $list[$i]['realname'] = !empty($userInfo) ? $userInfo['realname'] : '';
            $i++;
        }
        $dataAry['dataCount'] = $dataCount;
        $dataAry['userList'] = $list;

        if ($work_id !== 'all') {
            //任务列表统计
            $dataAry['classList'] = $workModel->classList($work_id);
            //标签统计
            $dataAry['labelList'] = $workModel->labelList($work_id,$lableary);            
        }
        return resultArray(['data'=>$dataAry]);
    }

    /**
     * 参与人角色添加
     *
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
	public function addUserGroup()
	{
        $param = $this->param;
		$userInfo = $this->userInfo;
		$workModel = model('Work');
        $list = $param['list'] ? : [];
        $work_id = $param['work_id'] ? : [];
		if (!is_array($list) || !$work_id) {
            return resultArray(['error'=>'参数错误']);
        }
        # 权限判断
        if (!$this->checkWorkOperationAuth('setWork', $work_id, $userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
//        if (!$workModel->isCheck('work','work','update',$param['work_id'],$userInfo['id'])) {
//            header('Content-Type:application/json; charset=utf-8');
//            exit(json_encode(['code'=>102,'error'=>'无权操作1']));
//        }
		foreach ($list as $value) {
			$data = array();
            $types = 0;
			$data['work_id'] = $work_id;
			$data['user_id'] = $value['user_id'];
			$flag = db('work_user')->where($data)->find();

			$data['group_id'] = $value['group_id'];
            if ($value['group_id'] == 1) $types = 1; //项目管理员，不能删除
            $data['types'] = $types;
			if (!$flag) {
				db('work_user')->insert($data);
			} else {
				db('work_user')->where(['work_id' => $work_id,'user_id' => $value['user_id']])->update($data);
			}
		}
		$dataList = db('work_user')->where(['work_id' => $work_id])->select();
		return resultArray(['data'=>$dataList]);
	} 

    /**
     * 项目下附件列表
     *
     * @param
     * @return 
     */
    public function fileList()
    {   
        $param = $this->param;
        $userInfo = $this->userInfo;
        $workModel = model('Work');
        $work_id = $param['work_id'];
        if (!$work_id) {
            return resultArray(['error'=>'参数错误']);
        }
        //判断权限
        $checkRes = $workModel->checkWork($work_id, $userInfo['id']);
        if ($checkRes !== true) {
            return resultArray(['error' => $workModel->getError()]);
        }

        $task_ids = db('task')->where(['work_id' => $work_id])->column('task_id');
        $request = [];
        $request['module'] = 'work_task';
        $request['module_id'] = $task_ids;
        $fileModel = new \app\admin\model\File();
        $data = $fileModel->getDataList($request, $param['by']);
        return resultArray(['data' => $data]);
    }

    /**
     * 项目角色列表
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function groupList()
    {
        $list[]    = ['id' => 1,'title' => '管理', 'remark' => '系统默认权限，包含项目所有权限,不可修改/删除'];
        $groupList = db('admin_group')->where(['pid' => 5, 'types' => 7, 'type' => 0])->order('system desc')->field('id, title, remark,rules')->select();
        $listArr   = array_merge($list, $groupList) ? : [];
        return resultArray(['data' => $listArr]);
    }

    /**
     * 项目关注
     *
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function follow()
    {
        if (!isset($this->param['follow']) || empty($this->param['work_id'])) return resultArray(['error' => '参数错误！']);
        $this->param['follow'] = !empty($this->param['follow']) ? $this->param['follow'] : 0;

        if (!Db::name('work')->where('work_id', $this->param['work_id'])->update(['is_follow' => $this->param['follow']])) {
            return resultArray(['error' => '操作失败！']);
        }

        return resultArray(['data' => '操作成功！']);
    }

    /**
     * 项目列表排序
     *
     * @author fanqi
     * @date 2021-03-11
     * @param WorkLogic $workLogic
     */
    public function updateWorkOrder(WorkLogic $workLogic)
    {
        $workIds  = $this->param['workIds'];
        $userInfo = $this->userInfo;

        $workLogic->setWorkOrder($workIds, $userInfo['id']);

        return resultArray(['data' => '操作成功！']);
    }

    // ======================= P1 项目实施扩展 =======================

    /**
     * 校验项目存在且当前用户有 setWork 管理权限。
     * @return array [bool $ok, mixed $errorOrWorkId]
     */
    private function requireWorkManage($workId, $userId)
    {
        $workId = (int)$workId;
        $userId = (int)$userId;
        if ($workId <= 0) return [false, '参数错误'];
        $work = Db::name('work')->where(['work_id' => $workId, 'ishidden' => 0])->find();
        if (!$work) return [false, '项目不存在或已删除'];
        if (!$this->checkWorkOperationAuth('setWork', $workId, $userId)) {
            return [false, '无权操作该项目'];
        }
        return [true, $workId];
    }

    /** 读取项目实施档案、里程碑、成员贡献、知识链接与字典 */
    public function implementationRead()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $workId = (int)($param['work_id'] ?? 0);
        if ($workId <= 0) return resultArray(['error' => '参数错误']);
        $work = Db::name('work')->where(['work_id' => $workId, 'ishidden' => 0])->find();
        if (!$work) return resultArray(['error' => '项目不存在或已删除']);

        $service = new \app\work\logic\ProjectService();
        $profile = $service->getProfile($workId);
        $milestones = Db::name('work_milestone')->where(['work_id' => $workId])->order('sort asc, milestone_id asc')->select();
        $contributions = Db::name('work_member_contribution')->where(['work_id' => $workId])->order('contribution_id asc')->select();
        $knowledge = Db::name('work_knowledge_link')->where(['work_id' => $workId])->order('sort asc, link_id asc')->select();

        // 绩效状态装饰：仅按当前项目的 milestone_id / contribution_id 批量查询对应事实，禁止全表扫描
        $msFactMap = [];
        $ctFactMap = [];
        $msIds = [];
        $ctIds = [];
        if (is_array($milestones)) {
            foreach ($milestones as $mTmp) { $msIds[] = (int)$mTmp['milestone_id']; }
        }
        if (is_array($contributions)) {
            foreach ($contributions as $cTmp) { $ctIds[] = (int)$cTmp['contribution_id']; }
        }
        $factSourceIds = [];
        foreach ($msIds as $mid) { $factSourceIds[] = 'milestone:' . $mid; }
        foreach ($ctIds as $ccid) { $factSourceIds[] = 'contribution:' . $ccid; }
        if (!empty($factSourceIds)) {
            // 表不存在时如实返回错误，不得用空 catch 伪装成"待归集"
            $projFacts = Db::name('performance_fact')
                ->where('source_type', 'in', ['project_milestone', 'project_contribution'])
                ->where('source_id', 'in', $factSourceIds)
                ->select();
            if (!is_array($projFacts)) $projFacts = [];
            foreach ($projFacts as $f) {
                $sid = (string)$f['source_id'];
                if (strpos($sid, 'milestone:') === 0) {
                    $msFactMap[(int)substr($sid, strlen('milestone:'))] = $f;
                } elseif (strpos($sid, 'contribution:') === 0) {
                    $ctFactMap[(int)substr($sid, strlen('contribution:'))] = $f;
                }
            }
        }
        if (is_array($milestones)) {
            foreach ($milestones as &$mRow) {
                $rid = (int)($mRow['responsible_user_id'] ?? 0);
                $fact = isset($msFactMap[(int)$mRow['milestone_id']]) ? $msFactMap[(int)$mRow['milestone_id']] : null;
                $isMember = $rid > 0 && $service->isProjectMember($workId, $rid);
                $p = \app\work\logic\ProjectService::milestonePerformanceStatus($mRow, $isMember, $fact ? $fact['status'] : null, $fact ? (int)$fact['fact_id'] : 0);
                $mRow['performance_status'] = $p['status'];
                $mRow['performance_status_text'] = isset(\app\work\logic\ProjectService::$perfStatusText[$p['status']]) ? \app\work\logic\ProjectService::$perfStatusText[$p['status']] : $p['status'];
                $mRow['performance_status_reason'] = $p['reason'];
                $mRow['performance_fact_id'] = $p['fact_id'];
                $mRow['performance_period'] = $fact ? (string)$fact['period'] : '';
                $mRow['performance_review_note'] = $fact ? (string)($fact['review_note'] ?? '') : '';
            }
            unset($mRow);
        }
        if (is_array($contributions)) {
            foreach ($contributions as &$cRow) {
                $uid = (int)($cRow['user_id'] ?? 0);
                $fact = isset($ctFactMap[(int)$cRow['contribution_id']]) ? $ctFactMap[(int)$cRow['contribution_id']] : null;
                $isMember = $uid > 0 && $service->isProjectMember($workId, $uid);
                $p = \app\work\logic\ProjectService::contributionPerformanceStatus($cRow, $isMember, $fact ? $fact['status'] : null, $fact ? (int)$fact['fact_id'] : 0);
                $cRow['performance_status'] = $p['status'];
                $cRow['performance_status_text'] = isset(\app\work\logic\ProjectService::$perfStatusText[$p['status']]) ? \app\work\logic\ProjectService::$perfStatusText[$p['status']] : $p['status'];
                $cRow['performance_status_reason'] = $p['reason'];
                $cRow['performance_fact_id'] = $p['fact_id'];
                // performance_period 取 performance_fact.period（真实季度），不得用周期天数赋值
                $cRow['performance_period'] = $fact ? (string)$fact['period'] : '';
                $cRow['performance_review_note'] = $fact ? (string)($fact['review_note'] ?? '') : '';
                // 周期天数使用单独字段 period_days
                $cRow['period_days'] = \app\work\logic\ProjectService::periodDays((int)($cRow['start_time'] ?? 0), (int)($cRow['end_time'] ?? 0));
            }
            unset($cRow);
        }

        $canManage = $this->checkWorkOperationAuth('setWork', $workId, (int)$userInfo['id']);
        return resultArray(['data' => [
            'work_id'       => $workId,
            'profile'       => $profile,
            'milestones'    => $milestones ?: [],
            'contributions' => $contributions ?: [],
            'knowledge'     => $knowledge ?: [],
            'dictionary'    => \app\work\logic\ProjectService::dictionary(),
            'can_manage'    => $canManage ? true : false,
            'can_accept'    => $service->canAccept($workId),
        ]]);
    }

    /** 保存/更新实施档案（类型、等级、计划/实际时间、稳定期、验收结果） */
    public function profileUpdate()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $workId = (int)($param['work_id'] ?? 0);
        list($ok, $err) = $this->requireWorkManage($workId, $userInfo['id']);
        if (!$ok) return resultArray(['error' => $err]);

        $service = new \app\work\logic\ProjectService();
        // 验收结果前置约束：未完成任何里程碑时禁止直接验收通过
        $acceptResult = trim((string)($param['acceptance_result'] ?? ''));
        if ($acceptResult !== '' && !$service->canAccept($workId)) {
            return resultArray(['error' => '至少完成一条里程碑后才能登记验收结果']);
        }
        list($ok, $payload) = $service->saveProfile($workId, $param, $userInfo['id']);
        if ($ok) {
            Db::name('work')->where(['work_id' => $workId])->update(['update_time' => time()]);
            return resultArray(['data' => $payload]);
        }
        return resultArray(['error' => $payload]);
    }

    /** 新增/更新里程碑（传 milestone_id 则更新） */
    public function milestoneSave()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $workId = (int)($param['work_id'] ?? 0);
        list($ok, $err) = $this->requireWorkManage($workId, $userInfo['id']);
        if (!$ok) return resultArray(['error' => $err]);

        $svc = '\\app\\work\\logic\\ProjectService';
        $milestoneId = (int)($param['milestone_id'] ?? 0);
        $perfSvc = new \app\work\logic\ProjectPerformanceService();

        // 事务前读取已有记录
        $existing = null;
        if ($milestoneId > 0) {
            $existing = Db::name('work_milestone')->where(['milestone_id' => $milestoneId, 'work_id' => $workId])->find();
            if (!$existing) return resultArray(['error' => '里程碑不存在或不属于当前项目']);
            // 更新前检查关联事实是否已通过：在写入源记录前拒绝
            // 对于已通过事实，检查是否有关键字段变更
            $existFact = Db::name('performance_fact')->where(['source_type' => 'project_milestone', 'source_id' => 'milestone:' . $milestoneId])->find();
            if ($existFact && (string)$existFact['status'] === '已通过') {
                $keyChanged = false;
                if (array_key_exists('responsible_user_id', $param) && (int)$param['responsible_user_id'] !== (int)$existing['responsible_user_id']) $keyChanged = true;
                if (array_key_exists('status', $param) && trim((string)$param['status']) !== (string)$existing['status']) $keyChanged = true;
                $actualSt = $svc::resolveFieldTimeState($param, 'actual_time');
                if ($actualSt['state'] === 2 && $actualSt['ts'] !== (int)$existing['actual_time']) $keyChanged = true;
                if ($keyChanged) {
                    return resultArray(['error' => '该里程碑绩效已通过，修改关键字段需先执行绩效撤回流程']);
                }
            }
        }

        $service = new \app\work\logic\ProjectService();
        $verr = $service->validateMilestone($param, $existing);
        if ($verr) return resultArray(['error' => $verr]);

        $respId = (int)($param['responsible_user_id'] ?? ($existing ? $existing['responsible_user_id'] : 0));
        if ($respId <= 0) return resultArray(['error' => '请选择里程碑负责人']);
        if (!$service->isProjectMember($workId, $respId)) return resultArray(['error' => '负责人必须是当前项目成员']);

        $effType = trim((string)($param['milestone_type'] ?? ($existing ? $existing['milestone_type'] : '')));
        $effName = trim((string)($param['name'] ?? ($existing ? $existing['name'] : '')));
        $effPlanSt = $svc::resolveFieldTimeState($param, 'plan_time');
        $effPlan = ($effPlanSt['state'] === 2) ? $effPlanSt['ts'] : ($effPlanSt['state'] === 1 ? 0 : (int)($existing ? $existing['plan_time'] : 0));
        if ($service->findDuplicateMilestone($workId, $effType, $effName, $effPlan, $respId, $milestoneId)) {
            return resultArray(['error' => '已存在相同类型、名称、计划时间与负责人的里程碑']);
        }

        $now = time();
        // 统一事务：写源记录 + 同步绩效事实原子完成
        Db::startTrans();
        try {
            if ($milestoneId > 0) {
                // 锁定源记录与事实后再次校验；事务前检查仅用于快速失败，不能作为并发保护。
                $lockedExisting = Db::name('work_milestone')->where(['milestone_id' => $milestoneId, 'work_id' => $workId])->lock(true)->find();
                if (!$lockedExisting) { Db::rollback(); return resultArray(['error' => '里程碑不存在或不属于当前项目']); }
                $lockedFact = Db::name('performance_fact')->where(['source_type' => 'project_milestone', 'source_id' => 'milestone:' . $milestoneId])->lock(true)->find();
                if ($lockedFact && (string)$lockedFact['status'] === '已通过') {
                    $critical = ['milestone_type', 'name', 'status', 'responsible_user_id', 'evidence_note'];
                    foreach ($critical as $field) {
                        if (array_key_exists($field, $param) && (string)$param[$field] !== (string)$lockedExisting[$field]) {
                            Db::rollback();
                            return resultArray(['error' => '该里程碑绩效已通过，修改关键字段需先执行绩效撤回流程']);
                        }
                    }
                    foreach (['plan_time', 'actual_time'] as $field) {
                        $state = $svc::resolveFieldTimeState($param, $field);
                        $newTs = $state['state'] === 2 ? (int)$state['ts'] : ($state['state'] === 1 ? 0 : (int)$lockedExisting[$field]);
                        if ($newTs !== (int)$lockedExisting[$field]) {
                            Db::rollback();
                            return resultArray(['error' => '该里程碑绩效已通过，修改关键字段需先执行绩效撤回流程']);
                        }
                    }
                }
                $existing = $lockedExisting;
                $lockedErr = $service->validateMilestone($param, $existing);
                if ($lockedErr) { Db::rollback(); return resultArray(['error' => $lockedErr]); }
                $lockedResp = (int)($param['responsible_user_id'] ?? $existing['responsible_user_id']);
                if (!$service->isProjectMember($workId, $lockedResp)) { Db::rollback(); return resultArray(['error' => '负责人必须是当前项目成员']); }
                $lockedPlanState = $svc::resolveFieldTimeState($param, 'plan_time');
                $lockedPlan = $lockedPlanState['state'] === 2 ? (int)$lockedPlanState['ts'] : ($lockedPlanState['state'] === 1 ? 0 : (int)$existing['plan_time']);
                $lockedType = trim((string)($param['milestone_type'] ?? $existing['milestone_type']));
                $lockedName = trim((string)($param['name'] ?? $existing['name']));
                if ($service->findDuplicateMilestone($workId, $lockedType, $lockedName, $lockedPlan, $lockedResp, $milestoneId)) {
                    Db::rollback();
                    return resultArray(['error' => '已存在相同类型、名称、计划时间与负责人的里程碑']);
                }
                $row = ['update_time' => $now];
                if (array_key_exists('milestone_type', $param)) $row['milestone_type'] = trim((string)$param['milestone_type']);
                if (array_key_exists('name', $param)) $row['name'] = trim((string)$param['name']);
                if (array_key_exists('status', $param)) $row['status'] = trim((string)$param['status']);
                if (array_key_exists('responsible_user_id', $param)) $row['responsible_user_id'] = (int)$param['responsible_user_id'];
                if (array_key_exists('sort', $param)) $row['sort'] = (int)$param['sort'];
                if (array_key_exists('evidence_note', $param)) $row['evidence_note'] = trim((string)$param['evidence_note']);
                $row = array_merge($row, $svc::buildDateRow($param, ['plan_time', 'actual_time'], false));
                $row = $this->filterRowColumns('work_milestone', $row);
                Db::name('work_milestone')->where(['milestone_id' => $milestoneId, 'work_id' => $workId])->update($row);
                $id = $milestoneId;
            } else {
                $row = [
                    'work_id'        => $workId,
                    'milestone_type' => trim((string)($param['milestone_type'] ?? '')),
                    'name'           => trim((string)($param['name'] ?? '')),
                    'status'         => trim((string)($param['status'] ?? \app\work\logic\ProjectService::MS_STATUS_TODO)),
                    'responsible_user_id' => $respId,
                    'sort'           => (int)($param['sort'] ?? 0),
                    'evidence_note'  => trim((string)($param['evidence_note'] ?? '')),
                    'create_user_id' => (int)$userInfo['id'],
                    'create_time'    => $now,
                    'update_time'    => $now,
                ];
                $row = array_merge($row, $svc::buildDateRow($param, ['plan_time', 'actual_time'], true));
                $row = $this->filterRowColumns('work_milestone', $row);
                $id = Db::name('work_milestone')->insertGetId($row);
                if (!$id) { Db::rollback(); return resultArray(['error' => '里程碑创建失败']); }
            }
            // 同步绩效事实（同一事务内；服务不自行开事务）
            $sync = $perfSvc->syncMilestone($id, (int)$userInfo['id']);
            if (!$sync['ok']) {
                Db::rollback();
                return resultArray(['error' => $sync['error']]);
            }
            Db::commit();
            return resultArray(['data' => ['milestone_id' => $id, 'performance_sync' => $sync]]);
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => '保存失败：' . $e->getMessage()]);
        }
    }

    /** 删除里程碑 */
    public function milestoneDelete()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $workId = (int)($param['work_id'] ?? 0);
        list($ok, $err) = $this->requireWorkManage($workId, $userInfo['id']);
        if (!$ok) return resultArray(['error' => $err]);
        $milestoneId = (int)($param['milestone_id'] ?? 0);
        if ($milestoneId <= 0) return resultArray(['error' => '参数错误']);
        $perfSvc = new \app\work\logic\ProjectPerformanceService();
        Db::startTrans();
        try {
            $source = Db::name('work_milestone')->where(['milestone_id' => $milestoneId, 'work_id' => $workId])->lock(true)->find();
            if (!$source) { Db::rollback(); return resultArray(['error' => '里程碑不存在或不属于当前项目']); }
            $factCheck = $perfSvc->prepareDeleteMilestone($milestoneId, (int)$userInfo['id']);
            if (!$factCheck['can_delete']) { Db::rollback(); return resultArray(['error' => $factCheck['reason']]); }
            $affected = Db::name('work_milestone')->where(['milestone_id' => $milestoneId, 'work_id' => $workId])->delete();
            if (!$affected) { Db::rollback(); return resultArray(['error' => '里程碑删除失败']); }
            Db::commit();
            return resultArray(['data' => '删除成功']);
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => '删除失败：' . $e->getMessage()]);
        }
    }

    /** 新增/更新成员贡献 */
    public function contributionSave()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $workId = (int)($param['work_id'] ?? 0);
        list($ok, $err) = $this->requireWorkManage($workId, $userInfo['id']);
        if (!$ok) return resultArray(['error' => $err]);

        $svc = '\\app\\work\\logic\\ProjectService';
        $cid = (int)($param['contribution_id'] ?? 0);
        $perfSvc = new \app\work\logic\ProjectPerformanceService();

        // 事务前读取已有记录
        $existing = null;
        if ($cid > 0) {
            $existing = Db::name('work_member_contribution')->where(['contribution_id' => $cid, 'work_id' => $workId])->find();
            if (!$existing) return resultArray(['error' => '贡献记录不存在或不属于当前项目']);
            // 更新前检查关联事实是否已通过：关键字段变更时拒绝
            $existFact = Db::name('performance_fact')->where(['source_type' => 'project_contribution', 'source_id' => 'contribution:' . $cid])->find();
            if ($existFact && (string)$existFact['status'] === '已通过') {
                $keyChanged = false;
                if (array_key_exists('user_id', $param) && (int)$param['user_id'] !== (int)$existing['user_id']) $keyChanged = true;
                if (array_key_exists('on_site_days', $param) && $param['on_site_days'] !== '' && $param['on_site_days'] !== null && (float)$param['on_site_days'] !== (float)$existing['on_site_days']) $keyChanged = true;
                if (array_key_exists('status', $param) && trim((string)$param['status']) !== (string)$existing['status']) $keyChanged = true;
                $startSt = $svc::resolveFieldTimeState($param, 'start_time');
                if ($startSt['state'] === 2 && $startSt['ts'] !== (int)$existing['start_time']) $keyChanged = true;
                $endSt = $svc::resolveFieldTimeState($param, 'end_time');
                if ($endSt['state'] === 2 && $endSt['ts'] !== (int)$existing['end_time']) $keyChanged = true;
                if ($keyChanged) {
                    return resultArray(['error' => '该贡献绩效已通过，修改关键字段需先执行绩效撤回流程']);
                }
            }
        }

        $service = new \app\work\logic\ProjectService();
        $verr = $service->validateContribution($param, $workId, $existing);
        if ($verr) return resultArray(['error' => $verr]);

        $contribStatus = trim((string)($param['status'] ?? ($existing ? $existing['status'] : \app\work\logic\ProjectService::CONTRIB_DRAFT)));
        $effUser = (int)($param['user_id'] ?? ($existing ? $existing['user_id'] : 0));
        $effRole = trim((string)($param['contribution_role'] ?? ($existing ? $existing['contribution_role'] : '')));
        $effStartSt = $svc::resolveFieldTimeState($param, 'start_time');
        $effStart = ($effStartSt['state'] === 2) ? $effStartSt['ts'] : ($effStartSt['state'] === 1 ? 0 : (int)($existing ? $existing['start_time'] : 0));
        $effEndSt = $svc::resolveFieldTimeState($param, 'end_time');
        $effEnd = ($effEndSt['state'] === 2) ? $effEndSt['ts'] : ($effEndSt['state'] === 1 ? 0 : (int)($existing ? $existing['end_time'] : 0));

        if ($service->findDuplicateContribution($workId, $effUser, $effRole, $effStart, $effEnd, $cid)) {
            return resultArray(['error' => '已存在相同贡献人、角色与起止时间的贡献记录']);
        }

        $now = time();
        // 统一事务：写源记录 + 同步绩效事实原子完成
        Db::startTrans();
        try {
            if ($cid > 0) {
                $lockedExisting = Db::name('work_member_contribution')->where(['contribution_id' => $cid, 'work_id' => $workId])->lock(true)->find();
                if (!$lockedExisting) { Db::rollback(); return resultArray(['error' => '贡献记录不存在或不属于当前项目']); }
                $lockedFact = Db::name('performance_fact')->where(['source_type' => 'project_contribution', 'source_id' => 'contribution:' . $cid])->lock(true)->find();
                if ($lockedFact && (string)$lockedFact['status'] === '已通过') {
                    $critical = ['user_id', 'contribution_role', 'status', 'on_site_days', 'evidence_note'];
                    foreach ($critical as $field) {
                        if (array_key_exists($field, $param) && (string)$param[$field] !== (string)$lockedExisting[$field]) {
                            Db::rollback();
                            return resultArray(['error' => '该贡献绩效已通过，修改关键字段需先执行绩效撤回流程']);
                        }
                    }
                    foreach (['start_time', 'end_time'] as $field) {
                        $state = $svc::resolveFieldTimeState($param, $field);
                        $newTs = $state['state'] === 2 ? (int)$state['ts'] : ($state['state'] === 1 ? 0 : (int)$lockedExisting[$field]);
                        if ($newTs !== (int)$lockedExisting[$field]) {
                            Db::rollback();
                            return resultArray(['error' => '该贡献绩效已通过，修改关键字段需先执行绩效撤回流程']);
                        }
                    }
                }
                $existing = $lockedExisting;
                $lockedErr = $service->validateContribution($param, $workId, $existing);
                if ($lockedErr) { Db::rollback(); return resultArray(['error' => $lockedErr]); }
                $lockedUser = (int)($param['user_id'] ?? $existing['user_id']);
                $lockedRole = trim((string)($param['contribution_role'] ?? $existing['contribution_role']));
                $lockedStartState = $svc::resolveFieldTimeState($param, 'start_time');
                $lockedStart = $lockedStartState['state'] === 2 ? (int)$lockedStartState['ts'] : ($lockedStartState['state'] === 1 ? 0 : (int)$existing['start_time']);
                $lockedEndState = $svc::resolveFieldTimeState($param, 'end_time');
                $lockedEnd = $lockedEndState['state'] === 2 ? (int)$lockedEndState['ts'] : ($lockedEndState['state'] === 1 ? 0 : (int)$existing['end_time']);
                if ($service->findDuplicateContribution($workId, $lockedUser, $lockedRole, $lockedStart, $lockedEnd, $cid)) {
                    Db::rollback();
                    return resultArray(['error' => '已存在相同贡献人、角色与起止时间的贡献记录']);
                }
                $lockedStatus = trim((string)($param['status'] ?? $existing['status']));
                $lockedIsConfirm = $lockedStatus === \app\work\logic\ProjectService::CONTRIB_CONFIRMED
                    && (string)$existing['status'] !== \app\work\logic\ProjectService::CONTRIB_CONFIRMED;
                $row = ['update_time' => $now];
                if (array_key_exists('status', $param)) $row['status'] = $lockedStatus;
                if (array_key_exists('user_id', $param)) $row['user_id'] = (int)$param['user_id'];
                if (array_key_exists('contribution_role', $param)) $row['contribution_role'] = trim((string)$param['contribution_role']);
                if (array_key_exists('on_site_days', $param) && $param['on_site_days'] !== '' && $param['on_site_days'] !== null) {
                    $row['on_site_days'] = $svc::roundDecimal1((float)$param['on_site_days']);
                }
                if (array_key_exists('evidence_note', $param)) $row['evidence_note'] = trim((string)$param['evidence_note']);
                if ($lockedIsConfirm) { $row['confirm_user_id'] = (int)$userInfo['id']; $row['confirm_time'] = $now; }
                $row = array_merge($row, $svc::buildDateRow($param, ['start_time', 'end_time'], false));
                $row = $this->filterRowColumns('work_member_contribution', $row);
                Db::name('work_member_contribution')->where(['contribution_id' => $cid, 'work_id' => $workId])->update($row);
                $id = $cid;
            } else {
                $row = [
                    'work_id'          => $workId,
                    'user_id'          => (int)($param['user_id'] ?? 0),
                    'contribution_role'=> trim((string)($param['contribution_role'] ?? '')),
                    'status'           => $contribStatus,
                    'on_site_days'     => (array_key_exists('on_site_days', $param) && $param['on_site_days'] !== '' && $param['on_site_days'] !== null) ? $svc::roundDecimal1((float)$param['on_site_days']) : 0,
                    'evidence_note'    => trim((string)($param['evidence_note'] ?? '')),
                    'create_user_id'   => (int)$userInfo['id'],
                    'create_time'      => $now,
                    'update_time'      => $now,
                ];
                if ($contribStatus === \app\work\logic\ProjectService::CONTRIB_CONFIRMED) { $row['confirm_user_id'] = (int)$userInfo['id']; $row['confirm_time'] = $now; }
                $row = array_merge($row, $svc::buildDateRow($param, ['start_time', 'end_time'], true));
                $row = $this->filterRowColumns('work_member_contribution', $row);
                $id = Db::name('work_member_contribution')->insertGetId($row);
                if (!$id) { Db::rollback(); return resultArray(['error' => '贡献记录创建失败']); }
            }
            // 同步绩效事实（同一事务内；服务不自行开事务）
            $sync = $perfSvc->syncContribution($id, (int)$userInfo['id']);
            if (!$sync['ok']) {
                Db::rollback();
                return resultArray(['error' => $sync['error']]);
            }
            Db::commit();
            return resultArray(['data' => ['contribution_id' => $id, 'performance_sync' => $sync]]);
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => '保存失败：' . $e->getMessage()]);
        }
    }

    /** 删除成员贡献 */
    public function contributionDelete()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $workId = (int)($param['work_id'] ?? 0);
        list($ok, $err) = $this->requireWorkManage($workId, $userInfo['id']);
        if (!$ok) return resultArray(['error' => $err]);
        $cid = (int)($param['contribution_id'] ?? 0);
        if ($cid <= 0) return resultArray(['error' => '参数错误']);
        $perfSvc = new \app\work\logic\ProjectPerformanceService();
        Db::startTrans();
        try {
            $source = Db::name('work_member_contribution')->where(['contribution_id' => $cid, 'work_id' => $workId])->lock(true)->find();
            if (!$source) { Db::rollback(); return resultArray(['error' => '贡献记录不存在或不属于当前项目']); }
            $factCheck = $perfSvc->prepareDeleteContribution($cid, (int)$userInfo['id']);
            if (!$factCheck['can_delete']) { Db::rollback(); return resultArray(['error' => $factCheck['reason']]); }
            $affected = Db::name('work_member_contribution')->where(['contribution_id' => $cid, 'work_id' => $workId])->delete();
            if (!$affected) { Db::rollback(); return resultArray(['error' => '贡献记录删除失败']); }
            Db::commit();
            return resultArray(['data' => '删除成功']);
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => '删除失败：' . $e->getMessage()]);
        }
    }

    /** 显式重新提交已驳回的项目绩效事实。 */
    public function projectPerformanceResubmit()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $workId = (int)($param['work_id'] ?? 0);
        list($ok, $err) = $this->requireWorkManage($workId, $userInfo['id']);
        if (!$ok) return resultArray(['error' => $err]);
        $sourceType = trim((string)($param['source_type'] ?? ''));
        $sourceId = (int)($param['source_id'] ?? 0);
        if ($sourceId <= 0 || !in_array($sourceType, ['milestone', 'contribution'], true)) return resultArray(['error' => '参数错误']);

        $table = $sourceType === 'milestone' ? 'work_milestone' : 'work_member_contribution';
        $idField = $sourceType === 'milestone' ? 'milestone_id' : 'contribution_id';
        $svc = new \app\work\logic\ProjectPerformanceService();
        Db::startTrans();
        try {
            $source = Db::name($table)->where([$idField => $sourceId, 'work_id' => $workId])->lock(true)->find();
            if (!$source) { Db::rollback(); return resultArray(['error' => '来源记录不存在或不属于当前项目']); }
            $result = $sourceType === 'milestone'
                ? $svc->resubmitMilestone($sourceId, (int)$userInfo['id'])
                : $svc->resubmitContribution($sourceId, (int)$userInfo['id']);
            if (!$result['ok']) { Db::rollback(); return resultArray(['error' => $result['error']]); }
            Db::commit();
            return resultArray(['data' => $result]);
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => '重新提交失败：' . $e->getMessage()]);
        }
    }

    /** 新增/更新知识链接 */
    public function knowledgeSave()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $workId = (int)($param['work_id'] ?? 0);
        list($ok, $err) = $this->requireWorkManage($workId, $userInfo['id']);
        if (!$ok) return resultArray(['error' => $err]);

        $service = new \app\work\logic\ProjectService();
        $verr = $service->validateKnowledge($param);
        if ($verr) return resultArray(['error' => $verr]);
        $oerr = $service->validateKnowledgeOwner($param, $workId);
        if ($oerr) return resultArray(['error' => $oerr]);

        $lid = (int)($param['link_id'] ?? 0);
        // 更新时确认 link_id 属于当前 work_id
        if ($lid > 0) {
            $exist = Db::name('work_knowledge_link')->where(['link_id' => $lid, 'work_id' => $workId])->find();
            if (!$exist) return resultArray(['error' => '知识链接不存在或不属于当前项目']);
        }

        $now = time();
        $row = [
            'work_id'            => $workId,
            'link_type'          => trim((string)$param['link_type']),
            'title'              => trim((string)$param['title']),
            'url'                => trim((string)($param['url'] ?? '')),
            'owner_user_id'      => (int)($param['owner_user_id'] ?? 0),
            'completeness_status'=> trim((string)($param['completeness_status'] ?? \app\work\logic\ProjectService::COMP_PARTIAL)),
            'sort'               => (int)($param['sort'] ?? 0),
            'update_time'        => $now,
        ];
        if ($lid > 0) {
            Db::name('work_knowledge_link')->where(['link_id' => $lid, 'work_id' => $workId])->update($row);
            $id = $lid;
        } else {
            $row['create_user_id'] = (int)$userInfo['id'];
            $row['create_time'] = $now;
            $id = Db::name('work_knowledge_link')->insertGetId($row);
            if (!$id) return resultArray(['error' => '知识链接创建失败']);
        }
        return resultArray(['data' => ['link_id' => $id]]);
    }

    /** 删除知识链接 */
    public function knowledgeDelete()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $workId = (int)($param['work_id'] ?? 0);
        list($ok, $err) = $this->requireWorkManage($workId, $userInfo['id']);
        if (!$ok) return resultArray(['error' => $err]);
        $lid = (int)($param['link_id'] ?? 0);
        if ($lid <= 0) return resultArray(['error' => '参数错误']);
        $affected = Db::name('work_knowledge_link')->where(['link_id' => $lid, 'work_id' => $workId])->delete();
        if (!$affected) return resultArray(['error' => '知识链接不存在或不属于当前项目']);
        return resultArray(['data' => '删除成功']);
    }

    /** 缓存：表列存在性检测，避免迁移未执行时 SQL 报错 */
    private static $columnCache = [];
    private function hasColumn($table, $column)
    {
        $key = $table . '.' . $column;
        if (isset(self::$columnCache[$key])) return self::$columnCache[$key];
        try {
            $row = Db::query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . addslashes($table) . "' AND COLUMN_NAME='" . addslashes($column) . "'");
            $exists = !empty($row) && (int)$row[0]['cnt'] > 0;
        } catch (\Exception $e) {
            $exists = false;
        }
        self::$columnCache[$key] = $exists;
        return $exists;
    }

    /** 过滤掉表中尚不存在的列，避免 INSERT/UPDATE 报 "fields not exists" */
    private function filterRowColumns($table, array $row)
    {
        $prefix = config('database.prefix');
        $fullTable = $prefix . $table;
        $filtered = [];
        foreach ($row as $col => $val) {
            if ($this->hasColumn($fullTable, $col)) {
                $filtered[$col] = $val;
            }
        }
        return $filtered;
    }
}
