<?php
// +----------------------------------------------------------------------
// | Description: Customer Ledger
// +----------------------------------------------------------------------
namespace app\ledger\controller;

use app\admin\controller\ApiCommon;
use app\work\traits\WorkAuthTrait;
use app\admin\logic\DingTalkLogic;
use app\ledger\logic\LedgerLogic;
use app\ledger\logic\NotifyService;
use app\work\logic\WorkflowService;
use think\Hook;
use think\Request;
use think\Db;

class Ledger extends ApiCommon
{
    use WorkAuthTrait;

    /** @var LedgerLogic */
    protected $ledgerLogic;

    public function _initialize()
    {
        $this->ledgerLogic = new LedgerLogic();
        $action = [
            'permission' => [],
            'allow' => ['excelexport']
        ];
        Hook::listen('check_auth', $action);
        $request = Request::instance();
        $a = strtolower($request->action());
        if (!in_array($a, $action['permission'])) {
            parent::_initialize();
        }
    }

    public function index()
    {
        $model = model('CustomerLedger');
        $param = $this->param;
        $userInfo = $this->userInfo;
        $data = $model->getDataList($param, $userInfo['id']);
        return resultArray(['data' => $data]);
    }

    public function customerList()
    {
        if (!checkPerByAction('ledger', 'ledger', 'index')) {
            return resultArray(['error' => '无权操作']);
        }
        $model = model('CustomerLedger');
        $param = $this->param;
        $userInfo = $this->userInfo;
        $keyword = $param['keyword'] ?? '';
        $data = $model->getCustomerList($userInfo['id'], $keyword);
        return resultArray(['data' => $data]);
    }

    public function read()
    {
        $model = model('CustomerLedger');
        $param = $this->param;
        if (empty($param['id'])) {
            return resultArray(['error' => '参数错误']);
        }
        $userInfo = $this->userInfo;
        $data = $model->getAccessibleById($param['id'], $userInfo['id']);
        if (!$data) {
            return resultArray(['error' => '无权限']);
        }
        return resultArray(['data' => $data]);
    }

    public function save()
    {
        $model = model('CustomerLedger');
        $param = $this->param;
        $userInfo = $this->userInfo;
        $replyContent = $this->normalizeReplyContent($param['reply_content'] ?? '');
        unset($param['reply_content']);
        $closeReason = $this->normalizeReplyContent($param['close_reason'] ?? '');
        unset($param['close_reason']);

        $relationError = $this->ledgerLogic->normalizeRelation($param);
        if ($relationError) {
            return resultArray(['error' => $relationError]);
        }
        $contractAccessError = $this->ledgerLogic->assertContractAccessible($param['contract_id'], $userInfo['id']);
        if ($contractAccessError) {
            return resultArray(['error' => $contractAccessError]);
        }
        if (empty($param['title'])) {
            return resultArray(['error' => '请填写反馈问题']);
        }
        if (array_key_exists('description', $param)) {
            $param['description'] = $this->sanitizeRichText($param['description']);
        }
        $this->normalizeProjectFields($param);

        $allowedCategory = $this->ledgerLogic->getAllowedCategories();
        $allowedStatus = ['待处理', '处理中', '待验证', '待发布', '已完成', '已关闭'];
        if (!empty($param['category']) && !in_array($param['category'], $allowedCategory)) {
            return resultArray(['error' => '问题分类不合法']);
        }
        if (!empty($param['status']) && !in_array($param['status'], $allowedStatus)) {
            return resultArray(['error' => '处理状态不合法']);
        }
        $closeReasonError = $this->validateCloseReason($param['status'] ?? '', $closeReason);
        if ($closeReasonError) {
            return resultArray(['error' => $closeReasonError]);
        }

        $param['register_user_id'] = $this->normalizeUserId($param['register_user_id'] ?? 0);
        if (empty($param['register_user_id'])) {
            $param['register_user_id'] = $userInfo['id'];
        }
        $param['handler_user_id'] = $this->normalizeUserId($param['handler_user_id'] ?? 0);
        if (empty($param['handler_user_id'])) {
            $param['handler_user_id'] = $userInfo['id'];
        }

        if (!empty($param['register_time']) && !is_numeric($param['register_time'])) {
            $param['register_time'] = strtotime($param['register_time']);
        }
        if (!empty($param['feedback_time']) && !is_numeric($param['feedback_time'])) {
            $param['feedback_time'] = strtotime($param['feedback_time']);
        }
        if (!empty($param['finish_time']) && !is_numeric($param['finish_time'])) {
            $param['finish_time'] = strtotime($param['finish_time']);
        }
        if (empty($param['register_time'])) {
            $param['register_time'] = time();
        }
        if (empty($param['feedback_time'])) {
            $param['feedback_time'] = $param['register_time'];
        }
        if (empty($param['finish_time'])) {
            $param['finish_time'] = 0;
        }
        if (($param['status'] ?? '') === '已完成' && empty($param['finish_time'])) {
            $param['finish_time'] = time();
        }
        if (empty($param['business_id'])) {
            $param['business_id'] = 0;
        }
        if (empty($param['contract_id'])) {
            $param['contract_id'] = 0;
        }
        if (empty($param['status'])) {
            $param['status'] = '待处理';
        }
        if (empty($param['feedback_channel'])) {
            $param['feedback_channel'] = '微信';
        }

        $res = $model->data($param)->allowField(true)->save();
        if ($res) {
            $ledgerId = $model->ledger_id;
            $model->addProgressRecord($ledgerId, $param['customer_id'], '创建台账', '', $param['status'], $userInfo['id']);
            $this->addCompletionReplyRecord($model, $ledgerId, $param['customer_id'], $replyContent, '', $param['status'], $userInfo['id']);
            $this->addCloseReasonRecord($model, $ledgerId, $param['customer_id'], $closeReason, '', $param['status'], $userInfo['id']);
            $this->addLedgerActivity($ledgerId, $param['customer_id'], '创建台账', $userInfo['id'], 1);
            $taskId = 0;
            $taskError = '';
            try {
                $taskId = $this->maybeCreateProjectTask($ledgerId, $param, $userInfo);
            } catch (\Exception $taskEx) {
                $taskError = $taskEx->getMessage();
            }
            if ($taskId) {
                $model->addProgressRecord($ledgerId, $param['customer_id'], '已生成项目任务', '', $param['status'], $userInfo['id']);
                $this->addLedgerActivity($ledgerId, $param['customer_id'], '已生成项目任务', $userInfo['id'], 1);
            }
            $notifyService = new NotifyService();
            $notifyService->notify(NotifyService::EVENT_CREATED, $ledgerId, $userInfo['id']);
            $handlerId = (int)($param['handler_user_id'] ?? 0);
            if ($handlerId > 0 && $handlerId !== (int)$userInfo['id']) {
                $notifyService->notify(NotifyService::EVENT_ASSIGNED, $ledgerId, $userInfo['id']);
            }
            if ($taskError) {
                return resultArray(['error' => '台账已创建，但自动建任务失败：' . $taskError]);
            }
            return resultArray(['data' => '添加成功']);
        }
        return resultArray(['error' => '添加失败']);
    }

    public function update()
    {
        $model = model('CustomerLedger');
        $param = $this->param;
        $userInfo = $this->userInfo;
        $replyContent = $this->normalizeReplyContent($param['reply_content'] ?? '');
        unset($param['reply_content']);
        $closeReason = $this->normalizeReplyContent($param['close_reason'] ?? '');
        unset($param['close_reason']);

        if (empty($param['id'])) {
            return resultArray(['error' => '参数错误']);
        }
        if (array_key_exists('description', $param)) {
            $param['description'] = $this->sanitizeRichText($param['description']);
        }
        $this->normalizeProjectFields($param);
        $oldData = $model->getAccessibleById($param['id'], $userInfo['id']);
        if (!$oldData) {
            return resultArray(['error' => '无权限']);
        }
        $allowedCategory = $this->ledgerLogic->getAllowedCategories();
        $allowedStatus = ['待处理', '处理中', '待验证', '待发布', '已完成', '已关闭'];
        if (!empty($param['category']) && !in_array($param['category'], $allowedCategory)) {
            return resultArray(['error' => '问题分类不合法']);
        }
        if (!empty($param['status']) && !in_array($param['status'], $allowedStatus)) {
            return resultArray(['error' => '处理状态不合法']);
        }
        $mergedStatus = $param['status'] ?? $oldData['status'] ?? '';
        $closeReasonError = $this->validateCloseReason($mergedStatus, $closeReason, (int)$param['id']);
        if ($closeReasonError) {
            return resultArray(['error' => $closeReasonError]);
        }

        if (array_key_exists('customer_id', $param) || array_key_exists('business_id', $param) || array_key_exists('contract_id', $param)) {
            $relationError = $this->ledgerLogic->normalizeRelation($param);
            if ($relationError) {
                return resultArray(['error' => $relationError]);
            }
            $contractAccessError = $this->ledgerLogic->assertContractAccessible($param['contract_id'], $userInfo['id']);
            if ($contractAccessError) {
                return resultArray(['error' => $contractAccessError]);
            }
        }

        if (array_key_exists('register_user_id', $param)) {
            $normalizedRegisterId = $this->normalizeUserId($param['register_user_id']);
            if ($normalizedRegisterId) {
                $param['register_user_id'] = $normalizedRegisterId;
            } else {
                unset($param['register_user_id']);
            }
        }
        if (array_key_exists('handler_user_id', $param)) {
            $normalizedHandlerId = $this->normalizeUserId($param['handler_user_id']);
            if ($normalizedHandlerId) {
                $param['handler_user_id'] = $normalizedHandlerId;
            } else {
                unset($param['handler_user_id']);
            }
        }
        if (array_key_exists('register_time', $param)) {
            if (!empty($param['register_time']) && !is_numeric($param['register_time'])) {
                $param['register_time'] = strtotime($param['register_time']);
            }
            if (empty($param['register_time'])) {
                $param['register_time'] = 0;
            }
        }
        if (array_key_exists('feedback_time', $param)) {
            if (!empty($param['feedback_time']) && !is_numeric($param['feedback_time'])) {
                $param['feedback_time'] = strtotime($param['feedback_time']);
            }
            if (empty($param['feedback_time'])) {
                $param['feedback_time'] = 0;
            }
        }
        if (array_key_exists('finish_time', $param)) {
            if (!empty($param['finish_time']) && !is_numeric($param['finish_time'])) {
                $param['finish_time'] = strtotime($param['finish_time']);
            }
            if (empty($param['finish_time'])) {
                $param['finish_time'] = 0;
            }
        }
        if (($param['status'] ?? $oldData['status'] ?? '') === '已完成' && empty($param['finish_time']) && empty($oldData['finish_time'])) {
            $param['finish_time'] = time();
        }
        if (array_key_exists('feedback_channel', $param) && empty($param['feedback_channel'])) {
            $param['feedback_channel'] = '微信';
        }
        if (array_key_exists('business_id', $param) && empty($param['business_id'])) {
            $param['business_id'] = 0;
        }
        if (array_key_exists('contract_id', $param) && empty($param['contract_id'])) {
            $param['contract_id'] = 0;
        }

        $ledgerId = $param['id'];
        unset($param['id']);

        $res = $model->allowField(true)->save($param, ['ledger_id' => $ledgerId]);
        if ($res !== false) {
            $mergedData = array_merge($oldData, $param);
            if ($oldData && array_key_exists('status', $param) && $param['status'] != $oldData['status']) {
                $content = '状态变更：' . $oldData['status'] . ' -> ' . $param['status'];
                $model->addProgressRecord($ledgerId, $oldData['customer_id'], $content, $oldData['status'], $param['status'], $userInfo['id']);
                $this->addLedgerActivity($ledgerId, $oldData['customer_id'], $content, $userInfo['id'], 1);
            }
            $this->addCompletionReplyRecord(
                $model,
                $ledgerId,
                $oldData['customer_id'],
                $replyContent,
                $oldData['status'] ?? '',
                $mergedData['status'] ?? '',
                $userInfo['id']
            );
            $this->addCloseReasonRecord(
                $model,
                $ledgerId,
                $oldData['customer_id'],
                $closeReason,
                $oldData['status'] ?? '',
                $mergedData['status'] ?? '',
                $userInfo['id']
            );
            $taskId = 0;
            $taskError = '';
            try {
                $taskId = $this->maybeCreateProjectTask($ledgerId, $mergedData, $userInfo);
            } catch (\Exception $taskEx) {
                $taskError = $taskEx->getMessage();
            }
            if ($taskId) {
                $model->addProgressRecord($ledgerId, $oldData['customer_id'], '已生成项目任务', '', $param['status'] ?? $oldData['status'], $userInfo['id']);
                $this->addLedgerActivity($ledgerId, $oldData['customer_id'], '已生成项目任务', $userInfo['id'], 1);
            }
            $this->syncLedgerTaskOnUpdate($ledgerId, $oldData, $param, $mergedData, $userInfo);
            $notifyService = new NotifyService();
            if (array_key_exists('handler_user_id', $param)) {
                $oldHandlerId = (int)($oldData['handler_user_id'] ?? 0);
                $newHandlerId = (int)($mergedData['handler_user_id'] ?? 0);
                if ($newHandlerId > 0 && $newHandlerId !== $oldHandlerId) {
                    $notifyService->notify(NotifyService::EVENT_ASSIGNED, $ledgerId, $userInfo['id']);
                }
            }
            if (array_key_exists('status', $param) && ($param['status'] ?? '') != ($oldData['status'] ?? '')) {
                $notifyService->notify(NotifyService::EVENT_STATUS_CHANGED, $ledgerId, $userInfo['id'], [
                    'old_status' => $oldData['status'] ?? '',
                    'new_status' => $param['status'] ?? ''
                ]);
            }
            if ($taskError) {
                return resultArray(['error' => '台账已更新，但自动建任务失败：' . $taskError]);
            }
            return resultArray(['data' => '编辑成功']);
        }
        return resultArray(['error' => '编辑失败']);
    }

    public function delete()
    {
        $param = $this->param;
        if (empty($param['id'])) {
            return resultArray(['error' => '参数错误']);
        }
        $ids = is_array($param['id']) ? $param['id'] : [$param['id']];

        $model = model('CustomerLedger');
        $userInfo = $this->userInfo;
        $accessibleIds = $model->filterIdsByScope($ids, $userInfo['id']);
        if (count($accessibleIds) !== count($ids)) {
            return resultArray(['error' => '无权限']);
        }

        $this->closeTasksByLedgerIds($accessibleIds, (int)$userInfo['id']);
        $res = db('customer_ledger')->where('ledger_id', 'in', $accessibleIds)->delete();
        if ($res) {
            // 删除关联的跟进记录
            db('customer_ledger_record')->where('ledger_id', 'in', $accessibleIds)->delete();
            // 删除关联的动态
            db('crm_activity')->where('activity_type', 13)->where('activity_type_id', 'in', $accessibleIds)->delete();
            
            return resultArray(['data' => '删除成功']);
        }
        return resultArray(['error' => '删除失败']);
    }

    public function excelExport()
    {
        $model = model('CustomerLedger');
        $param = $this->param;
        $userInfo = $this->userInfo;
        $param['user_id'] = $userInfo['id'];

        $fieldList = [
            ['field' => 'ledger_id', 'name' => 'ID', 'form_type' => 'text'],
            ['field' => 'customer_name', 'name' => '客户名称', 'form_type' => 'text'],
            ['field' => 'relation_name', 'name' => '关联对象', 'form_type' => 'text'],
            ['field' => 'title', 'name' => '反馈问题', 'form_type' => 'text'],
            ['field' => 'description_plain', 'name' => '描述信息', 'form_type' => 'text'],
            ['field' => 'completed_reply_plain', 'name' => '回答', 'form_type' => 'text'],
            ['field' => 'closed_reason_plain', 'name' => '关闭原因', 'form_type' => 'text'],
            ['field' => 'category', 'name' => '问题分类', 'form_type' => 'text'],
            ['field' => 'status', 'name' => '处理状态', 'form_type' => 'text'],
            ['field' => 'feedback_user', 'name' => '反馈人', 'form_type' => 'text'],
            ['field' => 'feedback_channel', 'name' => '反馈渠道', 'form_type' => 'text'],
            ['field' => 'register_user_name', 'name' => '登记人', 'form_type' => 'text'],
            ['field' => 'handler_user_name', 'name' => '处理人', 'form_type' => 'text'],
            ['field' => 'register_time', 'name' => '登记时间', 'form_type' => 'text'],
            ['field' => 'finish_time', 'name' => '完成时间', 'form_type' => 'text']
        ];

        $excelModel = new \app\admin\model\Excel();
        $fileName = 'CRM_ledger_' . date('Ymd');
        $tempFile = array_key_exists('temp_file', $param) ? $param['temp_file'] : null;
        $page = !empty($param['page']) ? (int)$param['page'] : 1;
        unset($param['temp_file'], $param['page'], $param['export_queue_index']);

        return $excelModel->batchExportCsv($fileName, $tempFile, $fieldList, $page, function ($pageNo, $limit) use ($model, $param, $userInfo) {
            $query = $param;
            $query['page'] = $pageNo;
            $query['limit'] = $limit;
            $data = $model->getDataList($query, $userInfo['id']);
            $list = $data['list'] ?? [];
            foreach ($list as &$item) {
                $item['relation_name'] = $item['business_name'] ?: ($item['contract_name'] ?: ($item['contract_num'] ?: '-'));
                $item['description_plain'] = $this->normalizeTaskDescription($item['description'] ?? '');
                $item['completed_reply_plain'] = $this->normalizeTaskDescription($item['completed_reply'] ?? '');
                $item['closed_reason_plain'] = $this->normalizeTaskDescription($item['closed_reason'] ?? '');
            }
            $data['list'] = $list;
            return $data;
        });
    }

    protected function addLedgerActivity($ledgerId, $customerId, $content, $userId, $type)
    {
        db('crm_activity')->insert([
            'type' => $type,
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

    protected function normalizeReplyContent($content)
    {
        return trim((string)$content);
    }

    protected function addCompletionReplyRecord($model, $ledgerId, $customerId, $content, $oldStatus, $newStatus, $userId)
    {
        $content = $this->normalizeReplyContent($content);
        if ($content === '' || $newStatus !== '已完成') {
            return false;
        }

        $lastContent = db('customer_ledger_record')
            ->where('ledger_id', $ledgerId)
            ->where('new_status', '已完成')
            ->where('content', '<>', '创建台账')
            ->where('content', 'not like', '状态变更：%')
            ->where('content', 'not like', '同步任务：%')
            ->order('create_time desc,record_id desc')
            ->value('content');
        if ($lastContent === $content) {
            return false;
        }

        $model->addProgressRecord($ledgerId, $customerId, $content, $oldStatus, '已完成', $userId);
        $this->addLedgerActivity($ledgerId, $customerId, $content, $userId, 1);
        return true;
    }

    protected function addCloseReasonRecord($model, $ledgerId, $customerId, $content, $oldStatus, $newStatus, $userId)
    {
        $content = $this->normalizeReplyContent($content);
        if ($content === '' || $newStatus !== '已关闭') {
            return false;
        }

        $lastContent = db('customer_ledger_record')
            ->where('ledger_id', $ledgerId)
            ->where('new_status', '已关闭')
            ->where('content', '<>', '创建台账')
            ->where('content', 'not like', '状态变更：%')
            ->where('content', 'not like', '同步任务：%')
            ->order('create_time desc,record_id desc')
            ->value('content');
        if ($lastContent === $content) {
            return false;
        }

        $model->addProgressRecord($ledgerId, $customerId, $content, $oldStatus, '已关闭', $userId);
        $this->addLedgerActivity($ledgerId, $customerId, $content, $userId, 1);
        return true;
    }

    protected function validateCloseReason($status, $closeReason, $ledgerId = 0)
    {
        if ($status !== '已关闭') {
            return '';
        }
        if ($this->normalizeReplyContent($closeReason) !== '') {
            return '';
        }
        if ($ledgerId && $this->getLatestCloseReason($ledgerId) !== '') {
            return '';
        }
        return '请填写关闭原因';
    }

    protected function getLatestCloseReason($ledgerId)
    {
        if (empty($ledgerId)) {
            return '';
        }
        return (string)db('customer_ledger_record')
            ->where('ledger_id', $ledgerId)
            ->where('new_status', '已关闭')
            ->where('content', '<>', '创建台账')
            ->where('content', 'not like', '状态变更：%')
            ->where('content', 'not like', '同步任务：%')
            ->order('create_time desc,record_id desc')
            ->value('content');
    }

    protected function normalizeRelation(&$param)
    {
        $contractId = !empty($param['contract_id']) ? (int)$param['contract_id'] : 0;
        if (!$contractId) {
            return '台账必须关联合同';
        }

        $contract = db('crm_contract')->where('contract_id', $contractId)->find();
        if (!$contract) {
            return '合同不存在';
        }

        $customerId = (int)$contract['customer_id'];
        if (!$customerId) {
            return '合同未关联客户';
        }

        // 台账统一按合同归档，忽略客户/商机直连
        $param['customer_id'] = $customerId;
        $param['business_id'] = 0;
        $param['contract_id'] = $contractId;
        return '';
    }

    protected function getAllowedCategories()
    {
        $default = ['使用指导', '操作错误', '功能完善', '系统BUG', '新增需求', '新需求', '其他问题'];
        $config = db('crm_config')->where(['name' => 'ledger_category'])->find();
        if ($config && !empty($config['value'])) {
            $value = json_decode($config['value'], true);
            if (is_array($value)) {
                $list = [];
                foreach ($value as $item) {
                    $item = trim((string)$item);
                    if ($item !== '') {
                        $list[] = $item;
                    }
                }
                $list = array_values(array_unique($list));
                if (!empty($list)) {
                    return $list;
                }
            }
        }
        return $default;
    }

    protected function normalizeProjectFields(array &$param)
    {
        if (array_key_exists('work_id', $param)) {
            $param['work_id'] = (int)($param['work_id'] ?: 0);
        }
        if (array_key_exists('class_id', $param)) {
            $param['class_id'] = (int)($param['class_id'] ?: 0);
        }
        if (array_key_exists('task_id', $param)) {
            $param['task_id'] = (int)($param['task_id'] ?: 0);
        }
    }

    protected function isAutoTaskCategory($category)
    {
        if ($category === null) {
            return false;
        }
        $category = $this->normalizeCategoryText($category);
        if ($category === '') {
            return false;
        }
        if (stripos($category, 'bug') !== false) {
            return true;
        }
        return in_array($category, ['系统BUG', '新增需求', '新需求'], true);
    }

    protected function maybeCreateProjectTask($ledgerId, array $param, $userInfo)
    {
        if (empty($ledgerId)) {
            return 0;
        }
        if (is_object($userInfo)) {
            $userInfo = (array) $userInfo;
        }
        if (!is_array($userInfo)) {
            $userInfo = [];
        }
        $category = $param['category'] ?? '';
        if (!$this->isAutoTaskCategory($category)) {
            return 0;
        }
        $workId = (int)($param['work_id'] ?? 0);
        $classId = (int)($param['class_id'] ?? 0);
        if (!$workId || !$classId) {
            return 0;
        }
        $taskId = (int)($param['task_id'] ?? 0);
        if ($taskId) {
            return 0;
        }
        $classExists = db('work_task_class')->where(['class_id' => $classId, 'work_id' => $workId])->find();
        if (!$classExists) {
            return 0;
        }
        $title = trim((string)($param['title'] ?? ''));
        $taskName = $title !== '' ? $title : '项目增项开发';
        $description = $this->normalizeTaskDescription($param['description'] ?? '');
        $priority = $this->resolveAutoTaskPriority($category);
        $startTime = $this->resolveTaskStartTime($param['register_time'] ?? 0);
        $mainUserId = (int)($param['handler_user_id'] ?? ($userInfo['id'] ?? 0));
        if (!$mainUserId) {
            $mainUserId = (int)($param['register_user_id'] ?? ($userInfo['id'] ?? 0));
        }
        $registerUserId = (int)($param['register_user_id'] ?? 0);
        if (!$registerUserId) {
            $registerUserId = $mainUserId;
        }
        $createUserId = (int)($userInfo['id'] ?? 0);
        if (!$createUserId) {
            $createUserId = $registerUserId ?: $mainUserId;
        }

        $taskParam = [
            'name' => $taskName,
            'description' => $description,
            'work_id' => $workId,
            'class_id' => $classId,
            'main_user_id' => $mainUserId,
            'create_user_id' => $createUserId,
            'priority' => $priority,
            'start_time' => $startTime,
            'pid' => 0
        ];
        $ledgerLabelId = $this->getOrCreateLedgerLabelId($createUserId);
        if ($ledgerLabelId) {
            $taskParam['lable_id'] = arrayToString([$ledgerLabelId]);
        }
        $customerId = (int)($param['customer_id'] ?? 0);
        if ($customerId) {
            $taskParam['customer_ids'] = [$customerId];
        }
        $businessId = (int)($param['business_id'] ?? 0);
        if ($businessId) {
            $taskParam['business_ids'] = [$businessId];
        }
        $contractId = (int)($param['contract_id'] ?? 0);
        if ($contractId) {
            $taskParam['contract_ids'] = [$contractId];
        }

        // 事务化：锁台账行 → 检查幂等 → 创建任务 + 回写 + 工作流初始化 → 提交
        // 工作流表或 auto_task_key 不存在时返回明确错误，不降级为无幂等/无工作流模式
        $idempotencyKey = 'ledger:' . $ledgerId . ':auto-task';
        $notifyContext = null;
        Db::startTrans();
        try {
            // 锁定目标台账行，防止并发重复
            $lockedLedger = db('customer_ledger')->where(['ledger_id' => $ledgerId])->lock(true)->find();
            if (!$lockedLedger) {
                throw new \Exception('台账不存在');
            }
            // 已存在有效 task_id 则返回现有任务，不重复创建
            $existingTaskId = (int)($lockedLedger['task_id'] ?? 0);
            if ($existingTaskId) {
                Db::commit();
                return $existingTaskId;
            }
            // 检查 auto_task_key 列是否存在；不存在说明未执行 P0 迁移
            $hasAutoKeyCol = false;
            try {
                db('customer_ledger')->where(['ledger_id' => $ledgerId])->field('auto_task_key')->find();
                $hasAutoKeyCol = true;
            } catch (\Exception $colEx) {
                $hasAutoKeyCol = false;
            }
            if (!$hasAutoKeyCol) {
                throw new \Exception('请先执行 P0 迁移（customer_ledger 缺少 auto_task_key 列）');
            }
            // compare-and-set：抢占幂等键
            $claimed = db('customer_ledger')
                ->where(['ledger_id' => $ledgerId, 'auto_task_key' => ''])
                ->update(['auto_task_key' => $idempotencyKey]);
            if (!$claimed) {
                // 并发竞争中本请求未抢占到，读取已创建的任务
                $taskId = (int)db('customer_ledger')->where('ledger_id', $ledgerId)->value('task_id');
                Db::commit();
                return $taskId;
            }
            // 创建任务
            $taskModel = new \app\work\model\Task();
            $newTaskId = (int)$taskModel->createTask($taskParam);
            if (!$newTaskId) {
                throw new \Exception($taskModel->getError() ?: '自动建任务失败');
            }
            $customerName = '';
            if ($customerId) {
                $customerName = (string)db('crm_customer')->where('customer_id', $customerId)->value('name');
            }
            db('customer_ledger')->where(['ledger_id' => $ledgerId])->update([
                'task_id' => $newTaskId,
                'work_id' => $workId,
                'class_id' => $classId,
                'update_time' => time()
            ]);
            if ($registerUserId && $registerUserId !== $mainUserId) {
                $ownerIds = array_unique(array_filter([$mainUserId, $registerUserId]));
                if ($ownerIds) {
                    db('task')->where(['task_id' => $newTaskId])->update([
                        'owner_user_id' => ',' . implode(',', $ownerIds) . ','
                    ]);
                }
            }
            // P0 工作流初始化：新自动任务默认进入“待评估”；表不存在时抛出明确错误，不降级
            $wfService = new WorkflowService();
            $wfRow = $wfService->getWorkflow($newTaskId, true);
            if (!$wfRow) {
                throw new \Exception('请先执行 P0 迁移（task_workflow 表初始化失败）');
            }
            $notifyContext = [
                'newTaskId' => $newTaskId,
                'customerName' => $customerName,
            ];
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            // 向上抛出真实错误，控制器据此返回可处理的中文错误
            throw $e;
        }

        // 通知必须在事务成功后发送；通知失败不影响已提交业务
        if ($notifyContext) {
            try {
                (new DingTalkLogic())->sendTaskNotify('任务创建', $notifyContext['newTaskId'], $userInfo['id'], [
                    'summary' => '台账生成任务',
                    'customer_name' => $notifyContext['customerName']
                ]);
            } catch (\Exception $e) {
                // 通知失败仅记录，不回滚已提交事务
            }
            return (int)$notifyContext['newTaskId'];
        }
        return 0;
    }

    protected function syncLedgerTaskOnUpdate($ledgerId, array $oldData, array $changedParam, array $mergedData, $userInfo)
    {
        if (is_object($userInfo)) {
            $userInfo = (array)$userInfo;
        }
        if (!is_array($userInfo)) {
            $userInfo = [];
        }
        $taskId = (int)($mergedData['task_id'] ?? 0);
        if (!$taskId) {
            return;
        }

        $task = db('task')->where(['task_id' => $taskId])->find();
        if (!$task) {
            return;
        }

        $updateData = [];
        if (array_key_exists('title', $changedParam)) {
            $title = trim((string)$mergedData['title']);
            if ($title !== '' && $title !== (string)($task['name'] ?? '')) {
                $updateData['name'] = $title;
            }
        }
        if (array_key_exists('description', $changedParam)) {
            $description = $this->normalizeTaskDescription($mergedData['description'] ?? '');
            if ($description !== '' && $description !== (string)($task['description'] ?? '')) {
                $updateData['description'] = $description;
            }
        }

        if (array_key_exists('handler_user_id', $changedParam)) {
            $mainUserId = (int)($mergedData['handler_user_id'] ?? 0);
            if ($mainUserId > 0 && $mainUserId !== (int)($task['main_user_id'] ?? 0)) {
                $updateData['main_user_id'] = $mainUserId;
                $ownerIds = stringToArray($task['owner_user_id'] ?? '');
                $ownerIds[] = $mainUserId;
                $ownerIds = array_values(array_unique(array_filter(array_map('intval', $ownerIds))));
                if (!empty($ownerIds)) {
                    $updateData['owner_user_id'] = ',' . implode(',', $ownerIds) . ',';
                }
            }
        }

        if (array_key_exists('category', $changedParam)) {
            $priority = $this->resolveAutoTaskPriority($mergedData['category'] ?? '');
            if ($priority > 0 && $priority !== (int)($task['priority'] ?? 0)) {
                $updateData['priority'] = $priority;
            }
        }
        $syncTaskStatus = true;
        if (array_key_exists('sync_task_status', $changedParam)) {
            $syncTaskStatus = (int)$changedParam['sync_task_status'] === 1;
        }
        if ($syncTaskStatus && array_key_exists('status', $changedParam)) {
            $ledgerStatus = (string)($mergedData['status'] ?? '');
            $targetTaskStatus = (strpos($ledgerStatus, '完成') !== false) ? 5 : 1;
            if ($targetTaskStatus !== (int)($task['status'] ?? 1)) {
                $updateData['status'] = $targetTaskStatus;
            }
        }

        if (empty($updateData)) {
            return;
        }

        $now = time();
        $updateData['update_time'] = $now;
        db('task')->where(['task_id' => $taskId])->update($updateData);

        $content = '同步任务：';
        $parts = [];
        if (isset($updateData['name'])) {
            $parts[] = '标题';
        }
        if (isset($updateData['description'])) {
            $parts[] = '描述';
        }
        if (isset($updateData['main_user_id'])) {
            $parts[] = '负责人';
        }
        if (isset($updateData['priority'])) {
            $parts[] = '优先级';
        }
        $content .= implode('、', $parts);
        model('CustomerLedger')->addProgressRecord(
            $ledgerId,
            (int)($oldData['customer_id'] ?? 0),
            $content,
            (string)($oldData['status'] ?? ''),
            (string)($mergedData['status'] ?? $oldData['status'] ?? ''),
            (int)($userInfo['id'] ?? 0)
        );
        $this->addLedgerActivity($ledgerId, (int)($oldData['customer_id'] ?? 0), $content, (int)($userInfo['id'] ?? 0), 1);
    }

    protected function closeTasksByLedgerIds(array $ledgerIds, $userId)
    {
        if (empty($ledgerIds)) {
            return;
        }
        $list = db('customer_ledger')
            ->where('ledger_id', 'in', $ledgerIds)
            ->where('task_id', 'gt', 0)
            ->field('ledger_id,customer_id,task_id,status')
            ->select();
        if (empty($list)) {
            return;
        }

        $now = time();
        foreach ($list as $item) {
            $taskId = (int)($item['task_id'] ?? 0);
            if (!$taskId) {
                continue;
            }
            db('task')->where(['task_id' => $taskId])->update([
                'status' => 5,
                'is_archive' => 1,
                'archive_time' => $now,
                'update_time' => $now
            ]);
            model('CustomerLedger')->addProgressRecord(
                (int)$item['ledger_id'],
                (int)$item['customer_id'],
                '删除台账同步关闭任务',
                (string)$item['status'],
                (string)$item['status'],
                (int)$userId
            );
            $this->addLedgerActivity((int)$item['ledger_id'], (int)$item['customer_id'], '删除台账同步关闭任务', (int)$userId, 1);
        }
    }

    protected function normalizeCategoryText($category)
    {
        $category = trim((string)$category);
        return preg_replace('/\s+/u', '', $category);
    }

    protected function resolveAutoTaskPriority($category)
    {
        $category = $this->normalizeCategoryText($category);
        if ($category === '') {
            return 0;
        }
        if ($category === '系统BUG' || stripos($category, 'bug') !== false) {
            return 3;
        }
        if (in_array($category, ['新增需求', '新需求'], true)) {
            return 2;
        }
        return 0;
    }

    protected function normalizeTaskDescription($content)
    {
        $text = html_entity_decode((string)$content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = str_replace(["\r\n", "\r", "\n", "\t", "\xC2\xA0"], ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    protected function resolveTaskStartTime($registerTime)
    {
        $timestamp = 0;
        if (is_numeric($registerTime)) {
            $timestamp = (int)$registerTime;
        } elseif (!empty($registerTime)) {
            $timestamp = strtotime((string)$registerTime);
        }
        if (!$timestamp) {
            $timestamp = time();
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    protected function getOrCreateLedgerLabelId($createUserId)
    {
        $labelName = '台账';
        $label = db('work_task_lable')->where(['name' => $labelName, 'status' => 1])->find();
        if (!empty($label['lable_id'])) {
            return (int)$label['lable_id'];
        }
        $ret = db('work_task_lable')->insert([
            'name' => $labelName,
            'color' => '#2362FB',
            'status' => 1,
            'create_time' => time(),
            'create_user_id' => (int)$createUserId
        ]);
        if (!$ret) {
            return 0;
        }
        return (int)db('work_task_lable')->getLastInsID();
    }

    protected function normalizeUserId($value)
    {
        if (is_array($value)) {
            $first = reset($value);
            if (is_array($first)) {
                return (int)($first['id'] ?? $first['user_id'] ?? 0);
            }
            return (int)$first;
        }
        return (int)$value;
    }

    protected function sanitizeRichText($html)
    {
        if ($html === null) {
            return '';
        }
        $content = (string)$html;
        if ($content === '') {
            return '';
        }
        $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><img><a><span><div><h1><h2><h3><h4><h5><h6><blockquote><pre><code>';
        $content = strip_tags($content, $allowed);
        $content = preg_replace('/\s*on\w+\s*=\s*(\"[^\"]*\"|\'[^\']*\'|[^\s>]+)/i', '', $content);
        $content = preg_replace('/\s*style\s*=\s*(\"[^\"]*\"|\'[^\']*\'|[^\s>]+)/i', '', $content);
        $content = preg_replace_callback('/\s*(href|src)\s*=\s*(["\'])(.*?)\2/i', function ($matches) {
            $attr = strtolower($matches[1]);
            $value = trim($matches[3]);
            $lower = strtolower($value);
            if ($lower === '' || strpos($lower, 'javascript:') === 0 || strpos($lower, 'vbscript:') === 0) {
                return '';
            }
            if (strpos($lower, 'data:') === 0) {
                // Only allow embedded image data urls for rich-text image rendering.
                if (!($attr === 'src' && preg_match('/^data:image\/[a-z0-9.+-]+;base64,[a-z0-9+\/=\s]+$/i', $value))) {
                    return '';
                }
            }
            return ' ' . $attr . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }, $content);
        return $content;
    }
}
