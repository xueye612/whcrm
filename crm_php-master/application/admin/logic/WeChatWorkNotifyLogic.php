<?php
// +----------------------------------------------------------------------
// | Description: WeChat Work (企业微信) application message
// +----------------------------------------------------------------------
namespace app\admin\logic;

use think\Cache;
use think\Db;
use think\Log;

class WeChatWorkNotifyLogic
{
    protected $configName = 'wechat_work_notify';

    public function getConfig()
    {
        $config = db('crm_config')->where(['name' => $this->configName])->find();
        if ($config && !empty($config['value'])) {
            $value = json_decode($config['value'], true);
            if (is_array($value)) {
                return $value;
            }
        }
        return [
            'corp_id' => '',
            'agent_id' => '',
            'secret' => ''
        ];
    }

    public function sendTextToUsers(array $userIds, $title, $description, $url = '')
    {
        $config = $this->getConfig();
        $corpId = trim((string)($config['corp_id'] ?? ''));
        $agentId = trim((string)($config['agent_id'] ?? ''));
        $secret = trim((string)($config['secret'] ?? ''));
        if ($corpId === '' || $agentId === '' || $secret === '') {
            return false;
        }

        $wxUserIds = $this->resolveWxWorkUserIds($userIds);
        if (empty($wxUserIds)) {
            return false;
        }

        $token = $this->getAccessToken($corpId, $secret);
        if ($token === '') {
            return false;
        }

        $content = $title;
        if ($description !== '') {
            $content .= "\n" . $description;
        }
        if ($url !== '') {
            $content .= "\n" . $url;
        }

        $payload = [
            'touser' => implode('|', $wxUserIds),
            'msgtype' => 'text',
            'agentid' => (int)$agentId,
            'text' => [
                'content' => $content
            ],
            'safe' => 0
        ];

        return $this->postJson(
            'https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token=' . $token,
            $payload
        );
    }

    protected function resolveWxWorkUserIds(array $userIds)
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (empty($userIds)) {
            return [];
        }
        $rows = Db::name('admin_user_threeparty')
            ->where('user_id', 'in', $userIds)
            ->where('key', 'wxwork_userid')
            ->column('value', 'user_id');
        $result = [];
        foreach ($rows as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $result[] = $value;
            }
        }
        return array_values(array_unique($result));
    }

    protected function getAccessToken($corpId, $secret)
    {
        $cacheKey = 'wxwork_token_' . md5($corpId . $secret);
        $cached = Cache::get($cacheKey);
        if (!empty($cached)) {
            return $cached;
        }
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=' . urlencode($corpId) . '&corpsecret=' . urlencode($secret);
        $response = $this->httpGet($url);
        if (!$response) {
            return '';
        }
        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            Log::write('WeChatWork token failed: ' . $response);
            return '';
        }
        $ttl = !empty($data['expires_in']) ? max(60, (int)$data['expires_in'] - 120) : 6600;
        Cache::set($cacheKey, $data['access_token'], $ttl);
        return $data['access_token'];
    }

    protected function httpGet($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        if ($response === false) {
            Log::write('WeChatWork GET failed: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        return $response;
    }

    protected function postJson($url, array $payload)
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json;charset=utf-8']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        if ($response === false) {
            Log::write('WeChatWork notify failed: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        return true;
    }
}
