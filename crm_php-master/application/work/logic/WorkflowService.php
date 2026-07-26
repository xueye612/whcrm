<?php
/**
 * P0 任务工作流、W/R/K、轻量测试与发布门禁规则引擎
 *
 * 集中维护中文主状态字典、合法迁移、W/R/K 等级、冻结规则、
 * 测试任务闭环和发布门禁，避免规则散落在控制器和前端。
 *
 * PHP 7.0 / ThinkPHP 5.0.24 兼容。
 */

namespace app\work\logic;

use think\Db;
use think\Exception;

class WorkflowService
{
    // ========== 中文主状态（workflow_version = 2）==========
    const STATUS_PENDING_EVAL   = '待评估';
    const STATUS_PENDING_HANDLE = '待处理';
    const STATUS_PROCESSING     = '处理中';
    const STATUS_ACCEPTANCE     = '待内部验收';
    const STATUS_RELEASE        = '待发布';
    const STATUS_CUSTOMER       = '待客户验证';
    const STATUS_DONE           = '已完成';

    // ========== 辅助状态 ==========
    const AUX_BLOCKED  = '阻塞';
    const AUX_SUSPEND  = '暂缓';
    const AUX_CANCEL   = '取消';
    const AUX_DUPLICATE = '重复';
    const AUX_NO_ACTION = '无需处理';

    // ========== 测试评定状态 ==========
    const REVIEW_PENDING     = 'pending';
    const REVIEW_COMPLIANT   = 'compliant';
    const REVIEW_NON_COMPLY  = 'non_compliant';

    // ========== W/R/K 等级 ==========
    private static $wLevels = ['W1', 'W2', 'W3', 'W4', 'W5'];
    private static $rLevels = ['R1', 'R2', 'R3', 'R4', 'R5'];
    private static $kLevels = ['K1', 'K2', 'K3', 'K4'];

    // ========== 中文定义（前后端共享）==========
    public static function wrkDictionary()
    {
        return [
            'W' => [
                'W1' => '两小时以内',
                'W2' => '两至八小时',
                'W3' => '一至三人日',
                'W4' => '三至十人日',
                'W5' => '超过十人日，原则上应拆分',
            ],
            'R' => [
                'R1' => '局部、易验证、易撤销',
                'R2' => '常规业务功能，影响有限',
                'R3' => '跨模块、共享能力，或可能造成部分数据不一致',
                'R4' => '影响生产流程、较大范围数据、外部接口或设备，恢复成本高或回退复杂',
                'R5' => '涉及患者安全、正式医疗文书真实性、核心数据、重大连续运行、法律合规或核心架构风险',
            ],
            'K' => [
                'K1' => '成熟',
                'K2' => '基本明确',
                'K3' => '需要专业确认',
                'K4' => '必须有正式专业依据',
            ],
        ];
    }

    /**
     * 主状态有序序列（用于展示）
     */
    public static function mainStatusOrder()
    {
        return [
            self::STATUS_PENDING_EVAL,
            self::STATUS_PENDING_HANDLE,
            self::STATUS_PROCESSING,
            self::STATUS_ACCEPTANCE,
            self::STATUS_RELEASE,
            self::STATUS_CUSTOMER,
            self::STATUS_DONE,
        ];
    }

    /**
     * 合法迁移矩阵：动作 => [from => to]
     * 返回 [from => to] 或动作到终态的映射。
     */
    public static function allowedTransitions()
    {
        return [
            // 主流程
            'evaluate'              => [self::STATUS_PENDING_EVAL  => self::STATUS_PENDING_HANDLE],
            'start'                 => [self::STATUS_PENDING_HANDLE => self::STATUS_PROCESSING],
            'submit_acceptance'     => [self::STATUS_PROCESSING     => self::STATUS_ACCEPTANCE],
            'acceptance_pass'       => [self::STATUS_ACCEPTANCE     => self::STATUS_RELEASE],
            'acceptance_return'     => [self::STATUS_ACCEPTANCE     => self::STATUS_PROCESSING],
            'apply_release'         => [self::STATUS_RELEASE        => null], // 门禁检查通过后停留
            'confirm_release'       => [self::STATUS_RELEASE        => self::STATUS_CUSTOMER],
            'submit_customer'       => [self::STATUS_CUSTOMER       => null],
            'customer_confirm'      => [self::STATUS_CUSTOMER       => self::STATUS_DONE],
            'customer_return'       => [self::STATUS_CUSTOMER       => self::STATUS_PROCESSING],
            'complete'              => [self::STATUS_RELEASE        => self::STATUS_DONE],
        ];
    }

    /**
     * 某动作是否允许从当前主状态发起
     */
    public function resolveTargetStatus($action, $currentStatus)
    {
        $map = self::allowedTransitions();
        if (!isset($map[$action])) {
            return false;
        }
        $transition = $map[$action];
        if (!array_key_exists($currentStatus, $transition)) {
            return false;
        }
        return $transition[$currentStatus];
    }

    // ========== W/R/K 校验 ==========

    public static function isValidW($v)
    {
        return in_array($v, self::$wLevels, true);
    }

    public static function isValidR($v)
    {
        return in_array($v, self::$rLevels, true);
    }

    public static function isValidK($v)
    {
        return in_array($v, self::$kLevels, true);
    }

    public function validateWrkField($field, $value)
    {
        $map = [
            'init_w' => 'isValidW', 'final_w' => 'isValidW',
            'init_r' => 'isValidR', 'final_r' => 'isValidR',
            'init_k' => 'isValidK', 'final_k' => 'isValidK',
        ];
        if (!isset($map[$field])) {
            return '未知 W/R/K 字段';
        }
        $checker = [$this, $map[$field]];
        if (!call_user_func($checker, $value)) {
            $label = strtoupper(substr($field, -1));
            return $label . ' 等级不合法';
        }
        return '';
    }

    // ========== 工作流扩展行读写 ==========

    /**
     * 读取任务工作流扩展行；不存在时按 workflow_version=2 创建初始行。
     * 旧任务（无扩展行）调用此方法不会自动创建，除非 $create 为 true。
     */
    public function getWorkflow($taskId, $create = false)
    {
        $taskId = (int)$taskId;
        if ($taskId <= 0) {
            return null;
        }
        $row = Db::name('task_workflow')->where(['task_id' => $taskId])->find();
        if ($row) {
            return $row;
        }
        if (!$create) {
            return null;
        }
        $time = time();
        $data = [
            'task_id'           => $taskId,
            'workflow_version'  => 2,
            'main_status'       => self::STATUS_PENDING_EVAL,
            'version'           => 1,
            'create_time'       => $time,
            'update_time'       => $time,
        ];
        Db::name('task_workflow')->insert($data);
        return Db::name('task_workflow')->where(['task_id' => $taskId])->find();
    }

    /**
     * 任务是否启用 P0 工作流（存在扩展行）。
     */
    public function isWorkflowEnabled($taskId)
    {
        return (int)Db::name('task_workflow')->where(['task_id' => (int)$taskId])->count() > 0;
    }

    // ========== W/R/K 冻结与调整审计 ==========

    /**
     * 写入初始 W/R/K（仅在首次进入处理中前；冻结 init）。
     * 返回空串成功，否则中文错误。
     */
    public function setInitialWrk($taskId, array $wrk, $userId)
    {
        $wf = $this->getWorkflow($taskId, true);
        if ((int)$wf['wrk_frozen'] === 1) {
            return 'W/R/K 已冻结，不能再次设置初始值';
        }
        $updates = [];
        $time = time();
        foreach (['init_w', 'init_r', 'init_k'] as $f) {
            if (array_key_exists($f, $wrk)) {
                $val = trim((string)$wrk[$f]);
                if ($val === '') {
                    return '初始 W/R/K 不能为空';
                }
                $err = $this->validateWrkField($f, $val);
                if ($err) {
                    return $err;
                }
                $updates[$f] = $val;
            }
        }
        if (!$updates) {
            return '未提供任何初始 W/R/K 值';
        }
        // 记录调整审计
        foreach ($updates as $f => $val) {
            $this->logWrkAdjust($taskId, $f, '', $val, '设置初始值', $userId, $time);
        }
        $updates['update_user_id'] = (int)$userId;
        $updates['update_time'] = $time;
        Db::name('task_workflow')->where(['task_id' => $taskId])->update($updates);
        return '';
    }

    /**
     * 冻结初始 W/R/K（首次进入处理中时调用）。
     */
    public function freezeInitialWrk($taskId)
    {
        Db::name('task_workflow')->where(['task_id' => (int)$taskId])->update([
            'wrk_frozen' => 1,
        ]);
    }

    /**
     * 写入最终 W/R/K（提交内部验收时；完成时冻结）。
     */
    public function setFinalWrk($taskId, array $wrk, $userId)
    {
        $wf = $this->getWorkflow($taskId);
        if (!$wf) {
            return '任务未启用工作流';
        }
        $updates = [];
        $time = time();
        foreach (['final_w', 'final_r', 'final_k'] as $f) {
            if (array_key_exists($f, $wrk)) {
                $val = trim((string)$wrk[$f]);
                if ($val === '') {
                    return '最终 W/R/K 不能为空';
                }
                $err = $this->validateWrkField($f, $val);
                if ($err) {
                    return $err;
                }
                $old = isset($wf[$f]) ? (string)$wf[$f] : '';
                if ($old !== $val) {
                    $this->logWrkAdjust($taskId, $f, $old, $val, '设置最终值', $userId, $time);
                }
                $updates[$f] = $val;
            }
        }
        if (!$updates) {
            return '未提供任何最终 W/R/K 值';
        }
        $updates['update_user_id'] = (int)$userId;
        $updates['update_time'] = $time;
        Db::name('task_workflow')->where(['task_id' => $taskId])->update($updates);
        return '';
    }

    private function logWrkAdjust($taskId, $field, $old, $new, $reason, $userId, $time)
    {
        Db::name('task_wrk_log')->insert([
            'task_id'     => (int)$taskId,
            'field_name'  => $field,
            'old_value'   => (string)$old,
            'new_value'   => (string)$new,
            'reason'      => (string)$reason,
            'user_id'     => (int)$userId,
            'create_time' => (int)$time,
        ]);
    }

    // ========== 状态迁移审计 ==========

    public function logTransition($taskId, $action, $fromStatus, $toStatus, array $fieldChanges, $reason, $userId, $correlationId = '')
    {
        Db::name('task_transition_log')->insert([
            'task_id'       => (int)$taskId,
            'action'        => (string)$action,
            'from_status'   => (string)$fromStatus,
            'to_status'     => (string)$toStatus,
            'field_changes' => $fieldChanges ? json_encode($fieldChanges, JSON_UNESCAPED_UNICODE) : '',
            'reason'        => (string)$reason,
            'user_id'       => (int)$userId,
            'correlation_id'=> (string)$correlationId,
            'create_time'   => time(),
        ]);
    }

    // ========== 发布门禁 ==========

    // P0 必需测试类型内部代码
    const TEST_TYPE_DEV_SELF = 'dev_self';      // 开发自测
    const TEST_TYPE_BUSINESS = 'business';       // 非开发人员业务测试

    /**
     * 检查 originTaskId 的发布门禁：必需测试齐全且符合要求、K3/K4 专业确认、R4/R5 风险说明。
     * need_release=1 时，零必需测试或缺少开发自测/业务测试必须拒绝。
     * 返回 [bool $ok, string $reason]。
     */
    public function checkReleaseGate($originTaskId)
    {
        $originTaskId = (int)$originTaskId;
        // 必需测试任务
        $required = Db::name('task_test_ext')
            ->where(['origin_task_id' => $originTaskId, 'is_required' => 1])
            ->select();
        // 零必需测试必须拒绝
        if (!$required) {
            return [false, '缺少必需测试任务（至少需要开发自测和业务测试各一条）'];
        }
        // 必须同时存在开发自测和业务测试两类必需任务
        $hasDevSelf = false;
        $hasBusiness = false;
        $devSelfCompliant = false;
        $businessCompliant = false;
        foreach ($required as $ext) {
            if ($ext['test_type'] === self::TEST_TYPE_DEV_SELF) {
                $hasDevSelf = true;
                $devSelfCompliant = ($ext['review_status'] === self::REVIEW_COMPLIANT);
            }
            if ($ext['test_type'] === self::TEST_TYPE_BUSINESS) {
                $hasBusiness = true;
                $businessCompliant = ($ext['review_status'] === self::REVIEW_COMPLIANT);
            }
        }
        if (!$hasDevSelf) {
            return [false, '缺少开发自测必需测试任务'];
        }
        if (!$hasBusiness) {
            return [false, '缺少非开发人员业务测试必需测试任务'];
        }
        if (!$devSelfCompliant) {
            return [false, '开发自测任务尚未符合要求'];
        }
        if (!$businessCompliant) {
            return [false, '业务测试任务尚未符合要求'];
        }
        // 其余必需测试也必须全部符合要求
        foreach ($required as $ext) {
            if ($ext['review_status'] !== self::REVIEW_COMPLIANT) {
                $taskName = Db::name('task')->where('task_id', $ext['task_id'])->value('name');
                return [false, '必需测试任务尚未符合要求：' . ($taskName ?: '#' . $ext['task_id'])];
            }
        }
        // K3/K4 专业确认（基于最终 K，未填则取初始 K）
        $wf = $this->getWorkflow($originTaskId);
        if ($wf) {
            $k = !empty($wf['final_k']) ? $wf['final_k'] : (isset($wf['init_k']) ? $wf['init_k'] : '');
            if (($k === 'K3' || $k === 'K4') && empty($wf['professional_confirm'])) {
                return [false, $k . ' 任务缺少专业确认依据，不能申请发布'];
            }
            // R4/R5 风险说明
            $r = !empty($wf['final_r']) ? $wf['final_r'] : (isset($wf['init_r']) ? $wf['init_r'] : '');
            if (($r === 'R4' || $r === 'R5') && empty($wf['risk_note'])) {
                return [false, $r . ' 任务缺少风险/备份/回滚说明，不能申请发布'];
            }
        }
        return [true, ''];
    }

    /**
     * 获取测试类型内部代码到中文名称的映射。
     */
    public static function testTypeDictionary()
    {
        return [
            self::TEST_TYPE_DEV_SELF => '开发自测',
            self::TEST_TYPE_BUSINESS => '非开发人员业务测试',
        ];
    }

    // ========== 测试任务 ==========

    /**
     * 生成来源幂等键（基于发起请求 ID + 测试人员，允许不同请求新开测试轮次）。
     */
    public function buildTestIdempotencyKey($requestId, $testerUserId)
    {
        return 'test:' . (string)$requestId . ':' . (int)$testerUserId;
    }

    /**
     * 检查幂等键是否已存在测试任务，返回已存在 task_id 或 0。
     */
    public function findExistingTestTask($idempotencyKey)
    {
        if ($idempotencyKey === '') {
            return 0;
        }
        $ext = Db::name('task_test_ext')->where(['idempotency_key' => $idempotencyKey])->find();
        return $ext ? (int)$ext['task_id'] : 0;
    }

    /**
     * 校验测试任务评定权限：当前用户必须是发起时保存的评定人，且不能等于测试执行人。
     * 返回空串通过，否则中文错误。
     */
    public function assertReviewer($testTaskId, $reviewerUserId)
    {
        $ext = Db::name('task_test_ext')->where(['task_id' => (int)$testTaskId])->find();
        if (!$ext) {
            return '测试任务不存在';
        }
        if ((int)$ext['reviewer_user_id'] === 0) {
            return '该测试任务未指定评定人';
        }
        if ((int)$ext['tester_user_id'] === (int)$reviewerUserId) {
            return '不能评定自己作为测试执行人的测试任务';
        }
        if ((int)$ext['reviewer_user_id'] !== (int)$reviewerUserId) {
            return '只有指定的评定人可以评定该测试任务';
        }
        return '';
    }

    /**
     * 校验测试任务是否可提交（提交状态必须为未提交或被退回后）。
     */
    public function canSubmitTest($testTaskId)
    {
        $ext = Db::name('task_test_ext')->where(['task_id' => (int)$testTaskId])->find();
        if (!$ext) {
            return [false, '测试任务不存在'];
        }
        // 已符合要求的不再允许提交
        if ($ext['review_status'] === self::REVIEW_COMPLIANT) {
            return [false, '该测试任务已符合要求，无需再次提交'];
        }
        return [true, ''];
    }
}
