<?php
// +----------------------------------------------------------------------
// | Description: WeChat Official Account template message (fallback)
// +----------------------------------------------------------------------
namespace app\admin\logic;

use think\Cache;
use think\Db;
use think\Log;

class WeChatMpNotifyLogic
{
    protected $configName = 'wechat_mp_notify';

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
            'app_id' => '',
            'secret' => '',
            'template_id' => ''
        ];
    }

    public function sendTemplateToUsers(array $userIds, $title, $description, $url = '')
    {
        $config = $this->getConfig();
        $appId = trim((string)($config['app_id'] ?? ''));
        $secret = trim((string)($config['secret'] ?? ''));
        $templateId = trim((string)($config['template_id'] ?? ''));
        if ($appId === '' || $secret === '' || $templateId === '') {
            return false;
        }

        $token = $this->getAccessToken($appId, $secret);
        if ($token === '') {
            return false;
        }

        $openIds = $this->resolveOpenIds($userIds);
        if (empty($openIds)) {
            return false;
        }

        $ok = false;
        foreach ($openIds as $openId) {
            $payload = [
                'touser' => $openId,
                'template_id' => $templateId,
                'url' => $url,
                'data' => [
                    'first' => ['value' => $title, 'color' => '#173177'],
                    'keyword1' => ['value' => $title, 'color' => '#173177'],
                    'keyword2' => ['value' => mb_substr($description, 0, 80, 'UTF-8'), 'color' => '#173177'],
                    'remark' => ['value' => '点击查看详情', 'color' => '#173177']
                ]
            ];
            if ($this->postJson('https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $token, $payload)) {
                $ok = true;
            }
        }
        return $ok;
    }

    protected function resolveOpenIds(array $userIds)
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (empty($userIds)) {
            return [];
        }
        $rows = Db::name('admin_user_threeparty')
            ->where('user_id', 'in', $userIds)
            ->where('key', 'wechat_mp_openid')
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

    protected function getAccessToken($appId, $secret)
    {
        $cacheKey = 'wxmp_token_' . md5($appId . $secret);
        $cached = Cache::get($cacheKey);
        if (!empty($cached)) {
            return $cached;
        }
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . urlencode($appId) . '&secret=' . urlencode($secret);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        curl_close($ch);
        if (!$response) {
            return '';
        }
        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            Log::write('WeChat MP token failed: ' . $response);
            return '';
        }
        $ttl = !empty($data['expires_in']) ? max(60, (int)$data['expires_in'] - 120) : 6600;
        Cache::set($cacheKey, $data['access_token'], $ttl);
        return $data['access_token'];
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
            Log::write('WeChat MP notify failed: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        $data = json_decode($response, true);
        return isset($data['errcode']) ? (int)$data['errcode'] === 0 : true;
    }
}
