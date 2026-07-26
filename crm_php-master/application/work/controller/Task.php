<?php
// +----------------------------------------------------------------------
// | Description: 任务及基础
// +----------------------------------------------------------------------
// | Author:  yykun
// +----------------------------------------------------------------------

namespace app\work\controller;

use app\oa\logic\TaskLogic as TasksLogic;
use app\work\logic\TaskLogic;
use app\work\traits\WorkAuthTrait;
use think\Request;
use think\Hook;
use app\admin\controller\ApiCommon;
use app\admin\model\Message;
use app\admin\logic\DingTalkLogic;
use app\work\logic\WorkflowService;
use think\helper\Time;
use think\Db;

class Task extends ApiCommon
{
    use WorkAuthTrait;
    
    protected function syncLedgerByTask($taskId, $userId)
    {
        $ledger = Db::name('customer_ledger')->where(['task_id' => $taskId])->find();
        if (!$ledger || $ledger['status'] === '已完成') {
            return;
        }
        $time = time();
        Db::name('customer_ledger')->where(['ledger_id' => $ledger['ledger_id']])->update([
            'status' => '已完成',
            'finish_time' => $time,
            'update_time' => $time
        ]);
        $ledgerModel = new \app\ledger\model\CustomerLedger();
        $oldStatus = $ledger['status'] ?? '';
        $ledgerModel->addProgressRecord($ledger['ledger_id'], $ledger['customer_id'], '任务完成同步', $oldStatus, '已完成', $userId);
        Db::name('crm_activity')->insert([
            'type' => 1,
            'activity_type' => 13,
            'activity_type_id' => $ledger['ledger_id'],
            'content' => '任务完成同步',
            'category' => '台账',
            'customer_ids' => $ledger['customer_id'] ? (',' . $ledger['customer_id'] . ',') : '',
            'create_user_id' => $userId,
            'update_time' => $time,
            'create_time' => $time
        ]);
    }

    protected function syncLedgerByTaskStatus($taskId, $userId, $taskStatus)
    {
        $ledger = Db::name('customer_ledger')->where(['task_id' => $taskId])->find();
        if (!$ledger) {
            return;
        }
        $targetLedgerStatus = ((int)$taskStatus === 5) ? '已完成' : '处理中';
        if ((string)($ledger['status'] ?? '') === $targetLedgerStatus) {
            return;
        }
        $time = time();
        $updateData = [
            'status' => $targetLedgerStatus,
            'update_time' => $time
        ];
        if ((int)$taskStatus === 5) {
            $updateData['finish_time'] = $time;
        } else {
            $updateData['finish_time'] = 0;
        }
        Db::name('customer_ledger')->where(['ledger_id' => $ledger['ledger_id']])->update($updateData);
        $ledgerModel = new \app\ledger\model\CustomerLedger();
        $oldStatus = $ledger['status'] ?? '';
        $content = ((int)$taskStatus === 5) ? '任务完成同步' : '任务回退同步';
        $ledgerModel->addProgressRecord($ledger['ledger_id'], $ledger['customer_id'], $content, $oldStatus, $targetLedgerStatus, $userId);
        Db::name('crm_activity')->insert([
            'type' => 1,
            'activity_type' => 13,
            'activity_type_id' => $ledger['ledger_id'],
            'content' => $content,
            'category' => '台账',
            'customer_ids' => $ledger['customer_id'] ? (',' . $ledger['customer_id'] . ',') : '',
            'create_user_id' => $userId,
            'update_time' => $time,
            'create_time' => $time
        ]);
    }

    /**
     * 用于判断权限
     * @permission 无限制
     * @allow 登录用户可访问
     * @other 其他根据系统设置
     **/
    public function _initialize()
    {
        $action = [
            'permission' => [''],
            'allow' => [
                'index', 'mytask', 'updatetop', 'updateorder', 'read', 'update', 'readloglist', 'updatepriority',
                'updateowner', 'delownerbyid', 'delstruceurebyid', 'updatestoptime', 'updatelable', 'updatename',
                'taskover', 'datelist', 'save', 'delmainuserid', 'rename', 'delete', 'archive', 'recover', 'archlist',
                'archivetask', 'setover', 'updateclassorder', 'excelimport', 'excelexport', 'taskusers', 'ownertasklist',
                // P0 工作流动作（登录可访问；细粒度鉴权由 assertTaskAuth/assertReviewer/测试人校验等内部逻辑保障）
                'wrkdictionary', 'workflowread', 'evaluate', 'startprocess',
                'submitacceptance', 'acceptancepass', 'acceptancereturn',
                'applyrelease', 'confirmrelease', 'customerconfirm', 'customerreturn', 'completetask',
                'setauxstatus', 'setreleaseexemption',
                'initiatetest', 'submittest', 'reviewtest', 'testlist']

        
        ];
        Hook::listen('check_auth', $action);
        $request = Request::instance();
        $a = strtolower($request->action());
        if (!in_array($a, $action['permission'])) {
            parent::_initialize();
        }
        //权限判断
        $param = $this->param;
        if (!empty($param['task_id'])) {
            $userInfo = $this->userInfo;
            $taskModel = model('Task');
            if (!$taskModel->checkTask($param['task_id'], $userInfo)) {
                header('Content-Type:application/json; charset=utf-8');
                exit(json_encode(['code' => 102, 'error' => '没有权限']));
            }
        }
    }
    
    /**
     * 项目下任务列表
     * @return
     * @author yykun
     */
    public function index()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $taskModel = model('Task');
        if (empty($param['work_id'])) {
            return resultArray(['error' => '参数错误']);
        }
        $list = $taskModel->getDataList($param, $userInfo['id']);
        return resultArray(['data' => $list]);
    }
    
    public function ownerTaskList()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $taskModel = model('Task');
        if (empty($param['work_id'])) {
            return resultArray(['error' => '参数错误']);
        }
        $list = $taskModel->getOwnerTaskList($param, $userInfo['id']);
        return resultArray(['data' => $list]);
    }
    
    /**
     * 任务列表导出
     *
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function excelExport()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['user_id'] = $userInfo['id'];
        # 权限判断
        if (!empty($param['work_id']) && !$this->checkWorkOperationAuth('excelExport', $param['work_id'], $userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
        $TaskLogic = new TasksLogic();
        $data = $TaskLogic->excelExport($param);
        RecordActionLog($userInfo['id'],'work_task','excelexport','导出全部','','','导出任务');
        return $data;
    }
    
    /**
     * 导入模板下载
     * @param string $save_path 本地保存路径     用于错误数据导出，在 Admin\Model\Excel::batchImportData()调用
     * @return
     * @author Michael_xu
     */
    public function excelDownload($save_path = '')
    {
        $excelModel = new \app\admin\model\Excel();
        $field_list = [
            '0' => [
                'name' => '任务名称',
                'field' => 'name',
                'types' => 'task',
                'form_type' => 'text',
                'default_value' => '',
                'is_unique' => 1,
                'is_null' => 1,
                'input_tips' => '',
                'setting' => array(),
                'is_hidden' => 0,
                'writeStatus' => 1,
                'value' => '',
            ],
            '1' => [
                'name' => '任务描述',
                'field' => 'description',
                'types' => 'task',
                'form_type' => 'textarea',
            ],
            '2' => [
                'name' => '开始时间',
                'field' => 'start_time',
                'types' => 'task',
                'form_type' => 'datetime',
            ],
            '3' => [
                'name' => '结束时间',
                'field' => 'stop_time',
                'types' => 'task',
                'form_type' => 'datetime',
            ],
            '4' => [
                'name' => '创建人',
                'field' => 'create_user_id',
                'types' => 'task',
                'form_type' => 'user',
            ],
            '5' => [
                'name' => '参与人',
                'field' => 'owner_user_id',
                'types' => 'task',
                'form_type' => 'user',
            ],
            '6' => [
                'name' => '所在任务列表',
                'field' => 'class_id',
                'types' => 'task',
                'form_type' => 'text',
                'is_unique' => 1,
                'is_null' => 1,
            ],
        ];
        // 导入的字段列表
        $excelModel->excelImportDownload($field_list, 'work_task', $save_path);
    }
    
    /**
     * 客户数据导入
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function excelImport()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        # 权限判断
        if (!empty($param['work_id']) && !$this->checkWorkOperationAuth('excelImport', $param['work_id'], $userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
        $field_list = [
            '0' => [
                'name' => '任务名称',
                'field' => 'name',
                'types' => 'task',
                'form_type' => 'text',
                'default_value' => '',
                'is_unique' => 1,
                'is_null' => 1,
                'input_tips' => '',
                'setting' => array(),
                'is_hidden' => 0,
                'writeStatus' => 1,
                'value' => '',
            ],
            '1' => [
                'name' => '任务描述',
                'field' => 'description',
                'types' => 'task',
                'form_type' => 'textarea',
            ],
            '2' => [
                'name' => '开始时间',
                'field' => 'start_time',
                'types' => 'task',
                'form_type' => 'datetime',
            ],
            '3' => [
                'name' => '结束时间',
                'field' => 'stop_time',
                'types' => 'task',
                'form_type' => 'datetime',
            ],
            '4' => [
                'name' => '创建人',
                'field' => 'create_user_id',
                'types' => 'task',
                'form_type' => 'user',
            ],
            '5' => [
                'name' => '参与人',
                'field' => 'owner_user_id',
                'types' => 'task',
                'form_type' => 'user',
            ],
            '6' => [
                'name' => '所在任务列表',
                'field' => 'class_id',
                'types' => 'task',
                'form_type' => 'text',
                'is_unique' => 1,
                'is_null' => 1,
            ],
        ];
        $excelModel = new \app\admin\model\Excel();
        $param['create_user_id'] = $userInfo['id'];
        $param['owner_user_id'] = $param['owner_user_id'] ?: 0;
        $file = request()->file('file');
        $param['types'] = 'task';
        // $res = $excelModel->importExcel($file, $param, $this);
        $res = $excelModel->batchTaskImportData($file,$field_list, $param, $this);
        if (!$res) {
            return resultArray(['error' => $excelModel->getError()]);
        }
        RecordActionLog($userInfo['id'],'work_task','excel','导入客户','','','导入客户');
        return resultArray(['data' => $excelModel->getError()]);
    }
    
    
    /**
     * 任务搜索
     *
     * @param TaskLogic $taskLogic
     * @return \think\response\Json
     */
    public function search(TaskLogic $taskLogic)
    {
        $data = $taskLogic->getSearchData($this->param);
        
        return resultArray(['data' => $data]);
    }
    
    /**
     * 我的任务
     * @return
     * @author yykun
     */
    public function myTask()
    {
        $taskModel = model('Task');
        $userId = $this->userInfo['id'];
        
        $data = [];
        $data[0]['title'] = '收件箱';
        $data[1]['title'] = '今天要做';
        $data[2]['title'] = '下一步要做';
        $data[3]['title'] = '以后要做';
        for ($k = 0; $k < 4; $k++) {
            $where = [];
            $where['ishidden'] = 0;
            $where['is_top'] = $k;
            $where['pid'] = 0;
            $where['whereStr'] = ' ( task.create_user_id =' . $userId . ' or (  task.owner_user_id like "%,' . $userId . ',%") or ( task.main_user_id = ' . $userId . ' ) )';
            if (!empty($this->param['search'])) $where['taskSearch'] = '(task.name like "%' . $this->param['search'] . '%" OR task.description like "%' . $this->param['search'] . '%")';
            $resData = $taskModel->getProjectTaskList($where, $this->param);
            $data[$k]['is_top'] = $k;
            $data[$k]['list'] = $resData['list'] ?: [];
            $data[$k]['count'] = $resData['count'] ?: 0;
        }
        return resultArray(['data' => $data]);
    }
    
    /**
     * 我的任务 拖拽改变分类
     *
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function updateTop()
    {
        $param = $this->param;
        $tolist = $param['tolist'];
        $fromlist = $param['fromlist'];
        
        # 权限判断
        if (!empty($param['work_id']) && !$this->checkWorkOperationAuth('setTaskOrder', $param['work_id'], $this->userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
        
        if ($param['to_top_id'] || $param['to_top_id'] == 0) {
            if ($tolist) {
                foreach ($tolist as $k1 => $v1) {
                    $toData = [];
                    $toData['is_top'] = $param['to_top_id'];
                    $toData['top_order_id'] = $k1 + 1;
                    Db::name('Task')->where(['task_id' => $v1])->update($toData);
                }
            }
        }
        if ($param['from_top_id'] || $param['from_top_id'] == 0) {
            if ($fromlist) {
                foreach ($fromlist as $k2 => $v2) {
                    $fromData = [];
                    $fromData['is_top'] = $param['from_top_id'];
                    $fromData['top_order_id'] = $k2 + 1;
                    Db::name('Task')->where(['task_id' => $v2])->update($fromData);
                }
            }
        } else {
            return resultArray(['error' => '参数错误']);
        }
        return resultArray(['data' => true]);
    }
    
    /**
     * 项目 拖拽改变分类并排序
     * @return
     * @author yykun
     */
    public function updateOrder()
    {
        $param = $this->param;
        
        # 权限判断
        if (!empty($param['work_id']) && !$this->checkWorkOperationAuth('setTaskOrder', $param['work_id'], $this->userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
        
        if ($param['tolist']) {
            $tolist = $param['tolist'];
            foreach ($tolist as $k1 => $v1) {
                $toData = [];
                $toData['class_id'] = $param['toid'];
                $toData['order_id'] = $k1 + 1;
                Db::name('Task')->where(['task_id' => $v1])->update($toData);
            }
        }
        if ($param['fromlist']) {
            $fromlist = $param['fromlist'];
            foreach ($fromlist as $k2 => $v2) {
                $fromData = [];
                $fromData['class_id'] = $param['fromid'];
                $fromData['order_id'] = $k2 + 1;
                Db::name('Task')->where(['task_id' => $v2])->update($fromData);
            }
        }
        return resultArray(['data' => true]);
    }
    
    /**
     * 项目下 拖拽整个分类排序
     *
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function updateClassOrder()
    {
        $param = $this->param;
        $classlist = $param['class_ids'];
        if (!$param['work_id'] || !$param['class_ids']) {
            return resultArray(['error' => '参数错误']);
        }
        
        # 权限判断
        if (!empty($param['work_id']) && !$this->checkWorkOperationAuth('updateClassOrder', $param['work_id'], $this->userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
        
        foreach ($classlist as $k => $v) {
            $temp = [];
            $temp['order_id'] = $k + 1;
            Db::name('WorkTaskClass')->where(['work_id' => $param['work_id'], 'class_id' => $v])->update($temp);
        }
        
        return resultArray(['data' => '操作成功！']);
    }
    
    /**
     * 任务详情
     * @return
     * @author yykun
     */
    public function read()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        if (!$param['task_id']) {
            return resultArray(['error' => '参数错误']);
        }
        $taskModel = model('Task');
        $taskData = $taskModel->getDataById($param['task_id'], $userInfo);
        
        # 获取任务的项目信息
        $workInfo = Db::name('work')->field(['work_id', 'group_id', 'is_open'])->where('work_id', $taskData['work_id'])->find();
        # 是否是公开项目
        $userId = $userInfo['id'];
        $groupId = !empty($workInfo['is_open']) ? $workInfo['group_id'] : 0;
        # 获取项目下的权限
        $taskData['auth'] = !empty($taskData['work_id']) ? $this->getRuleList($workInfo['work_id'], $userId, $groupId) : [];
        
        if ($taskData) {
            return resultArray(['data' => $taskData]);
        } else {
            return resultArray(['error' => $taskModel->getError()]);
        }
    }
    
    /**
     * 任务编辑
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function update()
    {
        $taskModel = model('Task');
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['create_user_id'] = $userInfo['id'];
        
        # 权限判断
        $action = 'updateChildTask'; # 修改子任务
        if (!empty($param['customer_ids']) || !empty($param['customer_ids']) || !empty($param['customer_ids']) || !empty($param['customer_ids'])) {
            $action = 'saveTaskRelation'; # 关联业务
        } elseif (!empty($param['description'])) {
            $action = 'setTaskDescription'; # 任务描述
        }
        if (!empty($param['work_id']) && !$this->checkWorkOperationAuth($action, $param['work_id'], $this->userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
        
        $ary = array('owner_userid_del', 'owner_userid_add', 'stop_time', 'lable_id_add', 'lable_id_del', 'name', 'structure_id_del', 'structure_id_add');
        if ((in_array($param['type'], $ary))) {
            return resultArray(['error' => '参数错误']);
        }
        if ($taskModel->updateDetTask($param)) {
            return resultArray(['data' => '操作成功']);
        } else {
            return resultArray(['error' => $taskModel->getError()]);
        }
    }
    
    /**
     * 任务操作记录
     * @return
     * @author yykun
     */
    public function readLoglist()
    {
        $param = $this->param;
        $taskModel = model('Task');
        if (!$param['task_id']) return resultArray(['error' => '参数错误']);
        $list = $taskModel->getTaskLogList($param);
        return resultArray(['data' => $list]);
    }
    
    /**
     * 优先级设置
     * @return
     * @author yykun
     */
    public function updatePriority()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['create_user_id'] = $userInfo['id'];
        if (!isset($param['priority_id']) || !$param['task_id']) {
            return resultArray(['error' => '参数错误']);
        }
        $dataInfo=Db::name('Task')->where(['task_id' => $param['task_id']])->find();
        # 权限判断
        if (!empty($param['work_id']) && !$this->checkWorkOperationAuth('setTaskPriority', $param['work_id'], $this->userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
        $priority=[0=>'无',1=>'低',2=>'中',3=>'高'];
        $flag = Db::name('Task')->where(['task_id' => $param['task_id']])->setField('priority', $param['priority_id']);
        if ($flag) {
            RecordActionLog($userInfo['id'], 'work_task', 'update',$dataInfo['name'], '','','修改任务优先级为：'.$priority[$param['priority_id']]);
            return resultArray(['data' => '操作成功']);
        } else {
            return resultArray(['error' => '操作失败']);
        }
    }
    
    /**
     * 参与人/参与部门编辑
     * @return
     * @author yykun
     */
    public function updateOwner()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $task_id = $param['task_id'] ?: '';
        $param['create_user_id'] = $userInfo['id'];
        $taskInfo = db('task')->where(['task_id' => $param['task_id']])->find();
        if (!$taskInfo) {
            return resultArray(['error' => '参数错误']);
        }
        
        # 权限判断
        if (!empty($param['work_id']) && !$this->checkWorkOperationAuth('setTaskOwnerUser', $param['work_id'], $this->userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
        
        $data = [];
        //部门编辑
        $structure_ids = '';
        if ($param['structure_ids']) {
            $structure_ids = arrayToString($param['structure_ids']);
        }
        $owner_user_id = '';
        $sendUserArr = [];
        if ($param['owner_userids']) {
            $owner_user_id = arrayToString($param['owner_userids']);
            foreach ($param['owner_userids'] as $k => $v) {
                if (!in_array($v, stringToArray($taskInfo['owner_user_id']))) {
                    $sendUserArr[] = $v;
                }
            }
            // $content = $userInfo['realname'].'邀请您参与《'.$taskInfo['name'].'》项目，请及时查看';
            // if ($sendUserArr) sendMessage($sendUserArr,$content,1);
            actionLog($param['task_id'], $param['owner_user_id'], $param['structure_ids'], '修改了参与人');
        }
        $data['structure_ids'] = $structure_ids;
        $data['owner_user_id'] = $owner_user_id;
        $resUpdate = db('task')->where(['task_id' => $param['task_id']])->update($data);
        if ($resUpdate) {
            //站内信
            if ($sendUserArr) {
                (new Message())->send(
                    Message::TASK_INVITE,
                    [
                        'title' => $taskInfo['name'],
                        'action_id' => $taskInfo['task_id']
                    ],
                    $sendUserArr
                );
            }
            return resultArray(['data' => '修改成功']);
        }
        return resultArray(['error' => '修改失败或数据无变化']);
    }
    
    /**
     * 单独删除参与人
     * @return
     * @author yykun
     */
    public function delOwnerById()
    {
        $taskModel = model('Task');
        $userInfo = $this->userInfo;
        $param = $this->param;
        $param['create_user_id'] = $userInfo['id'];
        $ary = array('owner_userid_del', 'owner_userid_add');
        if (!in_array($param['type'], $ary)) {
            return resultArray(['error' => '参数错误']);
        }
        
        # 权限判断
        if (!empty($param['work_id']) && !$this->checkWorkOperationAuth('setTaskOwnerUser', $param['work_id'], $this->userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
        
        $ret = $taskModel->updateDetTask($param);
        if ($ret) {
            return resultArray(['data' => '操作成功']);
        } else {
            return resultArray(['error' => $taskModel->getError()]);
        }
    }
    
    /**
     * 单独删除参与部门
     * @return
     * @author yykun
     */
    public function delStruceureById()
    {
        $taskModel = model('Task');
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['create_user_id'] = $userInfo['id'];
        $ary = array('structure_id_del', 'structure_id_add');
        if (!in_array($param['type'], $ary)) {
            return resultArray(['error' => '参数错误']);
        }
        $res = $taskModel->updateDetTask($param);
        if ($res) {
            return resultArray(['data' => '操作成功']);
        } else {
            return resultArray(['error' => $taskModel->getError()]);
        }
    }
    
    /**
     * 设置任务截止时间
     * @return
     * @author yykun
     */
    public function updateStoptime()
    {
        $taskModel = model('Task');
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['create_user_id'] = $userInfo['id'];
//        if (!$param['stop_time']) {
//            return resultArray(['error'=>'参数错误']);
//        }
        
        # 权限判断
        if (!empty($param['work_id']) && !$this->checkWorkOperationAuth('setTaskTime', $param['work_id'], $this->userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
        
        if ($taskModel->updateDetTask($param)) {
            return resultArray(['data' => '操作成功']);
        } else {
            return resultArray(['error' => $taskModel->getError()]);
        }
    }
    
    /**
     * 修改任务标签
     * @return
     * @author yykun
     */
    public function updateLable()
    {
        $taskModel = model('Task');
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['create_user_id'] = $userInfo['id'];
        $ary = array('lable_id_add', 'lable_id_del');
        if (!in_array($param['type'], $ary)) {
            return resultArray(['error' => '参数错误']);
        }
        
        # 权限判断
        if (!empty($param['work_id']) && !$this->checkWorkOperationAuth('setTaskLabel', $param['work_id'], $userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
        
        if (isset($param['lable_id_add']) && !is_array($param['lable_id_add'])) {
            $label_id_arr[] = $param['lable_id_add'];
            $param['lable_id_add'] = $label_id_arr;
        }
        if (isset($param['lable_id_del']) && !is_array($param['lable_id_del'])) {
            $label_id_arr[] = $param['lable_id_del'];
            $param['lable_id_del'] = $label_id_arr;
        }
        if ($taskModel->updateDetTask($param)) {
            return resultArray(['data' => '操作成功']);
        } else {
            return resultArray(['error' => $taskModel->getError()]);
        }
    }
    
    /**
     * 修改任务名称
     * @return
     * @author yykun
     */
    public function updateName()
    {
        $taskModel = model('Task');
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['create_user_id'] = $userInfo['id'];
        if ($param['type'] !== 'name') {
            return resultArray(['error' => '参数错误']);
        }
        
        # 权限判断
        if (!empty($param['work_id']) && !$this->checkWorkOperationAuth('setTaskTitle', $param['work_id'], $userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
        
        $res = $taskModel->updateDetTask($param);
        if ($res) {
            return resultArray(['data' => '操作成功']);
        } else {
            return resultArray(['error' => $taskModel->getError()]);
        }
    }
    
    /**
     * 任务标记结束
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function taskOver()
    {
        $taskModel = model('Task');
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['create_user_id'] = $userInfo['id'];
        if (!$param['task_id'] || !$param['status']) {
            return resultArray(['error' => '参数错误']);
        }
        
        # 权限判断
        $pid = Db::name('task')->where('task_id', $param['task_id'])->value('pid');
        if (!empty($param['work_id']) && !$this->checkWorkOperationAuth(empty($pid) ? 'setTaskStatus' : 'setChildTaskStatus', $param['work_id'], $userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
        
        $taskInfo = Db::name('Task')->where(['task_id' => $param['task_id']])->find();
        if ($param['status'] == '5') {
            $flag = Db::name('Task')->where(['task_id' => $param['task_id']])->setField('status', 5);
            if ($flag && !$taskInfo['pid']) {
                $temp['user_id'] = $userInfo['id'];
                $temp['content'] = '任务标记结束';
                $temp['create_time'] = time();
                $temp['task_id'] = $param['task_id'];
                Db::name('WorkTaskLog')->insert($temp);
//                actionLog($taskInfo['task_id'], $taskInfo['owner_user_id'], $taskInfo['structure_ids'], '任务标记结束');
                RecordActionLog($userInfo['id'], 'work_task', 'update',$taskInfo['name'], '','','将状态修改为：完成');
                //抄送站内信
                $sendUserArr = [];
                $sendUserArr[] = $taskInfo['create_user_id'];
                if ($taskInfo['main_user_id']) {
                    $sendUserArr[] = $taskInfo['main_user_id'];
                }
                if ($taskInfo['owner_user_id']) {
                    $sendUserArr = $sendUserArr ? array_merge($sendUserArr, stringToArray($taskInfo['owner_user_id'])) : stringToArray($taskInfo['owner_user_id']);
                }
                if ($sendUserArr) {
                    (new Message())->send(
                        Message::TASK_OVER,
                        [
                            'title' => $taskInfo['name'],
                            'action_id' => $param['task_id']
                        ],
                        $sendUserArr
                    );
                }
            }
        } else {
            $flag = Db::name('Task')->where('task_id =' . $param['task_id'])->setField('status', 1);
            if ($flag && !$taskInfo['pid']) {
                $temp['user_id'] = $userInfo['id'];
                $temp['content'] = '任务标记开始';
                $temp['create_time'] = time();
                $temp['task_id'] = $param['task_id'];
                Db::name('WorkTaskLog')->insert($temp);
//                actionLog($taskInfo['task_id'], $taskInfo['owner_user_id'], $taskInfo['structure_ids'], '任务标记开始');
                RecordActionLog($userInfo['id'], 'work_task', 'update',$taskInfo['name'], '','','将状态修改为：未完成');
            }
        }
        if ($flag) {
            $statusText = $param['status'] == '5' ? '已完成' : '未完成';
            $syncLedgerStatus = !array_key_exists('sync_ledger_status', $param) || (int)$param['sync_ledger_status'] === 1;
            if ($syncLedgerStatus) {
                $this->syncLedgerByTaskStatus((int)$param['task_id'], (int)$userInfo['id'], (int)$param['status']);
            }
            (new DingTalkLogic())->sendTaskNotify('状态变更', $param['task_id'], $userInfo['id'], [
                'status_text' => $statusText,
                'summary' => '任务状态变更'
            ]);
            return resultArray(['data' => true]);
        } else {
            return resultArray(['error' => '标记失败']);
        }
    }
    
    /**
     * 日历任务展示/月份
     * @return
     * @author yykun
     */
    public function dateList()
    {
        $param = $this->param;
        $taskModel = model('Task');
        $userInfo = $this->userInfo;
        $param['user_id'] = $userInfo['id'];
        $data = $taskModel->getDateList($param);
        return resultArray(['data' => $data]);
    }
    
    /**
     * 添加任务
     * @return
     * @author Michael_xu
     */
    public function save()
    {
        $param = $this->param;
        $taskModel = model('Task');
        $workModel = model('Work');
        if (!$param['name']) {
            return resultArray(['error' => '参数错误']);
        }
        $userInfo = $this->userInfo;
        $param['create_user_id'] = $userInfo['id'];
        $param['create_user_name'] = $userInfo['realname'];
        # 任务权限判断
        if (!empty($param['work_id']) && !$this->checkWorkOperationAuth(empty($param['pid']) ? 'addChildTask' : 'saveTask', $param['work_id'], $userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
//        if ($param['work_id'] && !$workModel->isCheck('work','task','save',$param['work_id'],$userInfo['id'])) {
//            header('Content-Type:application/json; charset=utf-8');
//            exit(json_encode(['code'=>102,'error'=>'无权操作']));
//        }
        $res = $taskModel->createTask($param);
        if ($res) {
            (new DingTalkLogic())->sendTaskNotify('任务创建', $res, $userInfo['id']);
            return resultArray(['data' => $res]);
        } else {
            return resultArray(['error' => $taskModel->getError()]);
        }
    }
    
    /**
     * 删除主负责人
     * @return
     * @author yykun
     */
    public function delMainUserId()
    {
        $param = $this->param;
        $workModel = model('Task');
        if ($param['task_id']) {
            $userInfo = $this->userInfo;
            $param['create_user_id'] = $userInfo['id'];
            $taskInfo = Db::name('Task')->where(['task_id' => $param['task_id']])->find();
            $data = [];
            $data['main_user_id'] = '';
            $data['status'] = 1;
            $flag = Db::name('Task')->where(['task_id' => $param['task_id']])->update($data);
            if ($flag && !$taskInfo['pid']) {
                actionLog($taskInfo['task_id'], $taskInfo['owner_user_id'], $taskInfo['structure_ids'], '删除负责人');
                return resultArray(['data' => '操作成功']);
            }
            return resultArray(['error' => '操作失败']);
        } else {
            return resultArray(['error' => '参数错误']);
        }
    }
    
    /**
     * 重命名任务
     * @return
     * @author yykun
     */
    public function rename()
    {
        $param = $this->param;
        $workModel = model('Work');
        if (!$param['rename'] || !$param['work_id']) {
            return resultArray(['error' => '参数错误']);
        }
        $userInfo = $this->userInfo;
        $param['create_user_id'] = $userInfo['id'];
        $flag = $workModel->rename($param);
        if ($flag) {
            return resultArray(['data' => '编辑成功']);
        } else {
            return resultArray(['error' => $workModel->getError()]);
        }
    }
    
    /**
     * 删除任务
     * @return
     * @author yykun
     */
    public function delete()
    {
        $param = $this->param;
        $taskModel = model('Task');
        if (!$param['task_id']) {
            return resultArray(['error' => '参数错误']);
        }
        
        # 权限判断
        $pid = Db::name('task')->where('task_id', $param['task_id'])->value('pid');
        if (!empty($param['work_id']) && !$this->checkWorkOperationAuth(empty($pid) ? 'deleteTask' : 'deleteChildTask', $param['work_id'], $this->userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
        $dataInfo=db('task')->where('task_id',$param['task_id'])->find();
        $userInfo = $this->userInfo;
        $param['create_user_id'] = $userInfo['id'];
        $flag = $taskModel->delTaskById($param);
        if ($flag) {
            RecordActionLog($userInfo['id'], 'work_task', 'delete', $dataInfo['name'], '', '', '删除了任务：' . $dataInfo['name']);
            return resultArray(['data' => '删除成功']);
        } else {
            return resultArray(['error' => $taskModel->getError()]);
        }
    }
    
    /**
     * 归档任务
     * @return
     * @author yykun
     */
    public function archive()
    {
        $param = $this->param;
        $taskModel = model('Task');
        if (!$param['task_id']) {
            return resultArray(['error' => '参数错误']);
        }
        
        # 权限判断
        if (!empty($param['work_id']) && !$this->checkWorkOperationAuth('archiveTask', $param['work_id'], $this->userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
        
        $userInfo = $this->userInfo;
        $param['create_user_id'] = $userInfo['id'];
        $flag = $taskModel->archiveData($param);
        if ($flag) {
            $temp['user_id'] = $userInfo['id'];
            $temp['content'] = '归档任务';
            $temp['create_time'] = time();
            $temp['task_id'] = $param['task_id'];
            Db::name('WorkTaskLog')->insert($temp);
            return resultArray(['data' => '归档成功']);
        } else {
            return resultArray(['error' => $taskModel->getError()]);
        }
    }
    
    /**
     * 恢复归档任务
     * @return
     * @author yykun
     */
    public function recover()
    {
        $param = $this->param;
        $taskModel = model('Task');
        if (!$param['task_id']) {
            return resultArray(['error' => '参数错误']);
        }
        
        # 权限判断
        if (!empty($param['work_id']) && !$this->checkWorkOperationAuth('archiveTask', $param['work_id'], $this->userInfo['id'])) {
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode(['code' => 102, 'error' => '无权操作！']));
        }
        
        $userInfo = $this->userInfo;
        $param['create_user_id'] = $userInfo['id'];
        $flag = $taskModel->recover($param);
        if ($flag) {
            $temp['user_id'] = $userInfo['id'];
            $temp['content'] = '恢复归档任务';
            $temp['create_time'] = time();
            $temp['task_id'] = $param['task_id'];
            Db::name('WorkTaskLog')->insert($temp);
            return resultArray(['data' => '操作成功']);
        } else {
            return resultArray(['error' => $taskModel->getError()]);
        }
    }
    
    /**
     * 归档任务列表
     * @return
     * @author yykun
     */
    public function archList()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $taskModel = model('Task');
        if (!$param['work_id']) return resultArray(['error' => '参数错误']);
        $request = [];
        $request['work_id'] = $param['work_id'];
        $request['is_archive'] = 1;
        $list = $taskModel->getTaskList($request);
        return resultArray(['data' => $list]);
    }
    
    /**
     * 归档某一类已完成任务
     * @return
     * @author yykun
     */
    public function archiveTask()
    {
        $param = $this->param;
        if (!$param['class_id']) return resultArray(['error' => '参数错误']);
        $data = array();
        $data['is_archive'] = 1;
        $data['archive_time'] = time();
        $res = db('task')->where(['class_id' => $param['class_id'], 'status' => '5'])->update($data);
        if ($res) {
            return resultArray(['data' => '操作成功']);
        } else {
            return resultArray(['error' => '暂无已完成任务，归档失败！']);
        }
    }
    
    /**
     * 任务成员列表
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function taskUsers()
    {
        $userId = $this->userInfo['id'];
        
        # 查询条件
        $where['create_user_id'] = $userId;
        $where['main_user_id'] = $userId;
        $where['owner_user_id'] = ['like', '%,' . $userId . ',%'];
        
        # 查询数据
        $data = Db::name('task')->field(['create_user_id', 'main_user_id', 'owner_user_id'])->whereOr($where)->select();
        
        # 整理数据
        $userIds = [];
        foreach ($data as $key => $value) {
            if (!empty($value['create_user_id'])) $userIds[] = $value['create_user_id'];
            if (!empty($value['main_user_id'])) $userIds[] = $value['main_user_id'];
            
            $ownerUserIds = explode(',', $value['owner_user_id']);
            foreach ($ownerUserIds as $k => $v) {
                if (!empty($v)) $userIds[] = $v;
            }
        }
        $userIds = array_unique($userIds);
        
        # 查询参与人
        $userList = Db::name('admin_user')->field(['id', 'realname'])->whereIn('id', $userIds)->select();

        return resultArray(['data' => $userList]);
    }

    // ===================== P0 任务工作流、W/R/K、轻量测试（骨架稳定化）=====================

    /** @var WorkflowService */
    private $wfService;

    private function wf()
    {
        if (!$this->wfService) {
            $this->wfService = new WorkflowService();
        }
        return $this->wfService;
    }

    /**
     * 集中任务权限校验（所有 P0 接口统一入口）。
     * @param int $taskId
     * @param string $level  'read' 查看 | 'manage' 状态管理
     * @return array [bool $ok, string|array $payload]  通过时 $payload 为 task 行
     */
    protected function assertTaskAuth($taskId, $level)
    {
        $userInfo = $this->userInfo;
        $taskId = (int)$taskId;
        $task = Db::name('task')->where(['task_id' => $taskId, 'ishidden' => 0])->find();
        if (!$task) {
            return [false, '任务不存在或已删除'];
        }
        $userId = (int)$userInfo['id'];
        if ($level === 'read') {
            $taskModel = new \app\work\model\Task();
            if (!$taskModel->checkTask($taskId, $userInfo)) {
                return [false, '无权查看该任务'];
            }
            return [true, $task];
        }
        // manage 级别
        $adminTypes = adminGroupTypes($userId);
        if (in_array(1, $adminTypes) || in_array(7, $adminTypes)) {
            return [true, $task];
        }
        // 项目任务：校验项目操作权限（work_id=0 不跳过）
        if (!empty($task['work_id'])) {
            if (!$this->checkWorkOperationAuth('setTaskStatus', $task['work_id'], $userId)) {
                return [false, '无权管理该任务状态'];
            }
            return [true, $task];
        }
        // OA 任务（work_id=0）：要求创建人或负责人
        if ((int)$task['create_user_id'] === $userId || (int)$task['main_user_id'] === $userId) {
            return [true, $task];
        }
        return [false, '无权管理该任务状态'];
    }

    /**
     * 要求客户端传入有效版本号（>0），不允许以 0 跳过乐观锁。
     */
    protected function requireVersion(array $param)
    {
        $v = (int)($param['version'] ?? 0);
        if ($v <= 0) {
            return [false, 0];
        }
        return [true, $v];
    }

    /**
     * W/R/K 字典（前后端共享）
     */
    public function wrkDictionary()
    {
        return resultArray(['data' => WorkflowService::wrkDictionary()]);
    }

    /**
     * 读取任务工作流与测试信息（需查看权限）
     */
    public function workflowRead()
    {
        $param = $this->param;
        $taskId = (int)($param['task_id'] ?? 0);
        if ($taskId <= 0) {
            return resultArray(['error' => '参数错误']);
        }
        list($ok, $payload) = $this->assertTaskAuth($taskId, 'read');
        if (!$ok) {
            return resultArray(['error' => $payload]);
        }
        $wf = $this->wf()->getWorkflow($taskId);
        $data = [
            'task_id' => $taskId,
            'legacy' => $wf ? false : true,
            'workflow_version' => $wf ? (int)$wf['workflow_version'] : 0,
            'main_status' => $wf ? $wf['main_status'] : '',
            'aux_status' => $wf ? $wf['aux_status'] : '',
            'init_w' => $wf ? $wf['init_w'] : null,
            'init_r' => $wf ? $wf['init_r'] : null,
            'init_k' => $wf ? $wf['init_k'] : null,
            'final_w' => $wf ? $wf['final_w'] : null,
            'final_r' => $wf ? $wf['final_r'] : null,
            'final_k' => $wf ? $wf['final_k'] : null,
            'wrk_frozen' => $wf ? (int)$wf['wrk_frozen'] : 0,
            'acceptance_criteria' => $wf ? $wf['acceptance_criteria'] : '',
            'acceptance_user_id' => $wf ? (int)$wf['acceptance_user_id'] : 0,
            'risk_note' => $wf ? $wf['risk_note'] : '',
            'professional_confirm' => $wf ? $wf['professional_confirm'] : '',
            'need_release' => $wf ? (int)$wf['need_release'] : 1,
            'need_customer_verify' => $wf ? (int)$wf['need_customer_verify'] : 1,
            'version' => $wf ? (int)$wf['version'] : 0,
        ];
        $testExt = Db::name('task_test_ext')->where(['task_id' => $taskId])->find();
        $data['is_test_task'] = $testExt ? true : false;
        $data['test_ext'] = $testExt ?: null;
        return resultArray(['data' => $data]);
    }

    /**
     * 事务内提交状态迁移：乐观锁更新 + 审计 + 旧状态兼容 + 台账同步。
     * @param array $wf 工作流行
     * @param string $action 动作名
     * @param string $targetStatus 目标主状态
     * @param array $extraWfUpdate 工作流附加更新（如 final_w 等）
     * @param string $reason
     * @return array [bool $ok, string|array $payload]
     */
    protected function commitTransition(array $wf, $action, $targetStatus, array $extraWfUpdate = [], $reason = '')
    {
        $taskId = (int)$wf['task_id'];
        $currentStatus = $wf['main_status'];
        $version = (int)$wf['version'];
        $userInfo = $this->userInfo;
        // 收集 W/R/K 字段变更用于审计
        $wrkFields = ['init_w', 'init_r', 'init_k', 'final_w', 'final_r', 'final_k'];
        $fieldChanges = [];
        $wrkLogs = [];
        $now = time();
        foreach ($wrkFields as $f) {
            if (array_key_exists($f, $extraWfUpdate)) {
                $oldVal = isset($wf[$f]) ? (string)$wf[$f] : '';
                $newVal = (string)$extraWfUpdate[$f];
                if ($oldVal !== $newVal) {
                    $fieldChanges[$f] = [$oldVal, $newVal];
                    $wrkLogs[] = [
                        'task_id' => $taskId,
                        'field_name' => $f,
                        'old_value' => $oldVal,
                        'new_value' => $newVal,
                        'reason' => (string)$reason,
                        'user_id' => (int)$userInfo['id'],
                        'create_time' => $now,
                    ];
                }
            }
        }
        Db::startTrans();
        try {
            $update = array_merge([
                'main_status' => $targetStatus,
                'version' => $version + 1,
                'update_user_id' => (int)$userInfo['id'],
                'update_time' => $now,
            ], $extraWfUpdate);
            $affected = Db::name('task_workflow')
                ->where(['task_id' => $taskId, 'version' => $version])
                ->update($update);
            if (!$affected) {
                Db::rollback();
                return [false, '并发冲突，数据版本已变化，请刷新后重试'];
            }
            // W/R/K 变更审计写入（在同一事务内，失败则整体回滚）
            if ($wrkLogs) {
                Db::name('task_wrk_log')->insertAll($wrkLogs);
            }
            // 状态迁移审计（含本次关键字段前后值）
            $this->wf()->logTransition($taskId, $action, $currentStatus, $targetStatus, $fieldChanges, $reason, $userInfo['id']);
            $legacyStatus = ($targetStatus === WorkflowService::STATUS_DONE) ? 5 : 1;
            Db::name('task')->where(['task_id' => $taskId])->update(['status' => $legacyStatus, 'update_time' => $now]);
            $this->syncLedgerByTaskStatus($taskId, (int)$userInfo['id'], $legacyStatus);
            Db::commit();
            return [true, ['task_id' => $taskId, 'main_status' => $targetStatus, 'version' => $version + 1]];
        } catch (\Exception $e) {
            Db::rollback();
            return [false, '状态迁移失败：' . $e->getMessage()];
        }
    }

    /** 评估：待评估 → 待处理 */
    public function evaluate()
    {
        return $this->runSimpleTransition('evaluate', WorkflowService::STATUS_PENDING_HANDLE);
    }

    /**
     * 简单状态迁移的统一入口（含权限、版本、辅助状态、迁移合法性校验）。
     */
    protected function runSimpleTransition($action, $expectedTarget)
    {
        $param = $this->param;
        $taskId = (int)($param['task_id'] ?? 0);
        if ($taskId <= 0) {
            return resultArray(['error' => '参数错误']);
        }
        list($okAuth, $task) = $this->assertTaskAuth($taskId, 'manage');
        if (!$okAuth) {
            return resultArray(['error' => $task]);
        }
        list($okV, $version) = $this->requireVersion($param);
        if (!$okV) {
            return resultArray(['error' => '必须提供有效版本号']);
        }
        $wf = $this->wf()->getWorkflow($taskId);
        if (!$wf) {
            return resultArray(['error' => '该任务未启用 P0 工作流，请先执行 P0 迁移']);
        }
        if ((int)$wf['version'] !== $version) {
            return resultArray(['error' => '数据版本已变化，请刷新后重试']);
        }
        if (!empty($wf['aux_status']) && in_array($wf['aux_status'], ['阻塞', '暂缓', '取消', '无需处理'], true)) {
            return resultArray(['error' => '当前辅助状态为「' . $wf['aux_status'] . '」，不能执行该动作']);
        }
        $target = $this->wf()->resolveTargetStatus($action, $wf['main_status']);
        if ($target === false || $target !== $expectedTarget) {
            return resultArray(['error' => '当前状态「' . $wf['main_status'] . '」不能执行该动作']);
        }
        list($ok, $payload) = $this->commitTransition($wf, $action, $target, [], (string)($param['reason'] ?? ''));
        if ($ok) {
            return resultArray(['data' => $payload]);
        }
        return resultArray(['error' => $payload]);
    }

    /** 开始处理：待处理 → 处理中（必须一次性提供完整初始 W/R/K，与冻结同事务）*/
    public function startProcess()
    {
        $param = $this->param;
        $taskId = (int)($param['task_id'] ?? 0);
        if ($taskId <= 0) {
            return resultArray(['error' => '参数错误']);
        }
        list($okAuth, $task) = $this->assertTaskAuth($taskId, 'manage');
        if (!$okAuth) {
            return resultArray(['error' => $task]);
        }
        list($okV, $version) = $this->requireVersion($param);
        if (!$okV) {
            return resultArray(['error' => '必须提供有效版本号']);
        }
        $wf = $this->wf()->getWorkflow($taskId);
        if (!$wf) {
            return resultArray(['error' => '该任务未启用 P0 工作流，请先执行 P0 迁移']);
        }
        if ((int)$wf['version'] !== $version) {
            return resultArray(['error' => '数据版本已变化，请刷新后重试']);
        }
        $target = $this->wf()->resolveTargetStatus('start', $wf['main_status']);
        if ($target === false) {
            return resultArray(['error' => '当前状态「' . $wf['main_status'] . '」不能开始处理']);
        }
        // 必须一次性提供完整初始 W/R/K（若尚未设置）
        $needSetInit = (empty($wf['init_w']) || empty($wf['init_r']) || empty($wf['init_k']));
        if ($needSetInit) {
            $initW = trim((string)($param['init_w'] ?? ''));
            $initR = trim((string)($param['init_r'] ?? ''));
            $initK = trim((string)($param['init_k'] ?? ''));
            if ($initW === '' || $initR === '' || $initK === '') {
                return resultArray(['error' => '开始处理必须一次性提供完整初始 W/R/K']);
            }
            foreach (['init_w' => $initW, 'init_r' => $initR, 'init_k' => $initK] as $f => $val) {
                $err = $this->wf()->validateWrkField($f, $val);
                if ($err) {
                    return resultArray(['error' => $err]);
                }
            }
            $extraUpdate = ['init_w' => $initW, 'init_r' => $initR, 'init_k' => $initK, 'wrk_frozen' => 1];
        } else {
            $extraUpdate = ['wrk_frozen' => 1];
        }
        list($ok, $payload) = $this->commitTransition($wf, 'start', $target, $extraUpdate, '开始处理');
        if ($ok) {
            return resultArray(['data' => $payload]);
        }
        return resultArray(['error' => $payload]);
    }

    /** 提交内部验收：处理中 → 待内部验收（必须完整最终 W/R/K、验收标准、验收人）*/
    public function submitAcceptance()
    {
        $param = $this->param;
        $taskId = (int)($param['task_id'] ?? 0);
        if ($taskId <= 0) {
            return resultArray(['error' => '参数错误']);
        }
        list($okAuth, $task) = $this->assertTaskAuth($taskId, 'manage');
        if (!$okAuth) {
            return resultArray(['error' => $task]);
        }
        list($okV, $version) = $this->requireVersion($param);
        if (!$okV) {
            return resultArray(['error' => '必须提供有效版本号']);
        }
        $wf = $this->wf()->getWorkflow($taskId);
        if (!$wf) {
            return resultArray(['error' => '该任务未启用 P0 工作流，请先执行 P0 迁移']);
        }
        if ((int)$wf['version'] !== $version) {
            return resultArray(['error' => '数据版本已变化，请刷新后重试']);
        }
        $target = $this->wf()->resolveTargetStatus('submit_acceptance', $wf['main_status']);
        if ($target === false) {
            return resultArray(['error' => '当前状态「' . $wf['main_status'] . '」不能提交验收']);
        }
        $finalW = trim((string)($param['final_w'] ?? ''));
        $finalR = trim((string)($param['final_r'] ?? ''));
        $finalK = trim((string)($param['final_k'] ?? ''));
        $criteria = trim((string)($param['acceptance_criteria'] ?? ''));
        $acceptUserId = (int)($param['acceptance_user_id'] ?? 0);
        if ($finalW === '' || $finalR === '' || $finalK === '') {
            return resultArray(['error' => '提交验收必须一次性提供完整最终 W/R/K']);
        }
        if ($criteria === '') {
            return resultArray(['error' => '提交验收必须提供验收标准']);
        }
        if ($acceptUserId <= 0) {
            return resultArray(['error' => '提交验收必须指定验收人']);
        }
        foreach (['final_w' => $finalW, 'final_r' => $finalR, 'final_k' => $finalK] as $f => $val) {
            $err = $this->wf()->validateWrkField($f, $val);
            if ($err) {
                return resultArray(['error' => $err]);
            }
        }
        $extraUpdate = [
            'final_w' => $finalW, 'final_r' => $finalR, 'final_k' => $finalK,
            'acceptance_criteria' => $criteria, 'acceptance_user_id' => $acceptUserId,
        ];
        list($ok, $payload) = $this->commitTransition($wf, 'submit_acceptance', $target, $extraUpdate, '提交内部验收');
        if ($ok) {
            return resultArray(['data' => $payload]);
        }
        return resultArray(['error' => $payload]);
    }

    /** 内部验收通过：只能由指定验收人操作 → 待发布 */
    public function acceptancePass()
    {
        $param = $this->param;
        $taskId = (int)($param['task_id'] ?? 0);
        if ($taskId <= 0) {
            return resultArray(['error' => '参数错误']);
        }
        list($okAuth, $task) = $this->assertTaskAuth($taskId, 'manage');
        if (!$okAuth) {
            return resultArray(['error' => $task]);
        }
        list($okV, $version) = $this->requireVersion($param);
        if (!$okV) {
            return resultArray(['error' => '必须提供有效版本号']);
        }
        $wf = $this->wf()->getWorkflow($taskId);
        if (!$wf || (int)$wf['version'] !== $version) {
            return resultArray(['error' => '数据版本已变化，请刷新后重试']);
        }
        if ($wf['main_status'] !== WorkflowService::STATUS_ACCEPTANCE) {
            return resultArray(['error' => '当前状态不能验收']);
        }
        // 只能由指定验收人操作（未指定则要求有管理权限，已在 assertTaskAuth 校验）
        if ((int)$wf['acceptance_user_id'] > 0 && (int)$wf['acceptance_user_id'] !== (int)$this->userInfo['id']) {
            $adminTypes = adminGroupTypes((int)$this->userInfo['id']);
            if (!in_array(1, $adminTypes) && !in_array(7, $adminTypes)) {
                return resultArray(['error' => '只能由指定的验收人操作']);
            }
        }
        return $this->runSimpleTransition('acceptance_pass', WorkflowService::STATUS_RELEASE);
    }

    /** 内部验收退回 → 处理中 */
    public function acceptanceReturn()
    {
        $param = $this->param;
        if (empty($param['reason'])) {
            return resultArray(['error' => '验收退回必须填写原因']);
        }
        return $this->runSimpleTransition('acceptance_return', WorkflowService::STATUS_PROCESSING);
    }

    /** 申请发布：只读预检（不持久化批准状态）*/
    public function applyRelease()
    {
        $param = $this->param;
        $taskId = (int)($param['task_id'] ?? 0);
        if ($taskId <= 0) {
            return resultArray(['error' => '参数错误']);
        }
        list($okAuth, $task) = $this->assertTaskAuth($taskId, 'manage');
        if (!$okAuth) {
            return resultArray(['error' => $task]);
        }
        $wf = $this->wf()->getWorkflow($taskId);
        if (!$wf) {
            return resultArray(['error' => '该任务未启用 P0 工作流']);
        }
        if ($wf['main_status'] !== WorkflowService::STATUS_RELEASE) {
            return resultArray(['error' => '当前状态不能申请发布']);
        }
        if ((int)$wf['need_release'] === 1) {
            list($ok, $reason) = $this->wf()->checkReleaseGate($taskId);
            if (!$ok) {
                return resultArray(['error' => $reason]);
            }
        }
        // 仅返回预检通过，不持久化；confirmRelease 会重新检查
        return resultArray(['data' => ['task_id' => $taskId, 'release_ready' => true, 'note' => '预检通过，确认发布时将再次检查门禁']]);
    }

    /** 确认发布：待发布 → 待客户验证（每次重新执行完整发布门禁）*/
    public function confirmRelease()
    {
        $param = $this->param;
        $taskId = (int)($param['task_id'] ?? 0);
        if ($taskId <= 0) {
            return resultArray(['error' => '参数错误']);
        }
        list($okAuth, $task) = $this->assertTaskAuth($taskId, 'manage');
        if (!$okAuth) {
            return resultArray(['error' => $task]);
        }
        list($okV, $version) = $this->requireVersion($param);
        if (!$okV) {
            return resultArray(['error' => '必须提供有效版本号']);
        }
        $wf = $this->wf()->getWorkflow($taskId);
        if (!$wf || (int)$wf['version'] !== $version) {
            return resultArray(['error' => '数据版本已变化，请刷新后重试']);
        }
        if ($wf['main_status'] !== WorkflowService::STATUS_RELEASE) {
            return resultArray(['error' => '当前状态不能确认发布']);
        }
        // 每次确认发布都重新执行完整门禁，不得绕过
        if ((int)$wf['need_release'] === 1) {
            list($ok, $reason) = $this->wf()->checkReleaseGate($taskId);
            if (!$ok) {
                return resultArray(['error' => '发布门禁未通过：' . $reason]);
            }
        }
        $extraUpdate = [];
        if (!empty($param['actual_release_version'])) {
            $extraUpdate['actual_release_version'] = (string)$param['actual_release_version'];
        }
        list($ok, $payload) = $this->commitTransition($wf, 'confirm_release', WorkflowService::STATUS_CUSTOMER, $extraUpdate, '确认发布');
        if ($ok) {
            return resultArray(['data' => $payload]);
        }
        return resultArray(['error' => $payload]);
    }

    /** 客户确认 → 已完成 */
    public function customerConfirm()
    {
        return $this->runSimpleTransition('customer_confirm', WorkflowService::STATUS_DONE);
    }

    /** 客户退回 → 处理中 */
    public function customerReturn()
    {
        $param = $this->param;
        if (empty($param['reason'])) {
            return resultArray(['error' => '客户退回必须填写原因']);
        }
        return $this->runSimpleTransition('customer_return', WorkflowService::STATUS_PROCESSING);
    }

    /** 直接完成（无需客户验证的任务）*/
    public function completeTask()
    {
        $param = $this->param;
        $taskId = (int)($param['task_id'] ?? 0);
        if ($taskId <= 0) {
            return resultArray(['error' => '参数错误']);
        }
        list($okAuth, $task) = $this->assertTaskAuth($taskId, 'manage');
        if (!$okAuth) {
            return resultArray(['error' => $task]);
        }
        $wf = $this->wf()->getWorkflow($taskId);
        if (!$wf) {
            return resultArray(['error' => '该任务未启用 P0 工作流']);
        }
        if ((int)$wf['need_customer_verify'] === 1 && $wf['main_status'] !== WorkflowService::STATUS_CUSTOMER) {
            return resultArray(['error' => '需要客户验证的任务须先进入待客户验证']);
        }
        if ($wf['main_status'] !== WorkflowService::STATUS_RELEASE && $wf['main_status'] !== WorkflowService::STATUS_CUSTOMER) {
            return resultArray(['error' => '当前状态不能完成']);
        }
        return $this->runSimpleTransition('complete', WorkflowService::STATUS_DONE);
    }

    /** 设置辅助状态（阻塞/暂缓/取消/重复/无需处理）—— 版本号 + 事务 */
    public function setAuxStatus()
    {
        $param = $this->param;
        $taskId = (int)($param['task_id'] ?? 0);
        $auxStatus = (string)($param['aux_status'] ?? '');
        $allowedAux = ['阻塞', '暂缓', '取消', '重复', '无需处理', ''];
        if (!in_array($auxStatus, $allowedAux, true)) {
            return resultArray(['error' => '辅助状态不合法']);
        }
        list($okAuth, $task) = $this->assertTaskAuth($taskId, 'manage');
        if (!$okAuth) {
            return resultArray(['error' => $task]);
        }
        list($okV, $version) = $this->requireVersion($param);
        if (!$okV) {
            return resultArray(['error' => '必须提供有效版本号']);
        }
        $wf = $this->wf()->getWorkflow($taskId);
        if (!$wf || (int)$wf['version'] !== $version) {
            return resultArray(['error' => '数据版本已变化，请刷新后重试']);
        }
        Db::startTrans();
        try {
            $affected = Db::name('task_workflow')->where(['task_id' => $taskId, 'version' => $version])->update([
                'aux_status' => $auxStatus,
                'aux_reason' => (string)($param['reason'] ?? ''),
                'version' => $version + 1,
                'update_user_id' => (int)$this->userInfo['id'],
                'update_time' => time(),
            ]);
            if (!$affected) {
                Db::rollback();
                return resultArray(['error' => '并发冲突，请刷新后重试']);
            }
            $this->wf()->logTransition($taskId, 'set_aux:' . $auxStatus, $wf['aux_status'], $auxStatus, [], (string)($param['reason'] ?? ''), $this->userInfo['id']);
            Db::commit();
            return resultArray(['data' => ['task_id' => $taskId, 'aux_status' => $auxStatus, 'version' => $version + 1]]);
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => '操作失败：' . $e->getMessage()]);
        }
    }

    /** 有审计地豁免发布或客户验证 —— 版本号 + 事务 */
    public function setReleaseExemption()
    {
        $param = $this->param;
        $taskId = (int)($param['task_id'] ?? 0);
        if (empty($param['reason'])) {
            return resultArray(['error' => '豁免必须填写原因']);
        }
        list($okAuth, $task) = $this->assertTaskAuth($taskId, 'manage');
        if (!$okAuth) {
            return resultArray(['error' => $task]);
        }
        list($okV, $version) = $this->requireVersion($param);
        if (!$okV) {
            return resultArray(['error' => '必须提供有效版本号']);
        }
        $wf = $this->wf()->getWorkflow($taskId);
        if (!$wf || (int)$wf['version'] !== $version) {
            return resultArray(['error' => '数据版本已变化，请刷新后重试']);
        }
        $update = ['release_skip_reason' => (string)$param['reason'], 'release_skip_user_id' => (int)$this->userInfo['id'], 'release_skip_time' => time(), 'update_time' => time(), 'version' => $version + 1];
        if (array_key_exists('need_release', $param)) {
            $update['need_release'] = $param['need_release'] ? 1 : 0;
        }
        if (array_key_exists('need_customer_verify', $param)) {
            $update['need_customer_verify'] = $param['need_customer_verify'] ? 1 : 0;
        }
        Db::startTrans();
        try {
            $affected = Db::name('task_workflow')->where(['task_id' => $taskId, 'version' => $version])->update($update);
            if (!$affected) {
                Db::rollback();
                return resultArray(['error' => '并发冲突，请刷新后重试']);
            }
            $this->wf()->logTransition($taskId, 'release_exemption', '', '', $update, (string)$param['reason'], $this->userInfo['id']);
            Db::commit();
            return resultArray(['data' => ['task_id' => $taskId, 'version' => $version + 1]]);
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => '操作失败：' . $e->getMessage()]);
        }
    }

    /**
     * updateWrk —— 本轮关闭（冻结后更正能力未开放，不暴露路由）。
     * 防止绕过冻结规则直接修改 W/R/K。
     */
    public function updateWrk()
    {
        return resultArray(['error' => 'W/R/K 更正功能尚未开放，请通过状态动作设置初始/最终值']);
    }

    // ===================== 轻量测试任务闭环 =====================

    /** 发起测试：必须指定评定人，request_id 幂等，复用现有任务模块生成 test 任务 */
    public function initiateTest()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $originTaskId = (int)($param['origin_task_id'] ?? 0);
        if ($originTaskId <= 0) {
            return resultArray(['error' => '必须指定原研发任务']);
        }
        // 必须有原研发任务的状态管理权限
        list($okAuth, $originTask) = $this->assertTaskAuth($originTaskId, 'manage');
        if (!$okAuth) {
            return resultArray(['error' => $originTask]);
        }
        $requestId = trim((string)($param['request_id'] ?? ''));
        if ($requestId === '') {
            return resultArray(['error' => '发起测试必须提供 request_id 幂等标识']);
        }
        $reviewerUserId = (int)($param['reviewer_user_id'] ?? 0);
        if ($reviewerUserId <= 0) {
            return resultArray(['error' => '发起测试必须指定评定人']);
        }
        $testers = !empty($param['testers']) ? $param['testers'] : [];
        if (!is_array($testers) || !$testers) {
            return resultArray(['error' => '至少指定一名测试人员']);
        }
        // 评定人不能等于任何测试执行人
        foreach ($testers as $t) {
            if ((int)$t === $reviewerUserId) {
                return resultArray(['error' => '评定人不能同时是测试执行人']);
            }
        }
        $testType = (string)($param['test_type'] ?? '');
        $allowedTestTypes = array_keys(WorkflowService::testTypeDictionary());
        if (!in_array($testType, $allowedTestTypes, true)) {
            return resultArray(['error' => '测试类型必须为：' . implode(' 或 ', $allowedTestTypes)]);
        }
        $testScope = (string)($param['test_scope'] ?? '');
        $completionCriteria = (string)($param['completion_criteria'] ?? '');
        $deadline = !empty($param['deadline']) ? (is_numeric($param['deadline']) ? (int)$param['deadline'] : strtotime($param['deadline'])) : 0;
        $isRequired = empty($param['is_required']) ? 0 : 1;
        $sourceType = (string)($param['source_type'] ?? 'task');
        $sourceId = (int)($param['source_id'] ?? $originTaskId);
        $now = time();
        // 业务测试人员不能与原任务主要负责人相同（后端校验）
        $originMainUserId = (int)($originTask['main_user_id'] ?? 0);
        if ($testType === WorkflowService::TEST_TYPE_BUSINESS) {
            foreach ($testers as $t) {
                if ((int)$t === $originMainUserId && $originMainUserId > 0) {
                    return resultArray(['error' => '业务测试人员不能与原任务主要负责人相同']);
                }
            }
        }
        // createTask() 内部会对 start_time/stop_time 调用 strtotime()，必须传 Y-m-d H:i:s 字符串
        $startTimeStr = date('Y-m-d H:i:s', $now);
        $stopTimeStr = $deadline > 0 ? date('Y-m-d H:i:s', $deadline) : '';
        $createdTaskIds = [];
        // 每个测试人员独立事务：并发唯一冲突时回滚该人员的孤儿任务，再读取既有任务
        foreach ($testers as $testerUserId) {
            $testerUserId = (int)$testerUserId;
            if ($testerUserId <= 0) {
                continue;
            }
            $idempotencyKey = $this->wf()->buildTestIdempotencyKey($requestId, $testerUserId);
            // 事务前先查一次，避免不必要的建任务
            $existingTaskId = $this->wf()->findExistingTestTask($idempotencyKey);
            if ($existingTaskId) {
                $createdTaskIds[] = $existingTaskId;
                continue;
            }
            Db::startTrans();
            try {
                // 事务内再次查询幂等键，防止并发
                $recheckId = $this->wf()->findExistingTestTask($idempotencyKey);
                if ($recheckId) {
                    Db::rollback();
                    $createdTaskIds[] = $recheckId;
                    continue;
                }
                $taskData = [
                    'name' => '测试任务：' . $originTask['name'],
                    'description' => $testScope,
                    'work_id' => (int)$originTask['work_id'],
                    'class_id' => (int)$originTask['class_id'],
                    'main_user_id' => $testerUserId,
                    'create_user_id' => (int)$userInfo['id'],
                    'priority' => 0,
                    'start_time' => $startTimeStr,
                    'stop_time' => $stopTimeStr,
                    'pid' => 0,
                ];
                $taskModel = new \app\work\model\Task();
                $newTaskId = (int)$taskModel->createTask($taskData);
                if (!$newTaskId) {
                    throw new \Exception('测试任务创建失败');
                }
                Db::name('task_test_ext')->insert([
                    'task_id' => $newTaskId,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'origin_task_id' => $originTaskId,
                    'test_type' => $testType,
                    'test_scope' => $testScope,
                    'completion_criteria' => $completionCriteria,
                    'tester_user_id' => $testerUserId,
                    'reviewer_user_id' => $reviewerUserId,
                    'deadline' => $deadline,
                    'is_required' => $isRequired,
                    'submit_status' => 'not_submitted',
                    'review_status' => WorkflowService::REVIEW_PENDING,
                    'current_round' => 0,
                    'idempotency_key' => $idempotencyKey,
                    'version' => 1,
                    'create_user_id' => (int)$userInfo['id'],
                    'create_time' => $now,
                    'update_time' => $now,
                ]);
                Db::commit();
                $createdTaskIds[] = $newTaskId;
            } catch (\Exception $createEx) {
                // 唯一索引冲突或其他异常：回滚该测试人员的整个事务，
                // 确保刚创建的普通任务、关系、消息和日志全部回滚，不留下孤儿任务
                Db::rollback();
                $existingId = $this->wf()->findExistingTestTask($idempotencyKey);
                if ($existingId) {
                    $createdTaskIds[] = $existingId;
                    continue;
                }
                // 真实错误（非并发冲突）向上返回
                return resultArray(['error' => '发起测试失败（测试人员 ' . $testerUserId . '）：' . $createEx->getMessage()]);
            }
        }
        return resultArray(['data' => ['task_ids' => $createdTaskIds, 'count' => count($createdTaskIds)]]);
    }

    /** 测试人员提交本轮结果（只有指定测试人员可提交，版本号防并发）*/
    public function submitTest()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $taskId = (int)($param['task_id'] ?? 0);
        if ($taskId <= 0) {
            return resultArray(['error' => '参数错误']);
        }
        list($okV, $version) = $this->requireVersion($param);
        if (!$okV) {
            return resultArray(['error' => '必须提供有效版本号']);
        }
        $ext = Db::name('task_test_ext')->where(['task_id' => $taskId])->find();
        if (!$ext) {
            return resultArray(['error' => '测试任务不存在']);
        }
        if ((int)$ext['version'] !== $version) {
            return resultArray(['error' => '数据版本已变化，请刷新后重试']);
        }
        // 只能由指定测试人员提交
        if ((int)$ext['tester_user_id'] !== (int)$userInfo['id']) {
            return resultArray(['error' => '只有指定的测试人员可以提交结果']);
        }
        list($can, $reason) = $this->wf()->canSubmitTest($taskId);
        if (!$can) {
            return resultArray(['error' => $reason]);
        }
        $now = time();
        $newRound = (int)$ext['current_round'] + 1;
        Db::startTrans();
        try {
            $affected = Db::name('task_test_ext')->where(['task_id' => $taskId, 'version' => $version])->update([
                'submit_status' => 'submitted',
                'review_status' => WorkflowService::REVIEW_PENDING,
                'current_round' => $newRound,
                'submit_result' => (string)($param['result'] ?? ''),
                'submit_issues' => (string)($param['issues'] ?? ''),
                'update_time' => $now,
                'version' => $version + 1,
            ]);
            if (!$affected) {
                Db::rollback();
                return resultArray(['error' => '并发冲突，请刷新后重试']);
            }
            Db::name('task_test_history')->insert([
                'task_id' => $taskId, 'round' => $newRound, 'history_type' => 'submit',
                'content' => (string)($param['result'] ?? ''), 'issues' => (string)($param['issues'] ?? ''),
                'user_id' => (int)$userInfo['id'], 'create_time' => $now,
            ]);
            Db::commit();
            return resultArray(['data' => ['task_id' => $taskId, 'round' => $newRound, 'review_status' => WorkflowService::REVIEW_PENDING, 'version' => $version + 1]]);
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => '提交失败：' . $e->getMessage()]);
        }
    }

    /** 研发负责人或授权替补评定：必须是保存的评定人，且测试已提交、当前待评定 */
    public function reviewTest()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $taskId = (int)($param['task_id'] ?? 0);
        $verdict = (string)($param['verdict'] ?? '');
        if ($taskId <= 0) {
            return resultArray(['error' => '参数错误']);
        }
        if (!in_array($verdict, [WorkflowService::REVIEW_COMPLIANT, WorkflowService::REVIEW_NON_COMPLY], true)) {
            return resultArray(['error' => '评定结果必须为符合要求或不符合要求']);
        }
        list($okV, $version) = $this->requireVersion($param);
        if (!$okV) {
            return resultArray(['error' => '必须提供有效版本号']);
        }
        $ext = Db::name('task_test_ext')->where(['task_id' => $taskId])->find();
        if (!$ext) {
            return resultArray(['error' => '测试任务不存在']);
        }
        if ((int)$ext['version'] !== $version) {
            return resultArray(['error' => '数据版本已变化，请刷新后重试']);
        }
        // 必须是保存的评定人
        $reviewerErr = $this->wf()->assertReviewer($taskId, $userInfo['id']);
        if ($reviewerErr) {
            return resultArray(['error' => $reviewerErr]);
        }
        // 只能评定已提交且当前待评定的轮次
        if ($ext['submit_status'] !== 'submitted') {
            return resultArray(['error' => '测试人员尚未提交结果，不能评定']);
        }
        if ($ext['review_status'] !== WorkflowService::REVIEW_PENDING) {
            return resultArray(['error' => '当前测试任务不处于待评定状态']);
        }
        if ($verdict === WorkflowService::REVIEW_NON_COMPLY && empty($param['return_reason'])) {
            return resultArray(['error' => '不符合要求必须填写退回原因']);
        }
        $now = time();
        $update = [
            'review_status' => $verdict,
            'review_user_id' => (int)$userInfo['id'],
            'review_time' => $now,
            'update_time' => $now,
            'version' => $version + 1,
        ];
        if ($verdict === WorkflowService::REVIEW_NON_COMPLY) {
            $update['return_reason'] = (string)$param['return_reason'];
            $update['return_requirements'] = (string)($param['return_requirements'] ?? '');
            $update['return_deadline'] = !empty($param['return_deadline']) ? (is_numeric($param['return_deadline']) ? (int)$param['return_deadline'] : strtotime($param['return_deadline'])) : 0;
            $update['submit_status'] = 'not_submitted';
        }
        Db::startTrans();
        try {
            $affected = Db::name('task_test_ext')->where(['task_id' => $taskId, 'version' => $version])->update($update);
            if (!$affected) {
                Db::rollback();
                return resultArray(['error' => '并发冲突，请刷新后重试']);
            }
            Db::name('task_test_history')->insert([
                'task_id' => $taskId, 'round' => (int)$ext['current_round'], 'history_type' => 'review',
                'content' => (string)($param['return_reason'] ?? ''), 'issues' => (string)($param['return_requirements'] ?? ''),
                'review_status' => $verdict, 'user_id' => (int)$userInfo['id'], 'create_time' => $now,
            ]);
            Db::commit();
            return resultArray(['data' => ['task_id' => $taskId, 'review_status' => $verdict, 'version' => $version + 1]]);
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => '评定失败：' . $e->getMessage()]);
        }
    }

    /** 查询某原研发任务下的测试任务列表（需查看权限）*/
    public function testList()
    {
        $param = $this->param;
        $originTaskId = (int)($param['origin_task_id'] ?? ($param['task_id'] ?? 0));
        if ($originTaskId <= 0) {
            return resultArray(['error' => '参数错误']);
        }
        list($okAuth, $task) = $this->assertTaskAuth($originTaskId, 'read');
        if (!$okAuth) {
            return resultArray(['error' => $task]);
        }
        $exts = Db::name('task_test_ext')->where(['origin_task_id' => $originTaskId])->select();
        $taskIds = [];
        foreach ($exts as $e) {
            $taskIds[] = (int)$e['task_id'];
        }
        $taskMap = [];
        if ($taskIds) {
            $rows = Db::name('task')->whereIn('task_id', $taskIds)->column('name', 'task_id');
            foreach ($rows as $tid => $name) {
                $taskMap[$tid] = $name;
            }
        }
        $list = [];
        $requiredOk = true;
        foreach ($exts as $e) {
            $e['task_name'] = isset($taskMap[$e['task_id']]) ? $taskMap[$e['task_id']] : '';
            if ((int)$e['is_required'] === 1 && $e['review_status'] !== WorkflowService::REVIEW_COMPLIANT) {
                $requiredOk = false;
            }
            $list[] = $e;
        }
        return resultArray(['data' => ['list' => $list, 'required_all_compliant' => $requiredOk]]);
    }
}
