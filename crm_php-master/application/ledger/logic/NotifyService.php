<?php
// +----------------------------------------------------------------------
// | Description: Ledger notification dispatcher
// +----------------------------------------------------------------------
namespace app\ledger\logic;

use app\admin\logic\DingTalkLogic;
use app\admin\logic\WeChatMpNotifyLogic;
use app\admin\logic\WeChatWorkNotifyLogic;
use app\admin\model\Message;
use think\Db;

class NotifyService
{
    const EVENT_ASSIGNED = 'ledger_assigned';
    const EVENT_STATUS_CHANGED = 'ledger_status_changed';
    const EVENT_PROGRESS_ADDED = 'ledger_progress_added';
    const EVENT_CREATED = 'ledger_created';

    public function notify($event, $ledgerId, $operatorUserId = 0, array $extra = [])
    {
        $ledgerId = (int)$ledgerId;
        if ($ledgerId <= 0) {
            return false;
        }

        $ledger = Db::name('customer_ledger')
            ->alias('ledger')
            ->join('__CRM_CUSTOMER__ customer', 'ledger.customer_id = customer.customer_id', 'LEFT')
            ->join('__CRM_CONTRACT__ contract', 'ledger.contract_id = contract.contract_id', 'LEFT')
            ->join('__ADMIN_USER__ handler', 'ledger.handler_user_id = handler.id', 'LEFT')
            ->field('ledger.*,customer.name as customer_name,contract.name as contract_name,contract.num as contract_num,handler.realname as handler_user_name')
            ->where('ledger.ledger_id', $ledgerId)
            ->find();
        if (!$ledger) {
            return false;
        }

        $operatorName = '';
        if (!empty($operatorUserId)) {
            $operatorName = Db::name('admin_user')->where('id', $operatorUserId)->value('realname') ?: '';
        }

        $title = (string)($ledger['title'] ?: '台账');
        $customerName = (string)($ledger['customer_name'] ?: '-');
        $handlerName = (string)($ledger['handler_user_name'] ?: '-');
        $status = (string)($ledger['status'] ?: '-');
        $link = $this->buildLedgerLink($ledgerId);
        $summary = $this->buildSummary($event, $extra, $status);

        $recipientIds = $this->resolveRecipients($event, $ledger, $extra);
        if (!empty($recipientIds)) {
            $this->sendInAppMessage($event, $ledgerId, $title, $summary, $recipientIds, $operatorUserId);
            $this->sendWeChatWork($recipientIds, $summary, $title, $customerName, $handlerName, $status, $operatorName, $link);
            $this->sendWeChatMp($recipientIds, $summary, $title, $link);
        }

        if ($this->shouldSendDingTalk($event)) {
            (new DingTalkLogic())->sendLedgerNotify($event, $ledger, $operatorUserId, array_merge($extra, [
                'summary' => $summary,
                'link' => $link,
                'operator_name' => $operatorName
            ]));
        }

        return true;
    }

    protected function buildSummary($event, array $extra, $status)
    {
        switch ($event) {
            case self::EVENT_ASSIGNED:
                return '台账已指派处理人';
            case self::EVENT_STATUS_CHANGED:
                $old = (string)($extra['old_status'] ?? '');
                $new = (string)($extra['new_status'] ?? $status);
                return $old !== '' ? ('台账状态变更：' . $old . ' → ' . $new) : ('台账状态：' . $new);
            case self::EVENT_PROGRESS_ADDED:
                $content = (string)($extra['content'] ?? '');
                if (mb_strlen($content, 'UTF-8') > 60) {
                    $content = mb_substr($content, 0, 60, 'UTF-8') . '...';
                }
                return $content !== '' ? ('新增进度：' . $content) : '台账新增进度';
            case self::EVENT_CREATED:
            default:
                return '新建台账';
        }
    }

    protected function resolveRecipients($event, array $ledger, array $extra)
    {
        $handlerId = (int)($ledger['handler_user_id'] ?? 0);
        $registerId = (int)($ledger['register_user_id'] ?? 0);
        $ids = [];

        if ($event === self::EVENT_CREATED) {
            if ($handlerId > 0) {
                $ids[] = $handlerId;
            }
            if ($registerId > 0) {
                $ids[] = $registerId;
            }
            return array_values(array_unique(array_filter(array_map('intval', $ids))));
        }

        if ($event === self::EVENT_ASSIGNED) {
            if ($handlerId > 0) {
                $ids[] = $handlerId;
            }
            return array_values(array_unique($ids));
        }

        if ($event === self::EVENT_STATUS_CHANGED || $event === self::EVENT_PROGRESS_ADDED) {
            if ($handlerId > 0) {
                $ids[] = $handlerId;
            }
            if ($registerId > 0) {
                $ids[] = $registerId;
            }
            if (!empty($extra['notify_user_ids']) && is_array($extra['notify_user_ids'])) {
                $ids = array_merge($ids, $extra['notify_user_ids']);
            }
            return array_values(array_unique(array_filter(array_map('intval', $ids))));
        }

        return $ids;
    }

    protected function shouldSendDingTalk($event)
    {
        return in_array($event, [
            self::EVENT_ASSIGNED,
            self::EVENT_STATUS_CHANGED,
            self::EVENT_PROGRESS_ADDED,
            self::EVENT_CREATED
        ], true);
    }

    protected function sendInAppMessage($event, $ledgerId, $title, $summary, array $userIds, $operatorUserId)
    {
        $typeMap = [
            self::EVENT_ASSIGNED => Message::LEDGER_ASSIGNED,
            self::EVENT_STATUS_CHANGED => Message::LEDGER_STATUS,
            self::EVENT_PROGRESS_ADDED => Message::LEDGER_PROGRESS,
            self::EVENT_CREATED => Message::LEDGER_ASSIGNED
        ];
        if (!isset($typeMap[$event])) {
            return;
        }
        $messageModel = new Message();
        $messageModel->send($typeMap[$event], [
            'action_id' => $ledgerId,
            'title' => $title,
            'summary' => $summary
        ], $userIds, false);
    }

    protected function sendWeChatWork(array $userIds, $summary, $title, $customerName, $handlerName, $status, $operatorName, $link)
    {
        $description = '客户：' . $customerName . "\n处理人：" . $handlerName . "\n状态：" . $status;
        if ($operatorName !== '') {
            $description .= "\n操作人：" . $operatorName;
        }
        (new WeChatWorkNotifyLogic())->sendTextToUsers($userIds, $summary, $description, $link);
    }

    protected function sendWeChatMp(array $userIds, $summary, $title, $link)
    {
        (new WeChatMpNotifyLogic())->sendTemplateToUsers($userIds, $summary, $title, $link);
    }

    protected function buildLedgerLink($ledgerId)
    {
        try {
            $domain = request()->domain();
        } catch (\Throwable $e) {
            $domain = '';
        }
        if ($domain === '') {
            return '';
        }
        return rtrim($domain, '/') . '/#/m/ledger/' . (int)$ledgerId;
    }
}
