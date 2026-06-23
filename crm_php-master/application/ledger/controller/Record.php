<?php
// +----------------------------------------------------------------------
// | Description: Customer Ledger Record
// +----------------------------------------------------------------------
namespace app\ledger\controller;

use app\admin\controller\ApiCommon;
use think\Hook;
use think\Request;

class Record extends ApiCommon
{
    public function _initialize()
    {
        $action = [
            'permission' => [],
            'allow' => []
        ];
        Hook::listen('check_auth', $action);
        $request = Request::instance();
        $a = strtolower($request->action());
        if (!in_array($a, $action['permission'])) {
            parent::_initialize();
        }
    }

    public function list()
    {
        if (!checkPerByAction('ledger', 'record', 'list')) {
            return resultArray(['error' => '无权限操作']);
        }
        $param = $this->param;
        if (empty($param['ledger_id'])) {
            return resultArray(['error' => '参数错误']);
        }
        $model = model('CustomerLedger');
        $userInfo = $this->userInfo;
        if (!$model->getAccessibleById($param['ledger_id'], $userInfo['id'])) {
            return resultArray(['error' => '鏃犳潈鎿嶄綔']);
        }
        $list = $model->listProgressRecord($param['ledger_id']);
        return resultArray(['data' => $list]);
    }

    public function add()
    {
        if (!checkPerByAction('ledger', 'record', 'add')) {
            return resultArray(['error' => '无权限操作']);
        }
        $param = $this->param;
        if (empty($param['ledger_id'])) {
            return resultArray(['error' => 'Missing ledger_id']);
        }
        if (empty($param['content'])) {
            return resultArray(['error' => 'Content is required']);
        }
        $model = model('CustomerLedger');
        $userInfo = $this->userInfo;
        $ledger = $model->getAccessibleById($param['ledger_id'], $userInfo['id']);
        if (!$ledger) {
            return resultArray(['error' => 'No permission']);
        }

        $oldStatus = $ledger['status'] ?? '';
        $newStatus = $oldStatus;
        $updateData = [];
        if (!empty($param['new_status'])) {
            $newStatus = $param['new_status'];
            $updateData['status'] = $newStatus;
            if ($newStatus === '已完成') {
                $updateData['finish_time'] = time();
            }
        }
        if (!empty($updateData)) {
            $updateData['update_time'] = time();
            db('customer_ledger')->where('ledger_id', $param['ledger_id'])->update($updateData);
        }
        if (!empty($param['new_status'])) {
            $syncTaskStatus = !array_key_exists('sync_task_status', $param) || (int)$param['sync_task_status'] === 1;
            if ($syncTaskStatus) {
                $this->syncTaskStatusByLedger($ledger, $newStatus, (int)$userInfo['id']);
            }
        }

        $model->addProgressRecord($param['ledger_id'], $ledger['customer_id'], $param['content'], $oldStatus, $newStatus, $userInfo['id']);
        $this->addLedgerActivity($param['ledger_id'], $ledger['customer_id'], $param['content'], $userInfo['id']);
        return resultArray(['data' => 'Record added']);
    }

    protected function syncTaskStatusByLedger(array $ledger, $newStatus, $userId)
    {
        $taskId = (int)($ledger['task_id'] ?? 0);
        if (!$taskId) {
            return;
        }
        $task = db('task')->where(['task_id' => $taskId])->field('task_id,status')->find();
        if (!$task) {
            return;
        }
        $targetStatus = (strpos((string)$newStatus, '完成') !== false) ? 5 : 1;
        if ((int)$task['status'] === $targetStatus) {
            return;
        }
        db('task')->where(['task_id' => $taskId])->update([
            'status' => $targetStatus,
            'update_time' => time()
        ]);
        $content = $targetStatus === 5 ? '台账完成同步任务' : '台账回退同步任务';
        model('CustomerLedger')->addProgressRecord(
            (int)$ledger['ledger_id'],
            (int)($ledger['customer_id'] ?? 0),
            $content,
            (string)($ledger['status'] ?? ''),
            (string)$newStatus,
            (int)$userId
        );
        $this->addLedgerActivity((int)$ledger['ledger_id'], (int)($ledger['customer_id'] ?? 0), $content, (int)$userId);
    }

    protected function addLedgerActivity($ledgerId, $customerId, $content, $userId)
    {
        db('crm_activity')->insert([
            'type' => 1,
            'activity_type' => 13,
            'activity_type_id' => $ledgerId,
            'content' => $content,
            'category' => '台账',
            'customer_ids' => $customerId ? (',' . $customerId . ',') : '',
            'create_user_id' => $userId,
            'update_time' => time(),
            'create_time' => time()
        ]);
    }
}
