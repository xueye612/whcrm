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
                'wrkdictionary', 'workflowread', 'evaluate', 'skipevaluate', 'startprocess',
                'submitacceptance', 'acceptancepass', 'acceptancereturn',
                'applyrelease', 'confirmrelease', 'customerconfirm', 'customerreturn', 'completetask',
                'setauxstatus', 'setreleaseexemption',                 'deletetest', 'setstarttime',
                'initiatetest', 'submittest', 'reviewtest', 'testlist',
                'testdetail', 'testhistory']

        
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
        $param = $this->param;

        // W/R/K 筛选条件传给模型
        $wrkFilter = [];
        if (!empty($param['init_w'])) $wrkFilter['init_w'] = $param['init_w'];
        if (!empty($param['init_r'])) $wrkFilter['init_r'] = $param['init_r'];
        if (!empty($param['init_k'])) $wrkFilter['init_k'] = $param['init_k'];

        // 只看我负责的 / 只看我参与的
        $onlyMine = !empty($param['only_mine']) ? 1 : 0;
        $onlyParticipate = !empty($param['only_participate']) ? 1 : 0;

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
            if ($onlyMine) {
                $where['whereStr'] = ' task.main_user_id = ' . $userId;
            } elseif ($onlyParticipate) {
                $where['whereStr'] = ' ( task.create_user_id =' . $userId . ' or ( task.owner_user_id like "%,' . $userId . ',%") or ( task.main_user_id = ' . $userId . ' ) )';
            } else {
                $where['whereStr'] = ' ( task.create_user_id =' . $userId . ' or ( task.owner_user_id like "%,' . $userId . ',%") or ( task.main_user_id = ' . $userId . ' ) )';
            }
            if (!empty($param['search'])) $where['taskSearch'] = '(task.name like "%' . $param['search'] . '%" OR task.description like "%' . $param['search'] . '%")';
            // 传入 W/R/K 和状态筛选
            $param['_wrk_filter'] = $wrkFilter;
            $param['_status_filter'] = !empty($param['status']) ? $param['status'] : '';
            $resData = $taskModel->getProjectTaskList($where, $param);
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
        if (!empty($param['structure_ids'])) {
            $structure_ids = arrayToString($param['structure_ids']);
        }
        $owner_user_id = '';
        $sendUserArr = [];
        if (!empty($param['owner_userids'])) {
            $owner_user_id = arrayToString($param['owner_userids']);
            // 前端可能以逗号拼接字符串提交，统一转为数组再遍历，避免 PHP8 TypeError
            $ownerUserIds = stringToArray($param['owner_userids']);
            foreach ($ownerUserIds as $k => $v) {
                if (!in_array($v, stringToArray($taskInfo['owner_user_id']))) {
                    $sendUserArr[] = $v;
                }
            }
            // 注意：前端 editOwnerList 只提交 work_id/task_id/owner_userids，
            // 不提交 owner_user_id/structure_ids，必须用本地变量，避免 PHP8 未定义键告警转异常导致保存中断
            actionLog($param['task_id'], $owner_user_id, $structure_ids, '修改了参与人');
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
        \think\Db::startTrans();
        try {
            $res = $taskModel->createTask($param);
            if (!$res) {
                throw new \Exception($taskModel->getError());
            }
            // Initialize task_workflow v2 for new project main tasks (not subtasks)
            if (!empty($param['work_id']) && empty($param['pid'])) {
                $taskId = is_array($res) ? (int)($res['task_id'] ?? 0) : (int)$res;
                if ($taskId > 0) {
                    \app\work\logic\WorkflowService::getWorkflow($taskId, true);
                }
            }
            \think\Db::commit();
            (new DingTalkLogic())->sendTaskNotify('task_created', $res, $userInfo['id']);
            return resultArray(['data' => $res]);
        } catch (\Exception $e) {
            \think\Db::rollback();
            return resultArray(['error' => $e->getMessage()]);
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
     * 权限校验优先于测试任务判断：先确认查看/管理权限，再判断任务类型，
     * 避免向无权限用户暴露任务类型信息。测试任务不读取/创建 task_workflow。
     */
    public function workflowRead()
    {
        $param = $this->param;
        $taskId = (int)($param['task_id'] ?? 0);
        if ($taskId <= 0) {
            return resultArray(['error' => '参数错误']);
        }
        $forceInit = !empty($param['force_init']);

        // 先完成权限校验，再判断任务类型（避免越权暴露任务类型）
        if ($forceInit) {
            list($ok, $payload) = $this->assertTaskAuth($taskId, 'update');
            if (!$ok) {
                return resultArray(['error' => $payload]);
            }
            $task = Db::name('task')->where(['task_id' => $taskId])->find();
            if (!$task) return resultArray(['error' => '任务不存在']);
            if ((int)($task['ishidden'] ?? 0) === 1 || (int)($task['is_archive'] ?? 0) === 1) {
                return resultArray(['error' => '已归档或已删除的任务不能初始化工作流']);
            }
        } else {
            list($ok, $payload) = $this->assertTaskAuth($taskId, 'read');
            if (!$ok) {
                return resultArray(['error' => $payload]);
            }
        }

        // 权限通过后再判断是否为测试任务（通过 task_test_ext 判断）
        $testExt = Db::name('task_test_ext')->where(['task_id' => $taskId])->find();
        $isTestTask = $testExt ? true : false;

        // 测试任务不允许初始化 W/R/K 工作流
        if ($isTestTask && $forceInit) {
            return resultArray(['error' => '测试任务不使用 W/R/K 工作流，不能初始化']);
        }

        // 测试任务：不读取/创建 task_workflow，返回测试专属信息
        if ($isTestTask) {
            $task = Db::name('task')->where(['task_id' => $taskId])
                ->field('name,main_user_id,stop_time,status,work_id,class_id,create_user_id')
                ->find();
            $originTaskName = '';
            if ((int)$testExt['origin_task_id'] > 0) {
                $originTaskName = (string)Db::name('task')->where('task_id', (int)$testExt['origin_task_id'])->value('name');
            }
            $data = [
                'task_id' => $taskId,
                'is_test_task' => true,
                'legacy' => false,
                'workflow_version' => 0,
                'main_status' => '',
                'aux_status' => '',
                'version' => 0,
                'test_ext' => $this->wf()->enrichTestExt($testExt, $task ? $task['name'] : '', $originTaskName),
                'task_name' => $task ? $task['name'] : '',
                'main_user_id' => $task ? (int)$task['main_user_id'] : 0,
                'stop_time' => $task ? $task['stop_time'] : '',
            ];
            return resultArray(['data' => $data]);
        }

        $wf = $this->wf()->getWorkflow($taskId, $forceInit);
        $data = [
            'task_id' => $taskId,
            'legacy' => $wf ? false : true,
            'is_test_task' => false,
            'test_ext' => null,
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
        // 从任务表补充负责人和截止时间，供评估表单回显
        $task = Db::name('task')->where(['task_id' => $taskId])->field('main_user_id,stop_time,start_time')->find();
        $data['main_user_id'] = $task ? (int)$task['main_user_id'] : 0;
        $data['stop_time'] = $task ? $task['stop_time'] : '';
        $data['start_time'] = $task ? (int)$task['start_time'] : 0;
        // 返回验收人姓名
        $data['acceptance_user_name'] = '';
        if (!empty($data['acceptance_user_id'])) {
            $acceptUser = Db::name('admin_user')->where('id', (int)$data['acceptance_user_id'])->field('realname,status')->find();
            if ($acceptUser) {
                $data['acceptance_user_name'] = $acceptUser['realname'];
                if (isset($acceptUser['status']) && (int)$acceptUser['status'] !== 1) {
                    $data['acceptance_user_name'] = $acceptUser['realname'] . '（已停用）';
                }
            }
        }
        // 查询最近一次客户退回原因
        $lastReturn = Db::name('task_transition_log')
            ->where(['task_id' => $taskId, 'action' => 'customer_return'])
            ->order('create_time DESC, log_id DESC')
            ->find();
        if ($lastReturn) {
            $data['last_customer_return_reason'] = (string)$lastReturn['reason'];
            $data['last_customer_return_user_id'] = (int)$lastReturn['user_id'];
            $data['last_customer_return_time'] = (int)$lastReturn['create_time'];
            $returnUser = Db::name('admin_user')->where('id', (int)$lastReturn['user_id'])->value('realname');
            $data['last_customer_return_user_name'] = $returnUser ?: ('#' . $lastReturn['user_id']);
        } else {
            $data['last_customer_return_reason'] = '';
            $data['last_customer_return_user_id'] = 0;
            $data['last_customer_return_user_name'] = '';
            $data['last_customer_return_time'] = 0;
        }
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
    protected function commitTransition(array $wf, $action, $targetStatus, array $extraWfUpdate = [], $reason = '', array $extraTaskUpdate = [])
    {
        $taskId = (int)$wf['task_id'];
        $currentStatus = $wf['main_status'];
        $version = (int)$wf['version'];
        $userInfo = $this->userInfo;
        // Only allow actual task_workflow columns (verified against real table structure)
        $allowedWfFields = [
            'aux_status', 'aux_reason',
            'init_w', 'init_r', 'init_k', 'final_w', 'final_r', 'final_k',
            'wrk_frozen', 'acceptance_criteria', 'acceptance_user_id',
            'risk_note', 'dependency_note', 'professional_confirm',
            'plan_release_version', 'actual_release_version',
            'need_release', 'need_customer_verify',
            'release_skip_reason', 'release_skip_user_id', 'release_skip_time',
        ];
        $cleanWfUpdate = [];
        foreach ($extraWfUpdate as $k => $v) {
            if (!in_array($k, $allowedWfFields, true)) {
                throw new \InvalidArgumentException('task_workflow unknown field: ' . $k);
            }
            $cleanWfUpdate[$k] = $v;
        }
        $wrkFields = ['init_w', 'init_r', 'init_k', 'final_w', 'final_r', 'final_k'];
        $fieldChanges = [];
        $wrkLogs = [];
        $now = time();
        foreach ($extraWfUpdate as $k => $v) {
            if (in_array($k, $allowedWfFields, true)) {
                $cleanWfUpdate[$k] = $v;
                if (in_array($k, $wrkFields, true)) {
                    $oldVal = isset($wf[$k]) ? (string)$wf[$k] : '';
                    $newVal = (string)$v;
                    if ($oldVal !== $newVal) {
                        $fieldChanges[$k] = [$oldVal, $newVal];
                        $wrkLogs[] = [
                            'task_id' => $taskId, 'field_name' => $k,
                            'old_value' => $oldVal, 'new_value' => $newVal,
                            'reason' => (string)$reason, 'user_id' => (int)$userInfo['id'],
                            'create_time' => $now,
                        ];
                    }
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
            ], $cleanWfUpdate);
            $affected = Db::name('task_workflow')
                ->where(['task_id' => $taskId, 'version' => $version])
                ->update($update);
            if (!$affected) {
                Db::rollback();
                return [false, '并发冲突，数据版本已变化，请刷新后重试'];
            }
            if ($wrkLogs) {
                Db::name('task_wrk_log')->insertAll($wrkLogs);
            }
            $this->wf()->logTransition($taskId, $action, $currentStatus, $targetStatus, $fieldChanges, $reason, $userInfo['id']);
            // Task main table update (separate from workflow fields)
            $taskUpdate = array_merge(['update_time' => $now], $extraTaskUpdate);
            if ($targetStatus === WorkflowService::STATUS_DONE) {
                $taskUpdate['status'] = 5;
            }
            if (!empty($taskUpdate)) {
                Db::name('task')->where(['task_id' => $taskId])->update($taskUpdate);
            }
            $legacyStatus = ($targetStatus === WorkflowService::STATUS_DONE) ? 5 : 1;
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
        $param = $this->param;
        $taskId = (int)($param['task_id'] ?? 0);
        if ($taskId <= 0) return resultArray(['error' => '参数错误']);
        list($okAuth, $task) = $this->assertTaskAuth($taskId, 'manage');
        if (!$okAuth) return resultArray(['error' => $task]);
        list($okV, $version) = $this->requireVersion($param);
        if (!$okV) return resultArray(['error' => '必须提供有效版本号']);
        $wf = $this->wf()->getWorkflow($taskId);
        if (!$wf) return resultArray(['error' => '该任务未启用 P0 工作流']);
        if ((int)$wf['version'] !== $version) return resultArray(['error' => '数据版本已变化，请刷新后重试']);
        $target = $this->wf()->resolveTargetStatus('evaluate', $wf['main_status']);
        if ($target === false) return resultArray(['error' => '当前状态「' . $wf['main_status'] . '」不能执行评估']);

        $initW = trim((string)($param['init_w'] ?? ''));
        $initR = trim((string)($param['init_r'] ?? ''));
        $initK = trim((string)($param['init_k'] ?? ''));
        $acceptanceCriteria = trim((string)($param['acceptance_criteria'] ?? ''));
        $mainUserId = (int)($param['main_user_id'] ?? 0);
        $stopTime = (string)($param['stop_time'] ?? '');
        if ($initW === '' || $initR === '' || $initK === '') return resultArray(['error' => '评估必须填写完整初始 W/R/K']);
        if ($acceptanceCriteria === '') return resultArray(['error' => '评估必须填写任务说明']);
        if ($mainUserId <= 0) return resultArray(['error' => '评估必须指定负责人']);
        if ($stopTime === '') return resultArray(['error' => '评估必须指定截止时间']);
        $stopTs = strtotime($stopTime);
        if ($stopTs === false || $stopTs <= 0) return resultArray(['error' => '截止时间格式无效']);
        foreach (['init_w' => $initW, 'init_r' => $initR, 'init_k' => $initK] as $f => $val) {
            $err = $this->wf()->validateWrkField($f, $val);
            if ($err) return resultArray(['error' => $err]);
        }

        // Validate main_user_id belongs to project
        $workId = (int)($task['work_id'] ?? 0);
        if ($workId > 0) {
            $isMember = Db::name('work_user')->where(['work_id' => $workId, 'user_id' => $mainUserId])->find();
            if (!$isMember && (int)$task['create_user_id'] !== $mainUserId) {
                return resultArray(['error' => '负责人不属于该项目']);
            }
        }

        // Workflow update fields (only task_workflow columns)
        $extraWfUpdate = [
            'init_w' => $initW, 'init_r' => $initR, 'init_k' => $initK,
            'acceptance_criteria' => $acceptanceCriteria,
        ];
        // Task main table update fields (separate, handled by commitTransition)
        $extraTaskUpdate = [
            'main_user_id' => $mainUserId,
            'stop_time' => $stopTs,
        ];

        list($ok, $payload) = $this->commitTransition($wf, 'evaluate', $target, $extraWfUpdate, (string)($param['reason'] ?? '评估完成'), $extraTaskUpdate);
        if ($ok) {
            return resultArray(['data' => $payload]);
        }
        return resultArray(['error' => $payload]);
    }

    /** 跳过评估：待评估 -> 待处理（无需评估，不填写 W/R/K）*/
    public function skipEvaluate()
    {
        $param = $this->param;
        $taskId = (int)($param['task_id'] ?? 0);
        if ($taskId <= 0) return resultArray(['error' => '参数错误']);
        list($okAuth, $task) = $this->assertTaskAuth($taskId, 'manage');
        if (!$okAuth) return resultArray(['error' => $task]);
        list($okV, $version) = $this->requireVersion($param);
        if (!$okV) return resultArray(['error' => '必须提供有效版本号']);
        $wf = $this->wf()->getWorkflow($taskId);
        if (!$wf) return resultArray(['error' => '该任务未启用 P0 工作流']);
        if ((int)$wf['version'] !== $version) return resultArray(['error' => '数据版本已变化，请刷新后重试']);
        $target = $this->wf()->resolveTargetStatus('skip_evaluate', $wf['main_status']);
        if ($target === false) return resultArray(['error' => '当前状态「' . $wf['main_status'] . '」不能跳过评估']);
        list($ok, $payload) = $this->commitTransition($wf, 'skip_evaluate', $target, [], '无需评估', []);
        if ($ok) {
            return resultArray(['data' => $payload]);
        }
        return resultArray(['error' => $payload]);
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

    /** 开始处理：待处理 → 处理中（只校验评估完整并冻结W/R/K，不再补录初始值）*/
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
        // 跳过评估的任务（无初始 W/R/K）也可以开始处理，不再因缺少初始 W/R/K 被拦截
        // 仅冻结 W/R/K（有值则冻结，无值则标记已冻结防止后续补录）
        $extraUpdate = ['wrk_frozen' => 1];
        // 如果 task.start_time 为空，写入服务器当前时间（同一事务内）
        $extraTaskUpdate = [];
        if (empty($task['start_time'])) {
            $extraTaskUpdate['start_time'] = time();
        }
        list($ok, $payload) = $this->commitTransition($wf, 'start', $target, $extraUpdate, '开始处理', $extraTaskUpdate);
        if ($ok) {
            return resultArray(['data' => $payload]);
        }
        return resultArray(['error' => $payload]);
    }

    /** 提交内部验收：处理中 -> 待内部验收（必须完整最终 W/R/K、任务说明、验收人）*/
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
        // 跳过评估的任务（无初始 W/R/K）提交验收时不要求填写最终 W/R/K
        $hasInitWrk = !empty($wf['init_w']) || !empty($wf['init_r']) || !empty($wf['init_k']);
        if ($hasInitWrk) {
            if ($finalW === '' || $finalR === '' || $finalK === '') {
                return resultArray(['error' => '提交验收必须一次性提供完整最终 W/R/K']);
            }
            foreach (['final_w' => $finalW, 'final_r' => $finalR, 'final_k' => $finalK] as $f => $val) {
                $err = $this->wf()->validateWrkField($f, $val);
                if ($err) {
                    return resultArray(['error' => $err]);
                }
            }
        }
        if ($criteria === '') {
            return resultArray(['error' => '提交验收必须提供任务说明']);
        }
        if ($acceptUserId <= 0) {
            return resultArray(['error' => '提交验收必须指定验收人']);
        }
        $extraUpdate = [
            'acceptance_criteria' => $criteria, 'acceptance_user_id' => $acceptUserId,
        ];
        if ($hasInitWrk) {
            $extraUpdate['final_w'] = $finalW;
            $extraUpdate['final_r'] = $finalR;
            $extraUpdate['final_k'] = $finalK;
        }
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

    /**
     * 设置/删除任务开始时间，与工作流状态双向关联。
     * - 设置开始时间：待评估禁止；待处理自动进入处理中；处理中及以后只更新时间。
     * - 删除开始时间：处理中自动回退待处理；后续流程禁止删除。
     * 时间修改和状态修改在同一事务内完成。
     */
    public function setStartTime()
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
        $userInfo = $this->userInfo;
        $now = time();
        $rawStart = trim((string)($param['start_time'] ?? ''));
        $isDelete = ($rawStart === '' || $rawStart === '0');
        $startTs = 0;
        if (!$isDelete) {
            $startTs = is_numeric($rawStart) ? (int)$rawStart : strtotime($rawStart);
            if ($startTs === false || $startTs <= 0) {
                return resultArray(['error' => '开始时间格式无效']);
            }
        }

        $wf = $this->wf()->getWorkflow($taskId);
        if (!$wf) {
            Db::name('task')->where(['task_id' => $taskId])->update([
                'start_time' => $startTs, 'update_time' => $now,
            ]);
            return resultArray(['data' => ['task_id' => $taskId, 'start_time' => $startTs]]);
        }

        $mainStatus = $wf['main_status'];
        $version = (int)$wf['version'];
        list($okV, $reqVersion) = $this->requireVersion($param);
        if (!$okV) {
            return resultArray(['error' => '必须提供有效版本号']);
        }
        if ($version !== $reqVersion) {
            return resultArray(['error' => '数据版本已变化，请刷新后重试']);
        }

        if (!$isDelete) {
            if ($mainStatus === WorkflowService::STATUS_PENDING_EVAL) {
                return resultArray(['error' => '请先完成评估或选择无需评估，再设置开始时间']);
            }
            if ($mainStatus === WorkflowService::STATUS_PENDING_HANDLE) {
                $extraUpdate = ['wrk_frozen' => 1];
                $extraTaskUpdate = ['start_time' => $startTs];
                list($ok, $payload) = $this->commitTransition($wf, 'start', WorkflowService::STATUS_PROCESSING, $extraUpdate, '设置开始时间，自动进入处理中', $extraTaskUpdate);
                if ($ok) {
                    return resultArray(['data' => array_merge($payload, ['start_time' => $startTs])]);
                }
                return resultArray(['error' => $payload]);
            }
            Db::startTrans();
            try {
                Db::name('task')->where(['task_id' => $taskId])->update(['start_time' => $startTs, 'update_time' => $now]);
                Db::name('task_transition_log')->insert([
                    'task_id' => $taskId, 'action' => 'set_start_time', 'from_status' => $mainStatus, 'to_status' => $mainStatus,
                    'field_changes' => json_encode(['start_time' => [(string)$task['start_time'], (string)$startTs]], JSON_UNESCAPED_UNICODE),
                    'reason' => '修改开始时间', 'user_id' => (int)$userInfo['id'], 'correlation_id' => '', 'create_time' => $now,
                ]);
                Db::commit();
                return resultArray(['data' => ['task_id' => $taskId, 'start_time' => $startTs, 'main_status' => $mainStatus, 'version' => $version]]);
            } catch (\Exception $e) {
                Db::rollback();
                return resultArray(['error' => '更新失败：' . $e->getMessage()]);
            }
        }

        $blockedStatuses = [WorkflowService::STATUS_ACCEPTANCE, WorkflowService::STATUS_RELEASE, WorkflowService::STATUS_CUSTOMER, WorkflowService::STATUS_DONE];
        if (in_array($mainStatus, $blockedStatuses, true)) {
            return resultArray(['error' => '当前任务已进入后续流程，不能删除开始时间']);
        }
        if ($mainStatus === WorkflowService::STATUS_PROCESSING) {
            $extraUpdate = ['wrk_frozen' => 0];
            $extraTaskUpdate = ['start_time' => 0, 'status' => 1];
            list($ok, $payload) = $this->commitTransition($wf, 'rollback_to_pending', WorkflowService::STATUS_PENDING_HANDLE, $extraUpdate, '删除开始时间，任务回退为待处理', $extraTaskUpdate);
            if ($ok) {
                return resultArray(['data' => array_merge($payload, ['start_time' => 0])]);
            }
            return resultArray(['error' => $payload]);
        }
        Db::name('task')->where(['task_id' => $taskId])->update(['start_time' => 0, 'update_time' => $now]);
        return resultArray(['data' => ['task_id' => $taskId, 'start_time' => 0, 'main_status' => $mainStatus, 'version' => $version]]);
    }

    // ===================== 轻量测试任务闭环 =====================

    /** 发起测试：测试人员/测试内容/截止时间必填，request_id 幂等，复用现有任务模块生成 test 任务 */
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
        // 新流程无评定人；reviewer_user_id 写 0 兼容旧字段
        $testers = !empty($param['testers']) ? $param['testers'] : [];
        if (!is_array($testers) || !$testers) {
            return resultArray(['error' => '至少指定一名测试人员']);
        }
        // 发起人可以被选为测试人员，不再因为评定人回避规则排除自己
        $testScope = trim((string)($param['test_scope'] ?? ''));
        if ($testScope === '') {
            return resultArray(['error' => '请填写测试内容']);
        }
        // 截止时间必填，必须晚于当前时间
        $isUrgent = !empty($param['is_urgent']) ? 1 : 0;
        $now = time();
        if ($isUrgent) {
            // 加急测试：后端重新计算截止时间为当前时间+2小时，不信任前端传值
            $deadline = $now + 7200;
        } else {
            $deadline = !empty($param['deadline']) ? (is_numeric($param['deadline']) ? (int)$param['deadline'] : strtotime($param['deadline'])) : 0;
            if ($deadline <= 0) {
                return resultArray(['error' => '请填写测试完成截止时间']);
            }
            if ($deadline <= $now) {
                return resultArray(['error' => '测试完成截止时间必须晚于当前时间']);
            }
        }
        // 兼容旧字段默认值：test_type/is_required/completion_criteria 不再由用户填写
        $testType = WorkflowService::TEST_TYPE_DEV_SELF;
        $completionCriteria = '';
        $isRequired = 0;
        $reviewerUserId = 0;
        $sourceType = (string)($param['source_type'] ?? 'task');
        $sourceId = (int)($param['source_id'] ?? $originTaskId);
        $taskPriority = $isUrgent ? 3 : 0; // 加急任务设为最高优先级
        // testers 去重并验证有效员工 status=1
        $testers = array_values(array_unique(array_map('intval', $testers)));
        $invalidTesters = [];
        foreach ($testers as $t) {
            $tUser = db('admin_user')->where(['id' => (int)$t, 'status' => 1])->field('id')->find();
            if (!$tUser) {
                $invalidTesters[] = $t;
            }
        }
        if ($invalidTesters) {
            return resultArray(['error' => '测试人员中包含无效或已禁用员工：' . implode(',', $invalidTesters)]);
        }
        // createTask() 内部会对 start_time/stop_time 调用 strtotime()，必须传 Y-m-d H:i:s 字符串
        $startTimeStr = date('Y-m-d H:i:s', $now);
        $stopTimeStr = $deadline > 0 ? date('Y-m-d H:i:s', $deadline) : '';
        $createdTaskIds = [];
        $newlyCreatedTaskIds = []; // 仅跟踪新创建的任务，用于通知幂等
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
                    'priority' => $taskPriority,
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
                $newlyCreatedTaskIds[] = $newTaskId;
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
        // 加急测试创建成功后向所有测试人员发送一次通知（仅新创建的任务，幂等）
        if ($isUrgent && $newlyCreatedTaskIds) {
            $notifyContent = $userInfo['realname'] . ' ' . $originTask['name'];
            try {
                $msgModel = new Message();
                $msgModel->send(
                    Message::TASK_INVITE,
                    ['title' => $notifyContent, 'action_id' => $originTaskId],
                    $testers
                );
            } catch (\Exception $e) {
                // 通知失败不影响测试任务创建
            }
        }
        return resultArray(['data' => ['task_ids' => $createdTaskIds, 'count' => count($createdTaskIds), 'is_urgent' => $isUrgent, 'deadline' => $deadline]]);
    }

    /** 测试人员提交反馈（只有指定测试人员可提交，版本号防并发）。提交后测试任务直接完成。*/
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
        // 测试结果：无问题 / 发现问题
        $result = (string)($param['result'] ?? '');
        if (!in_array($result, ['无问题', '发现问题'], true)) {
            return resultArray(['error' => '测试结果必须为「无问题」或「发现问题」']);
        }
        $issues = trim((string)($param['issues'] ?? ''));
        if ($issues === '') {
            $label = ($result === '发现问题') ? '问题说明' : '测试说明';
            return resultArray(['error' => '请填写' . $label . '（描述测试了哪些内容）']);
        }
        $now = time();
        $newRound = (int)$ext['current_round'] + 1;
        Db::startTrans();
        try {
            $affected = Db::name('task_test_ext')->where(['task_id' => $taskId, 'version' => $version])->update([
                'submit_status' => 'submitted',
                'current_round' => $newRound,
                'submit_result' => $result,
                'submit_issues' => $issues,
                'update_time' => $now,
                'version' => $version + 1,
            ]);
            if (!$affected) {
                Db::rollback();
                return resultArray(['error' => '并发冲突，请刷新后重试']);
            }
            Db::name('task_test_history')->insert([
                'task_id' => $taskId, 'round' => $newRound, 'history_type' => 'submit',
                'content' => $result, 'issues' => $issues,
                'user_id' => (int)$userInfo['id'], 'create_time' => $now,
            ]);
            // 提交反馈后测试任务直接标记完成，不再进入待评定
            Db::name('task')->where(['task_id' => $taskId])->update(['status' => 5, 'update_time' => $now]);
            Db::name('WorkTaskLog')->insert([
                'user_id' => (int)$userInfo['id'],
                'content' => '测试反馈已提交，任务完成',
                'create_time' => $now,
                'task_id' => $taskId,
            ]);
            // 同步底层普通任务状态
            $this->syncLedgerByTaskStatus($taskId, (int)$userInfo['id'], 5);
            Db::commit();
            return resultArray(['data' => ['task_id' => $taskId, 'round' => $newRound, 'display_status' => '已反馈', 'version' => $version + 1]]);
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
        if ($verdict === WorkflowService::REVIEW_COMPLIANT && empty($param['return_reason'])) {
            return resultArray(['error' => '合格时必须填写评价说明']);
        }
        if ($verdict === WorkflowService::REVIEW_NON_COMPLY && empty($param['return_reason'])) {
            return resultArray(['error' => '不合格必须填写不合格原因']);
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
            // 同步底层测试任务状态：合格 -> 完成(5)，不合格 -> 重新打开(1)
            // 与普通任务状态变更保持一致：同步台账 + 写任务日志
            $taskLogContent = '';
            $taskLegacyStatus = 1;
            if ($verdict === WorkflowService::REVIEW_COMPLIANT) {
                Db::name('task')->where(['task_id' => $taskId])->update(['status' => 5, 'update_time' => $now]);
                $taskLogContent = '测试评定合格，任务完成';
                $taskLegacyStatus = 5;
            } else {
                Db::name('task')->where(['task_id' => $taskId])->update(['status' => 1, 'update_time' => $now]);
                $taskLogContent = '测试评定不合格，任务重新打开';
                $taskLegacyStatus = 1;
            }
            Db::name('WorkTaskLog')->insert([
                'user_id' => (int)$userInfo['id'],
                'content' => $taskLogContent,
                'create_time' => $now,
                'task_id' => $taskId,
            ]);
            // 同步台账状态（如果测试任务关联了台账；无关联时为安全空操作）
            $this->syncLedgerByTaskStatus($taskId, (int)$userInfo['id'], $taskLegacyStatus);
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
        $allExts = Db::name('task_test_ext')->where(['origin_task_id' => $originTaskId])->select();
        // 过滤软删除的测试任务（is_deleted=1 的不显示）
        $exts = [];
        foreach ($allExts as $e) {
            if (!isset($e['is_deleted']) || (int)$e['is_deleted'] === 0) {
                $exts[] = $e;
            }
        }
        $taskIds = [];
        $userIds = [];
        foreach ($exts as $e) {
            $taskIds[] = (int)$e['task_id'];
            $userIds[] = (int)$e['tester_user_id'];
            $userIds[] = (int)$e['reviewer_user_id'];
        }
        $taskMap = [];
        $priorityMap = [];
        $originTaskName = '';
        if ($taskIds) {
            $rows = Db::name('task')->whereIn('task_id', $taskIds)->column('name,priority', 'task_id');
            foreach ($rows as $tid => $row) {
                $taskMap[$tid] = is_array($row) ? $row['name'] : $row;
                $priorityMap[$tid] = is_array($row) ? (int)$row['priority'] : 0;
            }
        }
        // 查询原任务名称，供前端头部和每条记录展示
        if ($originTaskId > 0) {
            $originTaskName = (string)Db::name('task')->where('task_id', $originTaskId)->value('name');
        }
        $userMap = $this->wf()->resolveUserNames($userIds);
        $list = [];
        foreach ($exts as $e) {
            $e['task_name'] = isset($taskMap[$e['task_id']]) ? $taskMap[$e['task_id']] : '';
            $e['origin_task_name'] = $originTaskName;
            $e['tester_name'] = isset($userMap[(int)$e['tester_user_id']]) ? $userMap[(int)$e['tester_user_id']] : '';
            $e['reviewer_name'] = isset($userMap[(int)$e['reviewer_user_id']]) ? $userMap[(int)$e['reviewer_user_id']] : '';
            $e['test_type_name'] = WorkflowService::testTypeName((string)$e['test_type']);
            $e['display_status'] = WorkflowService::testDisplayStatus($e);
            $e['is_urgent'] = isset($priorityMap[$e['task_id']]) && (int)$priorityMap[$e['task_id']] >= 3 ? 1 : 0;
            $list[] = $e;
        }
        // 状态统计（新流程：待反馈/已反馈/已逾期）
        $stats = [
            'total' => count($list),
            'pending_feedback' => 0,
            'feedbacked' => 0,
            'overdue' => 0,
        ];
        foreach ($list as $item) {
            if ($item['display_status'] === '已反馈') {
                $stats['feedbacked']++;
            } elseif ($item['display_status'] === '已逾期') {
                $stats['overdue']++;
            } else {
                $stats['pending_feedback']++;
            }
        }
        return resultArray(['data' => ['list' => $list, 'stats' => $stats, 'origin_task_name' => $originTaskName]]);
    }

    /** 查看测试任务详情（需查看权限，所有角色可查看）*/
    public function testDetail()
    {
        $param = $this->param;
        $taskId = (int)($param['task_id'] ?? 0);
        if ($taskId <= 0) {
            return resultArray(['error' => '参数错误']);
        }
        list($okAuth, $task) = $this->assertTaskAuth($taskId, 'read');
        if (!$okAuth) {
            return resultArray(['error' => $task]);
        }
        $ext = Db::name('task_test_ext')->where(['task_id' => $taskId])->find();
        if (!$ext) {
            return resultArray(['error' => '测试任务不存在']);
        }
        $testTaskName = (string)Db::name('task')->where('task_id', $taskId)->value('name');
        $originTaskName = '';
        if ((int)$ext['origin_task_id'] > 0) {
            $originTaskName = (string)Db::name('task')->where('task_id', (int)$ext['origin_task_id'])->value('name');
        }
        $userMap = $this->wf()->resolveUserNames([(int)$ext['tester_user_id'], (int)$ext['reviewer_user_id'], (int)$ext['review_user_id']]);
        $detail = $this->wf()->enrichTestExt($ext, $testTaskName, $originTaskName);
        $detail['tester_name'] = isset($userMap[(int)$ext['tester_user_id']]) ? $userMap[(int)$ext['tester_user_id']] : '';
        $detail['reviewer_name'] = isset($userMap[(int)$ext['reviewer_user_id']]) ? $userMap[(int)$ext['reviewer_user_id']] : '';
        $detail['review_user_name'] = isset($userMap[(int)$ext['review_user_id']]) ? $userMap[(int)$ext['review_user_id']] : '';
        $detail['test_type_name'] = WorkflowService::testTypeName((string)$ext['test_type']);
        $detail['display_status'] = WorkflowService::testDisplayStatus($ext);
        // 当前用户权限标记
        // can_submit：必须是测试人 + 未提交（not_submitted）+ 未合格
        $userId = (int)$this->userInfo['id'];
        $detail['can_submit'] = (
            (int)$ext['tester_user_id'] === $userId
            && $ext['submit_status'] === 'not_submitted'
            && $ext['review_status'] !== WorkflowService::REVIEW_COMPLIANT
        );
        // can_review：仅旧数据（有评定人）且已提交待评定时可用；新流程不再使用
        $detail['can_review'] = (
            (int)$ext['reviewer_user_id'] > 0
            && (int)$ext['reviewer_user_id'] === $userId
            && $ext['submit_status'] === 'submitted'
            && $ext['review_status'] === WorkflowService::REVIEW_PENDING
        );
        return resultArray(['data' => $detail]);
    }

    /** 查询测试任务历史记录（读取 task_test_history，需查看权限）*/
    public function testHistory()
    {
        $param = $this->param;
        $taskId = (int)($param['task_id'] ?? 0);
        if ($taskId <= 0) {
            return resultArray(['error' => '参数错误']);
        }
        list($okAuth, $task) = $this->assertTaskAuth($taskId, 'read');
        if (!$okAuth) {
            return resultArray(['error' => $task]);
        }
        $rows = Db::name('task_test_history')
            ->where(['task_id' => $taskId])
            ->order('create_time ASC, history_id ASC')
            ->select();
        $userIds = [];
        foreach ($rows as $r) {
            $userIds[] = (int)$r['user_id'];
        }
        $userMap = $this->wf()->resolveUserNames($userIds);
        $list = [];
        foreach ($rows as $r) {
            $r['user_name'] = isset($userMap[(int)$r['user_id']]) ? $userMap[(int)$r['user_id']] : '';
            $r['history_type_name'] = $r['history_type'] === 'submit' ? '测试提交' : '评定';
            $r['review_status_name'] = $r['review_status'] ? WorkflowService::reviewStatusName($r['review_status']) : '';
            $list[] = $r;
        }
        return resultArray(['data' => ['list' => $list]]);
    }

    /**
     * 删除（软删除）指定测试任务。
     * 权限：原任务负责人/创建人/管理权限。测试人员本人不能随意删除。
     * 使用 version 进行并发校验；重复删除返回幂等成功。
     * 软删除：task_test_ext 标记 is_deleted，底层 task 标记 ishidden。
     */
    public function deleteTest()
    {
        $param = $this->param;
        $userInfo = $this->userInfo;
        $taskId = (int)($param['task_id'] ?? 0);
        if ($taskId <= 0) {
            return resultArray(['error' => '参数错误']);
        }
        $ext = Db::name('task_test_ext')->where(['task_id' => $taskId])->find();
        if (!$ext) {
            return resultArray(['error' => '测试任务不存在']);
        }
        $originTaskId = (int)$ext['origin_task_id'];
        // 权限校验：必须有原任务的管理权限（不是测试任务本身）
        list($okAuth, $originTask) = $this->assertTaskAuth($originTaskId, 'manage');
        if (!$okAuth) {
            return resultArray(['error' => '只有原任务管理权限人员可以删除测试任务']);
        }
        $version = (int)($param['version'] ?? 0);
        $reason = trim((string)($param['reason'] ?? '取消该人员测试任务'));
        $now = time();
        // 幂等：已删除则直接返回成功
        if (!empty($ext['is_deleted']) && (int)$ext['is_deleted'] === 1) {
            return resultArray(['data' => ['task_id' => $taskId, 'deleted' => true, 'idempotent' => true]]);
        }
        // 版本校验
        if ($version > 0 && (int)$ext['version'] !== $version) {
            return resultArray(['error' => '数据版本已变化，请刷新后重试']);
        }
        Db::startTrans();
        try {
            // 软删除 task_test_ext（兼容字段可能不存在的情况）
            $updateData = [
                'version' => (int)$ext['version'] + 1,
                'update_time' => $now,
            ];
            // 检查 is_deleted 列是否存在（兼容旧库）
            $cols = Db::query("SHOW COLUMNS FROM 5kcrm_task_test_ext LIKE 'is_deleted'");
            if ($cols) {
                $updateData['is_deleted'] = 1;
                $updateData['delete_user_id'] = (int)$userInfo['id'];
                $updateData['delete_time'] = $now;
                $updateData['delete_reason'] = $reason;
            }
            $affected = Db::name('task_test_ext')->where(['task_id' => $taskId, 'version' => (int)$ext['version']])->update($updateData);
            if (!$affected) {
                Db::rollback();
                return resultArray(['data' => ['task_id' => $taskId, 'deleted' => true, 'idempotent' => true]]);
            }
            // 底层任务标记为隐藏（软删除）
            Db::name('task')->where(['task_id' => $taskId])->update(['ishidden' => 1, 'update_time' => $now]);
            // 审计日志
            Db::name('task_transition_log')->insert([
                'task_id' => $originTaskId,
                'action' => 'delete_test',
                'from_status' => '',
                'to_status' => '',
                'field_changes' => json_encode(['test_task_id' => $taskId, 'tester_user_id' => (int)$ext['tester_user_id']], JSON_UNESCAPED_UNICODE),
                'reason' => $reason,
                'user_id' => (int)$userInfo['id'],
                'correlation_id' => 'test:' . $taskId,
                'create_time' => $now,
            ]);
            Db::commit();
            return resultArray(['data' => ['task_id' => $taskId, 'deleted' => true, 'version' => (int)$ext['version'] + 1]]);
        } catch (\Exception $e) {
            Db::rollback();
            return resultArray(['error' => '删除失败：' . $e->getMessage()]);
        }
    }
}
