<?php
// +----------------------------------------------------------------------
// | Description: DingTalk Webhook Notify
// +----------------------------------------------------------------------
namespace app\admin\logic;

use think\Db;
use think\Log;

class DingTalkLogic
{
    protected $configName = 'dingtalk_task_notify';

    public function getTaskNotifyConfig()
    {
        $config = db('crm_config')->where(['name' => $this->configName])->find();
        if ($config && !empty($config['value'])) {
            $value = json_decode($config['value'], true);
            if (is_array($value)) {
                return $value;
            }
        }
        return [
            'webhook_url' => '',
            'secret' => ''
        ];
    }

    public function sendTaskNotify($event, $taskId, $operatorUserId = 0, array $extra = [])
    {
        $config = $this->getTaskNotifyConfig();
        $webhookUrl = isset($config['webhook_url']) ? trim((string)$config['webhook_url']) : '';
        if ($webhookUrl === '') {
            return false;
        }
        $task = Db::name('task')
            ->alias('t')
            ->join('__ADMIN_USER__ u', 'u.id = t.main_user_id', 'LEFT')
            ->field('t.task_id,t.name,t.main_user_id,t.status,t.work_id,u.realname as main_user_name')
            ->where('t.task_id', $taskId)
            ->find();
        if (!$task) {
            return false;
        }
        $operatorName = '';
        if (!empty($operatorUserId)) {
            $operatorName = Db::name('admin_user')->where('id', $operatorUserId)->value('realname') ?: '';
        }
        $statusText = isset($extra['status_text']) ? $extra['status_text'] : $this->mapStatus($task['status'] ?? 0);
        $summary = isset($extra['summary']) ? $extra['summary'] : $event;
        $customerName = isset($extra['customer_name']) ? trim((string)$extra['customer_name']) : '';
        $link = $this->buildTaskLink($taskId, $task['work_id'] ?? 0);
        $title = isset($extra['title']) ? $extra['title'] : ('任务通知 - ' . $event);

        $lines = [];
        $lines[] = '**' . $summary . '**';
        $lines[] = '';
        $lines[] = '**标题**：' . ($task['name'] ?: '-');
        if ($customerName !== '') {
            $lines[] = '**客户名称**：' . $customerName;
        }
        $lines[] = '**负责人**：' . (!empty($task['main_user_name']) ? $task['main_user_name'] : '-');
        $lines[] = '**状态**：' . ($statusText ?: '-');
        $lines[] = '**操作人**：' . ($operatorName ?: '-');
        if ($link) {
            $lines[] = '**链接**：' . '[打开任务](' . $link . ')';
        } else {
            $lines[] = '**链接**：-';
        }

        $payload = [
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => $title,
                'text' => implode("\n", $lines)
            ],
            'at' => [
                'isAtAll' => false
            ]
        ];

        return $this->postWebhook($webhookUrl, $payload, $config);
    }

    protected function mapStatus($status)
    {
        $status = (int)$status;
        if ($status === 5) {
            return '已完成';
        }
        return '未完成';
    }

    protected function buildTaskLink($taskId, $workId = 0)
    {
        try {
            $domain = request()->domain();
        } catch (\Throwable $e) {
            $domain = '';
        }
        if (!$domain) {
            return '';
        }
        $query = $taskId ? ('?task_id=' . $taskId) : '';
        return $domain . '/#/project/workbench' . $query;
    }

    protected function postWebhook($webhookUrl, array $payload, array $config)
    {
        $url = $this->buildWebhookUrl($webhookUrl, $config);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json;charset=utf-8']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $response = curl_exec($ch);
        if ($response === false) {
            Log::write('DingTalk notify failed: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        return true;
    }

    protected function buildWebhookUrl($webhookUrl, array $config)
    {
        $secret = isset($config['secret']) ? trim((string)$config['secret']) : '';
        if ($secret === '') {
            return $webhookUrl;
        }
        $timestamp = (string)round(microtime(true) * 1000);
        $signStr = $timestamp . "\n" . $secret;
        $sign = urlencode(base64_encode(hash_hmac('sha256', $signStr, $secret, true)));
        $separator = strpos($webhookUrl, '?') === false ? '?' : '&';
        return $webhookUrl . $separator . 'timestamp=' . $timestamp . '&sign=' . $sign;
    }
}
